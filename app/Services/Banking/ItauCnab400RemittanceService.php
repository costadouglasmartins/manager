<?php

namespace App\Services\Banking;

class ItauCnab400RemittanceService
	{
	/**
	 * Generates a CNAB400 remittance payload.
	 *
	 * Reference: Itaú Cobrança CNAB400 layout.
	 */
	public function generate(array $bankAccount, array $bankSlips): string
		{
		$lines = [];
		$recordNumber = 1;
		$generationDate = date('dmy');
		$generationTime = date('His');

		$header = '';
		$header .= '0';
		$header .= '1';
		$header .= 'REMESSA';
		$header .= '01';
		$header .= $this->pad('COBRANCA', 15);
		$header .= $this->pad($bankAccount['name'] ?? 'CEDENTE', 30);
		$header .= $this->padNumeric(preg_replace('/\D+/', '', (string) ($bankAccount['agency'] ?? '')), 4);
		$header .= $this->padNumeric(preg_replace('/\D+/', '', (string) ($bankAccount['account'] ?? '')), 8);
		$header .= $this->pad('00000', 5);
		$header .= $this->pad('ITAU', 15);
		$header .= $generationDate;
		$header .= $this->pad($generationTime, 6);
		$header .= $this->padNumeric((string) $recordNumber, 6);
		$lines[] = $this->finalizeLine($header);

		$recordNumber++;
		foreach ($bankSlips as $slip)
			{
			$line = '';
			$line .= '1';
			$line .= $this->padNumeric(preg_replace('/\D+/', '', (string) ($slip['portfolio'] ?? '109')), 3);
			$line .= $this->padNumeric(preg_replace('/\D+/', '', (string) ($slip['document_number'] ?? '0')), 10);
			$line .= $this->padNumeric(preg_replace('/\D+/', '', (string) ($slip['our_number'] ?? $slip['id'])), 8);
			$line .= $this->padNumeric((string) round(((float) ($slip['amount'] ?? 0)) * 100), 13);
			$line .= date('dmy', strtotime((string) ($slip['due_date'] ?? date('Y-m-d'))));
			$line .= $this->pad($slip['payer_name'] ?? '', 30);
			$line .= $this->padNumeric(preg_replace('/\D+/', '', (string) ($slip['payer_document'] ?? '')), 14);
			$line .= $this->pad($slip['payer_street'] ?? '', 40);
			$line .= $this->pad($slip['payer_neighborhood'] ?? '', 12);
			$line .= $this->pad($slip['payer_zip_code'] ?? '', 8);
			$line .= $this->pad($slip['payer_city'] ?? '', 15);
			$line .= $this->pad($slip['payer_state'] ?? '', 2);
			$line .= $this->padNumeric((string) $recordNumber, 6);
			$lines[] = $this->finalizeLine($line);
			$recordNumber++;
			}

		$trailer = '';
		$trailer .= '9';
		$trailer .= $this->pad('', 393);
		$trailer .= $this->padNumeric((string) $recordNumber, 6);
		$lines[] = $this->finalizeLine($trailer);

		return implode("\r\n", $lines) . "\r\n";
		}

	private function pad(string $value, int $length): string
		{
		$text = strtoupper(trim($value));
		$text = preg_replace('/[^A-Z0-9 \-\/]/', ' ', $text) ?? '';
		return str_pad(substr($text, 0, $length), $length, ' ');
		}

	private function padNumeric(string $value, int $length): string
		{
		$numeric = preg_replace('/\D+/', '', $value) ?? '';
		return str_pad(substr($numeric, 0, $length), $length, '0', STR_PAD_LEFT);
		}

	private function finalizeLine(string $line): string
		{
		if (strlen($line) < 400)
			{
			return str_pad($line, 400, ' ');
			}

		return substr($line, 0, 400);
		}
	}
?>
