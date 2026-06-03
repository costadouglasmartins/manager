<?php

namespace App\Services;

class ZipCodeLookupService
	{
	private const VIA_CEP_URL = 'https://viacep.com.br/ws/%s/json/';

	/**
	 * Looks up a ZIP code using the ViaCEP webservice and returns a normalized payload.
	 *
	 * The method handles the different ViaCEP responses internally:
	 * - invalid ZIP code format
	 * - ZIP code not found
	 * - transport or decoding errors
	 *
	 * @return array<string, mixed>
	 */
	public function lookup(string $zipCode): array
		{
		$normalizedZipCode = $this->onlyDigits($zipCode);

		if (strlen($normalizedZipCode) !== 8)
			{
			return $this->makeResponse(
				false,
				false,
				422,
				'Informe um CEP com 8 dígitos.',
				$normalizedZipCode
			);
			}

		try
			{
			$payload = $this->requestViaCep($normalizedZipCode);
			}
		catch (\RuntimeException $e)
			{
			return $this->makeResponse(
				false,
				false,
				502,
				'Não foi possível consultar o CEP agora.',
				$normalizedZipCode
			);
			}

		if (!empty($payload['erro']))
			{
			return $this->makeResponse(
				false,
				false,
				404,
				'CEP não encontrado.',
				$normalizedZipCode
			);
			}

		return $this->makeResponse(
			true,
			true,
			200,
			'CEP encontrado.',
			$normalizedZipCode,
			$payload
		);
		}

	/**
	 * Performs the HTTP request to ViaCEP and decodes the JSON response.
	 *
	 * @return array<string, mixed>
	 */
	private function requestViaCep(string $zipCode): array
		{
		$url = sprintf(self::VIA_CEP_URL, $zipCode);
		$responseBody = $this->fetchUrl($url);

		$decoded = json_decode($responseBody, true);
		if (!is_array($decoded))
			{
			throw new \RuntimeException('Invalid ViaCEP JSON response.');
			}

		return $decoded;
		}

	/**
	 * Fetches a remote URL using cURL when available, otherwise falls back to file_get_contents.
	 */
	private function fetchUrl(string $url): string
		{
		if (function_exists('curl_init'))
			{
			$ch = curl_init($url);
			if ($ch === false)
				{
				throw new \RuntimeException('Unable to initialize cURL.');
				}

			curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => 5,
				CURLOPT_TIMEOUT        => 8,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTPHEADER     => ['Accept: application/json'],
				CURLOPT_USERAGENT      => 'Manager ZipCodeLookupService/1.0',
			]);

			$body = curl_exec($ch);
			$error = curl_error($ch);
			$statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
			curl_close($ch);

			if ($body === false || $error !== '' || $statusCode >= 400)
				{
				throw new \RuntimeException('ViaCEP request failed.');
				}

			return (string) $body;
			}

		$context = stream_context_create([
			'http' => [
				'method'  => 'GET',
				'timeout' => 8,
				'header'  => "Accept: application/json\r\nUser-Agent: Manager ZipCodeLookupService/1.0\r\n",
			],
			'https' => [
				'method'  => 'GET',
				'timeout' => 8,
				'header'  => "Accept: application/json\r\nUser-Agent: Manager ZipCodeLookupService/1.0\r\n",
			],
		]);

		$body = @file_get_contents($url, false, $context);
		if ($body === false)
			{
			throw new \RuntimeException('ViaCEP request failed.');
			}

		return $body;
		}

	/**
	 * Normalizes the response sent back to the application.
	 *
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function makeResponse(
		bool $success,
		bool $found,
		int $httpStatus,
		string $message,
		string $zipCode,
		array $payload = []
	): array
		{
		$response = [
			'success'          => $success,
			'found'            => $found,
			'http_status'      => $httpStatus,
			'message'          => $message,
			'zip_code'         => $zipCode,
			'formatted_zip_code' => $this->formatZipCode($zipCode),
			'street'           => '',
			'complement'       => '',
			'neighborhood'     => '',
			'city'             => '',
			'state'            => '',
			'ibge'             => '',
			'gia'              => '',
			'ddd'              => '',
			'siafi'            => '',
			'source'           => 'viacep',
		];

		if ($success && $found)
			{
			$response['street'] = $this->normalizeText((string) ($payload['logradouro'] ?? ''));
			$response['complement'] = $this->normalizeText((string) ($payload['complemento'] ?? ''));
			$response['neighborhood'] = $this->normalizeText((string) ($payload['bairro'] ?? ''));
			$response['city'] = $this->normalizeText((string) ($payload['localidade'] ?? ''));
			$response['state'] = $this->normalizeText((string) ($payload['uf'] ?? ''));
			$response['ibge'] = $this->normalizeText((string) ($payload['ibge'] ?? ''));
			$response['gia'] = $this->normalizeText((string) ($payload['gia'] ?? ''));
			$response['ddd'] = $this->normalizeText((string) ($payload['ddd'] ?? ''));
			$response['siafi'] = $this->normalizeText((string) ($payload['siafi'] ?? ''));
			}

		return $response;
		}

	/**
	 * Removes every non-digit character from the input.
	 */
	private function onlyDigits(string $value): string
		{
		return preg_replace('/\D+/', '', $value) ?? '';
		}

	/**
	 * Formats a CEP in the standard `00000-000` mask.
	 */
	private function formatZipCode(string $zipCode): string
		{
		$digits = $this->onlyDigits($zipCode);

		if (strlen($digits) !== 8)
			{
			return $digits;
			}

		return substr($digits, 0, 5) . '-' . substr($digits, 5, 3);
		}

	/**
	 * Normalizes optional text fields returned by ViaCEP.
	 */
	private function normalizeText(string $value): string
		{
		return trim($value);
		}
	}

?>
