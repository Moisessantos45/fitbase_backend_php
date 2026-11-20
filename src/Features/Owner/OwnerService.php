<?php

declare(strict_types=1);

namespace App\Features\Owner;

use App\Config\Redis;
use App\Models\FileModel;
use App\Models\Owner;
use App\Shared\Errors\AppException;
use App\Shared\Errors\ConflictException;
use App\Shared\Errors\NotFoundException;
use App\Shared\Errors\ValidationException;
use Predis\Client as RedisClient;
use Throwable;

class OwnerService
{
    private const string CACHE_OWNER_PREFIX = 'owner_profile_';
    private const int CACHE_TTL_SECONDS = 600;
    public function __construct(
        private ?OwnerRepository $repo = null,
        private ?RedisClient $redis = null,
        private ?string $storageUrl = null
    ) {
        $this->storageUrl = $storageUrl ?? ($_ENV['HOST_URL_PROD'] ?? 'http://localhost:5173') . '/storage';
    }

    private function repo(): OwnerRepository
    {
        return $this->repo ??= new OwnerRepository();
    }

    private function redis(): RedisClient
    {
        return $this->redis ??= Redis::getClient();
    }

    public function completeProfile(int $authId, string $firstName, string $lastName, string $phone): OwnerDetail
    {
        if ($authId <= 0) {
            throw AppException::badRequest('auth_id inválido');
        }

        $firstName = trim($firstName);
        $lastName  = trim($lastName);
        $phone     = trim($phone);

        if ($firstName === '' || $lastName === '' || $phone === '') {
            throw new ValidationException('Todos los campos son obligatorios', [
                'first_name' => $firstName === '' ? 'El nombre es obligatorio' : null,
                'last_name'  => $lastName === '' ? 'El apellido es obligatorio' : null,
                'phone'      => $phone === '' ? 'El teléfono es obligatorio' : null,
            ]);
        }

        if ($this->repo()->existsByAuthId($authId)) {
            throw new ConflictException('El perfil de dueño ya ha sido completado previamente');
        }

        $owner = new Owner(
            id: 0,
            authId: $authId,
            firstName: $firstName,
            lastName: $lastName,
            phone: $phone
        );

        $this->repo()->withTransaction(function (OwnerRepository $txRepo) use ($owner, $authId) {
            $txRepo->create($owner);
            $txRepo->markAuthProfileCompleted($authId);
        });

        $this->invalidateCache($authId);

        $detail = $this->repo()->getByAuthId($authId);
        if ($detail === null) {
            throw new NotFoundException('Perfil de dueño no encontrado');
        }

        return $detail;
    }

    public function getProfile(int $authId): OwnerDetail
    {
        if ($authId <= 0) {
            throw AppException::badRequest('auth_id inválido');
        }

        $cacheKey = self::CACHE_OWNER_PREFIX . $authId;

        try {
            $cached = $this->redis()->get($cacheKey);
            if ($cached) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return OwnerDetail::fromArray($decoded);
                }
            }
        } catch (Throwable) {
        }

        $detail = $this->repo()->getByAuthId($authId);
        if ($detail === null) {
            throw new NotFoundException('Perfil de dueño no encontrado');
        }

        if (!empty($detail->slug)) {
            $detail->avatarUrl = \App\Shared\Utils\UrlHelper::buildEvidenceUrl($detail->slug);
        }

        try {
            $this->redis()->setex($cacheKey, self::CACHE_TTL_SECONDS, json_encode($detail->toArray()));
        } catch (Throwable) {
        }

        return $detail;
    }

    public function update(int $authId, ?string $firstName, ?string $lastName, ?string $phone): void
    {
        if ($authId <= 0) {
            throw AppException::badRequest('auth_id inválido');
        }

        $updateData = [];
        if ($firstName !== null && trim($firstName) !== '') {
            $updateData['first_name'] = trim($firstName);
        }
        if ($lastName !== null && trim($lastName) !== '') {
            $updateData['last_name'] = trim($lastName);
        }
        if ($phone !== null && trim($phone) !== '') {
            $updateData['phone'] = trim($phone);
        }

        if (empty($updateData)) {
            return;
        }

        $this->repo()->updateByAuthId($authId, $updateData);
        $this->invalidateCache($authId);
    }

    public function uploadAvatar(int $authId, string $slug, string $path, string $mimeType, int $size): void
    {
        if ($authId <= 0) {
            throw AppException::badRequest('auth_id inválido');
        }

        $file = new FileModel(
            id: 0,
            slug: $slug,
            path: $path,
            mimeType: $mimeType,
            size: $size,
            authorId: $authId
        );

        $this->repo()->withTransaction(function (OwnerRepository $txRepo) use ($file, $authId) {
            $fileId = $txRepo->createFile($file);
            $txRepo->updateAvatarByAuthId($authId, $fileId);
        });

        $this->invalidateCache($authId);
    }

    private function invalidateCache(int $authId): void
    {
        try {
            $this->redis()->del([self::CACHE_OWNER_PREFIX . $authId]);
        } catch (Throwable) {
        }
    }
}

