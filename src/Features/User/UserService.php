<?php

declare(strict_types=1);

namespace App\Features\User;

use App\Config\Redis;
use App\Models\FileModel;
use App\Models\User;
use App\Shared\Errors\AppException;
use App\Shared\Errors\NotFoundException;
use App\Shared\Errors\ValidationException;
use App\Shared\Utils\Validator;
use Predis\Client as RedisClient;
use Throwable;

class UserService
{
    private const string CACHE_PROFILE_PREFIX = 'user_profile_';
    private const int CACHE_TTL_SECONDS = 600;
    public function __construct(
        private ?UserRepository $repo = null,
        private ?RedisClient $redis = null,
        private ?string $storageUrl = null
    ) {
        $this->storageUrl = $storageUrl ?? ($_ENV['HOST_URL_PROD'] ?? 'http://localhost:5173') . '/storage';
    }

    private function repo(): UserRepository
    {
        return $this->repo ??= new UserRepository();
    }

    private function redis(): RedisClient
    {
        return $this->redis ??= Redis::getClient();
    }

    public function getByAuthId(int $authId): ?UserDetail
    {
        if ($authId <= 0) {
            return null;
        }

        $cacheKey = self::CACHE_PROFILE_PREFIX . $authId;

        try {
            $cached = $this->redis()->get($cacheKey);
            if ($cached) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return UserDetail::fromArray($decoded);
                }
            }
        } catch (Throwable) {
        }

        $detail = $this->repo()->getByAuthId($authId);
        if ($detail === null) {
            return null;
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

    public function completeProfile(int $authId, string $firstName, string $lastName, string $phone): User
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

        $user = new User(
            id: 0,
            authId: $authId,
            firstName: $firstName,
            lastName: $lastName,
            phone: $phone
        );

        $this->repo()->withTransaction(function (UserRepository $txRepo) use ($user, $authId) {
            $txRepo->create($user);
            $txRepo->changeCompletedProfile($authId, true);
        });

        $this->invalidateProfileCache($authId);

        return $user;
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
        $this->invalidateProfileCache($authId);
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

        $this->repo()->withTransaction(function (UserRepository $txRepo) use ($file, $authId) {
            $fileId = $txRepo->createFile($file);
            $txRepo->updateAvatarByAuthId($authId, $fileId);
        });

        $this->invalidateProfileCache($authId);
    }

    private function invalidateProfileCache(int $authId): void
    {
        try {
            $this->redis()->del([self::CACHE_PROFILE_PREFIX . $authId]);
        } catch (Throwable) {
        }
    }
}

