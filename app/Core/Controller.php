<?php

namespace App\Core;

use App\Core\Authorization;
use App\Services\PdfService;
use App\Services\AuditLogService;

class Controller
	{

	/**
	 * Creates the shared PDF service available to application controllers.
	 */
	protected function pdfService(): PdfService
		{
		return new PdfService();
		}

	/**
	 * Renders a view, optionally wrapping it with the main layout.
	 *
	 * @param string $view View name relative to `app/Views`.
	 * @param array $data Data exposed to the view.
	 */
	protected function view(string $view, array $data = [], bool $useLayout = true): void
		{
		$this->auditListResponse($data, $view);
		extract($data);

		$viewFile = __DIR__ . '/../Views/' . $view . '.php';

		ob_start();
		if (file_exists($viewFile))
			{
			require $viewFile;
			}
		else
			{
			echo "View {$view} not found!";
			}
		$content = ob_get_clean();

		if ($useLayout)
			{
			require __DIR__ . '/../Views/layouts/main.php';
			return;
			}

		// Render without the shared layout.
		echo $content;
		}

	/**
	 * Sends a JSON response and stops execution.
	 *
	 * @param mixed $data Payload to encode as JSON.
	 * @param int $status HTTP status code.
	 * @param array $headers Extra response headers.
	 */
	protected function json($data, int $status = 200, array $headers = []): void
		{
		if (is_array($data))
			{
			$this->auditListResponse($data);
			}

		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');

		foreach ($headers as $k => $v)
			{
			header($k . ': ' . $v);
			}

		echo json_encode(
			$data,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
		);
		exit;
		}

	/**
	 * Persists a centralized audit event for record listings and filtered searches.
	 *
	 * @param array<string, mixed> $payload Response payload rendered by the action.
	 * @param string|null $view Optional view path for HTML responses.
	 * @return void
	 */
	private function auditListResponse(array $payload, ?string $view = null): void
		{
		if (!$this->shouldAuditListResponse($payload, $view))
			{
			return;
			}

		try
			{
			$route = $this->currentRouteParts();
			$logger = new AuditLogService(Database::getManagerConnection());
			$filters = [];

			if (isset($payload['filters']) && is_array($payload['filters']))
				{
				$filters = $this->normalizeAuditFilters($payload['filters']);
				}
			else
				{
				$filters = $this->normalizeAuditFilters($_GET);
				}

			$activeFilters = array_filter(
				$filters,
				fn($value): bool => !$this->isEmptyAuditFilterValue($value)
			);

			$context = [
				'response_format' => $this->wantsJson() ? 'json' : 'html',
				'filters_applied' => $activeFilters !== [],
				'filters' => $activeFilters,
				'result_total' => isset($payload['total']) ? (int) $payload['total'] : null,
				'page' => isset($payload['page']) ? (int) $payload['page'] : 1,
				'per_page' => isset($payload['perPage']) ? (int) $payload['perPage'] : null,
			];

			$logger->logList(
				(string) ($route['resource'] ?? ''),
				$this->resolveAuditEntityName($view, (string) ($route['resource'] ?? '')),
				$context
			);
			}
		catch (\Throwable $e)
			{
			error_log('AUDIT LIST LOG ERROR: ' . $e->getMessage());
			}
		}

	/**
	 * Determines whether the current response should generate a listing audit entry.
	 *
	 * @param array<string, mixed> $payload Response payload rendered by the action.
	 * @param string|null $view Optional view path for HTML responses.
	 * @return bool
	 */
	private function shouldAuditListResponse(array $payload, ?string $view = null): bool
		{
		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET')
			{
			return false;
			}

		if (!$this->isAuthenticated())
			{
			return false;
			}

		$route = $this->currentRouteParts();
		if (($route['action'] ?? '') !== 'index')
			{
			return false;
			}

		if (($route['resource'] ?? '') === 'home')
			{
			return false;
			}

		if ($view !== null)
			{
			if (!str_ends_with($view, '/index') || $view === 'home/index')
				{
				return false;
				}
			}

		if ($view === null && !array_key_exists('data', $payload))
			{
			return false;
			}

		return true;
		}

	/**
	 * Breaks the current route into resource and action pieces.
	 *
	 * @return array<string, string>
	 */
	private function currentRouteParts(): array
		{
		$route = trim((string) ($_GET['url'] ?? ''), '/');
		if ($route === '')
			{
			return [
				'resource' => 'home',
				'action' => 'index',
			];
			}

		$segments = array_values(array_filter(explode('/', $route), static fn(string $part): bool => $part !== ''));
		$resource = $segments[0] ?? 'home';
		$action = $segments[1] ?? 'index';

		return [
			'resource' => $resource,
			'action' => $action,
		];
		}

	/**
	 * Resolves the audit entity name for listing events.
	 *
	 * @param string|null $view Optional view path for HTML responses.
	 * @param string $resource Route resource name.
	 * @return string
	 */
	private function resolveAuditEntityName(?string $view, string $resource): string
		{
		if ($view !== null)
			{
			$parts = explode('/', $view);
			if (!empty($parts[0]))
				{
				return (string) $parts[0];
				}
			}

		return $resource;
		}

	/**
	 * Normalizes filter values before persisting them in the audit context.
	 *
	 * @param array<string, mixed> $filters Raw filter payload.
	 * @return array<string, mixed>
	 */
	private function normalizeAuditFilters(array $filters): array
		{
		unset($filters['url'], $filters['format'], $filters['sort'], $filters['direction'], $filters['order']);

		$normalized = [];
		foreach ($filters as $key => $value)
			{
			if (is_string($value))
				{
				$normalized[$key] = trim($value);
				continue;
				}

			$normalized[$key] = $value;
			}

		return $normalized;
		}

	/**
	 * Checks whether the informed filter value should be considered empty.
	 *
	 * @param mixed $value Filter value.
	 * @return bool
	 */
	private function isEmptyAuditFilterValue($value): bool
		{
		if ($value === null)
			{
			return true;
			}

		if (is_string($value))
			{
			return trim($value) === '';
			}

		if (is_array($value))
			{
			return $value === [];
			}

		return false;
		}

	/**
	 * Determines whether the current request expects a JSON response.
	 */
	protected function wantsJson(): bool
		{
		if (isset($_GET['format']) && strtolower((string) $_GET['format']) === 'json')
			{
			return true;
			}

		$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
		if (stripos($accept, 'application/json') !== false)
			{
			return true;
			}

		$xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
		if (strtolower($xhr) === 'xmlhttprequest')
			{
			return true;
			}

		return false;
		}

	/**
	 * Checks whether a manager user is authenticated in the current session.
	 */
	protected function isAuthenticated()
		{
		return !empty($_SESSION['manager']['user']);
		}

	/**
	 * Redirects the client to an application path.
	 *
	 * @param string $path Relative application path.
	 */
	protected function redirect(string $path)
		{
		header('Location: ' . \url($path));
		exit;
		}

	/**
	 * Requires an authenticated session before continuing.
	 */
	protected function requireAuth(): void
		{
		if (!$this->isAuthenticated())
			{

			// If the request expects JSON, return JSON instead of redirecting with HTML.
			if ($this->wantsJson())
				{
				$this->json([
					'error' => 'unauthorized',
					'message' => 'Authentication required'
					], 401);
				}

			// Otherwise, follow the standard HTML redirect flow.
			$this->redirect('user/login');
			exit;
			}
		}

	/**
	 * Require an active tenant context.
	 */
	protected function requireTenant(): void
		{
		if (TenantContext::hasTenant())
			{
			return;
			}

		if ($this->wantsJson())
			{
			$this->json([
				'error' => 'tenant_required',
				'message' => 'Selecione um tenant antes de acessar este recurso',
			], 409);
			}

		$this->redirect('tenant/select');
		exit;
		}

	/**
	 * Require a specific permission (ability).
	 */
	protected function requirePermission(string $ability): void
		{
		$this->requireAuth();

		if (Authorization::can($ability))
			{
			return;
			}

		if ($this->wantsJson())
			{
			$this->json([
				'error' => 'forbidden',
				'message' => 'You do not have permission to access this resource',
				'ability' => $ability,
			], 403);
			}

		http_response_code(403);
		$this->view('errors/403', ['title' => 'Acesso negado', 'ability' => $ability]);
		exit;
		}
	}
?>
