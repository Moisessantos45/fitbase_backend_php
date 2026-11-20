<?php

declare(strict_types=1);

namespace App\Features\Gym;

use App\Config\Database;
use App\Models\FileModel;
use App\Models\Gym;
use App\Models\GymPhoto;
use App\Models\GymSchedule;
use App\Shared\Errors\NotFoundException;
use App\Shared\Helpers\ErrorHandler;
use PDO;
use PDOException;
use Throwable;

class GymRepository
{
    public function __construct(private ?PDO $db = null)
    {
    }

    private function db(): PDO
    {
        return $this->db ??= Database::getConnection();
    }

    public function withTransaction(callable $fn): mixed
    {
        $pdo = $this->db();
        if ($pdo->inTransaction()) {
            return $fn($this);
        }

        $pdo->beginTransaction();
        try {
            $result = $fn($this);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function getOwnerIdByAuthId(int $authId): ?int
    {
        $sql = "SELECT id FROM owners WHERE auth_id = :auth_id LIMIT 1";
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([':auth_id' => $authId]);
            $val = $stmt->fetchColumn();
            return $val !== false ? (int) $val : null;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function isGymOwner(int $authId, int $gymId): bool
    {
        $sql = "SELECT COUNT(1) FROM gyms g
                INNER JOIN owners o ON o.id = g.owner_id
                WHERE g.id = :gym_id AND o.auth_id = :auth_id";
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([':gym_id' => $gymId, ':auth_id' => $authId]);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function create(Gym $gym): int
    {
        $sql = "INSERT INTO gyms (owner_id, background_id, name, address, phone, email, latitude, longitude, created_at, updated_at)
                VALUES (:owner_id, :background_id, :name, :address, :phone, :email, :latitude, :longitude, NOW(), NOW())
                RETURNING id";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                ':owner_id'       => $gym->ownerId,
                ':background_id'  => $gym->backgroundId,
                ':name'           => $gym->name,
                ':address'        => $gym->address,
                ':phone'          => $gym->phone,
                ':email'          => $gym->email,
                ':latitude'       => $gym->latitude,
                ':longitude'      => $gym->longitude,
            ]);

            $id = (int) $stmt->fetchColumn();
            $gym->id = $id;
            return $id;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function createSchedule(GymSchedule $schedule): int
    {
        $sql = "INSERT INTO gym_schedules (gym_id, day_of_week, open_minutes, close_minutes, is_closed, created_at, updated_at)
                VALUES (:gym_id, :day_of_week, :open_minutes, :close_minutes, :is_closed, NOW(), NOW())
                RETURNING id";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                ':gym_id'        => $schedule->gymId,
                ':day_of_week'   => $schedule->dayOfWeek,
                ':open_minutes'  => $schedule->openMinutes,
                ':close_minutes' => $schedule->closeMinutes,
                ':is_closed'     => $schedule->isClosed ? 'true' : 'false',
            ]);

            $id = (int) $stmt->fetchColumn();
            $schedule->id = $id;
            return $id;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function createFile(FileModel $file): int
    {
        $sql = "INSERT INTO files (slug, path, mime_type, size, author_id, created_at, updated_at)
                VALUES (:slug, :path, :mime_type, :size, :author_id, NOW(), NOW())
                RETURNING id";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                ':slug'      => $file->slug,
                ':path'      => $file->path,
                ':mime_type' => $file->mimeType,
                ':size'      => $file->size,
                ':author_id' => $file->authorId,
            ]);

            $id = (int) $stmt->fetchColumn();
            $file->id = $id;
            return $id;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function createPhoto(GymPhoto $photo): int
    {
        $sql = "INSERT INTO gym_photos (file_id, gym_id, created_at, updated_at)
                VALUES (:file_id, :gym_id, NOW(), NOW())
                RETURNING id";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                ':file_id' => $photo->fileId,
                ':gym_id'  => $photo->gymId,
            ]);

            $id = (int) $stmt->fetchColumn();
            $photo->id = $id;
            return $id;
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function updateCover(int $gymId, int $fileId): void
    {
        $sql = "UPDATE gyms SET background_id = :background_id, updated_at = NOW() WHERE id = :id";
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                ':background_id' => $fileId,
                ':id'            => $gymId,
            ]);
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function update(int $gymId, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $fields = [];
        $params = [':id' => $gymId];

        foreach ($data as $col => $val) {
            $fields[] = "{$col} = :{$col}";
            $params[":{$col}"] = $val;
        }
        $fields[] = "updated_at = NOW()";

        $setClause = implode(', ', $fields);
        $sql = "UPDATE gyms SET {$setClause} WHERE id = :id";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                throw new NotFoundException('Gimnasio no encontrado');
            }
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function updateSchedule(int $gymId, int $scheduleId, array $data): void
    {
        if (empty($data)) {
            return;
        }

        $fields = [];
        $params = [':id' => $scheduleId, ':gym_id' => $gymId];

        foreach ($data as $col => $val) {
            $fields[] = "{$col} = :{$col}";
            $params[":{$col}"] = is_bool($val) ? ($val ? 'true' : 'false') : $val;
        }
        $fields[] = "updated_at = NOW()";

        $setClause = implode(', ', $fields);
        $sql = "UPDATE gym_schedules SET {$setClause} WHERE id = :id AND gym_id = :gym_id";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                throw new NotFoundException('Horario no encontrado para este gimnasio');
            }
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function upsertSchedule(int $gymId, int $dayOfWeek, int $openMinutes, int $closeMinutes, bool $isClosed): void
    {
        $sql = "INSERT INTO gym_schedules (gym_id, day_of_week, open_minutes, close_minutes, is_closed, created_at, updated_at)
                VALUES (:gym_id, :day_of_week, :open_minutes, :close_minutes, :is_closed, NOW(), NOW())
                ON CONFLICT (gym_id, day_of_week) 
                DO UPDATE SET 
                    open_minutes = EXCLUDED.open_minutes,
                    close_minutes = EXCLUDED.close_minutes,
                    is_closed = EXCLUDED.is_closed,
                    updated_at = NOW()";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                ':gym_id'        => $gymId,
                ':day_of_week'   => $dayOfWeek,
                ':open_minutes'  => $openMinutes,
                ':close_minutes' => $closeMinutes,
                ':is_closed'     => $isClosed ? 'true' : 'false',
            ]);
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function deletePhoto(int $gymId, int $photoId): void
    {
        $sql = "DELETE FROM gym_photos WHERE id = :photo_id AND gym_id = :gym_id";
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([':photo_id' => $photoId, ':gym_id' => $gymId]);

            if ($stmt->rowCount() === 0) {
                throw new NotFoundException('Foto no encontrada en este gimnasio');
            }
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function getDetailsById(int $gymId): ?GymDetailResponse
    {
        return $this->getDetailsWhere("gyms.id = :id", [':id' => $gymId]);
    }

    public function getDetailsByAuthId(int $authId): ?GymDetailResponse
    {
        $sqlWhere = "gyms.id = COALESCE(
            (SELECT gm.gym_id FROM gym_managers gm WHERE gm.auth_id = :auth_id LIMIT 1),
            (SELECT g2.id FROM gyms g2 INNER JOIN owners o ON o.id = g2.owner_id WHERE o.auth_id = :auth_id ORDER BY g2.id ASC LIMIT 1)
        )";

        return $this->getDetailsWhere($sqlWhere, [':auth_id' => $authId]);
    }

    private function getDetailsWhere(string $whereClause, array $params): ?GymDetailResponse
    {
        $sql = "SELECT 
                    gyms.id,
                    gyms.name,
                    gyms.address,
                    gyms.phone,
                    gyms.email,
                    gyms.latitude,
                    gyms.longitude,
                    gyms.created_at,
                    gyms.updated_at,
                    COALESCE(f_bg.slug, '') AS background_slug,
                    COALESCE(
                        (
                            SELECT json_agg(
                                json_build_object(
                                    'id', gs.id,
                                    'gym_id', gs.gym_id,
                                    'day_of_week', gs.day_of_week,
                                    'open_minutes', gs.open_minutes,
                                    'close_minutes', gs.close_minutes,
                                    'is_closed', gs.is_closed
                                ) ORDER BY gs.day_of_week ASC
                            )
                            FROM gym_schedules gs
                            WHERE gs.gym_id = gyms.id
                        ),
                        '[]'::json
                    ) AS schedules_json,
                    COALESCE(
                        (
                            SELECT json_agg(
                                json_build_object(
                                    'id', gp.id,
                                    'slug', f.slug
                                ) ORDER BY gp.id ASC
                            )
                            FROM gym_photos gp
                            INNER JOIN files f ON f.id = gp.file_id
                            WHERE gp.gym_id = gyms.id
                        ),
                        '[]'::json
                    ) AS photos_json
                FROM gyms
                LEFT JOIN files f_bg ON f_bg.id = gyms.background_id
                WHERE {$whereClause}
                LIMIT 1";

        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            $schedules = json_decode((string) ($row['schedules_json'] ?? '[]'), true) ?: [];
            $photos = json_decode((string) ($row['photos_json'] ?? '[]'), true) ?: [];

            return new GymDetailResponse(
                id: (int) $row['id'],
                name: (string) $row['name'],
                address: (string) $row['address'],
                phone: (string) $row['phone'],
                email: (string) $row['email'],
                latitude: (float) $row['latitude'],
                longitude: (float) $row['longitude'],
                backgroundSlug: $row['background_slug'] ?: null,
                createdAt: $row['created_at'] ?? null,
                updatedAt: $row['updated_at'] ?? null,
                schedules: $schedules,
                photos: $photos
            );
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function getAllByOwnerId(int $ownerId, int $offset, int $limit): array
    {
        try {
            $countStmt = $this->db()->prepare("SELECT COUNT(1) FROM gyms WHERE owner_id = :owner_id");
            $countStmt->execute([':owner_id' => $ownerId]);
            $total = (int) $countStmt->fetchColumn();

            $sql = "SELECT 
                        g.id,
                        g.name,
                        g.address,
                        g.phone,
                        g.email,
                        g.latitude,
                        g.longitude,
                        COALESCE(f_bg.slug, '') AS background_slug
                    FROM gyms g
                    LEFT JOIN files f_bg ON f_bg.id = g.background_id
                    WHERE g.owner_id = :owner_id
                    ORDER BY g.created_at DESC
                    OFFSET :offset LIMIT :limit";

            $stmt = $this->db()->prepare($sql);
            $stmt->bindValue(':owner_id', $ownerId, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $summaries = array_map(fn($r) => GymPublicSummary::fromArray($r), $rows);

            return ['data' => $summaries, 'total' => $total];
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }

    public function getAllPublic(string $query, float $lat, float $lng, int $offset, int $limit): array
    {
        try {
            $whereParts = [];
            $params = [];

            if ($query !== '') {
                $whereParts[] = "(g.name ILIKE :q OR g.address ILIKE :q)";
                $params[':q'] = "%{$query}%";
            }

            $whereSql = !empty($whereParts) ? "WHERE " . implode(' AND ', $whereParts) : "";

            $countSql = "SELECT COUNT(1) FROM gyms g {$whereSql}";
            $countStmt = $this->db()->prepare($countSql);
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $distanceCol = "NULL AS distance_meters";
            $orderBy = "g.created_at DESC";

            if ($lat !== 0.0 || $lng !== 0.0) {
                $distanceCol = "ROUND(
                    (6371000 * acos(
                        cos(radians(:user_lat)) * cos(radians(g.latitude)) *
                        cos(radians(g.longitude) - radians(:user_lng)) +
                        sin(radians(:user_lat)) * sin(radians(g.latitude))
                    ))::numeric, 2
                ) AS distance_meters";

                $orderBy = "distance_meters ASC";
                $params[':user_lat'] = $lat;
                $params[':user_lng'] = $lng;
            }

            $sql = "SELECT 
                        g.id,
                        g.name,
                        g.address,
                        g.phone,
                        g.email,
                        g.latitude,
                        g.longitude,
                        COALESCE(f_bg.slug, '') AS background_slug,
                        {$distanceCol}
                    FROM gyms g
                    LEFT JOIN files f_bg ON f_bg.id = g.background_id
                    {$whereSql}
                    ORDER BY {$orderBy}
                    OFFSET :offset LIMIT :limit";

            $stmt = $this->db()->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $summaries = array_map(fn($r) => GymPublicSummary::fromArray($r), $rows);

            return ['data' => $summaries, 'total' => $total];
        } catch (PDOException $e) {
            throw ErrorHandler::translateDBError($e);
        }
    }
}

