<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Handles moving uploaded images to the upload directory.
 * Validates magic bytes and enforces a strict extension whitelist.
 */
final class ImageUploader
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * Maps allowed MIME types to their magic byte signatures.
     * Each signature is checked against the first 12 bytes of the file.
     */
    private const MAGIC_SIGNATURES = [
        "\xFF\xD8\xFF"           => 'jpg',   // JPEG
        "\x89PNG\r\n\x1A\n"     => 'png',   // PNG
        'GIF87a'                 => 'gif',   // GIF 87a
        'GIF89a'                 => 'gif',   // GIF 89a
    ];

    public function __construct(
        private readonly string $uploadDirectory,
    ) {
    }

    /**
     * @throws FileException|\RuntimeException
     */
    public function upload(UploadedFile $file): string
    {
        $extension = strtolower($file->guessExtension() ?? '');

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \RuntimeException('Type de fichier non autorisé.');
        }

        if (!$this->hasValidMagicBytes($file)) {
            throw new \RuntimeException('Le contenu du fichier ne correspond pas à une image valide.');
        }

        $subDirectory = 'products/' . date('Y/m');
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;

        $file->move($this->uploadDirectory . '/' . $subDirectory, $filename);

        return $subDirectory . '/' . $filename;
    }

    public function delete(string $relativePath): void
    {
        $fullPath = $this->uploadDirectory . '/' . $relativePath;

        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    private function hasValidMagicBytes(UploadedFile $file): bool
    {
        $handle = fopen($file->getPathname(), 'rb');
        if ($handle === false) {
            return false;
        }

        $bytes = fread($handle, 12);
        fclose($handle);

        if ($bytes === false) {
            return false;
        }

        // Vérifie les signatures connues (JPEG, PNG, GIF)
        foreach (self::MAGIC_SIGNATURES as $signature => $_) {
            if (str_starts_with($bytes, $signature)) {
                return true;
            }
        }

        // WebP : octets 0-3 = 'RIFF', octets 8-11 = 'WEBP'
        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return true;
        }

        return false;
    }
}
