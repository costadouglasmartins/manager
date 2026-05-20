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
	}
?>
