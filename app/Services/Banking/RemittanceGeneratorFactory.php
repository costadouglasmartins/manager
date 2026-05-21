<?php

namespace App\Services\Banking;

use InvalidArgumentException;

class RemittanceGeneratorFactory
	{
	/**
	 * @var RemittanceGeneratorInterface[]
	 */
	private array $generators;

	/**
	 * Registers the available remittance generators.
	 */
	public function __construct(?array $generators = null)
		{
		$this->generators = $generators ?? [
			new ItauCnab240RemittanceService(),
			new ItauCnab400RemittanceService(),
		];
		}

	/**
	 * Returns the remittance generator for the bank account and layout.
	 */
	public function make(array $bankAccount, string $layout): RemittanceGeneratorInterface
		{
		$layout = $this->normalizeLayout($layout);
		$accountLayout = $this->layoutForBankAccount($bankAccount);
		if ($layout === '' && $accountLayout !== '')
			{
			$layout = $accountLayout;
			}
		if ($accountLayout !== '' && $layout !== $accountLayout)
			{
			throw new InvalidArgumentException('A conta bancária está configurada para ' . $accountLayout . ' e não permite gerar ' . $layout . '.');
			}

		foreach ($this->generators as $generator)
			{
			if ($generator->supports($bankAccount, $layout))
				{
				return $generator;
				}
			}

		$bankNumber = preg_replace('/\D+/', '', (string) ($bankAccount['bank_number'] ?? '')) ?? '';
		throw new InvalidArgumentException('Remessa ' . strtoupper($layout) . ' não implementada para o banco ' . ($bankNumber ?: 'informado') . '.');
		}

	/**
	 * Returns the remittance options allowed for the account bank and CNAB mode.
	 */
	public function optionsForBankAccount(array $bankAccount): array
		{
		$accountLayout = $this->layoutForBankAccount($bankAccount);
		$accountBankNumber = $this->normalizeBankNumber((string) ($bankAccount['bank_number'] ?? ''));
		if ($accountLayout === '' || $accountBankNumber === '')
			{
			return [];
			}

		$options = [];
		foreach ($this->generators as $generator)
			{
			foreach ($generator->options() as $option)
				{
				$layout = $this->normalizeLayout((string) ($option['layout'] ?? ''));
				$bankNumber = $this->normalizeBankNumber((string) ($option['bank_number'] ?? ''));
				if ($layout !== $accountLayout || $bankNumber !== $accountBankNumber)
					{
					continue;
					}
				if (!$generator->supports($bankAccount, $layout))
					{
					continue;
					}

				$bankName = trim((string) ($bankAccount['bank_name'] ?? $option['bank_name'] ?? 'Banco'));
				$options[] = [
					'bank_number' => $bankNumber,
					'bank_name' => $bankName,
					'layout' => $layout,
					'label' => trim($bankName . ' ' . $layout),
				];
				}
			}

		return $options;
		}

	/**
	 * Normalizes a CNAB layout value such as "240", "400", or "CNAB240".
	 */
	public function normalizeLayout(string $layout): string
		{
		$text = strtoupper(trim($layout));
		$digits = preg_replace('/\D+/', '', $text) ?? '';
		if ($digits === '240' || $digits === '400')
			{
			return 'CNAB' . $digits;
			}

		return $text;
		}

	/**
	 * Returns the CNAB layout configured in manager_main.banks for the account.
	 */
	private function layoutForBankAccount(array $bankAccount): string
		{
		return $this->normalizeLayout((string) ($bankAccount['bank_cnab'] ?? $bankAccount['cnab'] ?? ''));
		}

	/**
	 * Normalizes bank numbers to their three-digit Febraban code.
	 */
	private function normalizeBankNumber(string $bankNumber): string
		{
		$digits = preg_replace('/\D+/', '', $bankNumber) ?? '';
		if ($digits === '')
			{
			return '';
			}

		return substr(str_pad($digits, 3, '0', STR_PAD_LEFT), -3);
		}
	}
?>
