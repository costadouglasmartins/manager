<?php

namespace App\Services\Banking;

class ItauCnab240RemittanceService extends AbstractCnabRemittanceService
	{
	private const BANK_NUMBER = '341';
	private const LAYOUT = 'CNAB240';

	/**
	 * Returns the available Itaú CNAB 240 remittance option.
	 */
	public function options(): array
		{
		return [[
			'bank_number' => self::BANK_NUMBER,
			'bank_name' => 'Itaú',
			'layout' => self::LAYOUT,
			'label' => 'Itaú CNAB240',
		]];
		}

	/**
	 * Checks whether this generator can handle Itaú CNAB 240.
	 */
	public function supports(array $bankAccount, string $layout): bool
		{
		return $this->bankNumber($bankAccount) === self::BANK_NUMBER && strtoupper($layout) === self::LAYOUT;
		}

	/**
	 * Returns a safe file name for the generated remittance.
	 */
	public function fileName(array $bankAccount, int $sequence = 1): string
		{
		return 'C' . date('ymd') . $this->sequenceLetter($sequence) . '.TXT';
		}

	/**
	 * Returns the Itaú daily sequence letter used in the remittance file name.
	 */
	private function sequenceLetter(int $sequence): string
		{
		if ($sequence < 1 || $sequence > 26)
			{
			throw new \InvalidArgumentException('Itaú remittance file sequence must be between 1 and 26.');
			}

		return chr(64 + $sequence);
		}

	/**
	 * Generates an Itaú cobrança CNAB 240 remittance file.
	 */
	public function generate(array $bankAccount, array $bankSlips): string
		{
		$bankSlips = array_values($bankSlips);
		$this->validateDocuments($bankAccount, $bankSlips);
		$lotNumber = 1;
		$fileSequence = (int) date('His');
		$detailSequence = 1;
		$detailLines = [];

		foreach ($bankSlips as $bankSlip)
			{
			$detailLines[] = $this->segmentP($bankAccount, $bankSlip, $lotNumber, $detailSequence++);
			$detailLines[] = $this->segmentQ($bankSlip, $lotNumber, $detailSequence++);

			if ((float) ($bankSlip['fine_amount'] ?? 0) > 0)
				{
				$detailLines[] = $this->segmentR($bankSlip, $lotNumber, $detailSequence++);
				}
			}

		$lines = [];
		$lines[] = $this->headerFile($bankAccount, $fileSequence);
		$lines[] = $this->headerLot($bankAccount, $lotNumber, $fileSequence);

		foreach ($detailLines as $detailLine)
			{
			$lines[] = $detailLine;
			}

		$lotRecordCount = count($detailLines) + 2;
		$lines[] = $this->trailerLot($lotNumber, $lotRecordCount, count($bankSlips), $this->totalAmount($bankSlips));
		$lines[] = $this->trailerFile(1, count($lines) + 1);

		return implode("\r\n", $lines) . "\r\n";
		}

	private function validateDocuments(array $bankAccount, array $bankSlips): void
		{
		$companyDocument = $this->companyDocument($bankAccount);
		if ($companyDocument !== '' && !isValidCpfCnpj($companyDocument))
			{
			throw new \InvalidArgumentException('Documento do beneficiário inválido para remessa CNAB240.');
			}

		$errors = [];
		foreach ($bankSlips as $bankSlip)
			{
			$identifier = '#' . (int) ($bankSlip['id'] ?? 0);
			$payerType = strtoupper(trim((string) ($bankSlip['payer_document_type'] ?? '')));
			$payerDocument = normalizeCpfCnpjByType((string) ($bankSlip['payer_document'] ?? ''), $payerType);

			if (!in_array($payerType, ['CPF', 'CNPJ'], true))
				{
				$errors[] = "Boleto {$identifier}: tipo de documento do pagador inválido.";
				continue;
				}
			$isPayerValid = $payerType === 'CPF' ? isValidCpf($payerDocument) : isValidCnpj($payerDocument);
			if (!$isPayerValid)
				{
				$errors[] = "Boleto {$identifier}: documento do pagador inválido para o tipo informado.";
				}
			}

		if ($errors !== [])
			{
			throw new \InvalidArgumentException(implode(' ', $errors));
			}
		}

	private function headerFile(array $bankAccount, int $fileSequence): string
		{
		[$account, $accountDigit] = $this->account($bankAccount);
		$companyDocument = $this->companyDocument($bankAccount);
		$line = $this->newLine();

		$this->put($line, 1, 3, self::BANK_NUMBER, 'numeric');
		$this->put($line, 4, 7, '0000', 'numeric');
		$this->put($line, 8, 8, '0', 'numeric');
		$this->put($line, 9, 17, '', 'alpha');
		$this->put($line, 18, 18, $this->documentType($companyDocument), 'numeric');
		$this->putCompanyDocument($line, 19, 32, $companyDocument);
		$this->put($line, 33, 52, '', 'alpha');
		$this->put($line, 53, 53, '0', 'numeric');
		$this->put($line, 54, 57, $this->agency($bankAccount), 'numeric');
		$this->put($line, 58, 58, '', 'alpha');
		$this->put($line, 59, 65, '0', 'numeric');
		$this->put($line, 66, 70, $account, 'numeric');
		$this->put($line, 71, 71, '', 'alpha');
		$this->put($line, 72, 72, $accountDigit, 'numeric');
		$this->put($line, 73, 102, $this->beneficiaryName($bankAccount), 'alpha');
		$this->put($line, 103, 132, 'BANCO ITAU SA', 'alpha');
		$this->put($line, 133, 142, '', 'alpha');
		$this->put($line, 143, 143, '1', 'numeric');
		$this->put($line, 144, 151, date('dmY'), 'numeric');
		$this->put($line, 152, 157, date('His'), 'numeric');
		$this->put($line, 158, 163, (string) $fileSequence, 'numeric');
		$this->put($line, 164, 166, '040', 'numeric');
		$this->put($line, 167, 171, '0', 'numeric');
		$this->put($line, 172, 225, '', 'alpha');
		$this->put($line, 226, 228, '0', 'numeric');
		$this->put($line, 229, 240, '', 'alpha');

		return $this->finishLine($line);
		}

	private function headerLot(array $bankAccount, int $lotNumber, int $fileSequence): string
		{
		[$account, $accountDigit] = $this->account($bankAccount);
		$companyDocument = $this->companyDocument($bankAccount);
		$line = $this->newLine();

		$this->put($line, 1, 3, self::BANK_NUMBER, 'numeric');
		$this->put($line, 4, 7, (string) $lotNumber, 'numeric');
		$this->put($line, 8, 8, '1', 'numeric');
		$this->put($line, 9, 9, 'R', 'alpha');
		$this->put($line, 10, 11, '01', 'numeric');
		$this->put($line, 12, 13, '0', 'numeric');
		$this->put($line, 14, 16, '030', 'numeric');
		$this->put($line, 17, 17, '', 'alpha');
		$this->put($line, 18, 18, $this->documentType($companyDocument), 'numeric');
		$this->putCompanyDocument($line, 19, 33, $companyDocument);
		$this->put($line, 34, 53, '', 'alpha');
		$this->put($line, 54, 54, '0', 'numeric');
		$this->put($line, 55, 58, $this->agency($bankAccount), 'numeric');
		$this->put($line, 59, 59, '', 'alpha');
		$this->put($line, 60, 66, '0', 'numeric');
		$this->put($line, 67, 71, $account, 'numeric');
		$this->put($line, 72, 72, '', 'alpha');
		$this->put($line, 73, 73, $accountDigit, 'numeric');
		$this->put($line, 74, 103, $this->beneficiaryName($bankAccount), 'alpha');
		$this->put($line, 104, 183, '', 'alpha');
		$this->put($line, 184, 191, (string) $fileSequence, 'numeric');
		$this->put($line, 192, 199, date('dmY'), 'numeric');
		$this->put($line, 200, 207, date('dmY'), 'numeric');
		$this->put($line, 208, 240, '', 'alpha');

		return $this->finishLine($line);
		}

	private function segmentP(array $bankAccount, array $bankSlip, int $lotNumber, int $detailSequence): string
		{
		[$account, $accountDigit] = $this->account($bankAccount);
		$agency = $this->agency($bankAccount);
		$portfolio = $this->padNumeric((string) ($bankSlip['portfolio'] ?? '109'), 3);
		$ourNumber = $this->ourNumber($bankSlip);
		$ourNumberDigit = $this->ourNumberDigit($bankSlip, $agency, $account, $portfolio, $ourNumber);
		$line = $this->newLine();

		$this->put($line, 1, 3, self::BANK_NUMBER, 'numeric');
		$this->put($line, 4, 7, (string) $lotNumber, 'numeric');
		$this->put($line, 8, 8, '3', 'numeric');
		$this->put($line, 9, 13, (string) $detailSequence, 'numeric');
		$this->put($line, 14, 14, 'P', 'alpha');
		$this->put($line, 15, 15, '', 'alpha');
		$this->put($line, 16, 17, '01', 'numeric');
		$this->put($line, 18, 18, '0', 'numeric');
		$this->put($line, 19, 22, $agency, 'numeric');
		$this->put($line, 23, 23, '', 'alpha');
		$this->put($line, 24, 30, '0', 'numeric');
		$this->put($line, 31, 35, $account, 'numeric');
		$this->put($line, 36, 36, '', 'alpha');
		$this->put($line, 37, 37, $accountDigit, 'numeric');
		$this->put($line, 38, 40, $portfolio, 'numeric');
		$this->put($line, 41, 48, $ourNumber, 'numeric');
		$this->put($line, 49, 49, (string) $ourNumberDigit, 'numeric');
		$this->put($line, 50, 57, '', 'alpha');
		$this->put($line, 58, 62, '0', 'numeric');
		$this->put($line, 63, 72, (string) ($bankSlip['document_number'] ?? $bankSlip['id'] ?? ''), 'alpha');
		$this->put($line, 73, 77, '', 'alpha');
		$this->put($line, 78, 85, $this->dateDmy($bankSlip['due_date'] ?? ''), 'numeric');
		$this->put($line, 86, 100, $this->money($bankSlip['amount'] ?? 0, 15), 'numeric');
		$this->put($line, 101, 105, '0', 'numeric');
		$this->put($line, 106, 106, '0', 'numeric');
		$this->put($line, 107, 108, (string) ($bankSlip['title_species'] ?? '01'), 'numeric');
		$this->put($line, 109, 109, (string) ($bankSlip['acceptance'] ?? 'N'), 'alpha');
		$this->put($line, 110, 117, $this->dateDmy($bankSlip['issue_date'] ?? ''), 'numeric');
		$this->put($line, 118, 118, '0', 'numeric');
		$this->put($line, 119, 126, $this->dateDmy($bankSlip['interest_date'] ?? $bankSlip['due_date'] ?? ''), 'numeric');
		$this->put($line, 127, 141, $this->money($bankSlip['interest_amount_per_day'] ?? 0, 15), 'numeric');
		$this->put($line, 142, 142, '0', 'numeric');
		$this->put($line, 143, 150, $this->dateDmy($bankSlip['discount_date'] ?? ''), 'numeric');
		$this->put($line, 151, 165, $this->money($bankSlip['discount_amount'] ?? 0, 15), 'numeric');
		$this->put($line, 166, 180, '0', 'numeric');
		$this->put($line, 181, 195, $this->money($bankSlip['abatement_amount'] ?? 0, 15), 'numeric');
		$this->put($line, 196, 220, (string) ($bankSlip['document_number'] ?? $bankSlip['id'] ?? ''), 'alpha');
		$this->put($line, 221, 221, (string) ($bankSlip['protest_code'] ?? '0'), 'numeric');
		$this->put($line, 222, 223, (string) ($bankSlip['protest_days'] ?? '0'), 'numeric');
		$this->put($line, 224, 224, (string) ($bankSlip['write_off_code'] ?? '0'), 'numeric');
		$this->put($line, 225, 226, (string) ($bankSlip['write_off_days'] ?? '0'), 'numeric');
		$this->put($line, 227, 239, '0', 'numeric');
		$this->put($line, 240, 240, '', 'alpha');

		return $this->finishLine($line);
		}

	private function segmentQ(array $bankSlip, int $lotNumber, int $detailSequence): string
		{
		$payerType = strtoupper((string) ($bankSlip['payer_document_type'] ?? ''));
		$payerDocument = normalizeCpfCnpjByType((string) ($bankSlip['payer_document'] ?? ''), $payerType);
		$payerDocumentFieldType = preg_match('/^\d+$/', $payerDocument) === 1 ? 'numeric' : 'alpha';
		$payerDocumentType = $payerType === 'CPF' ? '1' : '2';
		$zipCode = $this->padNumeric((string) ($bankSlip['payer_zip_code'] ?? ''), 8);
		$line = $this->newLine();

		$this->put($line, 1, 3, self::BANK_NUMBER, 'numeric');
		$this->put($line, 4, 7, (string) $lotNumber, 'numeric');
		$this->put($line, 8, 8, '3', 'numeric');
		$this->put($line, 9, 13, (string) $detailSequence, 'numeric');
		$this->put($line, 14, 14, 'Q', 'alpha');
		$this->put($line, 15, 15, '', 'alpha');
		$this->put($line, 16, 17, '01', 'numeric');
		$this->put($line, 18, 18, $payerDocumentType, 'numeric');
		$this->put($line, 19, 33, $payerDocument, $payerDocumentFieldType);
		$this->put($line, 34, 63, (string) ($bankSlip['payer_name'] ?? ''), 'alpha');
		$this->put($line, 64, 73, '', 'alpha');
		$this->put($line, 74, 113, (string) ($bankSlip['payer_street'] ?? ''), 'alpha');
		$this->put($line, 114, 128, (string) ($bankSlip['payer_neighborhood'] ?? ''), 'alpha');
		$this->put($line, 129, 133, substr($zipCode, 0, 5), 'numeric');
		$this->put($line, 134, 136, substr($zipCode, 5, 3), 'numeric');
		$this->put($line, 137, 151, (string) ($bankSlip['payer_city'] ?? ''), 'alpha');
		$this->put($line, 152, 153, (string) ($bankSlip['payer_state'] ?? ''), 'alpha');
		$this->put($line, 154, 154, '0', 'numeric');
		$this->put($line, 155, 169, '0', 'numeric');
		$this->put($line, 170, 199, '', 'alpha');
		$this->put($line, 200, 209, '', 'alpha');
		$this->put($line, 210, 212, '0', 'numeric');
		$this->put($line, 213, 240, '', 'alpha');

		return $this->finishLine($line);
		}

	private function segmentR(array $bankSlip, int $lotNumber, int $detailSequence): string
		{
		$fineDate = $bankSlip['fine_date'] ?? $bankSlip['due_date'] ?? '';
		$line = $this->newLine();

		$this->put($line, 1, 3, self::BANK_NUMBER, 'numeric');
		$this->put($line, 4, 7, (string) $lotNumber, 'numeric');
		$this->put($line, 8, 8, '3', 'numeric');
		$this->put($line, 9, 13, (string) $detailSequence, 'numeric');
		$this->put($line, 14, 14, 'R', 'alpha');
		$this->put($line, 15, 15, '', 'alpha');
		$this->put($line, 16, 17, '01', 'numeric');
		$this->put($line, 18, 18, '0', 'numeric');
		$this->put($line, 19, 26, '0', 'numeric');
		$this->put($line, 27, 41, '0', 'numeric');
		$this->put($line, 42, 42, '0', 'numeric');
		$this->put($line, 43, 50, '0', 'numeric');
		$this->put($line, 51, 65, '0', 'numeric');
		$this->put($line, 66, 66, '1', 'numeric');
		$this->put($line, 67, 74, $this->dateDmy($fineDate), 'numeric');
		$this->put($line, 75, 89, $this->money($bankSlip['fine_amount'] ?? 0, 15), 'numeric');
		$this->put($line, 90, 99, '', 'alpha');
		$this->put($line, 100, 139, '', 'alpha');
		$this->put($line, 140, 199, '', 'alpha');
		$this->put($line, 200, 207, '0', 'numeric');
		$this->put($line, 208, 215, '0', 'numeric');
		$this->put($line, 216, 216, '', 'alpha');
		$this->put($line, 217, 228, '0', 'numeric');
		$this->put($line, 229, 230, '', 'alpha');
		$this->put($line, 231, 231, '0', 'numeric');
		$this->put($line, 232, 240, '', 'alpha');

		return $this->finishLine($line);
		}

	private function trailerLot(int $lotNumber, int $recordCount, int $titleCount, float $totalAmount): string
		{
		$line = $this->newLine();

		$this->put($line, 1, 3, self::BANK_NUMBER, 'numeric');
		$this->put($line, 4, 7, (string) $lotNumber, 'numeric');
		$this->put($line, 8, 8, '5', 'numeric');
		$this->put($line, 9, 17, '', 'alpha');
		$this->put($line, 18, 23, (string) $recordCount, 'numeric');
		$this->put($line, 24, 29, (string) $titleCount, 'numeric');
		$this->put($line, 30, 46, $this->money($totalAmount, 17), 'numeric');
		$this->put($line, 47, 52, '0', 'numeric');
		$this->put($line, 53, 69, '0', 'numeric');
		$this->put($line, 70, 115, '0', 'numeric');
		$this->put($line, 116, 123, '', 'alpha');
		$this->put($line, 124, 240, '', 'alpha');

		return $this->finishLine($line);
		}

	private function trailerFile(int $lotCount, int $recordCount): string
		{
		$line = $this->newLine();

		$this->put($line, 1, 3, self::BANK_NUMBER, 'numeric');
		$this->put($line, 4, 7, '9999', 'numeric');
		$this->put($line, 8, 8, '9', 'numeric');
		$this->put($line, 9, 17, '', 'alpha');
		$this->put($line, 18, 23, (string) $lotCount, 'numeric');
		$this->put($line, 24, 29, (string) $recordCount, 'numeric');
		$this->put($line, 30, 35, '0', 'numeric');
		$this->put($line, 36, 240, '', 'alpha');

		return $this->finishLine($line);
		}

	private function totalAmount(array $bankSlips): float
		{
		$total = 0.0;
		foreach ($bankSlips as $bankSlip)
			{
			$total += (float) ($bankSlip['amount'] ?? 0);
			}
		return $total;
		}

	private function beneficiaryName(array $bankAccount): string
		{
		return (string) ($bankAccount['beneficiary_name'] ?? $bankAccount['bank_account_name'] ?? $bankAccount['name'] ?? 'CEDENTE');
		}

	private function companyDocument(array $bankAccount): string
		{
		return normalizeCpfCnpjByType((string) ($bankAccount['beneficiary_document'] ?? $bankAccount['company_document'] ?? $bankAccount['document'] ?? ''));
		}

	private function putCompanyDocument(string &$line, int $start, int $end, string $document): void
		{
		$fieldType = preg_match('/^\d+$/', $document) === 1 ? 'numeric' : 'alpha';
		$this->put($line, $start, $end, $document, $fieldType);
		}

	private function ourNumber(array $bankSlip): string
		{
		$value = trim((string) ($bankSlip['our_number'] ?? ''));
		if ($value === '')
			{
			$value = (string) ($bankSlip['id'] ?? '0');
			}

		return $this->padNumeric($value, 8);
		}

	private function ourNumberDigit(array $bankSlip, string $agency, string $account, string $portfolio, string $ourNumber): int
		{
		$digit = trim((string) ($bankSlip['our_number_digit'] ?? ''));
		if ($digit !== '')
			{
			return (int) substr($this->padNumeric($digit, 1), -1);
			}

		return $this->modulo10Digit($agency . $account . $portfolio . $ourNumber);
		}
	}
?>
