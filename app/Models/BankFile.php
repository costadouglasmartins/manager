<?php

namespace App\Models;

use App\Core\TenantModel;
use PDO;

class BankFile extends TenantModel
	{
	/**
	 * Creates a bank file metadata record and returns its identifier.
	 */
	public function create(array $data): array
		{
		$stmt = $this->db->prepare("
			INSERT INTO bank_files
			    (`bank_account_id`, `file_type`, `file_name`, `file_path`, `file_hash`, `created`, `modified`)
			VALUES
			    (:bank_account_id, :file_type, :file_name, :file_path, :file_hash, :created, :modified)
		");

		$stmt->execute([
			'bank_account_id' => isset($data['bank_account_id']) ? (int) $data['bank_account_id'] : null,
			'file_type'       => trim((string) ($data['file_type'] ?? 'REMITTANCE')),
			'file_name'       => trim((string) ($data['file_name'] ?? '')),
			'file_path'       => trim((string) ($data['file_path'] ?? '')),
			'file_hash'       => trim((string) ($data['file_hash'] ?? '')) ?: null,
			'created'         => date('Y-m-d H:i:s'),
			'modified'        => date('Y-m-d H:i:s'),
		]);

		return ['bank_file_id' => (int) $this->db->lastInsertId()];
		}

	/**
	 * Updates file storage metadata after generating a final file name.
	 */
	public function updateStorage(int $id, string $fileName, string $filePath): void
		{
		$stmt = $this->db->prepare("
			UPDATE bank_files
			SET file_name = :file_name,
			    file_path = :file_path,
			    modified = :modified
			WHERE id = :id
		");
		$stmt->execute([
			'file_name' => $fileName,
			'file_path' => $filePath,
			'modified'  => date('Y-m-d H:i:s'),
			'id'        => $id,
		]);
		}

	/**
	 * Returns the next daily sequence for an Itaú remittance file prefix.
	 */
	public function nextRemittanceSequence(string $typePrefix = 'C', ?string $date = null): int
		{
		$typePrefix = strtoupper(substr(preg_replace('/[^A-Z]/i', '', $typePrefix) ?? 'C', 0, 1));
		if ($typePrefix === '')
			{
			$typePrefix = 'C';
			}

		$timestamp = strtotime($date ?: date('Y-m-d'));
		if ($timestamp === false)
			{
			$timestamp = time();
			}

		$day = date('Y-m-d', $timestamp);
		$prefix = $typePrefix . date('ymd', $timestamp);
		$stmt = $this->db->prepare("
			SELECT file_name
			FROM bank_files
			WHERE file_type = 'REMITTANCE'
			  AND created >= :start_date
			  AND created <= :end_date
		");
		$stmt->execute([
			'start_date' => $day . ' 00:00:00',
			'end_date'   => $day . ' 23:59:59',
		]);

		$count = 0;
		$maxSequence = 0;
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
			{
			$count++;
			$fileName = strtoupper((string) ($row['file_name'] ?? ''));
			if (preg_match('/^' . preg_quote($prefix, '/') . '([A-Z])\.TXT$/', $fileName, $matches))
				{
				$maxSequence = max($maxSequence, ord($matches[1]) - 64);
				}
			}

		return max($count, $maxSequence) + 1;
		}

	/**
	 * Finds a bank file by id.
	 */
	public function find(int $id): array
		{
		$stmt = $this->db->prepare("
			SELECT BankFile.*
			FROM bank_files AS BankFile
			WHERE BankFile.id = :id
			LIMIT 1
		");
		$stmt->execute(['id' => $id]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ?: [];
		}
	}
?>
