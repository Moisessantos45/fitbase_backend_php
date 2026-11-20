<?php

declare(strict_types=1);

namespace App\Features\Gym;

use App\Config\Redis;
use App\Models\FileModel;
use App\Models\Gym;
use App\Models\GymPhoto;
use App\Models\GymSchedule;
use App\Models\Paginate;
use App\Shared\Errors\AppException;
use App\Shared\Errors\ForbiddenException;
use App\Shared\Errors\NotFoundException;
use App\Shared\Errors\ValidationException;
use App\Shared\Utils\Validator;
use DateTime;
use Predis\Client as RedisClient;
use Throwable;

class GymService
{
    private const string CACHE_GYM_PREFIX = 'gym_detail_';
    private const int CACHE_TTL_SECONDS = 600;
    public function __construct(
        private ?GymRepository $repo = null,
        private ?RedisClient $redis = null,
        private ?string $storageUrl = null
    ) {
        $this->storageUrl = $storageUrl ?? ($_ENV['HOST_URL_PROD'] ?? 'http://localhost:5173') . '/storage';
    }

    private function repo(): GymRepository
    {
        return $this->repo ??= new GymRepository();
    }

    private function redis(): RedisClient
    {
        return $this->redis ??= Redis::getClient();
    }

    public static function calculateIsOpen(array $schedules, ?DateTime $dt = null): bool
    {
        $now = $dt ?? new DateTime();
        $currentDay = (int) $now->format('w');        $currentMinutes = ((int) $now->format('H') * 60) + (int) $now->format('i');

        foreach ($schedules as $s) {
            if ((int) ($s['day_of_week'] ?? -1) === $currentDay) {
                if (!empty($s['is_closed'])) {
                    return false;
                }
                $open = (int) ($s['open_minutes'] ?? 0);
                $close = (int) ($s['close_minutes'] ?? 0);
                return $currentMinutes >= $open && $currentMinutes <= $close;
            }
        }

        return false;
    }

    public function create(
        int $authId,
        string $name,
        string $address,
        string $phone,
        string $email,
        float $latitude,
        float $longitude,
        array $schedules = []
    ): GymDetailResponse {
        $ownerId = $this->repo()->getOwnerIdByAuthId($authId);
        if ($ownerId === null) {
            throw new NotFoundException('Perfil de dueño no encontrado para este usuario');
        }

        $cleanEmail = Validator::validateEmail($email);
        $gym = new Gym(
            id: 0,
            ownerId: $ownerId,
            name: trim($name),
            address: trim($address),
            phone: trim($phone),
            email: $cleanEmail,
            latitude: $latitude,
            longitude: $longitude
        );

        $this->repo()->withTransaction(function (GymRepository $txRepo) use ($gym, $schedules) {
            $gymId = $txRepo->create($gym);

            $scheduleMap = [];
            foreach ($schedules as $s) {
                if (isset($s['day_of_week'])) {
                    $scheduleMap[(int) $s['day_of_week']] = $s;
                }
            }

            for ($day = 0; $day <= 6; $day++) {
                $item = $scheduleMap[$day] ?? null;
                $sched = new GymSchedule(
                    id: 0,
                    gymId: $gymId,
                    dayOfWeek: $day,
                    openMinutes: (int) ($item['open_minutes'] ?? 480),                    closeMinutes: (int) ($item['close_minutes'] ?? 1320),                    isClosed: isset($item['is_closed']) ? (bool) $item['is_closed'] : ($item === null)
                );
                $txRepo->createSchedule($sched);
            }
        });

        $detail = $this->repo()->getDetailsById($gym->id);
        if ($detail === null) {
            throw new NotFoundException('No se pudo recuperar el gimnasio creado');
        }

        return $this->formatDetail($detail);
    }

    public function getDetailsById(int $gymId): GymDetailResponse
    {
        $cacheKey = self::CACHE_GYM_PREFIX . $gymId;

        try {
            $cached = $this->redis()->get($cacheKey);
            if ($cached) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    $cachedResponse = GymDetailResponse::fromArray($decoded);
                    $cachedResponse->isOpen = self::calculateIsOpen($cachedResponse->schedules);
                    return $cachedResponse;
                }
            }
        } catch (Throwable) {
        }

        $detail = $this->repo()->getDetailsById($gymId);
        if ($detail === null) {
            throw new NotFoundException('Gimnasio no encontrado');
        }

        $formatted = $this->formatDetail($detail);

        try {
            $this->redis()->setex($cacheKey, self::CACHE_TTL_SECONDS, json_encode($formatted->toArray()));
        } catch (Throwable) {
        }

        return $formatted;
    }

    public function getMyGymDetails(int $authId): GymDetailResponse
    {
        $detail = $this->repo()->getDetailsByAuthId($authId);
        if ($detail === null) {
            throw new NotFoundException('No tienes ningún gimnasio asociado');
        }

        return $this->formatDetail($detail);
    }

    public function getAllByOwner(int $authId, int $page = 1, int $pageSize = 10): array
    {
        $ownerId = $this->repo()->getOwnerIdByAuthId($authId);
        if ($ownerId === null) {
            throw new NotFoundException('Perfil de dueño no encontrado');
        }

        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $offset = ($page - 1) * $pageSize;

        $res = $this->repo()->getAllByOwnerId($ownerId, $offset, $pageSize);

        $items = $res['data'];
        foreach ($items as $item) {
            if ($item->backgroundSlug) {
                $item->backgroundUrl = \App\Shared\Utils\UrlHelper::buildEvidenceUrl($item->backgroundSlug);
            }
        }

        $dataArray = array_map(fn($it) => $it->toArray(), $items);
        return Paginate::response($dataArray, $res['total'], $page, $pageSize);
    }

    public function getAllPublic(string $search, float $lat = 0.0, float $lng = 0.0, int $page = 1, int $pageSize = 10): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $offset = ($page - 1) * $pageSize;

        $res = $this->repo()->getAllPublic($search, $lat, $lng, $offset, $pageSize);

        $items = $res['data'];
        foreach ($items as $item) {
            if ($item->backgroundSlug) {
                $item->backgroundUrl = \App\Shared\Utils\UrlHelper::buildEvidenceUrl($item->backgroundSlug);
            }
        }

        $dataArray = array_map(fn($it) => $it->toArray(), $items);
        return Paginate::response($dataArray, $res['total'], $page, $pageSize);
    }

    public function update(int $authId, int $gymId, array $data): void
    {
        $this->verifyOwnership($authId, $gymId);

        $updateData = [];
        if (!empty($data['name'])) {
            $updateData['name'] = trim((string) $data['name']);
        }
        if (!empty($data['address'])) {
            $updateData['address'] = trim((string) $data['address']);
        }
        if (!empty($data['phone'])) {
            $updateData['phone'] = trim((string) $data['phone']);
        }
        if (!empty($data['email'])) {
            $updateData['email'] = Validator::validateEmail((string) $data['email']);
        }
        if (isset($data['latitude'])) {
            $updateData['latitude'] = (float) $data['latitude'];
        }
        if (isset($data['longitude'])) {
            $updateData['longitude'] = (float) $data['longitude'];
        }

        if (empty($updateData)) {
            return;
        }

        $this->repo()->update($gymId, $updateData);
        $this->invalidateCache($gymId);
    }

    public function setSchedules(int $authId, int $gymId, array $schedules): void
    {
        $this->verifyOwnership($authId, $gymId);

        $this->repo()->withTransaction(function (GymRepository $txRepo) use ($gymId, $schedules) {
            foreach ($schedules as $s) {
                if (isset($s['day_of_week'])) {
                    $txRepo->upsertSchedule(
                        gymId: $gymId,
                        dayOfWeek: (int) $s['day_of_week'],
                        openMinutes: (int) ($s['open_minutes'] ?? 480),
                        closeMinutes: (int) ($s['close_minutes'] ?? 1320),
                        isClosed: (bool) ($s['is_closed'] ?? false)
                    );
                }
            }
        });

        $this->invalidateCache($gymId);
    }

    public function updateSchedule(int $authId, int $gymId, int $scheduleId, array $data): void
    {
        $this->verifyOwnership($authId, $gymId);

        $updateData = [];
        if (isset($data['open_minutes'])) {
            $updateData['open_minutes'] = (int) $data['open_minutes'];
        }
        if (isset($data['close_minutes'])) {
            $updateData['close_minutes'] = (int) $data['close_minutes'];
        }
        if (isset($data['is_closed'])) {
            $updateData['is_closed'] = (bool) $data['is_closed'];
        }

        $this->repo()->updateSchedule($gymId, $scheduleId, $updateData);
        $this->invalidateCache($gymId);
    }

    public function uploadCover(int $authId, int $gymId, string $slug, string $path, string $mimeType, int $size): string
    {
        $this->verifyOwnership($authId, $gymId);

        $file = new FileModel(
            id: 0,
            slug: $slug,
            path: $path,
            mimeType: $mimeType,
            size: $size,
            authorId: $authId
        );

        $this->repo()->withTransaction(function (GymRepository $txRepo) use ($gymId, $file) {
            $fileId = $txRepo->createFile($file);
            $txRepo->updateCover($gymId, $fileId);
        });

        $this->invalidateCache($gymId);
        return \App\Shared\Utils\UrlHelper::buildEvidenceUrl($slug) ?? '';
    }

    /**
     * Sube una o varias fotos a la galería del gimnasio.
     *
     * @param array<array{slug: string, path: string, mime_type: string, size: int}> $photos
     */
    public function uploadPhotos(int $authId, int $gymId, array $photos): int
    {
        $this->verifyOwnership($authId, $gymId);

        $this->repo()->withTransaction(function (GymRepository $txRepo) use ($authId, $gymId, $photos) {
            foreach ($photos as $p) {
                $file = new FileModel(
                    id: 0,
                    slug: $p['slug'],
                    path: $p['path'],
                    mimeType: $p['mime_type'],
                    size: $p['size'],
                    authorId: $authId
                );
                $fileId = $txRepo->createFile($file);

                $gymPhoto = new GymPhoto(
                    id: 0,
                    fileId: $fileId,
                    gymId: $gymId
                );
                $txRepo->createPhoto($gymPhoto);
            }
        });

        $this->invalidateCache($gymId);
        return count($photos);
    }

    public function deletePhoto(int $authId, int $gymId, int $photoId): void
    {
        $this->verifyOwnership($authId, $gymId);
        $this->repo()->deletePhoto($gymId, $photoId);
        $this->invalidateCache($gymId);
    }

    private function verifyOwnership(int $authId, int $gymId): void
    {
        if (!$this->repo()->isGymOwner($authId, $gymId)) {
            throw new ForbiddenException('No tienes permiso para administrar este gimnasio');
        }
    }

    private function formatDetail(GymDetailResponse $detail): GymDetailResponse
    {
        if ($detail->backgroundSlug) {
            $detail->backgroundUrl = \App\Shared\Utils\UrlHelper::buildEvidenceUrl($detail->backgroundSlug);
        }

        foreach ($detail->photos as &$photo) {
            if (!empty($photo['slug'])) {
                $photo['url'] = \App\Shared\Utils\UrlHelper::buildEvidenceUrl($photo['slug']);
            }
        }

        $detail->isOpen = self::calculateIsOpen($detail->schedules);
        return $detail;
    }

    private function invalidateCache(int $gymId): void
    {
        try {
            $this->redis()->del([self::CACHE_GYM_PREFIX . $gymId]);
        } catch (Throwable) {
        }
    }
}

