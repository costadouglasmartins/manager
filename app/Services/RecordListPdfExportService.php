<?php

namespace App\Services;

/**
 * Exports filtered record lists to PDF files.
 */
class RecordListPdfExportService extends RecordListExportService
	{
	/**
	 * Streams the filtered list PDF file to the browser.
	 *
	 * @param string $resource Resource key derived from the controller route.
	 * @param array<string, mixed> $query Query string values.
	 * @return void
	 */
	public function stream(string $resource, array $query = []): void
		{
		$payload = $this->buildExportPayload($resource, $query);
		$definition = $payload['definition'];
		$columns = $definition['columns'] ?? [];
		$title = (string) ($definition['title'] ?? 'Registros');
		$filename = $this->filename((string) ($definition['filename'] ?? $resource), 'pdf');
		$orientation = (string) ($definition['pdfOrientation'] ?? $this->defaultOrientation($columns));

		$pdfService = new PdfService();
		$pdfService->downloadTableReport(
			$title,
			$this->pdfColumns($columns),
			$this->pdfRows($columns, $payload['rows']),
			$this->pdfFilters($payload['filters']),
			$filename,
			$orientation
		);
		}

	/**
	 * Builds PDF column definitions keyed by generated stable names.
	 *
	 * @param array<int, array<string, mixed>> $columns Shared export columns.
	 * @return array<string, array<string, string>>
	 */
	private function pdfColumns(array $columns): array
		{
		$pdfColumns = [];
		foreach ($columns as $index => $column)
			{
			$key = $this->pdfColumnKey($index);
			$pdfColumns[$key] = [
				'label' => (string) ($column['label'] ?? ''),
				'align' => (string) ($column['align'] ?? 'left'),
			];

			if (!empty($column['pdfWidth']))
				{
				$pdfColumns[$key]['width'] = (string) $column['pdfWidth'];
				}
			}

		return $pdfColumns;
		}

	/**
	 * Formats result rows using the shared export column definitions.
	 *
	 * @param array<int, array<string, mixed>> $columns Shared export columns.
	 * @param array<int, array<string, mixed>> $rows Raw result rows.
	 * @return array<int, array<string, string>>
	 */
	private function pdfRows(array $columns, array $rows): array
		{
		$pdfRows = [];
		foreach ($rows as $row)
			{
			$pdfRow = [];
			foreach ($columns as $index => $column)
				{
				$pdfRow[$this->pdfColumnKey($index)] = $this->columnValue($column, $row);
				}
			$pdfRows[] = $pdfRow;
			}

		return $pdfRows;
		}

	/**
	 * Builds readable filter labels for the PDF report header.
	 *
	 * @param array<string, mixed> $filters Active filters.
	 * @return array<string, string>
	 */
	private function pdfFilters(array $filters): array
		{
		$pdfFilters = [];
		foreach ($filters as $key => $value)
			{
			$value = $this->stringValue($value);
			if ($value === '')
				{
				continue;
				}

			$pdfFilters[$this->humanLabel((string) $key)] = $value;
			}

		return $pdfFilters;
		}

	/**
	 * Returns a stable generated column key.
	 */
	private function pdfColumnKey(int $index): string
		{
		return 'column_' . $index;
		}

	/**
	 * Chooses a readable paper orientation based on column count.
	 *
	 * @param array<int, array<string, mixed>> $columns Shared export columns.
	 * @return string
	 */
	private function defaultOrientation(array $columns): string
		{
		return count($columns) > 5 ? 'landscape' : 'portrait';
		}
	}

?>
