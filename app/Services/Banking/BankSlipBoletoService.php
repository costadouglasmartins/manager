<?php

namespace App\Services\Banking;

use DateTimeImmutable;

class BankSlipBoletoService
	{
	/**
	 * Builds printable boleto data from a bank slip row.
	 */
	public function build(array $bankSlip): array
		{
		$bankNumber = $this->padNumeric((string) ($bankSlip['bank_number'] ?? '341'), 3);
		$currencyCode = '9';
		$dueDateFactor = $this->dueDateFactor((string) ($bankSlip['due_date'] ?? ''));
		$amount = $this->padNumeric((string) round(((float) ($bankSlip['amount'] ?? 0)) * 100), 10);
		$freeField = $this->buildFreeField($bankSlip, $bankNumber);

		$withoutGeneralDigit = $bankNumber . $currencyCode . $dueDateFactor . $amount . $freeField;
		$generalDigit = (string) $this->modulo11BoletoDigit($withoutGeneralDigit);
		$barcode = substr($withoutGeneralDigit, 0, 4) . $generalDigit . substr($withoutGeneralDigit, 4);

		return [
			'barcode' => $barcode,
			'digitable_line' => $this->digitableLine($barcode),
			'barcode_svg' => $this->interleaved2of5Svg($barcode),
			'our_number_display' => $this->ourNumberDisplay($bankSlip, $bankNumber),
			'agency_account_display' => $this->agencyAccountDisplay($bankSlip),
		];
		}

	/**
	 * Builds the 25-digit free field used in the barcode.
	 */
	private function buildFreeField(array $bankSlip, string $bankNumber): string
		{
		if ($bankNumber === '341')
			{
			return $this->buildItauFreeField($bankSlip);
			}

		$portfolio = $this->padNumeric((string) ($bankSlip['portfolio'] ?? '0'), 3);
		$ourNumber = $this->padNumeric($this->ourNumber($bankSlip), 10);
		$agency = $this->agencyNumber((string) ($bankSlip['agency'] ?? ''));
		$account = $this->accountNumber((string) ($bankSlip['account'] ?? ''))[0];
		$document = $this->padNumeric((string) ($bankSlip['document_number'] ?? $bankSlip['id'] ?? '0'), 3);

		return substr($portfolio . $ourNumber . $agency . $account . $document, 0, 25);
		}

	/**
	 * Builds Itaú barcode free field: carteira, nosso número, DAC, agência, conta, DAC, zeros.
	 */
	private function buildItauFreeField(array $bankSlip): string
		{
		$portfolio = $this->padNumeric((string) ($bankSlip['portfolio'] ?? '109'), 3);
		$ourNumber = $this->padNumeric($this->ourNumber($bankSlip), 8);
		$agency = $this->agencyNumber((string) ($bankSlip['agency'] ?? ''));
		[$account, $accountDigit] = $this->accountNumber((string) ($bankSlip['account'] ?? ''));

		$ourNumberDigit = $this->modulo10Digit($agency . $account . $portfolio . $ourNumber);
		if ($accountDigit === '')
			{
			$accountDigit = (string) $this->modulo10Digit($agency . $account);
			}

		return $portfolio . $ourNumber . $ourNumberDigit . $agency . $account . $accountDigit . '000';
		}

	/**
	 * Formats the "nosso número" field for display.
	 */
	private function ourNumberDisplay(array $bankSlip, string $bankNumber): string
		{
		$portfolio = $this->padNumeric((string) ($bankSlip['portfolio'] ?? '109'), 3);
		$ourNumber = $this->padNumeric($this->ourNumber($bankSlip), 8);

		if ($bankNumber !== '341')
			{
			return $portfolio . '/' . $ourNumber;
			}

		$agency = $this->agencyNumber((string) ($bankSlip['agency'] ?? ''));
		[$account] = $this->accountNumber((string) ($bankSlip['account'] ?? ''));
		$digit = $this->modulo10Digit($agency . $account . $portfolio . $ourNumber);

		return $portfolio . '/' . $ourNumber . '-' . $digit;
		}

	/**
	 * Formats the agency/account value for display.
	 */
	private function agencyAccountDisplay(array $bankSlip): string
		{
		$agency = $this->agencyNumber((string) ($bankSlip['agency'] ?? ''));
		[$account, $digit] = $this->accountNumber((string) ($bankSlip['account'] ?? ''));

		return $agency . ' / ' . $account . ($digit !== '' ? '-' . $digit : '');
		}

	/**
	 * Converts the barcode into a boleto digitable line.
	 */
	private function digitableLine(string $barcode): string
		{
		$field1 = substr($barcode, 0, 4) . substr($barcode, 19, 5);
		$field1 .= (string) $this->modulo10Digit($field1);

		$field2 = substr($barcode, 24, 10);
		$field2 .= (string) $this->modulo10Digit($field2);

		$field3 = substr($barcode, 34, 10);
		$field3 .= (string) $this->modulo10Digit($field3);

		$field4 = substr($barcode, 4, 1);
		$field5 = substr($barcode, 5, 14);

		return substr($field1, 0, 5) . '.' . substr($field1, 5) . ' ' .
			substr($field2, 0, 5) . '.' . substr($field2, 5) . ' ' .
			substr($field3, 0, 5) . '.' . substr($field3, 5) . ' ' .
			$field4 . ' ' . $field5;
		}

	/**
	 * Calculates the boleto due date factor, including the 2025 rollover rule.
	 */
	private function dueDateFactor(string $date): string
		{
		$date = substr(trim($date), 0, 10);
		$dueDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
		if (!$dueDate)
			{
			return '0000';
			}

		$resetDate = new DateTimeImmutable('2025-02-22');
		if ($dueDate >= $resetDate)
			{
			return $this->padNumeric((string) (1000 + (int) $resetDate->diff($dueDate)->days), 4);
			}

		$baseDate = new DateTimeImmutable('1997-10-07');
		return $this->padNumeric((string) (int) $baseDate->diff($dueDate)->days, 4);
		}

	/**
	 * Calculates modulo 10 check digit.
	 */
	private function modulo10Digit(string $number): int
		{
		$sum = 0;
		$multiplier = 2;

		for ($i = strlen($number) - 1; $i >= 0; $i--)
			{
			$product = ((int) $number[$i]) * $multiplier;
			$sum += $product >= 10 ? intdiv($product, 10) + ($product % 10) : $product;
			$multiplier = $multiplier === 2 ? 1 : 2;
			}

		$remainder = $sum % 10;
		return $remainder === 0 ? 0 : 10 - $remainder;
		}

	/**
	 * Calculates the boleto general barcode check digit.
	 */
	private function modulo11BoletoDigit(string $number): int
		{
		$sum = 0;
		$multiplier = 2;

		for ($i = strlen($number) - 1; $i >= 0; $i--)
			{
			$sum += ((int) $number[$i]) * $multiplier;
			$multiplier++;
			if ($multiplier > 9)
				{
				$multiplier = 2;
				}
			}

		$digit = 11 - ($sum % 11);
		return in_array($digit, [0, 10, 11], true) ? 1 : $digit;
		}

	/**
	 * Renders an Interleaved 2 of 5 barcode as SVG.
	 */
	private function interleaved2of5Svg(string $number): string
		{
		if ((strlen($number) % 2) !== 0)
			{
			$number = '0' . $number;
			}

		$patterns = [
			'0' => 'nnwwn',
			'1' => 'wnnnw',
			'2' => 'nwnnw',
			'3' => 'wwnnn',
			'4' => 'nnwnw',
			'5' => 'wnwnn',
			'6' => 'nwwnn',
			'7' => 'nnnww',
			'8' => 'wnnwn',
			'9' => 'nwnwn',
		];

		$narrow = 2;
		$wide = 5;
		$height = 70;
		$x = 0;
		$bars = [];

		$this->appendBar($bars, $x, $narrow, $height);
		$x += $narrow + $narrow;
		$this->appendBar($bars, $x, $narrow, $height);
		$x += $narrow + $narrow;

		for ($i = 0; $i < strlen($number); $i += 2)
			{
			$barPattern = $patterns[$number[$i]];
			$spacePattern = $patterns[$number[$i + 1]];

			for ($j = 0; $j < 5; $j++)
				{
				$barWidth = $barPattern[$j] === 'w' ? $wide : $narrow;
				$spaceWidth = $spacePattern[$j] === 'w' ? $wide : $narrow;
				$this->appendBar($bars, $x, $barWidth, $height);
				$x += $barWidth + $spaceWidth;
				}
			}

		$this->appendBar($bars, $x, $wide, $height);
		$x += $wide + $narrow;
		$this->appendBar($bars, $x, $narrow, $height);
		$x += $narrow;

		return '<svg class="barcode-svg" viewBox="0 0 ' . $x . ' ' . $height . '" role="img" aria-label="Código de barras" preserveAspectRatio="none" shape-rendering="crispEdges">' .
			implode('', $bars) .
			'</svg>';
		}

	/**
	 * Appends a barcode rectangle.
	 */
	private function appendBar(array &$bars, int $x, int $width, int $height): void
		{
		$bars[] = '<rect x="' . $x . '" y="0" width="' . $width . '" height="' . $height . '"></rect>';
		}

	/**
	 * Returns the normalized nosso número source.
	 */
	private function ourNumber(array $bankSlip): string
		{
		$value = (string) ($bankSlip['our_number'] ?? '');
		if (trim($value) === '')
			{
			$value = (string) ($bankSlip['id'] ?? '0');
			}

		return $value;
		}

	/**
	 * Normalizes Itaú agency number to four digits.
	 */
	private function agencyNumber(string $agency): string
		{
		$digits = preg_replace('/\D+/', '', $agency) ?? '';
		if (strlen($digits) === 5)
			{
			return substr($digits, 0, 4);
			}

		return substr($this->padNumeric($digits, 4), -4);
		}

	/**
	 * Normalizes Itaú account number to account and optional digit.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function accountNumber(string $account): array
		{
		$digits = preg_replace('/\D+/', '', $account) ?? '';
		if (strlen($digits) > 5)
			{
			$tail = substr($digits, -6);
			return [substr($tail, 0, 5), substr($tail, 5, 1)];
			}

		return [substr($this->padNumeric($digits, 5), -5), ''];
		}

	/**
	 * Left pads numeric text and keeps the rightmost digits.
	 */
	private function padNumeric(string $value, int $length): string
		{
		$numeric = preg_replace('/\D+/', '', $value) ?? '';
		return substr(str_pad($numeric, $length, '0', STR_PAD_LEFT), -$length);
		}
	}
?>
