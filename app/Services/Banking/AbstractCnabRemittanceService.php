<?php

namespace App\Services\Banking;

use RuntimeException;

abstract class AbstractCnabRemittanceService implements RemittanceGeneratorInterface
	{
	/**
	 * Returns a blank fixed-width CNAB line.
	 */
	protected function newLine(int $length = 240): string
		{
		return str_repeat(' ', $length);
		}

	/**
	 * Writes a fixed-width field using 1-based positions.
	 */
	protected function put(string &$line, int $start, int $end, string $value, string $type = 'alpha'): void
		{
		$length = $end - $start + 1;
		$value = ($type === 'numeric') ? $this->padNumeric($value, $length) : $this->padAlpha($value, $length);
		$line = substr_replace($line, $value, $start - 1, $length);
		}

	/**
	 * Finalizes and validates a fixed-width CNAB line.
	 */
	protected function finishLine(string $line, int $length = 240): string
		{
		$line = substr(str_pad($line, $length, ' '), 0, $length);
		if (strlen($line) !== $length)
			{
			throw new RuntimeException('Invalid CNAB line length.');
			}

		return $line;
		}

	/**
	 * Pads alphanumeric content with spaces.
	 */
	protected function padAlpha(string $value, int $length): string
		{
		$text = $this->normalizeText($value);
		return str_pad(substr($text, 0, $length), $length, ' ');
		}

	/**
	 * Pads numeric content with zeros.
	 */
	protected function padNumeric(string $value, int $length): string
		{
		$numeric = preg_replace('/\D+/', '', $value) ?? '';
		return substr(str_pad($numeric, $length, '0', STR_PAD_LEFT), -$length);
		}

	/**
	 * Converts a decimal value to CNAB cents.
	 */
	protected function money($value, int $length): string
		{
		return $this->padNumeric((string) round(((float) $value) * 100), $length);
		}

	/**
	 * Formats a date as DDMMAAAA, or zeros when absent.
	 */
	protected function dateDmy($value): string
		{
		$value = trim((string) $value);
		if ($value === '' || $value === '0000-00-00')
			{
			return '00000000';
			}

		$timestamp = strtotime($value);
		if ($timestamp === false)
			{
			return '00000000';
			}

		return date('dmY', $timestamp);
		}

	/**
	 * Returns only numeric characters.
	 */
	protected function onlyDigits(string $value): string
		{
		return preg_replace('/\D+/', '', $value) ?? '';
		}

	/**
	 * Normalizes the bank number to three digits.
	 */
	protected function bankNumber(array $bankAccount): string
		{
		return $this->padNumeric((string) ($bankAccount['bank_number'] ?? ''), 3);
		}

	/**
	 * Normalizes the account agency to four digits.
	 */
	protected function agency(array $bankAccount): string
		{
		$digits = $this->onlyDigits((string) ($bankAccount['agency'] ?? ''));
		if (strlen($digits) === 5)
			{
			return substr($digits, 0, 4);
			}

		return substr($this->padNumeric($digits, 4), -4);
		}

	/**
	 * Returns account number and digit.
	 *
	 * @return array{0: string, 1: string}
	 */
	protected function account(array $bankAccount): array
		{
		$digits = $this->onlyDigits((string) ($bankAccount['account'] ?? ''));
		if (strlen($digits) > 5)
			{
			$tail = substr($digits, -6);
			return [substr($tail, 0, 5), substr($tail, 5, 1)];
			}

		return [substr($this->padNumeric($digits, 5), -5), '0'];
		}

	/**
	 * Returns a document type code for CNAB.
	 */
	protected function documentType(string $document, string $default = '2'): string
		{
		$digits = $this->onlyDigits($document);
		if (strlen($digits) === 11)
			{
			return '1';
			}
		if (strlen($digits) === 14)
			{
			return '2';
			}

		return $default;
		}

	/**
	 * Calculates a modulo 10 check digit.
	 */
	protected function modulo10Digit(string $value): int
		{
		$digits = strrev($this->onlyDigits($value));
		$sum = 0;

		for ($i = 0; $i < strlen($digits); $i++)
			{
			$product = (int) $digits[$i] * (($i % 2 === 0) ? 2 : 1);
			if ($product > 9)
				{
				$product = intdiv($product, 10) + ($product % 10);
				}
			$sum += $product;
			}

		$remainder = $sum % 10;
		return $remainder === 0 ? 0 : 10 - $remainder;
		}

	/**
	 * Removes accents and unsupported characters.
	 */
	private function normalizeText(string $value): string
		{
		return normalizeAsciiText($value);
		}
	}
?>
