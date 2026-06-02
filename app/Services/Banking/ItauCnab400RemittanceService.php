<?php

namespace App\Services\Banking;

class ItauCnab400RemittanceService extends AbstractCnabRemittanceService
	{
	private const BANK_NUMBER = '341';
	private const LAYOUT = 'CNAB400';
	private const PORTFOLIO_CODES = [
		'104' => 'I',
		'108' => 'I',
		'109' => 'I',
		'112' => 'I',
		'115' => 'I',
		'121' => 'I',
		'150' => 'U',
		'180' => 'I',
		'188' => 'I',
		'191' => '1',
	];

	/**
	 * Returns the available Itaú CNAB 400 remittance option.
	 */
	public function options(): array
		{
		return [[
			'bank_number' => self::BANK_NUMBER,
			'bank_name' => 'Itaú',
			'layout' => self::LAYOUT,
			'label' => 'Itaú CNAB400',
		]];
		}

	/**
	 * Checks whether this generator can handle Itaú CNAB 400.
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
	 * Generates an Itaú cobrança CNAB 400 remittance file.
	 *
	 * Reference: Itaú Cobrança CNAB400 layout.
	 */
	public function generate(array $bankAccount, array $bankSlips): string
		{
		$bankSlips = array_values($bankSlips);
		$this->validateBankSlips($bankSlips);

		$lines = [];
		$recordNumber = 1;

		$lines[] = $this->header($bankAccount, $recordNumber++);

		foreach ($bankSlips as $bankSlip)
			{
			$lines[] = $this->detail($bankAccount, $bankSlip, $recordNumber++);

			$fineAmount = $this->fineAmount($bankAccount, $bankSlip);
			if ($fineAmount > 0)
				{
				$lines[] = $this->fineDetail($bankSlip, $fineAmount, $recordNumber++);
				}
			}

		$lines[] = $this->trailer($recordNumber);

		return implode("\r\n", $lines) . "\r\n";
		}

	private function validateBankSlips(array $bankSlips): void
		{
		$errors = [];

		foreach ($bankSlips as $bankSlip)
			{
			$identifier = '#' . (int) ($bankSlip['id'] ?? 0);
			$documentType = strtoupper(trim((string) ($bankSlip['payer_document_type'] ?? '')));
			$document = normalizeCpfCnpjByType((string) ($bankSlip['payer_document'] ?? ''), $documentType);
			if ($documentType === 'CPF' && !isValidCpf($document))
				{
				$errors[] = "Boleto {$identifier}: CPF do pagador deve ter 11 dígitos.";
				}
			elseif ($documentType === 'CNPJ' && !isValidCnpj($document))
				{
				$errors[] = "Boleto {$identifier}: CNPJ do pagador inválido no modelo atual ou no novo modelo alfanumérico.";
				}
			elseif (!in_array($documentType, ['CPF', 'CNPJ'], true))
				{
				$errors[] = "Boleto {$identifier}: tipo de documento do pagador inválido.";
				}

			$zipCode = $this->onlyDigits((string) ($bankSlip['payer_zip_code'] ?? ''));
			if (strlen($zipCode) !== 8)
				{
				$errors[] = "Boleto {$identifier}: CEP do pagador deve ter 8 dígitos.";
				}
			}

		if ($errors !== [])
			{
			throw new \InvalidArgumentException(implode(' ', $errors));
			}
		}

	private function header(array $bankAccount, int $recordNumber): string
		{
		[$account, $accountDigit] = $this->account($bankAccount);
		$line = $this->newLine(400);

		$this->put($line, 1, 1, '0', 'numeric');
		$this->put($line, 2, 2, '1', 'numeric');
		$this->put($line, 3, 9, 'REMESSA', 'alpha');
		$this->put($line, 10, 11, '01', 'numeric');
		$this->put($line, 12, 26, 'COBRANCA', 'alpha');
		$this->put($line, 27, 30, $this->agency($bankAccount), 'numeric');
		$this->put($line, 31, 32, '0', 'numeric');
		$this->put($line, 33, 37, $account, 'numeric');
		$this->put($line, 38, 38, $accountDigit, 'numeric');
		$this->put($line, 39, 46, '', 'alpha');
		$this->put($line, 47, 76, $this->beneficiaryName($bankAccount), 'alpha');
		$this->put($line, 77, 79, self::BANK_NUMBER, 'numeric');
		$this->put($line, 80, 94, 'BANCO ITAU SA', 'alpha');
		$this->put($line, 95, 100, date('dmy'), 'numeric');
		$this->put($line, 101, 394, '', 'alpha');
		$this->put($line, 395, 400, (string) $recordNumber, 'numeric');

		return $this->finishLine($line, 400);
		}

	private function detail(array $bankAccount, array $bankSlip, int $recordNumber): string
		{
		[$account, $accountDigit] = $this->account($bankAccount);
		$companyDocument = $this->companyDocument($bankAccount);
		$instructionOne = $this->instructionOne($bankAccount, $bankSlip);
		$line = $this->newLine(400);

		$this->put($line, 1, 1, '1', 'numeric');
		$this->put($line, 2, 3, $this->registrationType($companyDocument), 'numeric');
		$this->putRegistrationNumber($line, 4, 17, $companyDocument);
		$this->put($line, 18, 21, $this->agency($bankAccount), 'numeric');
		$this->put($line, 22, 23, '0', 'numeric');
		$this->put($line, 24, 28, $account, 'numeric');
		$this->put($line, 29, 29, $accountDigit, 'numeric');
		$this->put($line, 30, 33, '', 'alpha');
		$this->put($line, 34, 37, '0', 'numeric');
		$this->put($line, 38, 62, $this->documentNumber($bankSlip), 'alpha');
		$this->put($line, 63, 70, $this->ourNumber($bankSlip), 'numeric');
		$this->put($line, 71, 83, '0', 'numeric');
		$this->put($line, 84, 86, $this->portfolio($bankAccount, $bankSlip), 'numeric');
		$this->put($line, 87, 107, '', 'alpha');
		$this->put($line, 108, 108, $this->portfolioCode($bankAccount, $bankSlip), 'alpha');
		$this->put($line, 109, 110, '01', 'numeric');
		$this->put($line, 111, 120, $this->documentNumber($bankSlip), 'alpha');
		$this->put($line, 121, 126, $this->dateDmyShort($bankSlip['due_date'] ?? ''), 'numeric');
		$this->put($line, 127, 139, $this->money($bankSlip['amount'] ?? 0, 13), 'numeric');
		$this->put($line, 140, 142, self::BANK_NUMBER, 'numeric');
		$this->put($line, 143, 147, '0', 'numeric');
		$this->put($line, 148, 149, $this->titleSpecies($bankAccount, $bankSlip), 'alpha');
		$this->put($line, 150, 150, (string) ($bankSlip['acceptance'] ?? 'N'), 'alpha');
		$this->put($line, 151, 156, $this->dateDmyShort($bankSlip['issue_date'] ?? ''), 'numeric');
		$this->put($line, 157, 158, $instructionOne, 'alpha');
		$this->put($line, 159, 160, '00', 'alpha');
		$this->put($line, 161, 173, $this->money($this->interestAmountPerDay($bankAccount, $bankSlip), 13), 'numeric');
		$this->put($line, 174, 179, $this->dateDmyShort($bankSlip['discount_date'] ?? ''), 'numeric');
		$this->put($line, 180, 192, $this->money($bankSlip['discount_amount'] ?? 0, 13), 'numeric');
		$this->put($line, 193, 205, '0', 'numeric');
		$this->put($line, 206, 218, $this->money($bankSlip['abatement_amount'] ?? 0, 13), 'numeric');
		$this->put($line, 219, 220, $this->payerRegistrationType($bankSlip), 'numeric');
		$this->putRegistrationNumber($line, 221, 234, (string) ($bankSlip['payer_document'] ?? ''));
		$this->put($line, 235, 264, (string) ($bankSlip['payer_name'] ?? ''), 'alpha');
		$this->put($line, 265, 274, '', 'alpha');
		$this->put($line, 275, 314, (string) ($bankSlip['payer_street'] ?? ''), 'alpha');
		$this->put($line, 315, 326, (string) ($bankSlip['payer_neighborhood'] ?? ''), 'alpha');
		$this->put($line, 327, 334, (string) ($bankSlip['payer_zip_code'] ?? ''), 'numeric');
		$this->put($line, 335, 349, (string) ($bankSlip['payer_city'] ?? ''), 'alpha');
		$this->put($line, 350, 351, (string) ($bankSlip['payer_state'] ?? ''), 'alpha');
		$this->put($line, 352, 381, '', 'alpha');
		$this->put($line, 382, 385, '', 'alpha');
		$this->put($line, 386, 391, $this->dateDmyShort($bankSlip['interest_date'] ?? ''), 'numeric');
		$this->put($line, 392, 393, $this->instructionDays($bankAccount, $bankSlip, $instructionOne), 'numeric');
		$this->put($line, 394, 394, '', 'alpha');
		$this->put($line, 395, 400, (string) $recordNumber, 'numeric');

		return $this->finishLine($line, 400);
		}

	private function fineDetail(array $bankSlip, float $fineAmount, int $recordNumber): string
		{
		$fineDate = $bankSlip['fine_date'] ?? $bankSlip['due_date'] ?? '';
		$line = $this->newLine(400);

		$this->put($line, 1, 1, '2', 'numeric');
		$this->put($line, 2, 2, '1', 'numeric');
		$this->put($line, 3, 10, $this->dateDmyLong($fineDate), 'numeric');
		$this->put($line, 11, 23, $this->money($fineAmount, 13), 'numeric');
		$this->put($line, 24, 394, '', 'alpha');
		$this->put($line, 395, 400, (string) $recordNumber, 'numeric');

		return $this->finishLine($line, 400);
		}

	private function trailer(int $recordNumber): string
		{
		$line = $this->newLine(400);

		$this->put($line, 1, 1, '9', 'numeric');
		$this->put($line, 2, 394, '', 'alpha');
		$this->put($line, 395, 400, (string) $recordNumber, 'numeric');

		return $this->finishLine($line, 400);
		}

	private function beneficiaryName(array $bankAccount): string
		{
		return (string) ($bankAccount['beneficiary_name'] ?? $bankAccount['bank_account_name'] ?? $bankAccount['name'] ?? 'CEDENTE');
		}

	private function companyDocument(array $bankAccount): string
		{
		return normalizeCpfCnpjByType((string) ($bankAccount['beneficiary_document'] ?? $bankAccount['company_document'] ?? $bankAccount['document'] ?? ''));
		}

	private function registrationType(string $document): string
		{
		return preg_match('/^\d{11}$/', $document) === 1 ? '01' : '02';
		}

	private function registrationNumber(string $document): string
		{
		return normalizeCpfCnpjByType($document);
		}

	private function putRegistrationNumber(string &$line, int $start, int $end, string $document): void
		{
		$document = $this->registrationNumber($document);
		$type = $document === '' || preg_match('/^\d+$/', $document) === 1 ? 'numeric' : 'alpha';
		$this->put($line, $start, $end, $document, $type);
		}

	private function payerRegistrationType(array $bankSlip): string
		{
		return strtoupper((string) ($bankSlip['payer_document_type'] ?? '')) === 'CPF' ? '01' : '02';
		}

	private function documentNumber(array $bankSlip): string
		{
		return (string) ($bankSlip['document_number'] ?? $bankSlip['id'] ?? '');
		}

	private function ourNumber(array $bankSlip): string
		{
		$value = trim((string) ($bankSlip['our_number'] ?? ''));
		return $value !== '' ? $value : (string) ($bankSlip['id'] ?? '0');
		}

	private function portfolio(array $bankAccount, array $bankSlip): string
		{
		return (string) ($bankSlip['portfolio'] ?? $bankAccount['bank_slip_wallet'] ?? '109');
		}

	private function portfolioCode(array $bankAccount, array $bankSlip): string
		{
		$portfolio = $this->padNumeric($this->portfolio($bankAccount, $bankSlip), 3);
		return self::PORTFOLIO_CODES[$portfolio] ?? 'I';
		}

	private function titleSpecies(array $bankAccount, array $bankSlip): string
		{
		return (string) ($bankSlip['title_species'] ?? $bankAccount['bank_slip_species_title'] ?? '01');
		}

	private function instructionOne(array $bankAccount, array $bankSlip): string
		{
		$protestCode = trim((string) ($bankSlip['protest_code'] ?? ''));
		if ($protestCode !== '')
			{
			return $this->padNumeric($protestCode, 2);
			}

		$protest = $bankAccount['bank_slip_protest'] ?? null;
		if ($protest === null || $protest === '')
			{
			return '00';
			}

		return (int) $protest === 1 ? '34' : '10';
		}

	private function instructionDays(array $bankAccount, array $bankSlip, string $instruction): string
		{
		if (!in_array($instruction, ['34', '35', '42', '66', '91', '92'], true))
			{
			return '00';
			}

		return (string) ($bankSlip['protest_days'] ?? $bankAccount['bank_slip_protest_number_of_days'] ?? '0');
		}

	private function interestAmountPerDay(array $bankAccount, array $bankSlip): float
		{
		$amountPerDay = (float) ($bankSlip['interest_amount_per_day'] ?? 0);
		if ($amountPerDay > 0)
			{
			return $amountPerDay;
			}

		$interest = (float) ($bankAccount['bank_slip_interest'] ?? 0);
		if ($interest <= 0)
			{
			return 0.0;
			}

		if (($bankAccount['bank_slip_interest_amount_type'] ?? '') === 'PERCENTAGE')
			{
			$interest = ((float) ($bankSlip['amount'] ?? 0)) * $interest / 100;
			}

		if (($bankAccount['bank_slip_interest_type'] ?? '') === 'MONTHLY')
			{
			$interest /= 30;
			}

		return $interest;
		}

	private function fineAmount(array $bankAccount, array $bankSlip): float
		{
		$fineAmount = (float) ($bankSlip['fine_amount'] ?? 0);
		if ($fineAmount > 0)
			{
			return $fineAmount;
			}

		return (float) ($bankAccount['bank_slip_late_fee_amount'] ?? 0);
		}

	private function dateDmyShort($value): string
		{
		return $this->formattedDate($value, 'dmy', 6);
		}

	private function dateDmyLong($value): string
		{
		return $this->formattedDate($value, 'dmY', 8);
		}

	private function formattedDate($value, string $format, int $length): string
		{
		$value = trim((string) $value);
		if ($value === '' || $value === '0000-00-00')
			{
			return str_repeat('0', $length);
			}

		$timestamp = strtotime($value);
		if ($timestamp === false)
			{
			return str_repeat('0', $length);
			}

		return date($format, $timestamp);
		}
	}
?>
