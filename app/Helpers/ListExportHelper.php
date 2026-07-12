<?php
/**
 * List export UI helpers.
 */

if (!function_exists('listExportColumns'))
	{
	/**
	 * Returns configured export columns for a list resource.
	 *
	 * @param string $route Base list route.
	 * @return array<int, array{key: string, label: string, checked: bool}>
	 */
	function listExportColumns(string $route): array
		{
		$resource = trim($route, '/');
		$serviceClass = \App\Services\RecordListExportService::class;

		if (!class_exists($serviceClass))
			{
			return [];
			}

		$service = new $serviceClass();

		if (!$service->supports($resource))
			{
			return [];
			}

		return $service->exportColumns($resource);
		}
	}

if (!function_exists('listExportDropdownButton'))
	{
	/**
	 * Renders an export dropdown with column selection checkboxes.
	 *
	 * @param string $route Base list route.
	 * @param string $href Export URL.
	 * @param string $format Export format label.
	 * @param string $label Button label.
	 * @param string $class CSS classes.
	 * @param string $icon Bootstrap icon class.
	 * @return string
	 */
	function listExportDropdownButton(
		string $route,
		string $href,
		string $format,
		string $label,
		string $class,
		string $icon
	): string
		{
		static $counter = 0;

		$columns = listExportColumns($route);
		if (empty($columns))
			{
			return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">' .
				'<i class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . ' me-1" aria-hidden="true"></i>' .
				htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
				'</a>';
			}

		$counter++;
		$baseId = preg_replace('/[^a-zA-Z0-9_-]+/', '-', trim($route . '-' . $format, '-')) ?: 'list-export';
		$dropdownId = 'listExportDropdown-' . $baseId . '-' . $counter;
		$items = '';

		foreach ($columns as $index => $column)
			{
			$inputId = $dropdownId . '-column-' . $index;
			$checked = (bool) ($column['checked'] ?? true) ? ' checked' : '';
			$items .= '<label class="dropdown-item list-export-column" for="' . htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') . '">' .
				'<input class="form-check-input js-list-export-column" id="' . htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') . '" type="checkbox" value="' . htmlspecialchars((string) $column['key'], ENT_QUOTES, 'UTF-8') . '"' . $checked . '>' .
				'<span>' . htmlspecialchars((string) $column['label'], ENT_QUOTES, 'UTF-8') . '</span>' .
				'</label>';
			}

		return '<div class="dropdown d-inline-block list-export-dropdown" data-list-export-dropdown data-list-export-format="' . htmlspecialchars($format, ENT_QUOTES, 'UTF-8') . '">' .
			'<button class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . ' dropdown-toggle" type="button" id="' . htmlspecialchars($dropdownId, ENT_QUOTES, 'UTF-8') . '" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">' .
			'<i class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . ' me-1" aria-hidden="true"></i>' .
			htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
			'</button>' .
			'<div class="dropdown-menu dropdown-menu-end list-export-menu" aria-labelledby="' . htmlspecialchars($dropdownId, ENT_QUOTES, 'UTF-8') . '">' .
			'<div class="list-export-menu-title">Colunas para exportar</div>' .
			'<div class="list-export-columns">' . $items . '</div>' .
			'<div class="d-flex gap-2 my-2">' .
			'<button class="btn btn-outline-secondary btn-sm flex-fill js-list-export-select-all" type="button">Todas</button>' .
			'<button class="btn btn-outline-secondary btn-sm flex-fill js-list-export-clear" type="button">Limpar</button>' .
			'</div>' .
			'<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . ' w-100 js-list-export-submit" data-list-export-url="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' .
			'<i class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . ' me-1" aria-hidden="true"></i>' .
			htmlspecialchars($label, ENT_QUOTES, 'UTF-8') .
			'</a>' .
			'</div>' .
			'</div>';
		}
	}

?>
