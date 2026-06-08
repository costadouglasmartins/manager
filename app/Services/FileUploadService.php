<?php

namespace App\Services;

use App\Core\TenantContext;

class FileUploadService
	{
	private const DEFAULT_MAX_FILE_SIZE = 8388608;
	private const DEFAULT_MAX_IMAGE_WIDTH = 1600;
	private const DEFAULT_MAX_IMAGE_HEIGHT = 1600;
	private const DEFAULT_THUMB_WIDTH = 420;
	private const DEFAULT_THUMB_HEIGHT = 420;
	private const JPEG_QUALITY = 88;
	private const WEBP_QUALITY = 86;

	private const DEFAULT_IMAGE_MIME_TYPES = [
		'image/jpeg',
		'image/png',
		'image/webp',
	];

	private const DEFAULT_IMAGE_TYPES = [
		IMAGETYPE_JPEG,
		IMAGETYPE_PNG,
		IMAGETYPE_WEBP,
	];

	private const DANGEROUS_EXTENSIONS = [
		'asp',
		'aspx',
		'bat',
		'cgi',
		'cmd',
		'com',
		'exe',
		'htm',
		'html',
		'js',
		'msi',
		'phar',
		'php',
		'php3',
		'php4',
		'php5',
		'phtml',
		'pl',
		'sh',
		'svg',
	];

	/**
	 * Indicates whether a file input contains uploaded files.
	 */
	public function hasUploads(array $files): bool
		{
		foreach ($this->normalizeFiles($files) as $file)
			{
			if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)
				{
				return true;
				}
			}

		return false;
		}

	/**
	 * Stores uploaded files and returns metadata ready for database insertion.
	 *
	 * Supported options:
	 * - directory_parts: extra path segments after tenant_files/{tenant}/{module}
	 * - file_prefix: generated file name prefix
	 * - max_file_size: maximum upload size in bytes
	 * - allowed_mime_types: accepted MIME types; empty accepts any non-dangerous file
	 * - allowed_extensions: accepted file extensions; empty accepts any non-dangerous extension
	 * - process_image: resizes image uploads and creates thumbnail metadata
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function storeUploadedFiles(string $moduleName, array $files, array $options = []): array
		{
		$storedFiles = [];

		foreach ($this->normalizeFiles($files) as $file)
			{
			$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
			if ($error === UPLOAD_ERR_NO_FILE)
				{
				continue;
				}

			if ($error !== UPLOAD_ERR_OK)
				{
				throw new \RuntimeException($this->optionMessage($options, 'upload_error_message', 'Não foi possível enviar um dos arquivos.'));
				}

			$storedFiles[] = $this->storeSingleFile($moduleName, $file, $options);
			}

		return $storedFiles;
		}

	/**
	 * Deletes stored files associated with payloads.
	 */
	public function deleteStoredFiles(array $files, array $pathKeys = ['file_path', 'thumb_path']): void
		{
		$deletedPaths = [];

		foreach ($files as $file)
			{
			foreach ($pathKeys as $key)
				{
				$relativePath = trim((string) ($file[$key] ?? ''));
				if ($relativePath === '' || isset($deletedPaths[$relativePath]))
					{
					continue;
					}

				$fullPath = $this->publicPath($relativePath);
				if (is_file($fullPath))
					{
					@unlink($fullPath);
					}

				$deletedPaths[$relativePath] = true;
				}
			}
		}

	/**
	 * Stores one uploaded file and returns normalized metadata.
	 *
	 * @param array<string, mixed> $file Normalized $_FILES item.
	 * @return array<string, mixed>
	 */
	private function storeSingleFile(string $moduleName, array $file, array $options): array
		{
		$tmpName = (string) ($file['tmp_name'] ?? '');
		$originalName = $this->sanitizeOriginalName((string) ($file['name'] ?? 'arquivo'));
		$fileSize = (int) ($file['size'] ?? 0);
		$maxFileSize = $this->maxFileSize($options);

		if ($fileSize <= 0 || ($maxFileSize > 0 && $fileSize > $maxFileSize))
			{
			throw new \RuntimeException($this->optionMessage($options, 'max_file_size_message', $this->maxFileSizeMessage($maxFileSize)));
			}

		if ($tmpName === '' || (!is_uploaded_file($tmpName) && !is_file($tmpName)))
			{
			throw new \RuntimeException($this->optionMessage($options, 'invalid_file_message', 'Arquivo enviado inválido.'));
			}

		$mimeType = $this->detectMimeType($tmpName);
		$this->validateMimeType($mimeType, $options);

		if ($this->shouldProcessImage($options))
			{
			return $this->storeImageFile($moduleName, $tmpName, $originalName, $mimeType, $options);
			}

		$extension = $this->extensionForFile($originalName, $mimeType);
		$this->validateFileExtension($extension, $options);

		$relativeDirectory = $this->uploadRelativeDirectory($moduleName, $options);
		$this->ensureDirectory($relativeDirectory, $options);

		$filePrefix = $this->sanitizeFilePrefix((string) ($options['file_prefix'] ?? $moduleName));
		$fileBase = $filePrefix . '_' . bin2hex(random_bytes(8));
		$fileRelativePath = $relativeDirectory . '/' . $fileBase . '.' . $extension;

		$this->copyUploadedFile($tmpName, $this->publicPath($fileRelativePath), $options);

		return [
			'original_name' => $originalName,
			'file_path'     => $fileRelativePath,
			'thumb_path'    => null,
			'mime_type'     => $mimeType,
			'file_size'     => (int) (@filesize($this->publicPath($fileRelativePath)) ?: 0),
			'width'         => null,
			'height'        => null,
			'extension'     => $extension,
		];
		}

	/**
	 * Stores an uploaded image with a resized image and thumbnail.
	 *
	 * @return array<string, mixed>
	 */
	private function storeImageFile(string $moduleName, string $tmpName, string $originalName, string $detectedMimeType, array $options): array
		{
		$sourceInfo = @getimagesize($tmpName);
		if (!is_array($sourceInfo) || empty($sourceInfo[0]) || empty($sourceInfo[1]))
			{
			throw new \RuntimeException($this->optionMessage($options, 'invalid_image_message', 'Não foi possível ler uma das imagens enviadas.'));
			}

		$imageType = (int) ($sourceInfo[2] ?? 0);
		$this->validateImageType($imageType, $options);

		$relativeDirectory = $this->uploadRelativeDirectory($moduleName, $options);
		$this->ensureDirectory($relativeDirectory, $options);

		$filePrefix = $this->sanitizeFilePrefix((string) ($options['file_prefix'] ?? $moduleName));
		$fileBase = $filePrefix . '_' . bin2hex(random_bytes(8));
		$extension = $this->imageOutputExtension($options);
		$outputMimeType = $this->mimeTypeForExtension($extension, $detectedMimeType);
		$fileRelativePath = $relativeDirectory . '/' . $fileBase . '.' . $extension;
		$thumbRelativePath = $relativeDirectory . '/' . $fileBase . '_thumb.' . $extension;

		try
			{
			$imageResult = $this->resizeAndSave(
				$tmpName,
				$this->publicPath($fileRelativePath),
				$imageType,
				$this->integerOption($options, 'max_width', self::DEFAULT_MAX_IMAGE_WIDTH),
				$this->integerOption($options, 'max_height', self::DEFAULT_MAX_IMAGE_HEIGHT),
				$extension,
				$options
			);
			$this->resizeAndSave(
				$tmpName,
				$this->publicPath($thumbRelativePath),
				$imageType,
				$this->integerOption($options, 'thumbnail_width', self::DEFAULT_THUMB_WIDTH),
				$this->integerOption($options, 'thumbnail_height', self::DEFAULT_THUMB_HEIGHT),
				$extension,
				$options
			);
			}
		catch (\RuntimeException $e)
			{
			@unlink($this->publicPath($fileRelativePath));
			@unlink($this->publicPath($thumbRelativePath));

			$extension = $this->extensionForImageType($imageType);
			$outputMimeType = $this->mimeTypeForImageType($imageType);
			$fileRelativePath = $relativeDirectory . '/' . $fileBase . '.' . $extension;
			$thumbRelativePath = $fileRelativePath;
			$this->copyUploadedFile($tmpName, $this->publicPath($fileRelativePath), $options);
			$imageResult = [
				'width'  => (int) $sourceInfo[0],
				'height' => (int) $sourceInfo[1],
			];
			}

		return [
			'original_name' => $originalName,
			'file_path'     => $fileRelativePath,
			'thumb_path'    => $thumbRelativePath,
			'mime_type'     => $outputMimeType,
			'file_size'     => (int) (@filesize($this->publicPath($fileRelativePath)) ?: 0),
			'width'         => $imageResult['width'],
			'height'        => $imageResult['height'],
			'extension'     => $extension,
		];
		}

	/**
	 * Resizes an image without upscaling and saves it as WebP or JPEG.
	 *
	 * @return array{width:int,height:int}
	 */
	private function resizeAndSave(
		string $sourcePath,
		string $destinationPath,
		int $sourceImageType,
		int $maxWidth,
		int $maxHeight,
		string $extension,
		array $options
	): array
		{
		$source = $this->createImageResource($sourcePath, $sourceImageType, $options);
		$target = null;

		try
			{
			$sourceWidth = \imagesx($source);
			$sourceHeight = \imagesy($source);

			$ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1);
			$targetWidth = max(1, (int) floor($sourceWidth * $ratio));
			$targetHeight = max(1, (int) floor($sourceHeight * $ratio));
			$target = \imagecreatetruecolor($targetWidth, $targetHeight);

			if ($extension === 'webp')
				{
				\imagealphablending($target, false);
				\imagesavealpha($target, true);
				$transparent = \imagecolorallocatealpha($target, 0, 0, 0, 127);
				\imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
				}
			else
				{
				$white = \imagecolorallocate($target, 255, 255, 255);
				\imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $white);
				}

			\imagecopyresampled(
				$target,
				$source,
				0,
				0,
				0,
				0,
				$targetWidth,
				$targetHeight,
				$sourceWidth,
				$sourceHeight
			);

			$this->saveImageResource($target, $destinationPath, $extension, $options);

			return [
				'width'  => $targetWidth,
				'height' => $targetHeight,
			];
			}
		finally
			{
			if ($target instanceof \GdImage)
				{
				\imagedestroy($target);
				}

			\imagedestroy($source);
			}
		}

	/**
	 * Creates a GD image resource from the source MIME type.
	 */
	private function createImageResource(string $sourcePath, int $imageType, array $options): \GdImage
		{
		$image = match ($imageType)
			{
			IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @\imagecreatefromjpeg($sourcePath) : false,
			IMAGETYPE_PNG  => function_exists('imagecreatefrompng') ? @\imagecreatefrompng($sourcePath) : false,
			IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @\imagecreatefromwebp($sourcePath) : false,
			default        => false,
			};

		if (!$image instanceof \GdImage)
			{
			$contents = @file_get_contents($sourcePath);
			if (is_string($contents) && $contents !== '' && function_exists('imagecreatefromstring'))
				{
				$image = @\imagecreatefromstring($contents);
				}
			}

		if (!$image instanceof \GdImage)
			{
			throw new \RuntimeException($this->optionMessage($options, 'image_processing_message', 'Não foi possível processar uma das imagens enviadas. Reexporte a imagem como JPG, PNG ou WebP e tente novamente.'));
			}

		if (!\imageistruecolor($image))
			{
			\imagepalettetotruecolor($image);
			}

		return $image;
		}

	/**
	 * Saves a GD resource using the configured quality.
	 */
	private function saveImageResource(\GdImage $image, string $destinationPath, string $extension, array $options): void
		{
		$saved = false;

		if ($extension === 'webp' && function_exists('imagewebp'))
			{
			$saved = \imagewebp($image, $destinationPath, $this->integerOption($options, 'webp_quality', self::WEBP_QUALITY));
			}
		else
			{
			\imageinterlace($image, true);
			$saved = \imagejpeg($image, $destinationPath, $this->integerOption($options, 'jpeg_quality', self::JPEG_QUALITY));
			}

		if (!$saved)
			{
			throw new \RuntimeException($this->optionMessage($options, 'image_save_message', 'Não foi possível salvar uma das imagens redimensionadas.'));
			}
		}

	/**
	 * Copies an uploaded file or a local test file to its final destination.
	 */
	private function copyUploadedFile(string $sourcePath, string $destinationPath, array $options): void
		{
		$copied = false;
		if (is_uploaded_file($sourcePath))
			{
			$copied = move_uploaded_file($sourcePath, $destinationPath);
			}
		else
			{
			$copied = copy($sourcePath, $destinationPath);
			}

		if (!$copied)
			{
			throw new \RuntimeException($this->optionMessage($options, 'copy_error_message', 'Não foi possível salvar um dos arquivos enviados.'));
			}
		}

	/**
	 * Validates the detected MIME type when the caller restricts accepted files.
	 */
	private function validateMimeType(string $mimeType, array $options): void
		{
		$defaultMimeTypes = $this->shouldProcessImage($options) ? self::DEFAULT_IMAGE_MIME_TYPES : [];
		$allowedMimeTypes = $this->stringListOption($options, 'allowed_mime_types', $defaultMimeTypes);
		if ($allowedMimeTypes === [])
			{
			return;
			}

		if (!in_array($mimeType, $allowedMimeTypes, true))
			{
			throw new \RuntimeException($this->optionMessage($options, 'mime_type_error_message', 'Tipo de arquivo não permitido.'));
			}
		}

	/**
	 * Validates the final file extension.
	 */
	private function validateFileExtension(string $extension, array $options): void
		{
		$allowedExtensions = $this->normalizeExtensions($this->stringListOption($options, 'allowed_extensions'));
		if ($allowedExtensions !== [] && !in_array($extension, $allowedExtensions, true))
			{
			throw new \RuntimeException($this->optionMessage($options, 'extension_error_message', 'Extensão de arquivo não permitida.'));
			}

		$disallowedExtensions = $this->normalizeExtensions($this->stringListOption($options, 'disallowed_extensions', self::DANGEROUS_EXTENSIONS));
		if (in_array($extension, $disallowedExtensions, true))
			{
			throw new \RuntimeException($this->optionMessage($options, 'extension_error_message', 'Extensão de arquivo não permitida.'));
			}
		}

	/**
	 * Validates the GD image type.
	 */
	private function validateImageType(int $imageType, array $options): void
		{
		$allowedImageTypes = $options['allowed_image_types'] ?? self::DEFAULT_IMAGE_TYPES;
		if (!is_array($allowedImageTypes))
			{
			$allowedImageTypes = [$allowedImageTypes];
			}

		$allowedImageTypes = array_map('intval', $allowedImageTypes);
		if (!in_array($imageType, $allowedImageTypes, true))
			{
			throw new \RuntimeException($this->optionMessage($options, 'image_type_error_message', 'Tipo de imagem não permitido.'));
			}
		}

	/**
	 * Returns whether image processing should run for uploaded files.
	 */
	private function shouldProcessImage(array $options): bool
		{
		return (bool) ($options['process_image'] ?? false);
		}

	/**
	 * Returns the output extension for processed images.
	 */
	private function imageOutputExtension(array $options): string
		{
		$extension = $this->sanitizeExtension((string) ($options['image_output_extension'] ?? ''));
		if (in_array($extension, ['jpg', 'jpeg'], true))
			{
			return 'jpg';
			}

		if ($extension === 'webp')
			{
			return function_exists('imagewebp') ? 'webp' : 'jpg';
			}

		return function_exists('imagewebp') ? 'webp' : 'jpg';
		}

	/**
	 * Returns the storage extension for a supported image type.
	 */
	private function extensionForImageType(int $imageType): string
		{
		return match ($imageType)
			{
			IMAGETYPE_JPEG => 'jpg',
			IMAGETYPE_PNG  => 'png',
			IMAGETYPE_WEBP => 'webp',
			default        => 'jpg',
			};
		}

	/**
	 * Returns the MIME type for a supported image type.
	 */
	private function mimeTypeForImageType(int $imageType): string
		{
		return match ($imageType)
			{
			IMAGETYPE_JPEG => 'image/jpeg',
			IMAGETYPE_PNG  => 'image/png',
			IMAGETYPE_WEBP => 'image/webp',
			default        => 'image/jpeg',
			};
		}

	/**
	 * Returns the MIME type for a saved file extension.
	 */
	private function mimeTypeForExtension(string $extension, string $fallbackMimeType): string
		{
		return match ($extension)
			{
			'jpg',
			'jpeg' => 'image/jpeg',
			'webp' => 'image/webp',
			default => $fallbackMimeType,
			};
		}

	/**
	 * Returns a safe file extension using the original name or MIME type.
	 */
	private function extensionForFile(string $originalName, string $mimeType): string
		{
		$extension = $this->sanitizeExtension((string) pathinfo($originalName, PATHINFO_EXTENSION));
		if ($extension !== '')
			{
			return $extension;
			}

		return $this->extensionForMimeType($mimeType);
		}

	/**
	 * Returns a common extension for known MIME types.
	 */
	private function extensionForMimeType(string $mimeType): string
		{
		return match ($mimeType)
			{
			'application/json' => 'json',
			'application/msword' => 'doc',
			'application/pdf' => 'pdf',
			'application/vnd.ms-excel' => 'xls',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
			'application/zip',
			'application/x-zip-compressed' => 'zip',
			'image/gif' => 'gif',
			'image/jpeg' => 'jpg',
			'image/png' => 'png',
			'image/webp' => 'webp',
			'text/csv' => 'csv',
			'text/plain' => 'txt',
			default => 'bin',
			};
		}

	/**
	 * Normalizes a multiple file input into a list of upload rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function normalizeFiles(array $files): array
		{
		if (!isset($files['name']))
			{
			return [];
			}

		if (!is_array($files['name']))
			{
			return [$files];
			}

		$normalized = [];
		foreach ($files['name'] as $index => $name)
			{
			$normalized[] = [
				'name'     => $name,
				'type'     => $files['type'][$index] ?? null,
				'tmp_name' => $files['tmp_name'][$index] ?? null,
				'error'    => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
				'size'     => $files['size'][$index] ?? 0,
			];
			}

		return $normalized;
		}

	/**
	 * Detects a file MIME type using Fileinfo.
	 */
	private function detectMimeType(string $path): string
		{
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		return (string) $finfo->file($path);
		}

	/**
	 * Returns the upload relative directory for the active tenant and module.
	 */
	private function uploadRelativeDirectory(string $moduleName, array $options = []): string
		{
		$databaseName = TenantContext::databaseName();
		if (!$databaseName)
			{
			throw new \RuntimeException('Tenant context is required to store uploaded files.');
			}

		$moduleSegment = $this->sanitizePathSegment($moduleName);
		if ($moduleSegment === '')
			{
			throw new \InvalidArgumentException('Informe o módulo para armazenar os arquivos.');
			}

		$segments = [
			'tenant_files',
			$this->sanitizePathSegment($databaseName),
			$moduleSegment,
		];

		$directoryParts = $options['directory_parts'] ?? [];
		if (!is_array($directoryParts))
			{
			$directoryParts = [$directoryParts];
			}

		foreach ($directoryParts as $part)
			{
			$part = $this->sanitizePathSegment((string) $part);
			if ($part !== '')
				{
				$segments[] = $part;
				}
			}

		return implode('/', $segments);
		}

	/**
	 * Ensures the upload destination directory exists.
	 */
	private function ensureDirectory(string $relativeDirectory, array $options): void
		{
		$directory = $this->publicPath($relativeDirectory);
		if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory))
			{
			throw new \RuntimeException($this->optionMessage($options, 'directory_error_message', 'Não foi possível preparar a pasta de arquivos.'));
			}
		}

	/**
	 * Returns the maximum upload size in bytes.
	 */
	private function maxFileSize(array $options): int
		{
		return max(0, (int) ($options['max_file_size'] ?? self::DEFAULT_MAX_FILE_SIZE));
		}

	/**
	 * Returns a user-facing maximum upload size message.
	 */
	private function maxFileSizeMessage(int $maxFileSize): string
		{
		if ($maxFileSize <= 0)
			{
			return 'Arquivo enviado inválido.';
			}

		$megabytes = max(1, (int) ceil($maxFileSize / 1048576));
		return 'Cada arquivo deve ter no máximo ' . $megabytes . ' MB.';
		}

	/**
	 * Returns a configured integer option.
	 */
	private function integerOption(array $options, string $key, int $default): int
		{
		return max(1, (int) ($options[$key] ?? $default));
		}

	/**
	 * Returns a configured message or fallback.
	 */
	private function optionMessage(array $options, string $key, string $fallback): string
		{
		$message = trim((string) ($options[$key] ?? ''));
		return $message !== '' ? $message : $fallback;
		}

	/**
	 * Returns a normalized list of string option values.
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
	 * Normalizes extension lists.
	 */
	private function normalizeExtensions(array $extensions): array
		{
		$normalized = [];
		foreach ($extensions as $extension)
			{
			$extension = $this->sanitizeExtension($extension);
			if ($extension !== '')
				{
				$normalized[] = $extension;
				}
			}

		return array_values(array_unique($normalized));
		}

	/**
	 * Sanitizes a user-provided file name.
	 */
	private function sanitizeOriginalName(string $fileName): string
		{
		$fileName = basename(str_replace('\\', '/', $fileName));
		$fileName = preg_replace('/[[:cntrl:]]+/', '', $fileName) ?? '';
		$fileName = trim($fileName);

		return $fileName !== '' ? $fileName : 'arquivo';
		}

	/**
	 * Sanitizes a file extension.
	 */
	private function sanitizeExtension(string $extension): string
		{
		$extension = strtolower(trim($extension));
		$extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?? '';
		return $extension;
		}

	/**
	 * Sanitizes a directory segment for safe storage paths.
	 */
	private function sanitizePathSegment(string $value): string
		{
		$value = trim($value);
		$value = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $value) ?? '';
		return trim($value, '_');
		}

	/**
	 * Sanitizes the generated file name prefix.
	 */
	private function sanitizeFilePrefix(string $value): string
		{
		$value = $this->sanitizePathSegment($value);
		return $value !== '' ? $value : 'file';
		}

	/**
	 * Resolves a public relative path to an absolute filesystem path.
	 */
	private function publicPath(string $relativePath): string
		{
		$publicPath = defined('PUBLIC_PATH') ? PUBLIC_PATH : dirname(__DIR__, 2) . '/public';
		return rtrim($publicPath, '/\\') . '/' . ltrim($relativePath, '/\\');
		}
	}
?>
