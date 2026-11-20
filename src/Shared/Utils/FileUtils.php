<?php

declare(strict_types=1);

namespace App\Shared\Utils;

use App\Shared\Errors\BadRequestException;
use App\Shared\Errors\ValidationException;
use Flight;

class FileUtils
{
    private const int MAX_IMAGE_SIZE = 5 * 1024 * 1024;    private const array ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public static function getBaseUploadsDir(): string
    {
        $sharedGoDir = '/home/moy45/Proyectos_go/fitbase/cmd/uploads';
        if (is_dir($sharedGoDir) && is_writable($sharedGoDir)) {
            return $sharedGoDir;
        }

        $localDir = dirname(__DIR__, 3) . '/storage';
        if (!is_dir($localDir)) {
            @mkdir($localDir, 0755, true);
        }
        return $localDir;
    }

    /**
     * Extrae y valida un único archivo de imagen soportando múltiples nombres de campo (image, avatar, etc.).
     *
     * @param string[] $candidateFields
     * @return array{name: string, type: string, tmp_name: string, error: int, size: int, mime: string}
     */
    public static function extractImage(array $candidateFields = ['image', 'avatar', 'cover', 'background', 'file']): array
    {
        $file = null;
        $reqFiles = Flight::request()->files ?? [];

        foreach ($candidateFields as $field) {
            if (!empty($_FILES[$field]['tmp_name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$field];
                break;
            }
            if (isset($reqFiles[$field]) && is_array($reqFiles[$field]) && !empty($reqFiles[$field]['tmp_name']) && ($reqFiles[$field]['error'] ?? -1) === UPLOAD_ERR_OK) {
                $file = $reqFiles[$field];
                break;
            }
        }

        if (!$file) {
            foreach ($candidateFields as $field) {
                if (isset($_FILES[$field]['error']) && $_FILES[$field]['error'] === UPLOAD_ERR_INI_SIZE) {
                    throw new BadRequestException('El archivo excede el tamaño máximo permitido por el servidor');
                }
            }
            throw new ValidationException('No se envió ningún archivo de imagen válido', ['image' => 'Archivo requerido']);
        }

        if ($file['size'] <= 0 || !is_uploaded_file($file['tmp_name'])) {
            throw new BadRequestException('El archivo subido está vacío o no es válido');
        }

        if ($file['size'] > self::MAX_IMAGE_SIZE) {
            throw new BadRequestException('La imagen excede el límite permitido de 5MB');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']) ?: 'image/jpeg';
        finfo_close($finfo);

        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new BadRequestException('Tipo de archivo no permitido. Solo se aceptan JPEG, PNG y WEBP');
        }

        $file['mime'] = $mime;
        return $file;
    }

    /**
     * Extrae y valida múltiples imágenes (galería de fotos de gimnasio).
     *
     * @param string[] $candidateFields
     * @return array<int, array{name: string, type: string, tmp_name: string, error: int, size: int, mime: string}>
     */
    public static function extractImages(array $candidateFields = ['photos', 'images', 'files']): array
    {
        $rawFiles = null;
        foreach ($candidateFields as $field) {
            if (!empty($_FILES[$field]['name'])) {
                $rawFiles = $_FILES[$field];
                break;
            }
        }

        if (!$rawFiles) {
            return [];
        }

        $list = [];
        $names = (array) $rawFiles['name'];
        $tmpNames = (array) $rawFiles['tmp_name'];
        $errors = (array) $rawFiles['error'];
        $sizes = (array) $rawFiles['size'];

        foreach ($names as $idx => $origName) {
            if (($errors[$idx] ?? -1) !== UPLOAD_ERR_OK || empty($tmpNames[$idx]) || !is_uploaded_file($tmpNames[$idx])) {
                continue;
            }

            $size = (int) ($sizes[$idx] ?? 0);
            if ($size <= 0 || $size > self::MAX_IMAGE_SIZE) {
                continue;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $tmpNames[$idx]) ?: 'image/jpeg';
            finfo_close($finfo);

            if (!in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
                continue;
            }

            $list[] = [
                'name'     => $origName,
                'tmp_name' => $tmpNames[$idx],
                'error'    => $errors[$idx],
                'size'     => $size,
                'type'     => $mime,
                'mime'     => $mime,
            ];
        }

        return $list;
    }

    /**
     * Guarda un archivo de forma segura en el directorio de almacenamiento y retorna metadatos.
     *
     * @return array{slug: string, path: string, mime_type: string, size: int}
     */
    public static function saveFile(string $subDir, array $fileInfo): array
    {
        $baseDir = self::getBaseUploadsDir();
        $cleanSubDir = trim(str_replace(['..', '\\'], ['', '/'], $subDir), '/');
        $targetDir = $baseDir . '/' . $cleanSubDir;

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);        $slug = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        $ext = strtolower(pathinfo($fileInfo['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = ($fileInfo['mime'] === 'image/png') ? 'png' : (($fileInfo['mime'] === 'image/webp') ? 'webp' : 'jpg');
        }

        $filename = "{$slug}.{$ext}";
        $destination = "{$targetDir}/{$filename}";

        $moved = false;
        if (is_uploaded_file($fileInfo['tmp_name'])) {
            $moved = move_uploaded_file($fileInfo['tmp_name'], $destination);
        } else {
            $moved = @rename($fileInfo['tmp_name'], $destination) || @copy($fileInfo['tmp_name'], $destination);
        }

        if (!$moved) {
            throw new BadRequestException('No se pudo guardar la imagen en el servidor');
        }

        @chmod($destination, 0644);

        $size = (int) filesize($destination);
        $mime = $fileInfo['mime'] ?? 'image/jpeg';

        return [
            'slug'      => $slug,
            'path'      => $destination,
            'mime_type' => $mime,
            'size'      => $size,
        ];
    }
}
