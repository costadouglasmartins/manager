<?php
$year = (int) ($year ?? date('Y'));
$report = is_array($report ?? null) ? $report : [];
$months = $report['months'] ?? [];
$sections = $report['sections'] ?? [];
$canEditSpreadsheet = function_exists('authCan') && (authCan('financial_operation.store') || authCan('financial_operation.update'));
$monthAbbreviations = [
	1  => 'JAN',
	2  => 'FEV',
	3  => 'MAR',
	4  => 'ABR',
	5  => 'MAI',
	6  => 'JUN',
	7  => 'JUL',
	8  => 'AGO',
	9  => 'SET',
	10 => 'OUT',
	11 => 'NOV',
	12 => 'DEZ',
];

$formatMoney = static function ($value): string {
	return number_format((float) $value, 2, ',', '.');
};

$formatPercent = static function ($value): string {
	if ($value === null)
		{
		return '-';
		}

	return number_format((float) $value, 2, ',', '.');
};
?>
<link rel="stylesheet" href="<?= url('assets/css/financial_operations/spreadsheet.css?v=9'); ?>">

<div class="d-flex flex-column gap-3">
	<div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-end gap-3">
		<div>
			<span class="text-secondary small fw-bold text-uppercase d-inline-flex align-items-center gap-1"><i class="bi bi-table"></i> Financeiro</span>
			<h5 class="fw-bold mb-0 mt-1">Planilha anual</h5>
		</div>

		<form action="<?= url('financial_operation/spreadsheet'); ?>" method="get" class="row g-2 align-items-end">
			<div class="col-12 col-sm-auto">
				<label class="form-label" for="year">Ano</label>
				<input class="form-control" id="year" max="2100" min="1900" name="year" type="number" value="<?= $year; ?>">
			</div>
			<div class="col-12 col-sm-auto">
				<button class="btn btn-primary w-100" type="submit"><i class="bi bi-search"></i> Filtrar</button>
			</div>
		</form>
	</div>

	<div class="row g-3">
		<div class="col-12 col-md-4">
			<div class="card h-100 border-start border-success border-4">
				<div class="card-body py-3">
					<span class="text-secondary small fw-bold text-uppercase">Total receitas</span>
					<strong class="d-block fs-5 lh-sm mt-1">R$ <?= $formatMoney($sections['income']['total'] ?? 0); ?></strong>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-4">
			<div class="card h-100 border-start border-danger border-4">
				<div class="card-body py-3">
					<span class="text-secondary small fw-bold text-uppercase">Total despesas</span>
					<strong class="d-block fs-5 lh-sm mt-1">R$ <?= $formatMoney($sections['expense']['total'] ?? 0); ?></strong>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-4">
			<div class="card h-100 border-start border-warning border-4">
				<div class="card-body py-3">
					<span class="text-secondary small fw-bold text-uppercase">Saldo anual</span>
					<strong class="d-block fs-5 lh-sm mt-1">R$ <?= $formatMoney($report['balance_total'] ?? 0); ?></strong>
				</div>
			</div>
		</div>
	</div>

	<div class="table-responsive border rounded financial-spreadsheet-shell">
		<table class="table table-bordered table-sm align-middle mb-0 financial-spreadsheet" aria-label="Planilha anual financeira">
			<thead>
				<tr class="table-success text-success-emphasis text-center fw-bold">
					<th class="financial-spreadsheet-label position-sticky start-0 text-start"></th>
					<th colspan="13">RECEITAS E DESPESAS - <?= $year; ?></th>
				</tr>
				<tr class="table-dark text-center fw-bold">
					<th class="financial-spreadsheet-label position-sticky start-0 text-start">Categoria</th>
					<?php foreach ($months as $monthNumber => $month): ?>
						<th>
							<span class="financial-spreadsheet-month-full"><?= htmlspecialchars((string) $month, ENT_QUOTES, 'UTF-8'); ?></span>
							<span class="financial-spreadsheet-month-short"><?= htmlspecialchars((string) ($monthAbbreviations[$monthNumber] ?? $month), ENT_QUOTES, 'UTF-8'); ?></span>
						</th>
					<?php endforeach; ?>
					<th>Total</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach (['income', 'expense'] as $sectionKey): ?>
					<?php
					$section = $sections[$sectionKey] ?? ['title' => '', 'rows' => [], 'month_totals' => array_fill(1, 12, 0.0), 'total' => 0.0];
					$isIncome = $sectionKey === 'income';
					$rowContextClass = $isIncome ? 'table-success text-success-emphasis' : 'table-danger text-danger-emphasis';
					?>
					<tr class="<?= $isIncome ? 'table-success text-success-emphasis' : 'table-danger text-white'; ?> text-center fw-bold">
						<th colspan="14" class="text-center"><?= htmlspecialchars((string) $section['title'], ENT_QUOTES, 'UTF-8'); ?></th>
					</tr>

					<?php if (!empty($section['rows'])): ?>
						<?php foreach ($section['rows'] as $row): ?>
							<tr class="<?= $rowContextClass; ?>">
								<th class="financial-spreadsheet-label position-sticky start-0 text-start <?= $isIncome ? 'fw-bold' : 'fw-normal'; ?>">
									<?= htmlspecialchars((string) $row['label'], ENT_QUOTES, 'UTF-8'); ?>
								</th>
								<?php foreach ($months as $monthNumber => $month): ?>
									<?php $cellValue = $formatMoney($row['months'][$monthNumber] ?? 0); ?>
									<td
										class="<?= $canEditSpreadsheet ? 'financial-spreadsheet-value js-financial-spreadsheet-cell ' : ''; ?>text-end text-nowrap"
										<?php if ($canEditSpreadsheet): ?>
											data-category-id="<?= (int) $row['category_id']; ?>"
											data-subcategory-id="<?= $row['subcategory_id'] !== null ? (int) $row['subcategory_id'] : ''; ?>"
											data-row-label="<?= htmlspecialchars((string) $row['label'], ENT_QUOTES, 'UTF-8'); ?>"
											data-month="<?= (int) $monthNumber; ?>"
											data-month-label="<?= htmlspecialchars((string) $month, ENT_QUOTES, 'UTF-8'); ?>"
											data-year="<?= $year; ?>"
											data-value="<?= htmlspecialchars($cellValue, ENT_QUOTES, 'UTF-8'); ?>"
										<?php endif; ?>
									>
										<?php if ($canEditSpreadsheet): ?>
											<i class="bi bi-pencil-square financial-spreadsheet-edit-icon" aria-hidden="true"></i>
										<?php endif; ?>
										<span><?= $cellValue; ?></span>
									</td>
								<?php endforeach; ?>
								<td class="text-end text-nowrap fw-bold"><?= $formatMoney($row['total'] ?? 0); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr class="<?= $rowContextClass; ?>">
							<th class="financial-spreadsheet-label position-sticky start-0 text-start">Nenhuma categoria cadastrada</th>
							<?php foreach ($months as $month): ?>
								<td class="text-end text-nowrap">0,00</td>
							<?php endforeach; ?>
							<td class="text-end text-nowrap fw-bold">0,00</td>
						</tr>
					<?php endif; ?>

					<tr class="<?= $rowContextClass; ?> fw-bold">
						<th class="financial-spreadsheet-label position-sticky start-0 text-start">SUBTOTAL <?= htmlspecialchars((string) $section['title'], ENT_QUOTES, 'UTF-8'); ?></th>
						<?php foreach ($months as $monthNumber => $month): ?>
							<td class="text-end text-nowrap"><?= $formatMoney($section['month_totals'][$monthNumber] ?? 0); ?></td>
						<?php endforeach; ?>
						<td class="text-end text-nowrap"><?= $formatMoney($section['total'] ?? 0); ?></td>
					</tr>
				<?php endforeach; ?>

				<tr class="table-warning text-warning-emphasis text-center fw-bold">
					<th colspan="14" class="text-center">SALDOS</th>
				</tr>
				<tr class="table-warning text-warning-emphasis fw-bold">
					<th class="financial-spreadsheet-label position-sticky start-0 text-start">SUBTOTAL SALDOS (R$)</th>
					<?php foreach ($months as $monthNumber => $month): ?>
						<td class="text-end text-nowrap"><?= $formatMoney($report['balance_months'][$monthNumber] ?? 0); ?></td>
					<?php endforeach; ?>
					<td class="text-end text-nowrap"><?= $formatMoney($report['balance_total'] ?? 0); ?></td>
				</tr>
				<tr class="table-warning text-warning-emphasis fw-bold">
					<th class="financial-spreadsheet-label position-sticky start-0 text-start">PERCENTUAL DESPESAS (%)</th>
					<?php foreach ($months as $monthNumber => $month): ?>
						<td class="text-end text-nowrap"><?= $formatPercent($report['expense_percent_months'][$monthNumber] ?? null); ?></td>
					<?php endforeach; ?>
					<td class="text-end text-nowrap"><?= $formatPercent($report['expense_percent_total'] ?? null); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>

<?php if ($canEditSpreadsheet): ?>
	<div class="modal fade" id="financialSpreadsheetCellModal" tabindex="-1" aria-labelledby="financialSpreadsheetCellModalTitle" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<form class="modal-content needs-validation" id="financialSpreadsheetCellForm" action="<?= url('financial_operation/spreadsheetSave'); ?>" method="POST" novalidate>
				<input type="hidden" id="financialSpreadsheetCategoryId" name="financial_operation_category_id">
				<input type="hidden" id="financialSpreadsheetSubcategoryId" name="financial_operation_subcategory_id">
				<input type="hidden" id="financialSpreadsheetYear" name="year">
				<input type="hidden" id="financialSpreadsheetMonth" name="month">

				<div class="modal-header">
					<h5 class="modal-title" id="financialSpreadsheetCellModalTitle">Operação financeira</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
				</div>

				<div class="modal-body">
					<div class="alert alert-danger d-none" id="financialSpreadsheetCellError" role="alert"></div>

					<div class="row g-3">
						<div class="col-12">
							<label class="form-label" for="financialSpreadsheetCategoryLabel">Categoria</label>
							<input class="form-control" id="financialSpreadsheetCategoryLabel" type="text" readonly>
						</div>

						<div class="col-12 col-md-6">
							<label class="form-label" for="financialSpreadsheetMonthLabel">Mês</label>
							<input class="form-control" id="financialSpreadsheetMonthLabel" type="text" readonly>
						</div>

						<div class="col-12 col-md-6">
							<label class="form-label" for="financialSpreadsheetValue">Valor</label>
							<input autocomplete="off" class="form-control js-money" id="financialSpreadsheetValue" inputmode="decimal" name="value" required type="text" placeholder="0,00">
							<div class="invalid-feedback">Informe o valor.</div>
						</div>
					</div>
				</div>

				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary" id="financialSpreadsheetCellSubmit"><i class="bi bi-check-lg"></i> Salvar</button>
				</div>
			</form>
		</div>
	</div>

	<script src="<?= url('assets/js/financial_operations/spreadsheet.js?v=1'); ?>"></script>
<?php endif; ?>
