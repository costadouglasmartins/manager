<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

class PdfService
	{
	/**
	 * Generates a PDF document containing a generic tabular report.
	 *
	 * @param array<string, string|array<string, string>> $columns
	 * @param array<int, array<string, mixed>> $rows
	 * @param array<string, mixed> $filters
	 */
	public function generateTableReport(
		string $title,
		array $columns,
		array $rows,
		array $filters = [],
		string $orientation = 'portrait'
	): string
		{
		$options = new Options();
		$options->setChroot(ROOT_PATH);
		$options->setDefaultFont('DejaVu Sans');
		$options->setIsRemoteEnabled(false);

		$dompdf = new Dompdf($options);
		$dompdf->loadHtml(
			$this->buildTableReportHtml($title, $columns, $rows, $filters),
			'UTF-8'
		);
		$dompdf->setPaper('A4', $orientation === 'landscape' ? 'landscape' : 'portrait');
		$dompdf->render();

		$canvas = $dompdf->getCanvas();
		$font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
		$canvas->page_text(
			36,
			$canvas->get_height() - 24,
			'Página {PAGE_NUM} de {PAGE_COUNT}',
			$font,
			8,
			[0.35, 0.35, 0.35]
		);

		return $dompdf->output();
		}

	/**
	 * Sends a generated tabular report as a PDF download.
	 *
	 * @param array<string, string|array<string, string>> $columns
	 * @param array<int, array<string, mixed>> $rows
	 * @param array<string, mixed> $filters
	 */
	public function downloadTableReport(
		string $title,
		array $columns,
		array $rows,
		array $filters = [],
		string $filename = 'report.pdf',
		string $orientation = 'portrait'
	): void
		{
		if (headers_sent())
			{
			throw new RuntimeException('PDF download headers have already been sent.');
			}

		$pdf = $this->generateTableReport($title, $columns, $rows, $filters, $orientation);
		$safeFilename = $this->sanitizeFilename($filename);

		header('Content-Type: application/pdf');
		header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
		header('Content-Length: ' . strlen($pdf));
		header('Cache-Control: private, max-age=0, must-revalidate');

		echo $pdf;
		exit;
		}

	/**
	 * Builds the self-contained HTML used by Dompdf.
	 *
	 * @param array<string, string|array<string, string>> $columns
	 * @param array<int, array<string, mixed>> $rows
	 * @param array<string, mixed> $filters
	 */
	private function buildTableReportHtml(
		string $title,
		array $columns,
		array $rows,
		array $filters
	): string
		{
		$headerCells = '';
		foreach ($columns as $key => $definition)
			{
			$config = is_array($definition) ? $definition : ['label' => $definition];
			$label = (string) ($config['label'] ?? $key);
			$align = $this->normalizeAlignment((string) ($config['align'] ?? 'left'));
			$width = $this->normalizeWidth((string) ($config['width'] ?? ''));
			$style = 'text-align:' . $align . ';';
			if ($width !== '')
				{
				$style .= 'width:' . $width . ';';
				}

			$headerCells .= '<th style="' . $style . '">' . $this->escape($label) . '</th>';
			}

		$bodyRows = '';
		foreach ($rows as $row)
			{
			$bodyRows .= '<tr>';
			foreach ($columns as $key => $definition)
				{
				$config = is_array($definition) ? $definition : [];
				$align = $this->normalizeAlignment((string) ($config['align'] ?? 'left'));
				$value = $this->formatValue($row[$key] ?? null);
				$bodyRows .= '<td style="text-align:' . $align . ';">' . $this->escape($value) . '</td>';
				}
			$bodyRows .= '</tr>';
			}

		if ($bodyRows === '')
			{
			$bodyRows = '<tr><td class="empty" colspan="' . max(1, count($columns)) . '">Nenhum registro encontrado.</td></tr>';
			}

		$filterItems = '';
		foreach ($filters as $label => $value)
			{
			$formattedValue = trim($this->formatValue($value));
			if ($formattedValue === '')
				{
				continue;
				}

			$filterItems .= '<span><strong>' . $this->escape((string) $label) . ':</strong> '
				. $this->escape($formattedValue) . '</span>';
			}

		if ($filterItems === '')
			{
			$filterItems = '<span>Nenhum filtro aplicado</span>';
			}

		return '<!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="UTF-8">
	<style>
		@page { margin: 34px 36px 42px; }
		body { color: #1f2933; font-family: "DejaVu Sans", sans-serif; font-size: 10px; }
		h1 { font-size: 18px; margin: 0 0 8px; }
		.meta { color: #52606d; margin-bottom: 14px; }
		.filters { background: #f1f5f9; border: 1px solid #d9e2ec; margin-bottom: 14px; padding: 8px 10px; }
		.filters span { display: inline-block; margin-right: 18px; }
		table { border-collapse: collapse; width: 100%; }
		thead { display: table-header-group; }
		tr { page-break-inside: avoid; }
		th { background: #243b53; color: #ffffff; font-weight: bold; padding: 7px; }
		td { border-bottom: 1px solid #d9e2ec; padding: 6px 7px; vertical-align: top; }
		tbody tr:nth-child(even) { background: #f8fafc; }
		.empty { padding: 18px; text-align: center; }
	</style>
</head>
<body>
	<h1>' . $this->escape($title) . '</h1>
	<div class="meta">Gerado em ' . $this->escape(date('d/m/Y H:i:s')) . ' | '
			. count($rows) . ' registro(s)</div>
	<div class="filters">' . $filterItems . '</div>
	<table>
		<thead><tr>' . $headerCells . '</tr></thead>
		<tbody>' . $bodyRows . '</tbody>
	</table>
</body>
</html>';
		}

	/**
	 * Converts supported report values to displayable text.
	 */
	private function formatValue($value): string
		{
		if ($value === null)
			{
			return '';
			}

		if (is_bool($value))
			{
			return $value ? 'Sim' : 'Não';
			}

		if (is_scalar($value))
			{
			return (string) $value;
			}

		$encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return is_string($encoded) ? $encoded : '';
		}

	/**
	 * Escapes dynamic content before it is inserted into the report HTML.
	 */
	private function escape(string $value): string
		{
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		}

	/**
	 * Restricts table alignment values to valid CSS options.
	 */
	private function normalizeAlignment(string $alignment): string
		{
		return in_array($alignment, ['left', 'center', 'right'], true) ? $alignment : 'left';
		}

	/**
	 * Restricts optional widths to numeric CSS units.
	 */
	private function normalizeWidth(string $width): string
		{
		return preg_match('/^\d+(?:\.\d+)?(?:%|px|pt|cm|mm)$/', $width) === 1 ? $width : '';
		}

	/**
	 * Produces a safe ASCII filename for the response header.
	 */
	private function sanitizeFilename(string $filename): string
		{
		$filename = trim($filename);
		$ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);
		if (is_string($ascii) && $ascii !== '')
			{
			$filename = $ascii;
			}

		$filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?? '';
		$filename = trim($filename, '-.');
		if ($filename === '')
			{
			$filename = 'report.pdf';
			}

		if (!str_ends_with(strtolower($filename), '.pdf'))
			{
			$filename .= '.pdf';
			}

		return $filename;
		}
	}

?>
