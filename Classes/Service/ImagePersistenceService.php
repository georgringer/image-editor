<?php

declare(strict_types=1);

namespace GeorgRinger\ImageEditor\Service;

use GeorgRinger\ImageEditor\Event\ImageEditedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Writes edited image data back into FAL. The decision-rich logic (naming,
 * extension handling, conflict resolution, metadata preservation) lives here so
 * it can be tested without the HTTP layer.
 */
final readonly class ImagePersistenceService
{
    /**
     * Maps the supported output extensions to their canonical mime-type, used to
     * detect when the received bytes no longer match the target extension.
     */
    private const EXTENSION_MIME = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    private const JPEG_QUALITY = 90;
    private const WEBP_QUALITY = 90;

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ProcessedFileRepository $processedFileRepository,
    ) {}

    /**
     * Stores the edited image as a new file in the original's folder. The output
     * keeps the original extension; name collisions auto-increment (RENAME).
     */
    public function saveCopy(File $original, string $binary, string $desiredName): File
    {
        $extension = strtolower($original->getExtension());
        $targetName = $this->buildBaseName($desiredName, $original) . '.' . $extension;
        $binary = $this->ensureFormat($binary, $extension);

        $tempPath = GeneralUtility::tempnam('image_editor_', '.' . $extension);
        try {
            GeneralUtility::writeFile($tempPath, $binary, true);
            $newFile = $original->getStorage()->addFile(
                $tempPath,
                $original->getParentFolder(),
                $targetName,
                DuplicationBehavior::RENAME,
            );
        } finally {
            if (file_exists($tempPath)) {
                GeneralUtility::unlink_tempfile($tempPath);
            }
        }

        $this->eventDispatcher->dispatch(new ImageEditedEvent($original, $newFile, 'copy', $binary));

        return $newFile;
    }

    /**
     * Replaces the contents of an existing file in place. Keeps the sys_file
     * record and its sys_file_metadata (copyright/title/alt), refreshes the FAL
     * index (dimensions) and flushes outdated processed files.
     */
    public function overwrite(File $target, string $binary): File
    {
        // An overwrite cannot change the extension, so the bytes must match it.
        $binary = $this->ensureFormat($binary, strtolower($target->getExtension()));
        // setContents() writes the bytes and re-indexes (size, checksum, dimensions)
        // while leaving the editorial sys_file_metadata untouched.
        $target->setContents($binary);

        foreach ($this->processedFileRepository->findAllByOriginalFile($target) as $processedFile) {
            $processedFile->delete(true);
        }

        $this->eventDispatcher->dispatch(new ImageEditedEvent($target, $target, 'overwrite', $binary));

        return $target;
    }

    private function buildBaseName(string $desired, File $original): string
    {
        $desired = trim($desired);
        if ($desired === '') {
            return $original->getNameWithoutExtension();
        }

        // Ignore any extension the user typed; the original extension is enforced.
        $base = pathinfo($desired, PATHINFO_FILENAME);

        return $base !== '' ? $base : $original->getNameWithoutExtension();
    }

    /**
     * Guarantees the binary is encoded in the format implied by the target
     * extension. Filerobot forces PNG output for elliptical/free crops (to keep
     * transparency) regardless of the requested type, which would otherwise yield
     * e.g. PNG bytes under a ".webp" name — rejected by TYPO3's resource
     * consistency check. The bytes are re-encoded only when they actually differ.
     */
    private function ensureFormat(string $binary, string $extension): string
    {
        $targetMime = self::EXTENSION_MIME[$extension] ?? null;
        if ($targetMime === null) {
            return $binary;
        }
        // Only re-encode between recognised raster formats; anything else (or
        // already-matching bytes) is left untouched and validated downstream.
        $actualMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        if ($actualMime === $targetMime || !in_array($actualMime, self::EXTENSION_MIME, true)) {
            return $binary;
        }

        return $this->reencode($binary, $extension);
    }

    private function reencode(string $binary, string $extension): string
    {
        if ($extension === 'webp' && !function_exists('imagewebp')) {
            throw new \RuntimeException('WebP encoding is not available in this PHP installation.', 1718712000);
        }
        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            throw new \RuntimeException('The edited image data could not be decoded for re-encoding.', 1718712100);
        }

        try {
            ob_start();
            $success = match ($extension) {
                'png' => $this->outputPng($image),
                'webp' => $this->outputWebp($image),
                default => $this->outputJpeg($image),
            };
            $data = ob_get_clean();
            if (!$success || !is_string($data) || $data === '') {
                throw new \RuntimeException('The edited image could not be re-encoded to "' . $extension . '".', 1718712200);
            }

            return $data;
        } finally {
            imagedestroy($image);
        }
    }

    private function outputPng(\GdImage $image): bool
    {
        $this->preserveAlpha($image);

        return imagepng($image);
    }

    private function outputWebp(\GdImage $image): bool
    {
        $this->preserveAlpha($image);

        return imagewebp($image, null, self::WEBP_QUALITY);
    }

    /**
     * JPEG has no alpha channel, so any transparency (e.g. from an elliptical
     * crop) is flattened onto a white background before encoding.
     */
    private function outputJpeg(\GdImage $image): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagealphablending($canvas, true);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);

        try {
            return imagejpeg($canvas, null, self::JPEG_QUALITY);
        } finally {
            imagedestroy($canvas);
        }
    }

    private function preserveAlpha(\GdImage $image): void
    {
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
    }
}
