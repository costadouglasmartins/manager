<?php

namespace App\Models;

use App\Core\Database;
use App\Core\TenantModel;
use PDO;

class BankFile extends TenantModel
	{
	/**
	 * Returns the manager bank table.
	 */
	private function bankTable(): string
		{
		return sprintf('`%s`.`banks`', Database::getManagerDatabaseName());
		}

	/**
	 * Returns the base bank file select query with account details.
	 */
	private function baseSelectSql(): string
		{
		return "SELECT BankFile.*,
		              BankAccount.name AS bank_account_name,
		              Bank.name AS bank_name,
		              Bank.number AS bank_number,
		              (
		                SELECT COUNT(*)
		                FROM bank_slips AS BankSlip
		                WHERE BankSlip.bank_file_id = BankFile.id
		              ) AS bank_slips_count
		        FROM bank_files AS BankFile
		        LEFT JOIN bank_accounts AS BankAccount ON BankAccount.id = BankFile.bank_account_id
		        LEFT JOIN " . $this->bankTable() . " AS Bank ON Bank.id = BankAccount.bank_id";
		}

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
		$bankFileId = (int) $this->db->lastInsertId();
		$bankFileSnapshot = $this->find($bankFileId);
		$this->auditCreate('bank_file', 'bank_files', $bankFileId, $bankFileSnapshot);

		return ['bank_file_id' => $bankFileId];
		}

	/**
	 * Updates file storage metadata after generating a final file name.
	 */
	public function updateStorage(int $id, string $fileName, string $filePath): void
		{
		$beforeData = $this->find($id);
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

		$afterData = $this->find($id);
		$this->auditUpdate('bank_file', 'bank_files', $id, $beforeData, $afterData);
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
		$stmt = $this->db->prepare($this->baseSelectSql() . "
			WHERE BankFile.id = :id
			LIMIT 1
		");
		$stmt->execute(['id' => $id]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ?: [];
		}

	/**
	 * Searches remittance files with filters and pagination.
	 */
	public function search(array $filters = [], int $limit = 10, int $offset = 0): array
		{
		$sql = $this->baseSelectSql() . " WHERE 1 = 1";
		$params = [];

		if (($filters['id'] ?? '') !== '')
			{
			$sql .= " AND BankFile.id = :id";
			$params['id'] = (int) $filters['id'];
			}
		if (($filters['file_type'] ?? '') !== '')
			{
			$sql .= " AND BankFile.file_type = :file_type";
			$params['file_type'] = trim((string) $filters['file_type']);
			}
		if (($filters['file_name'] ?? '') !== '')
			{
			$sql .= " AND BankFile.file_name LIKE :file_name";
			$params['file_name'] = '%' . trim((string) $filters['file_name']) . '%';
			}
		if (($filters['bank_account_id'] ?? '') !== '')
			{
			$sql .= " AND BankFile.bank_account_id = :bank_account_id";
			$params['bank_account_id'] = (int) $filters['bank_account_id'];
			}

		$sql .= " ORDER BY BankFile.id DESC LIMIT :limit OFFSET :offset";
		$stmt = $this->db->prepare($sql);
		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}
		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Counts remittance files matching filters.
	 */
	public function count(array $filters = []): int
		{
		$sql = "SELECT COUNT(*)
		        FROM bank_files AS BankFile
		        WHERE 1 = 1";
		$params = [];

		if (($filters['id'] ?? '') !== '')
			{
			$sql .= " AND BankFile.id = :id";
			$params['id'] = (int) $filters['id'];
			}
		if (($filters['file_type'] ?? '') !== '')
			{
			$sql .= " AND BankFile.file_type = :file_type";
			$params['file_type'] = trim((string) $filters['file_type']);
			}
		if (($filters['file_name'] ?? '') !== '')
			{
			$sql .= " AND BankFile.file_name LIKE :file_name";
			$params['file_name'] = '%' . trim((string) $filters['file_name']) . '%';
			}
		if (($filters['bank_account_id'] ?? '') !== '')
			{
			$sql .= " AND BankFile.bank_account_id = :bank_account_id";
			$params['bank_account_id'] = (int) $filters['bank_account_id'];
			}

		$stmt = $this->db->prepare($sql);
		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}
		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Deletes a remittance file and all linked bank slips.
	 */
	public function deleteRemittanceWithBankSlips(int $id): void
		{
		if ($id <= 0)
			{
			throw new \InvalidArgumentException('Remessa inválida.');
			}

		try
			{
			$this->db->beginTransaction();

			$bankFile = $this->find($id);
			if ($bankFile === [])
				{
				throw new \InvalidArgumentException('Remessa não encontrada.');
				}
			if (strtoupper((string) ($bankFile['file_type'] ?? '')) !== 'REMITTANCE')
				{
				throw new \RuntimeException('A exclusão em lote é permitida apenas para arquivos de remessa.');
				}

			$deleteSlips = $this->db->prepare("DELETE FROM bank_slips WHERE bank_file_id = :bank_file_id");
			$deleteSlips->execute(['bank_file_id' => $id]);

			$deleteFile = $this->db->prepare("DELETE FROM bank_files WHERE id = :id");
			$deleteFile->execute(['id' => $id]);

			$this->auditDelete('bank_file', 'bank_files', $id, $bankFile, [
				'affected_tables' => ['bank_files', 'bank_slips'],
				'deleted_bank_slips_count' => (int) $deleteSlips->rowCount(),
			]);

			$this->db->commit();
			}
		catch (\Exception $e)
			{
			if ($this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			throw $e;
			}
		}

	/**
	 * Resolves the best download name for a remittance file.
	 */
	public function resolveDownloadFileName(array $bankFile): string
		{
		$fileName = trim((string) ($bankFile['file_name'] ?? ''));
		if ($fileName !== '' && !preg_match('/^\d+\.(rem|txt)$/i', $fileName))
			{
			return $fileName;
			}

		if (strtoupper((string) ($bankFile['file_type'] ?? '')) !== 'REMITTANCE')
			{
			return $fileName !== '' ? $fileName : 'arquivo.txt';
			}

		$createdAt = trim((string) ($bankFile['created'] ?? ''));
		$timestamp = strtotime($createdAt);
		if ($timestamp === false)
			{
			return $fileName !== '' ? $fileName : 'arquivo.txt';
			}

		$day = date('Y-m-d', $timestamp);
		$sequence = $this->sequenceInDay((int) ($bankFile['id'] ?? 0), $day);
		if ($sequence < 1 || $sequence > 26)
			{
			return $fileName !== '' ? $fileName : 'arquivo.txt';
			}

		return 'C' . date('ymd', $timestamp) . chr(64 + $sequence) . '.TXT';
		}

	/**
	 * Returns the remittance sequence position in a day ordered by creation time and id.
	 */
	private function sequenceInDay(int $bankFileId, string $day): int
		{
		if ($bankFileId <= 0)
			{
			return 0;
			}

		$stmt = $this->db->prepare("
			SELECT id
			FROM bank_files
			WHERE file_type = 'REMITTANCE'
			  AND created >= :start_date
			  AND created <= :end_date
			ORDER BY created ASC, id ASC
		");
		$stmt->execute([
			'start_date' => $day . ' 00:00:00',
			'end_date'   => $day . ' 23:59:59',
		]);

		$sequence = 0;
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
			{
			$sequence++;
			if ((int) ($row['id'] ?? 0) === $bankFileId)
				{
				return $sequence;
				}
			}

		return 0;
		}
	}
?>
