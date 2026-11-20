<?php

declare(strict_types=1);

namespace App\Features\Storage;

use App\Shared\Errors\NotFoundException;

class StorageService
{
    private ?StorageRepository $repo = null;

    public function __construct(?StorageRepository $repo = null)
    {
        $this->repo = $repo;
    }

    private function repo(): StorageRepository
    {
        if ($this->repo === null) {
            $this->repo = new StorageRepository();
        }
        return $this->repo;
    }

    public function getBySlug(string $slug): array
    {
        $file = $this->repo()->getBySlug($slug);
        if (!$file) {
            throw new NotFoundException('Archivo no encontrado');
        }
        return $file;
    }

    public function delete(string $slug): void
    {
        $file = $this->repo()->getBySlug($slug);
        if (!$file) {
            throw new NotFoundException('Archivo no encontrado');
        }

        $fullPath = dirname(__DIR__, 3) . '/storage/' . $file['path'];
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }

        $this->repo()->delete($slug);
    }
}

