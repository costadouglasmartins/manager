<?php

namespace App\Core;

use App\Core\Authorization;

class Controller
	{

	/**
	 * Renders a view, optionally wrapping it with the main layout.
	 *
	 * @param string $view View name relative to `app/Views`.
	 * @param array $data Data exposed to the view.
	 */
	protected function view(string $view, array $data = [], bool $useLayout = true): void
		{
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