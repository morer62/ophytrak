<?php

namespace App\Utils;

use Exception;
use Mimey\MimeTypes;
use App\Utils\CloudinaryClient;

class FileUtils
{
    public static function generateName(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public static function saveFile(array $file, string $folder): string
    {
        $upload = self::uploadFile($file, $folder);
        $url = (string)($upload['secure_url'] ?? $upload['url'] ?? '');
        if ($url === '') {
            throw new Exception("Upload completed without a public URL.");
        }

        return $url;
    }

    public static function uploadFile(array $file, string $folder): array
    {
        $mime = new MimeTypes();
        $type = (string)($file['type'] ?? '');
        $tmpName = $file['tmp_name'];
        $extension = $mime->getExtension($type) ?: strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/i', '', (string)$extension) ?: 'bin';
        $fileName = self::generateName() . '.' . $extension;

        if (strtolower((string)($_ENV['FILE_UPLOAD_DRIVER'] ?? getenv('FILE_UPLOAD_DRIVER') ?: 'cloudinary')) === 'local') {
            return self::saveLocalUpload($file, $folder, $fileName, $type);
        }

        try {
            $client = CloudinaryClient::client();

            $upload = $client->uploadApi()->upload($tmpName, [
                'folder' => $folder,
                'public_id' => pathinfo($fileName, PATHINFO_FILENAME),
                'resource_type' => 'auto'
            ]);

            return self::normalizeUploadResponse($upload);
        } catch (\Throwable $e) {
            error_log("Cloudinary upload fallback to local storage: " . $e->getMessage());
            return self::saveLocalUpload($file, $folder, $fileName, $type);
        }
    }

    private static function normalizeUploadResponse(mixed $upload): array
    {
        if (is_array($upload)) {
            return $upload;
        }

        if ($upload instanceof \Traversable) {
            return iterator_to_array($upload);
        }

        if (is_object($upload)) {
            foreach (['getArrayCopy', 'toArray', 'jsonSerialize'] as $method) {
                if (method_exists($upload, $method)) {
                    $data = $upload->{$method}();
                    if (is_array($data)) {
                        return $data;
                    }
                }
            }
        }

        throw new Exception('Upload completed with an unsupported response format.');
    }

    private static function saveLocalUpload(array $file, string $folder, string $fileName, string $type = ''): array
    {
        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new Exception("Invalid uploaded file.");
        }

        $safeFolder = trim(preg_replace('/[^a-z0-9_\-\/]/i', '-', $folder) ?? 'uploads', '/');
        $relativeFolder = 'uploads/' . $safeFolder . '/' . date('Y/m');
        $baseDir = self::getPublicUploadsRoot() . '/' . $relativeFolder;

        if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
            throw new Exception("Could not create local upload folder.");
        }

        $targetPath = $baseDir . '/' . $fileName;
        if (!move_uploaded_file($tmpName, $targetPath)) {
            throw new Exception("Could not save uploaded file locally.");
        }

        $publicPath = '/public/' . $relativeFolder . '/' . $fileName;
        $url = rtrim(LocationUtils::getBasePath(), '/') . $publicPath;

        return [
            'secure_url' => $url,
            'url' => $url,
            'public_id' => 'local/' . $safeFolder . '/' . pathinfo($fileName, PATHINFO_FILENAME),
            'resource_type' => str_starts_with($type, 'image/') ? 'image' : 'raw',
            'format' => pathinfo($fileName, PATHINFO_EXTENSION),
            'bytes' => (int)($file['size'] ?? filesize($targetPath) ?: 0),
            'local_path' => $targetPath,
        ];
    }

    private static function getPublicUploadsRoot(): string
    {
        return dirname(__DIR__, 2) . '/public';
    }

    public static function saveFileFromContent(string $content, string $folder, string $extension = "mp3"): string
    {
        $fileName = self::generateName() . '.' . $extension;
        $tmpPath = sys_get_temp_dir() . '/' . $fileName;
        file_put_contents($tmpPath, $content);

        try {
            $client = CloudinaryClient::client();

            $upload = $client->uploadApi()->upload($tmpPath, [
                'folder' => $folder,
                'public_id' => pathinfo($fileName, PATHINFO_FILENAME),
                'resource_type' => 'auto'
            ]);

            unlink($tmpPath);
            $uploadData = self::normalizeUploadResponse($upload);
            $url = (string)($uploadData['secure_url'] ?? $uploadData['url'] ?? '');
            if ($url === '') {
                throw new Exception("Upload completed without a public URL.");
            }

            return $url;
        } catch (Exception $e) {
            if (file_exists($tmpPath)) unlink($tmpPath);
            throw new Exception("Cloudinary upload error (content): " . $e->getMessage());
        }
    }

    public static function hasFile(array $files, string $file): bool
    {
        return !empty($files[$file]['name'] ?? '');
    }

    // Cloudinary no necesita delete si no se desea, pero lo incluimos opcionalmente
    public static function removeFile(string $publicUrl): bool
    {
        $localPath = self::localPathFromPublicUrl($publicUrl);
        if ($localPath && is_file($localPath)) {
            return unlink($localPath);
        }

        try {
            $publicId = self::extractPublicIdFromUrl($publicUrl);
            if (!$publicId) return false;

            $client = CloudinaryClient::client();
            $client->uploadApi()->destroy($publicId, [
                'resource_type' => 'auto'
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private static function localPathFromPublicUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path || !str_contains($path, '/public/uploads/')) {
            return null;
        }

        $relative = substr($path, strpos($path, '/public/uploads/') + strlen('/public/'));
        $relative = ltrim(str_replace(['..', '\\'], ['', '/'], $relative), '/');

        return dirname(__DIR__, 2) . '/public/' . $relative;
    }

    public static function getPublicUrl(string $cloudinaryUrl): string
    {
        return $cloudinaryUrl;
    }

    /**
     * Extrae el public_id desde una URL de Cloudinary
     */
    private static function extractPublicIdFromUrl(string $url): ?string
    {
        $parsed = parse_url($url);
        if (!isset($parsed['path'])) return null;

        $path = $parsed['path'];
        $parts = explode('/', ltrim($path, '/'));

        // Última parte debe ser el archivo
        $filename = end($parts);
        $publicId = preg_replace('/\.[^.]+$/', '', $filename); // quitar extensión
        array_pop($parts); // remove filename
        $folder = implode('/', $parts);

        return $folder ? "$folder/$publicId" : $publicId;
    }
}
