<?php

declare(strict_types=1);

namespace App\Features\Storage;

use App\Shared\Errors\BadRequestException;
use App\Shared\Errors\NotFoundException;
use App\Shared\Utils\Response;
use Flight;

class StorageController
{
    private ?StorageService $service = null;

    public function __construct(?StorageService $service = null)
    {
        $this->service = $service;
    }

    private function service(): StorageService
    {
        if ($this->service === null) {
            $this->service = new StorageService();
        }
        return $this->service;
    }

    public function getBySlug(string $slug): void
    {
        $slug = trim($slug);
        if (empty($slug)) {
            throw new BadRequestException('Slug no proporcionado');
        }

        $file = $this->service()->getBySlug($slug);
        $rawPath = (string) ($file['path'] ?? '');

        $fullPath = null;
        if (!empty($rawPath)) {
            $candidates = [
                $rawPath,
                \App\Shared\Utils\FileUtils::getBaseUploadsDir() . '/' . ltrim($rawPath, '/'),
                dirname(__DIR__, 3) . '/storage/' . ltrim($rawPath, '/'),
                dirname(__DIR__, 3) . '/public/storage/' . ltrim($rawPath, '/'),
                dirname(__DIR__, 4) . '/Proyectos_go/fitbase/' . ltrim($rawPath, '/'),
                dirname(__DIR__, 4) . '/Proyectos_go/fitbase/cmd/uploads/' . basename($rawPath),
            ];

            foreach ($candidates as $cand) {
                if (file_exists($cand)) {
                    $fullPath = $cand;
                    break;
                }
            }
        }

        if ($fullPath === null) {
            $baseDirs = [
                \App\Shared\Utils\FileUtils::getBaseUploadsDir(),
                dirname(__DIR__, 3) . '/storage',
                dirname(__DIR__, 3) . '/public/storage',
            ];
            foreach ($baseDirs as $bDir) {
                if (!is_dir($bDir)) {
                    continue;
                }
                $matches = glob("{$bDir}/*/*/{$slug}*") ?: glob("{$bDir}/*/{$slug}*") ?: glob("{$bDir}/{$slug}*");
                if (!empty($matches) && file_exists($matches[0])) {
                    $fullPath = $matches[0];
                    break;
                }
            }
        }

        if ($fullPath === null || !file_exists($fullPath)) {
            throw new NotFoundException('Archivo físico no encontrado');
        }

        header('Content-Type: ' . ($file['mime_type'] ?? 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($fullPath));
        header('Content-Disposition: inline');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=3600');

        if (ob_get_level()) {
            ob_end_clean();
        }
        readfile($fullPath);
        exit;
    }

    public function deleteBySlug(string $slug): void
    {
        $slug = trim($slug);
        if (empty($slug)) {
            throw new BadRequestException('Slug no proporcionado');
        }

        $this->service()->delete($slug);
        Response::json(['message' => 'File deleted successfully']);
    }
}

