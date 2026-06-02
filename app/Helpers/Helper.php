<?php
/**
 * Normalizes text to uppercase ASCII preserving Portuguese replacements.
 */
if (!function_exists('normalizeAsciiText'))
	{
	function normalizeAsciiText(?string $value): string
		{
		$text = trim((string) ($value ?? ''));
		if ($text === '')
			{
			return '';
			}

		$utf8 = $text;
		if (preg_match('//u', $utf8) !== 1)
			{
			$convertedIso = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $utf8);
			$convertedWin = @iconv('Windows-1252', 'UTF-8//IGNORE', $utf8);
			if ($convertedIso !== false && $convertedIso !== '')
				{
				$utf8 = $convertedIso;
				}
			elseif ($convertedWin !== false && $convertedWin !== '')
				{
				$utf8 = $convertedWin;
				}
			}

		$utf8 = strtr($utf8, [
			'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A',
			'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
			'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
			'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
			'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
			'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
			'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
			'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
			'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
			'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
			'Ç' => 'C', 'ç' => 'c',
		]);

		$ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $utf8);
		if ($ascii !== false && $ascii !== '')
			{
			$utf8 = $ascii;
			}

		$utf8 = strtoupper($utf8);
		return preg_replace('/[^A-Z0-9 \-\/.,]/', ' ', $utf8) ?? '';
		}
	}

/**
 * Only digits
 */
if (!function_exists('onlyDigits'))
	{

	function onlyDigits(?string $value): string
		{
		if ($value === null)
			{
			return '';
			}
		return preg_replace('/\D+/', '', $value);
		}

	}

/**
 * Removes special characters from CPF and CNPJ values, preserving alphanumeric CNPJ.
 */
if (!function_exists('cleanCpfCnpj'))
	{

	function cleanCpfCnpj(?string $value): string
		{
		if ($value === null)
			{
			return '';
			}

		return preg_replace('/[^A-Z0-9]+/', '', strtoupper($value)) ?? '';
		}

		}

	/**
	 * Detects the document type from a cleaned CPF/CNPJ value.
	 */
	if (!function_exists('detectCpfCnpjType'))
		{

		function detectCpfCnpjType(?string $value): ?string
			{
			$clean = cleanCpfCnpj($value);
			if (preg_match('/^\d{11}$/', $clean) === 1)
				{
				return 'CPF';
				}
			if (preg_match('/^[A-Z0-9]{14}$/', $clean) === 1)
				{
				return 'CNPJ';
				}

			return null;
			}

		}

	/**
	 * Normalizes CPF/CNPJ according to the informed or detected type.
	 */
	if (!function_exists('normalizeCpfCnpjByType'))
		{

		function normalizeCpfCnpjByType(?string $value, ?string $documentType = null): string
			{
			$type = strtoupper(trim((string) ($documentType ?? '')));
			if ($type === '')
				{
				$type = (string) (detectCpfCnpjType($value) ?? '');
				}

			if ($type === 'CPF')
				{
				return onlyDigits($value);
				}

			return cleanCpfCnpj($value);
			}

		}

	/**
	 * Validates a CPF number using check digits.
	 */
	if (!function_exists('isValidCpf'))
		{

		function isValidCpf(?string $value): bool
			{
			$cpf = onlyDigits($value);
			if (preg_match('/^\d{11}$/', $cpf) !== 1)
				{
				return false;
				}
			if (preg_match('/^(\d)\1{10}$/', $cpf) === 1)
				{
				return false;
				}

			for ($position = 9; $position < 11; $position++)
				{
				$sum = 0;
				for ($i = 0; $i < $position; $i++)
					{
					$sum += ((int) $cpf[$i]) * (($position + 1) - $i);
					}

				$digit = ((10 * $sum) % 11) % 10;
				if ((int) $cpf[$position] !== $digit)
					{
					return false;
					}
				}

			return true;
			}

		}

	/**
	 * Validates a numeric CNPJ using check digits.
	 */
	if (!function_exists('isValidNumericCnpj'))
		{

		function isValidNumericCnpj(?string $value): bool
			{
			$cnpj = onlyDigits($value);
			if (preg_match('/^\d{14}$/', $cnpj) !== 1)
				{
				return false;
				}
			if (preg_match('/^(\d)\1{13}$/', $cnpj) === 1)
				{
				return false;
				}

			$weightsFirst = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
			$weightsSecond = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

			$sum = 0;
			for ($i = 0; $i < 12; $i++)
				{
				$sum += ((int) $cnpj[$i]) * $weightsFirst[$i];
				}
			$remainder = $sum % 11;
			$digitOne = ($remainder < 2) ? 0 : 11 - $remainder;
			if ((int) $cnpj[12] !== $digitOne)
				{
				return false;
				}

			$sum = 0;
			for ($i = 0; $i < 13; $i++)
				{
				$sum += ((int) $cnpj[$i]) * $weightsSecond[$i];
				}
			$remainder = $sum % 11;
			$digitTwo = ($remainder < 2) ? 0 : 11 - $remainder;

			return (int) $cnpj[13] === $digitTwo;
			}

		}

	/**
	 * Validates numeric and alphanumeric CNPJ models.
	 */
	if (!function_exists('isValidCnpj'))
		{

		function isValidCnpj(?string $value): bool
			{
			$clean = cleanCpfCnpj($value);
			if (preg_match('/^\d{14}$/', $clean) === 1)
				{
				return isValidNumericCnpj($clean);
				}

			return preg_match('/^[A-Z0-9]{12}\d{2}$/', $clean) === 1;
			}

		}

	/**
	 * Validates CPF/CNPJ based on type (explicit or inferred).
	 */
	if (!function_exists('isValidCpfCnpj'))
		{

		function isValidCpfCnpj(?string $value, ?string $documentType = null): bool
			{
			$type = strtoupper(trim((string) ($documentType ?? '')));
			if ($type === '')
				{
				$type = (string) (detectCpfCnpjType($value) ?? '');
				}

			if ($type === 'CPF')
				{
				return isValidCpf($value);
				}
			if ($type === 'CNPJ')
				{
				return isValidCnpj($value);
				}

			return false;
			}

		}

	/**
	 * Mask CPF and CNPJ
	 */
if (!function_exists('maskCpfCnpj'))
	{

	function maskCpfCnpj(?string $value): string
		{
		$clean = cleanCpfCnpj($value);
		if ($clean === '')
			{
			return '';
			}
		// CPF: 000.000.000-00
		if (preg_match('/^\d{11}$/', $clean))
			{
			return preg_replace(
				'/(\d{3})(\d{3})(\d{3})(\d{2})/',
				'$1.$2.$3-$4',
				$clean
			);
			}
		// CNPJ: 00.000.000/0000-00
		if (preg_match('/^[A-Z0-9]{12}\d{2}$/', $clean))
			{
			return preg_replace(
				'/^([A-Z0-9]{2})([A-Z0-9]{3})([A-Z0-9]{3})([A-Z0-9]{4})(\d{2})$/',
				'$1.$2.$3/$4-$5',
				$clean
			);
			}
		// If there is not a CPF or CNPJ length, returns original value without mask
		return $value ?? '';
		}

	}

/**
 * Mask phone numbers
 */
if (!function_exists('maskPhoneBr'))
	{

	function maskPhoneBr(?string $value): string
		{
		$digits = onlyDigits($value);
		if ($digits === '')
			{
			return '';
			}
		$len = strlen($digits);
		// 0800 xxx xxxx (11 dígitos começando com 0800)
		if ($len === 11 && substr($digits, 0, 4) === '0800')
			{
			return preg_replace(
				'/(0800)(\d{3})(\d{4})/',
				'$1 $2 $3',
				$digits
			);
			}
		// Celular com DDD: (11) 91234-5678 (11 dígitos)
		if ($len === 11)
			{
			return preg_replace(
				'/(\d{2})(\d{1})(\d{4})(\d{4})/',
				'($1) $2$3-$4',
				$digits
			);
			}
		// Fixo com DDD: (11) 1234-5678 (10 dígitos)
		if ($len === 10)
			{
			return preg_replace(
				'/(\d{2})(\d{4})(\d{4})/',
				'($1) $2-$3',
				$digits
			);
			}
		// Sem DDD (8 dígitos fixo): 1234-5678
		if ($len === 8)
			{
			return preg_replace(
				'/(\d{4})(\d{4})/',
				'$1-$2',
				$digits
			);
			}
		// Sem DDD (9 dígitos celular): 91234-5678
		if ($len === 9)
			{
			return preg_replace(
				'/(\d{1})(\d{4})(\d{4})/',
				'$1$2-$3',
				$digits
			);
			}
		// Qualquer outro formato: devolve o original
		return $value ?? '';
		}

	/**
	 * Helper to create urls
	 */
	if (!function_exists('url'))
		{

		function url(string $path = ''): string
			{
			return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
			}

		}
	}
	
/**
 * Masks brazilian dates
 */
if (!function_exists('maskDateBr'))
	{

	function maskDateBr(?string $input): ?string
		{
		if ($input === null)
			{
			return null;
			}

		$s = trim($input);
		if ($s === '')
			{
			return null;
			}

		// Valida estritamente YYYY-MM-DD
		$dt = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
		$errors = DateTimeImmutable::getLastErrors();

		if ($dt === false || ($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)
			{
			return null;
			}

		return $dt->format('d/m/Y'); // DD/MM/YYYY
		}

	}
	
/**
 * Convers brazilian dates to american dates 
 */
if (!function_exists('dateBrToUs'))
	{

	function dateBrToUs($date)
		{
		$dateTmp = explode('/', $date);
		/**
		 * 0 => day
		 * 1 => month
		 * 2 => year
		 */
		return $dateTmp[2] . '-' . $dateTmp[1] . '-' . $dateTmp[0];
		}

	}

/**
 * Authorization helper for views.
 */
if (!function_exists('authCan'))
	{
	function authCan(string $ability): bool
		{
		if (!class_exists('App\\Core\\Authorization'))
			{
			return false;
			}

		return \App\Core\Authorization::can($ability);
		}
	}
?>
