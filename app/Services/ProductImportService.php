<?php

namespace App\Services;

use App\Core\TenantModel;
use App\Models\Product;
use App\Models\ProductAttribute;
use PDO;

class ProductImportService extends TenantModel
	{
	private const MAX_FILE_SIZE = 5242880;
	private const MAX_ROWS = 1000;

	public function importCsv(int $categoryId, array $file, int $userId = 0): array
		{
		$this->assertCategory($categoryId);
		$path = (string) ($file['tmp_name'] ?? '');
		$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
		$size = (int) ($file['size'] ?? 0);
		if ($error !== UPLOAD_ERR_OK || $path === '' || (!is_uploaded_file($path) && !is_file($path))) throw new \InvalidArgumentException('Selecione uma planilha CSV válida.');
		if ($size <= 0 || $size > self::MAX_FILE_SIZE) throw new \InvalidArgumentException('A planilha deve ter no máximo 5 MB.');

		$fileName = mb_substr(basename((string) ($file['name'] ?? 'produtos.csv')), 0, 180);
		$batchId = $this->createBatch($categoryId, $fileName, $userId);
		$total = 0;
		$imported = 0;
		$failed = 0;
		$handle = fopen($path, 'rb');
		if (!$handle) throw new \RuntimeException('Não foi possível ler a planilha.');

		try
			{
			$firstLine = fgets($handle);
			if ($firstLine === false) throw new \InvalidArgumentException('A planilha está vazia.');
			$delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
			rewind($handle);
			$headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
			if (!is_array($headers)) throw new \InvalidArgumentException('O cabeçalho da planilha é inválido.');
			$headers = array_map(fn ($header): string => $this->normalizeHeader((string) $header), $headers);
			if (!in_array('nome', $headers, true)) throw new \InvalidArgumentException('A planilha precisa possuir a coluna “nome”.');
			$context = $this->importContext($categoryId);
			$product = new Product();
			$rowNumber = 1;

			while (($values = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false)
				{
				$rowNumber++;
				if ($this->emptyRow($values)) continue;
				$total++;
				if ($total > self::MAX_ROWS)
					{
					$this->recordError($batchId, $rowNumber, 'O limite de ' . self::MAX_ROWS . ' linhas por importação foi excedido.', []);
					$failed++;
					break;
					}
				$row = [];
				foreach ($headers as $index => $header) if ($header !== '') $row[$header] = trim((string) ($values[$index] ?? ''));
				try
					{
					$product->create($this->productData($categoryId, $row, $context));
					$imported++;
					}
				catch (\Throwable $e)
					{
					$failed++;
					$this->recordError($batchId, $rowNumber, $e->getMessage(), $row);
					}
				}
			}
		catch (\Throwable $e)
			{
			$this->finishBatch($batchId, $total, $imported, max(1, $failed), 'FAILED');
			throw $e;
			}
		finally
			{
			fclose($handle);
			}

		$status = $failed === 0 ? 'COMPLETED' : ($imported > 0 ? 'PARTIAL' : 'FAILED');
		$this->finishBatch($batchId, $total, $imported, $failed, $status);
		return $this->findBatch($batchId);
		}

	public function templateCsv(int $categoryId): string
		{
		$this->assertCategory($categoryId);
		$schema = (new ProductAttribute())->getCategorySchema($categoryId);
		$headers = ['nome', 'sku', 'codigo_barras', 'preco_custo', 'preco_venda', 'unidade_venda',
			'incremento_venda', 'controlar_estoque', 'estoque_inicial', 'estoque_minimo', 'ativo',
			'comprimento', 'largura', 'altura', 'unidade_dimensoes', 'peso', 'unidade_peso', 'descricao'];
		$sample = ['Produto exemplo', 'EXEMPLO-001', '', '10,00', '19,90', 'un', '1', 'sim', '0', '0', 'sim', '', '', '', 'cm', '', 'kg', ''];
		foreach ($schema as $attribute)
			{
			if ((int) ($attribute['variation_specific'] ?? 0) === 1) continue;
			$headers[] = 'atributo:' . (string) $attribute['code'];
			$sample[] = $this->sampleAttributeValue($attribute);
			if (!empty($attribute['measurement_type']))
				{
				$headers[] = 'unidade:' . (string) $attribute['code'];
				$sample[] = (string) ($attribute['units'][0]['code'] ?? '');
				}
			}
		$stream = fopen('php://temp', 'w+b');
		fputcsv($stream, $headers, ';', '"', '\\');
		fputcsv($stream, $sample, ';', '"', '\\');
		rewind($stream);
		$content = (string) stream_get_contents($stream);
		fclose($stream);
		return "\xEF\xBB\xBF" . $content;
		}

	public function recentBatches(int $limit = 10): array
		{
		$limit = max(1, min(50, $limit));
		$stmt = $this->db->query("SELECT Batch.*, Category.name AS category_name
			FROM product_import_batches AS Batch
			INNER JOIN product_categories AS Category ON Category.id = Batch.product_category_id
			ORDER BY Batch.id DESC LIMIT {$limit}");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	public function findBatch(int $batchId): array
		{
		$stmt = $this->db->prepare("SELECT Batch.*, Category.name AS category_name
			FROM product_import_batches AS Batch
			INNER JOIN product_categories AS Category ON Category.id = Batch.product_category_id
			WHERE Batch.id = :id");
		$stmt->execute(['id' => $batchId]);
		$batch = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
		$errors = $this->db->prepare('SELECT `row_number`, `message`, row_json FROM product_import_errors WHERE product_import_batch_id = :id ORDER BY `row_number`, id');
		$errors->execute(['id' => $batchId]);
		$batch['errors'] = $errors->fetchAll(PDO::FETCH_ASSOC);
		return $batch;
		}

	private function productData(int $categoryId, array $row, array $context): array
		{
		$data = [
			'product_category_id' => $categoryId,
			'name' => $row['nome'] ?? '',
			'sku' => $row['sku'] ?? '',
			'barcode' => $row['codigo_barras'] ?? '',
			'cost_price' => $row['preco_custo'] ?? '0',
			'sale_price' => $row['preco_venda'] ?? '0',
			'sale_unit_id' => $this->unitId($row['unidade_venda'] ?? 'un', $context['units'], null),
			'sale_quantity_step' => $row['incremento_venda'] ?? '1',
			'track_stock' => $this->booleanValue($row['controlar_estoque'] ?? 'sim'),
			'initial_stock' => $row['estoque_inicial'] ?? '0',
			'minimum_stock' => $row['estoque_minimo'] ?? '0',
			'active' => $this->booleanValue($row['ativo'] ?? 'sim'),
			'length_value' => $row['comprimento'] ?? '',
			'width_value' => $row['largura'] ?? '',
			'height_value' => $row['altura'] ?? '',
			'dimension_unit_id' => $this->unitId($row['unidade_dimensoes'] ?? '', $context['units'], 'LENGTH', false),
			'weight_value' => $row['peso'] ?? '',
			'weight_unit_id' => $this->unitId($row['unidade_peso'] ?? '', $context['units'], 'WEIGHT', false),
			'description' => $row['descricao'] ?? '',
			'attributes' => [],
		];
		foreach ($context['schema'] as $attribute)
			{
			if ((int) ($attribute['variation_specific'] ?? 0) === 1) continue;
			$code = (string) $attribute['code'];
			$value = $row['atributo:' . $code] ?? '';
			if (in_array((string) $attribute['data_type'], ['OPTION', 'SELECT'], true) && $value !== '') $value = $this->optionId($attribute, $value);
			elseif (in_array((string) $attribute['data_type'], ['BOOLEAN', 'BOOL'], true) && $value !== '') $value = $this->booleanValue($value);
			$data['attributes'][(int) $attribute['product_attribute_id']] = [
				'value' => $value,
				'unit_id' => !empty($attribute['measurement_type'])
					? $this->unitId($row['unidade:' . $code] ?? '', $context['units'], (string) $attribute['measurement_type'], false)
					: null,
			];
			}
		return $data;
		}

	private function importContext(int $categoryId): array
		{
		$units = [];
		foreach ($this->db->query('SELECT id, code, symbol, measurement_type FROM product_units WHERE active = 1')->fetchAll(PDO::FETCH_ASSOC) as $unit)
			{
			$units[mb_strtolower((string) $unit['code'])] = $unit;
			$units[mb_strtolower((string) $unit['symbol'])] = $unit;
			}
		return ['units' => $units, 'schema' => (new ProductAttribute())->getCategorySchema($categoryId)];
		}

	private function unitId(string $value, array $units, ?string $type, bool $required = true): ?int
		{
		$value = mb_strtolower(trim($value));
		if ($value === '')
			{
			if ($required) throw new \InvalidArgumentException('Informe uma unidade válida.');
			return null;
			}
		$unit = $units[$value] ?? null;
		if (!$unit || ($type !== null && strtoupper((string) $unit['measurement_type']) !== strtoupper($type))) throw new \InvalidArgumentException('Unidade “' . $value . '” inválida.');
		return (int) $unit['id'];
		}

	private function optionId(array $attribute, string $value): int
		{
		$needle = $this->normalizeComparable($value);
		foreach ($attribute['options'] ?? [] as $option)
			{
			if ($needle === $this->normalizeComparable((string) ($option['value'] ?? '')) || $needle === $this->normalizeComparable((string) ($option['label'] ?? ''))) return (int) $option['id'];
			}
		throw new \InvalidArgumentException('Opção “' . $value . '” inválida para “' . $attribute['name'] . '”.');
		}

	private function booleanValue(string $value): string
		{
		return in_array($this->normalizeComparable($value), ['1', 'sim', 's', 'yes', 'true', 'ativo'], true) ? '1' : '0';
		}

	private function sampleAttributeValue(array $attribute): string
		{
		$type = (string) ($attribute['data_type'] ?? 'TEXT');
		if (in_array($type, ['OPTION', 'SELECT'], true)) return (string) ($attribute['options'][0]['label'] ?? '');
		if (in_array($type, ['BOOLEAN', 'BOOL'], true)) return 'sim';
		if (in_array($type, ['INTEGER', 'INT'], true)) return '1';
		if (in_array($type, ['DECIMAL', 'NUMBER'], true)) return '1,5';
		if ($type === 'DATE') return date('Y-m-d');
		return '';
		}

	private function createBatch(int $categoryId, string $fileName, int $userId): int
		{
		$stmt = $this->db->prepare("INSERT INTO product_import_batches
			(product_category_id, file_name, status, created_by_user_id, created, modified)
			VALUES (:category_id, :file_name, 'PROCESSING', :user_id, NOW(), NOW())");
		$stmt->execute(['category_id' => $categoryId, 'file_name' => $fileName, 'user_id' => $userId > 0 ? $userId : null]);
		return (int) $this->db->lastInsertId();
		}

	private function finishBatch(int $batchId, int $total, int $imported, int $failed, string $status): void
		{
		$stmt = $this->db->prepare("UPDATE product_import_batches SET status = :status,
			total_rows = :total, imported_rows = :imported, failed_rows = :failed, modified = NOW()
			WHERE id = :id");
		$stmt->execute(['status' => $status, 'total' => $total, 'imported' => $imported, 'failed' => $failed, 'id' => $batchId]);
		}

	private function recordError(int $batchId, int $rowNumber, string $message, array $row): void
		{
		$stmt = $this->db->prepare("INSERT INTO product_import_errors
			(product_import_batch_id, `row_number`, `message`, row_json, created)
			VALUES (:batch_id, :row_number, :message, :row_json, NOW())");
		$stmt->execute([
			'batch_id' => $batchId,
			'row_number' => $rowNumber,
			'message' => mb_substr($message, 0, 500),
			'row_json' => $row === [] ? null : json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
		]);
		}

	private function assertCategory(int $categoryId): void
		{
		$stmt = $this->db->prepare('SELECT 1 FROM product_categories WHERE id = :id AND active = 1');
		$stmt->execute(['id' => $categoryId]);
		if (!$stmt->fetchColumn()) throw new \InvalidArgumentException('Selecione uma categoria ativa.');
		}

	private function normalizeHeader(string $value): string
		{
		$value = preg_replace('/^\xEF\xBB\xBF/', '', trim($value)) ?? trim($value);
		$ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
		$value = mb_strtolower($ascii !== false ? $ascii : $value);
		$value = preg_replace('/\s+/', '_', $value) ?? $value;
		return trim($value);
		}

	private function normalizeComparable(string $value): string
		{
		$ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($value));
		return mb_strtolower($ascii !== false ? $ascii : trim($value));
		}

	private function emptyRow(array $values): bool
		{
		foreach ($values as $value) if (trim((string) $value) !== '') return false;
		return true;
		}
	}
?>
