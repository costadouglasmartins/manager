<?php

namespace App\Services;

use App\Core\TenantContext;

class ChunkedUploadService
	{
	private const SESSION_KEY = 'chunked_uploads';
	private const DEFAULT_MAX_FILE_SIZE = 8388608;
	private const DEFAULT_MAX_CHUNK_SIZE = 1048576;
	private const DEFAULT_MAX_UPLOAD_AGE = 86400;

	/**
	 * Receives a file chunk and returns a token when the file is complete.
	 *
	 * @return array<string, mixed>
	 */
	public function receiveChunk(string $namespace, array $post, array $files, array $options = []): array
		{
		$this->cleanupExpired($namespace, $options);

		$metadata = $this->normalizeChunkMetadata($post, $options);
		$chunk = $this->uploadedChunk($files);
		$chunkSize = (int) ($chunk['size'] ?? 0);
		$maxChunkSize = $this->integerOption($options, 'max_chunk_size', self::DEFAULT_MAX_CHUNK_SIZE);

		if ($chunkSize <= 0 || $chunkSize > $maxChunkSize)
			{
			throw new \RuntimeException('O pedaço enviado é inválido ou excede o tamanho permitido.');
			}

		$uploadDirectory = $this->uploadDirectory($namespace, $metadata['upload_id']);
		$this->ensureDirectory($uploadDirectory);

		$chunkPath = $uploadDirectory . '/' . str_pad((string) $metadata['chunk_index'], 6, '0', STR_PAD_LEFT) . '.part';
		$this->storeUploadedChunk((string) $chunk['tmp_name'], $chunkPath);

		if (!$this->hasAllChunks($uploadDirectory, $metadata['total_chunks']))
			{
			return [
				'complete'       => false,
				'chunk_index'    => $metadata['chunk_index'],
				'total_chunks'   => $metadata['total_chunks'],
				'uploaded_bytes' => $this->uploadedBytes($uploadDirectory),
			];
			}

		$assembledPath = $this->assembleChunks($uploadDirectory, $metadata);
		$token = $this->registerCompletedUpload($namespace, $metadata, $assembledPath);
		$this->deleteChunkDirectory($uploadDirectory, $metadata['total_chunks']);

		return [
			'complete' => true,
			'token'    => $token,
			'name'     => $metadata['file_name'],
			'size'     => $metadata['file_size'],
		];
		}

	/**
	 * Converts completed chunk tokens to a normalized files array.
	 */
	public function consumeCompletedUploads(string $namespace, array $tokens): array
		{
		$this->cleanupExpired($namespace);

		$tokens = $this->normalizeTokens($tokens);
		if ($tokens === [])
			{
			return [];
			}

		$uploads = $this->completedUploads($namespace);
		$selectedUploads = [];

		foreach ($tokens as $token)
			{
			if (!isset($uploads[$token]) || !is_array($uploads[$token]))
				{
				throw new \RuntimeException('Upload temporário não encontrado. Envie as imagens novamente.');
				}

			$upload = $uploads[$token];
			$path = (string) ($upload['path'] ?? '');
			if ($path === '' || !is_file($path))
				{
				throw new \RuntimeException('Arquivo temporário não encontrado. Envie as imagens novamente.');
				}

			$selectedUploads[$token] = $upload;
			}

		$normalized = [
			'name'     => [],
			'type'     => [],
			'tmp_name' => [],
			'error'    => [],
			'size'     => [],
		];

		foreach ($selectedUploads as $token => $upload)
			{
			$normalized['name'][] = (string) ($upload['name'] ?? 'arquivo');
			$normalized['type'][] = (string) ($upload['type'] ?? 'application/octet-stream');
			$normalized['tmp_name'][] = (string) ($upload['path'] ?? '');
			$normalized['error'][] = UPLOAD_ERR_OK;
			$normalized['size'][] = (int) ($upload['size'] ?? 0);

			unset($_SESSION['manager'][self::SESSION_KEY][$namespace][$token]);
			}

		return $normalized;
		}

	/**
	 * Deletes temporary files from a normalized files array.
	 */
	public function deleteTemporaryFiles(array $files): void
		{
		$tmpNames = $files['tmp_name'] ?? [];
		if (!is_array($tmpNames))
			{
			$tmpNames = [$tmpNames];
			}

		foreach ($tmpNames as $tmpName)
			{
			$tmpName = (string) $tmpName;
			if ($tmpName !== '' && is_file($tmpName))
				{
				@unlink($tmpName);
				}
			}
		}

	/**
	 * Normalizes and validates chunk request metadata.
	 *
	 * @return array<string, mixed>
	 */
	private function normalizeChunkMetadata(array $post, array $options): array
		{
		$uploadId = $this->sanitizePathSegment((string) ($post['upload_id'] ?? ''));
		$fileName = $this->sanitizeFileName((string) ($post['file_name'] ?? 'arquivo'));
		$fileType = trim((string) ($post['file_type'] ?? 'application/octet-stream'));
		$fileSize = (int) ($post['file_size'] ?? 0);
		$chunkIndex = (int) ($post['chunk_index'] ?? -1);
		$totalChunks = (int) ($post['total_chunks'] ?? 0);
		$maxFileSize = $this->integerOption($options, 'max_file_size', self::DEFAULT_MAX_FILE_SIZE);
		$allowedMimeTypes = $this->stringListOption($options, 'allowed_mime_types', []);

		if ($uploadId === '')
			{
			throw new \RuntimeException('Identificador do upload inválido.');
			}

		if ($fileSize <= 0 || $fileSize > $maxFileSize)
			{
			throw new \RuntimeException('Cada imagem deve ter no máximo 8 MB.');
			}

		if ($totalChunks < 1 || $totalChunks > 4096 || $chunkIndex < 0 || $chunkIndex >= $totalChunks)
			{
			throw new \RuntimeException('Sequência de upload inválida.');
			}

		if ($allowedMimeTypes !== [] && $fileType !== '' && !in_array($fileType, $allowedMimeTypes, true))
			{
			throw new \RuntimeException('Envie somente imagens JPG, PNG ou WebP.');
			}

		return [
			'upload_id'    => $uploadId,
			'file_name'    => $fileName,
			'file_type'    => $fileType !== '' ? $fileType : 'application/octet-stream',
			'file_size'    => $fileSize,
			'chunk_index'  => $chunkIndex,
			'total_chunks' => $totalChunks,
		];
		}

	/**
	 * Returns the uploaded chunk row.
	 *
	 * @return array<string, mixed>
	 */
	private function uploadedChunk(array $files): array
		{
		$chunk = $files['chunk'] ?? null;
		if (!is_array($chunk))
			{
			throw new \RuntimeException('Nenhum pedaço de arquivo foi recebido.');
			}

		if ((int) ($chunk['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
			{
			throw new \RuntimeException('Não foi possível receber um pedaço do arquivo.');
			}

		$tmpName = (string) ($chunk['tmp_name'] ?? '');
		if ($tmpName === '' || (!is_uploaded_file($tmpName) && !is_file($tmpName)))
			{
			throw new \RuntimeException('Pedaço de arquivo inválido.');
			}

		return $chunk;
		}

	/**
	 * Stores an uploaded chunk in the temporary directory.
	 */
	private function storeUploadedChunk(string $sourcePath, string $destinationPath): void
		{
		if (is_uploaded_file($sourcePath))
			{
			$moved = move_uploaded_file($sourcePath, $destinationPath);
			}
		else
			{
			$moved = copy($sourcePath, $destinationPath);
			}

		if (!$moved)
			{
			throw new \RuntimeException('Não foi possível salvar um pedaço do arquivo.');
			}
		}

	/**
	 * Indicates whether every chunk for an upload is present.
	 */
	private function hasAllChunks(string $uploadDirectory, int $totalChunks): bool
		{
		for ($index = 0; $index < $totalChunks; $index++)
			{
			if (!is_file($this->chunkPath($uploadDirectory, $index)))
				{
				return false;
				}
			}

		return true;
		}

	/**
	 * Returns the total number of uploaded bytes for a chunk directory.
	 */
	private function uploadedBytes(string $uploadDirectory): int
		{
		$total = 0;
		foreach (glob($uploadDirectory . '/*.part') ?: [] as $chunkPath)
			{
			$total += (int) (@filesize($chunkPath) ?: 0);
			}

		return $total;
		}

	/**
	 * Assembles chunks into one temporary file.
	 *
	 * @param array<string, mixed> $metadata
	 */
	private function assembleChunks(string $uploadDirectory, array $metadata): string
		{
		$completedDirectory = $this->completedDirectory();
		$this->ensureDirectory($completedDirectory);

		$assembledPath = $completedDirectory . '/' . $metadata['upload_id'] . '_' . bin2hex(random_bytes(8)) . '.upload';
		$target = @fopen($assembledPath, 'wb');
		if (!$target)
			{
			throw new \RuntimeException('Não foi possível montar o arquivo enviado.');
			}

		try
			{
			for ($index = 0; $index < (int) $metadata['total_chunks']; $index++)
				{
				$source = @fopen($this->chunkPath($uploadDirectory, $index), 'rb');
				if (!$source)
					{
					throw new \RuntimeException('Um pedaço do arquivo não foi encontrado.');
					}

				try
					{
					stream_copy_to_stream($source, $target);
					}
				finally
					{
					fclose($source);
					}
				}
			}
		finally
			{
			fclose($target);
			}

		$assembledSize = (int) (@filesize($assembledPath) ?: 0);
		if ($assembledSize !== (int) $metadata['file_size'])
			{
			@unlink($assembledPath);
			throw new \RuntimeException('O tamanho final do arquivo enviado não confere.');
			}

		return $assembledPath;
		}

	/**
	 * Registers a completed temporary upload in the current session.
	 *
	 * @param array<string, mixed> $metadata
	 */
	private function registerCompletedUpload(string $namespace, array $metadata, string $assembledPath): string
		{
		$token = bin2hex(random_bytes(16));

		if (!isset($_SESSION['manager'][self::SESSION_KEY][$namespace]) || !is_array($_SESSION['manager'][self::SESSION_KEY][$namespace]))
			{
			$_SESSION['manager'][self::SESSION_KEY][$namespace] = [];
			}

		$_SESSION['manager'][self::SESSION_KEY][$namespace][$token] = [
			'path'    => $assembledPath,
			'name'    => $metadata['file_name'],
			'type'    => $metadata['file_type'],
			'size'    => $metadata['file_size'],
			'created' => time(),
		];

		return $token;
		}

	/**
	 * Removes stale completed uploads for the namespace.
	 */
	private function cleanupExpired(string $namespace, array $options = []): void
		{
		$maxAge = $this->integerOption($options, 'max_upload_age', self::DEFAULT_MAX_UPLOAD_AGE);
		$uploads = $this->completedUploads($namespace);
		$now = time();

		foreach ($uploads as $token => $upload)
			{
			$created = (int) ($upload['created'] ?? 0);
			if ($created > 0 && ($now - $created) <= $maxAge)
				{
				continue;
				}

			$path = (string) ($upload['path'] ?? '');
			if ($path !== '' && is_file($path))
				{
				@unlink($path);
				}

			unset($_SESSION['manager'][self::SESSION_KEY][$namespace][$token]);
			}
		}

	/**
	 * Returns completed uploads for a namespace.
	 */
	private function completedUploads(string $namespace): array
		{
		$uploads = $_SESSION['manager'][self::SESSION_KEY][$namespace] ?? [];
		return is_array($uploads) ? $uploads : [];
		}

	/**
	 * Deletes chunk files and their upload directory after assembly.
	 */
	private function deleteChunkDirectory(string $uploadDirectory, int $totalChunks): void
		{
		for ($index = 0; $index < $totalChunks; $index++)
			{
			@unlink($this->chunkPath($uploadDirectory, $index));
			}

		@rmdir($uploadDirectory);
		}

	/**
	 * Returns the path for one chunk.
	 */
	private function chunkPath(string $uploadDirectory, int $index): string
		{
		return $uploadDirectory . '/' . str_pad((string) $index, 6, '0', STR_PAD_LEFT) . '.part';
		}

	/**
	 * Returns the temporary directory for one upload.
	 */
	private function uploadDirectory(string $namespace, string $uploadId): string
		{
		return $this->baseDirectory($namespace) . '/chunks/' . $uploadId;
		}

	/**
	 * Returns the directory where completed temporary files are stored.
	 */
	private function completedDirectory(): string
		{
		return $this->baseDirectory('completed');
		}

	/**
	 * Returns the base temporary directory isolated by tenant and user.
	 */
	private function baseDirectory(string $namespace): string
		{
		$rootPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
		$tenant = TenantContext::databaseName() ?: 'tenant';
		$userId = (int) ($_SESSION['manager']['user']['id'] ?? 0);

		return rtrim($rootPath, '/\\')
			. '/tmp/chunk_uploads/'
			. $this->sanitizePathSegment($namespace)
			. '/'
			. $this->sanitizePathSegment($tenant)
			. '/'
			. $userId;
		}

	/**
	 * Ensures a directory exists.
	 */
	private function ensureDirectory(string $directory): void
		{
		if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory))
			{
			throw new \RuntimeException('Não foi possível preparar a pasta temporária de upload.');
			}
		}

	/**
	 * Normalizes token inputs.
	 */
	private function normalizeTokens(array $tokens): array
		{
		$normalized = [];
		foreach ($tokens as $token)
			{
			$token = preg_replace('/[^a-f0-9]+/', '', strtolower((string) $token)) ?? '';
			if ($token !== '')
				{
				$normalized[] = $token;
				}
			}

		return array_values(array_unique($normalized));
		}

	/**
	 * Returns a configured positive integer option.
	 */
	private function integerOption(array $options, string $key, int $default): int
		{
		return max(1, (int) ($options[$key] ?? $default));
		}

	/**
	 * Returns a normalized string list option.
	 */
	private function stringListOption(array $options, string $key, array $default = []): array
		{
		$value = $options[$key] ?? $default;
		if (!is_array($value))
			{
			$value = [$value];
			}

		$list = [];
		foreach ($value as $item)
			{
			$item = trim((string) $item);
			if ($item !== '')
				{
				$list[] = $item;
				}
			}

		return array_values(array_unique($list));
		}

	/**
	 * Sanitizes a path segment.
	 */
	private function sanitizePathSegment(string $value): string
		{
		$value = trim($value);
		$value = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $value) ?? '';
		return trim($value, '_');
		}

	/**
	 * Sanitizes a user-provided file name.
	 */
	private function sanitizeFileName(string $fileName): string
		{
		$fileName = basename(str_replace('\\', '/', $fileName));
		$fileName = preg_replace('/[[:cntrl:]]+/', '', $fileName) ?? '';
		$fileName = trim($fileName);

		if ($fileName === '')
			{
			return 'arquivo';
			}

		if (function_exists('mb_substr'))
			{
			return mb_substr($fileName, 0, 180);
			}

		return substr($fileName, 0, 180);
		}
	}
?>
