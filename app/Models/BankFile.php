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
