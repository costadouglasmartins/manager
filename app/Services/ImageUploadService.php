<?php

namespace App\Services;

use App\Core\TenantContext;

class ImageUploadService
	{
	private const MAX_FILE_SIZE = 8388608;
	private const MAX_IMAGE_WIDTH = 1600;
	private const MAX_IMAGE_HEIGHT = 1600;
	private const THUMB_WIDTH = 420;
	private const THUMB_HEIGHT = 420;
	private const JPEG_QUALITY = 88;
	private const WEBP_QUALITY = 86;

	private const ALLOWED_MIME_TYPES = [
		'image/jpeg',
		'image/png',
		'image/webp',
	];

	private const ALLOWED_IMAGE_TYPES = [
		IMAGETYPE_JPEG,
		IMAGETYPE_PNG,
		IMAGETYPE_WEBP,
	];

	/**
	 * Indicates whether a multiple file input contains uploaded files.
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
	 * Stores uploaded images with a resized image and thumbnail.
	 *
	 * Supported options:
	 * - directory_parts: extra path segments after tenant_files/{tenant}/{module}
	 * - file_prefix: generated file name prefix
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function storeUploadedImages(string $moduleName, array $files, array $options = []): array
		{
		$storedImages = [];

		foreach ($this->normalizeFiles($files) as $file)
			{
			$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
			if ($error === UPLOAD_ERR_NO_FILE)
				{
				continue;
				}

			if ($error !== UPLOAD_ERR_OK)
				{
				throw new \RuntimeException('Não foi possível enviar uma das imagens.');
				}

			$storedImages[] = $this->storeSingleImage($moduleName, $file, $options);
			}

		return $storedImages;
		}

	/**
	 * Deletes image files associated with stored payloads.
	 */
	public function deleteStoredImages(array $images, array $pathKeys = ['file_path', 'thumb_path']): void
		{
		foreach ($images as $image)
			{
			foreach ($pathKeys as $key)
				{
				$relativePath = trim((string) ($image[$key] ?? ''));
				if ($relativePath === '')
					{
					continue;
					}

				$fullPath = $this->publicPath($relativePath);
				if (is_file($fullPath))
					{
					@unlink($fullPath);
					}
				}
			}
		}

	/**
	 * Stores one uploaded image and returns metadata ready for database insertion.
	 *
	 * @param array<string, mixed> $file Normalized $_FILES item.
	 * @return array<string, mixed>
	 */
	private function storeSingleImage(string $moduleName, array $file, array $options): array
		{
		$tmpName = (string) ($file['tmp_name'] ?? '');
		$originalName = (string) ($file['name'] ?? 'imagem');
		$fileSize = (int) ($file['size'] ?? 0);

		if ($fileSize <= 0 || $fileSize > self::MAX_FILE_SIZE)
			{
			throw new \RuntimeException('Cada imagem deve ter no máximo 8 MB.');
			}

		if ($tmpName === '' || (!is_uploaded_file($tmpName) && !is_file($tmpName)))
			{
			throw new \RuntimeException('Arquivo de imagem inválido.');
			}

		$mimeType = $this->detectMimeType($tmpName);
		if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true))
			{
			throw new \RuntimeException('Envie somente imagens JPG, PNG ou WebP.');
			}

		$sourceInfo = @getimagesize($tmpName);
		if (!is_array($sourceInfo) || empty($sourceInfo[0]) || empty($sourceInfo[1]))
			{
			throw new \RuntimeException('Não foi possível ler uma das imagens enviadas.');
			}
		$imageType = (int) ($sourceInfo[2] ?? 0);
		if (!in_array($imageType, self::ALLOWED_IMAGE_TYPES, true))
			{
			throw new \RuntimeException('Envie somente imagens JPG, PNG ou WebP.');
			}

		$relativeDirectory = $this->uploadRelativeDirectory($moduleName, $options);
		$directory = $this->publicPath($relativeDirectory);
		if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory))
			{
			throw new \RuntimeException('Não foi possível preparar a pasta de imagens.');
			}

		$filePrefix = $this->sanitizeFilePrefix((string) ($options['file_prefix'] ?? $moduleName));
		$fileBase = $filePrefix . '_' . bin2hex(random_bytes(8));
		$extension = function_exists('imagewebp') ? 'webp' : 'jpg';
		$outputMimeType = $extension === 'webp' ? 'image/webp' : 'image/jpeg';
		$imageRelativePath = $relativeDirectory . '/' . $fileBase . '.' . $extension;
		$thumbRelativePath = $relativeDirectory . '/' . $fileBase . '_thumb.' . $extension;

		try
			{
			$imageResult = $this->resizeAndSave(
				$tmpName,
				$this->publicPath($imageRelativePath),
				$imageType,
				self::MAX_IMAGE_WIDTH,
				self::MAX_IMAGE_HEIGHT,
				$extension
			);
			$this->resizeAndSave(
				$tmpName,
				$this->publicPath($thumbRelativePath),
				$imageType,
				self::THUMB_WIDTH,
				self::THUMB_HEIGHT,
				$extension
			);
			}
		catch (\RuntimeException $e)
			{
			@unlink($this->publicPath($imageRelativePath));
			@unlink($this->publicPath($thumbRelativePath));

			$extension = $this->extensionForImageType($imageType);
			$outputMimeType = $this->mimeTypeForImageType($imageType);
			$imageRelativePath = $relativeDirectory . '/' . $fileBase . '.' . $extension;
			$thumbRelativePath = $imageRelativePath;
			$this->copyUploadedFile($tmpName, $this->publicPath($imageRelativePath));
			$imageResult = [
				'width'  => (int) $sourceInfo[0],
				'height' => (int) $sourceInfo[1],
			];
			}

		return [
			'original_name' => $originalName,
			'file_path'     => $imageRelativePath,
			'thumb_path'    => $thumbRelativePath,
			'mime_type'     => $outputMimeType,
			'file_size'     => (int) (@filesize($this->publicPath($imageRelativePath)) ?: 0),
			'width'         => $imageResult['width'],
			'height'        => $imageResult['height'],
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
		string $extension
	): array
		{
		$source = $this->createImageResource($sourcePath, $sourceImageType);
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

		$this->saveImageResource($target, $destinationPath, $extension);

		return [
			'width'  => $targetWidth,
			'height' => $targetHeight,
		];
		}

	/**
	 * Creates a GD image resource from the source MIME type.
	 */
	private function createImageResource(string $sourcePath, int $imageType): \GdImage
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
			throw new \RuntimeException('Não foi possível processar uma das imagens enviadas. Reexporte a imagem como JPG, PNG ou WebP e tente novamente.');
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
	private function saveImageResource(\GdImage $image, string $destinationPath, string $extension): void
		{
		$saved = false;

		if ($extension === 'webp' && function_exists('imagewebp'))
			{
			$saved = \imagewebp($image, $destinationPath, self::WEBP_QUALITY);
			}
		else
			{
			\imageinterlace($image, true);
			$saved = \imagejpeg($image, $destinationPath, self::JPEG_QUALITY);
			}

		if (!$saved)
			{
			throw new \RuntimeException('Não foi possível salvar uma das imagens redimensionadas.');
			}
		}

	/**
	 * Copies an uploaded file or a local test file to its final destination.
	 */
	private function copyUploadedFile(string $sourcePath, string $destinationPath): void
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
			throw new \RuntimeException('Não foi possível salvar uma das imagens enviadas.');
			}
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
			throw new \RuntimeException('Tenant context is required to store uploaded images.');
			}

		$moduleSegment = $this->sanitizePathSegment($moduleName);
		if ($moduleSegment === '')
			{
			throw new \InvalidArgumentException('Informe o módulo para armazenar as imagens.');
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
		return $value !== '' ? $value : 'image';
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
