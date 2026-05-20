<?php

namespace App\Services\Banking;

interface RemittanceGeneratorInterface
	{
	/**
	 * Checks whether this generator can handle the bank account and layout.
	 */
	public function supports(array $bankAccount, string $layout): bool;

	/**
	 * Generates the remittance file content.
	 */
	public function generate(array $bankAccount, array $bankSlips): string;

	/**
	 * Returns a safe file name for the generated remittance.
	 */
	public function fileName(array $bankAccount, int $sequence = 1): string;
	}
?>
