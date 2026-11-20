<?php

declare(strict_types=1);

namespace App\Features\Gym;

use App\Controllers\BaseController;
use App\Shared\Errors\AppException;
use App\Shared\Errors\ValidationException;
use App\Shared\Helpers\ErrorHandler;
use App\Shared\Utils\Response;
use App\Shared\Utils\Validator;
use Flight;
use Throwable;

class GymController extends BaseController
{
    private ?GymService $service = null;

    public function __construct(?GymService $service = null)
    {
        $this->service = $service;
    }

    private function service(): GymService
    {
        return $this->service ??= new GymService();
    }

    public function getAllPublic(): void
    {
        try {
            $query = (string) (Flight::request()->query->search ?? '');
            $lat = (float) (Flight::request()->query->lat ?? 0.0);
            $lng = (float) (Flight::request()->query->lng ?? 0.0);
            $page = (int) (Flight::request()->query->page ?? 1);
            $pageSize = (int) (Flight::request()->query->page_size ?? 10);

            $result = $this->service()->getAllPublic($query, $lat, $lng, $page, $pageSize);

            Response::json($result, 'Gimnasios obtenidos con éxito');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function getDetailsById(string|int $id): void
    {
        try {
            $gymId = (int) $id;
            $detail = $this->service()->getDetailsById($gymId);

            Response::json($detail->toArray(), 'Detalle del gimnasio obtenido con éxito');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function getMyGymDetails(): void
    {
        try {
            $authId = (int) Flight::get('userId');
            $detail = $this->service()->getMyGymDetails($authId);

            Response::json($detail->toArray(), 'Tu gimnasio obtenido con éxito');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function getAllByOwner(): void
    {
        try {
            $authId = (int) Flight::get('userId');
            $page = (int) (Flight::request()->query->page ?? 1);
            $pageSize = (int) (Flight::request()->query->page_size ?? 10);

            $result = $this->service()->getAllByOwner($authId, $page, $pageSize);

            Response::json($result, 'Gimnasios del dueño obtenidos con éxito');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function create(): void
    {
        try {
            $authId = (int) Flight::get('userId');
            $data = $this->getRequestBody();

            Validator::validateRequired($data, ['name', 'address', 'phone', 'email', 'latitude', 'longitude']);

            $detail = $this->service()->create(
                authId: $authId,
                name: (string) $data['name'],
                address: (string) $data['address'],
                phone: (string) $data['phone'],
                email: (string) $data['email'],
                latitude: (float) $data['latitude'],
                longitude: (float) $data['longitude'],
                schedules: (array) ($data['schedules'] ?? [])
            );

            Response::json($detail->toArray(), 'Gimnasio creado exitosamente', 201);
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function update(string|int $id): void
    {
        try {
            $authId = (int) Flight::get('userId');
            $gymId = (int) $id;
            $data = $this->getRequestBody();

            $this->service()->update($authId, $gymId, $data);

            Response::json(null, 'Gimnasio actualizado exitosamente');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function setSchedules(string|int $id): void
    {
        try {
            $authId = (int) Flight::get('userId');
            $gymId = (int) $id;
            $data = $this->getRequestBody();

            $schedules = (array) ($data['schedules'] ?? $data);
            $this->service()->setSchedules($authId, $gymId, $schedules);

            Response::json(null, 'Horarios actualizados exitosamente');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function updateSchedule(string|int $id, string|int $scheduleId): void
    {
        try {
            $authId = (int) Flight::get('userId');
            $gymId = (int) $id;
            $schedId = (int) $scheduleId;
            $data = $this->getRequestBody();

            $this->service()->updateSchedule($authId, $gymId, $schedId, $data);

            Response::json(null, 'Horario actualizado exitosamente');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function uploadCover(string|int $id): void
    {
        try {
            $authId = (int) (Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
            if ($authId <= 0) {
                throw new \App\Shared\Errors\UnauthorizedException('No autorizado');
            }
            $gymId = (int) $id;

            $fileInfo = \App\Shared\Utils\FileUtils::extractImage(['image', 'cover', 'background', 'file']);
            $saved = \App\Shared\Utils\FileUtils::saveFile('gyms/covers', $fileInfo);

            $url = $this->service()->uploadCover($authId, $gymId, $saved['slug'], $saved['path'], $saved['mime_type'], $saved['size']);

            Response::json([
                'slug' => $saved['slug'],
                'url'  => $url,
            ], 'Portada actualizada exitosamente');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function uploadPhotos(string|int $id): void
    {
        try {
            $authId = (int) (Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
            if ($authId <= 0) {
                throw new \App\Shared\Errors\UnauthorizedException('No autorizado');
            }
            $gymId = (int) $id;

            $extracted = \App\Shared\Utils\FileUtils::extractImages(['photos', 'images', 'files']);
            if (empty($extracted)) {
                try {
                    $single = \App\Shared\Utils\FileUtils::extractImage(['image', 'photo', 'file']);
                    $extracted = [$single];
                } catch (\Throwable) {
                    throw new ValidationException('No se enviaron archivos de fotos válidos', ['photos' => 'Archivos requeridos']);
                }
            }

            $uploadedList = [];
            foreach ($extracted as $item) {
                $saved = \App\Shared\Utils\FileUtils::saveFile('gyms/photos', $item);
                $uploadedList[] = $saved;
            }

            $count = $this->service()->uploadPhotos($authId, $gymId, $uploadedList);

            Response::json([
                'count' => $count,
                'photos' => array_map(fn($f) => [
                    'slug' => $f['slug'],
                    'url' => \App\Shared\Utils\UrlHelper::buildEvidenceUrl($f['slug']),
                ], $uploadedList),
            ], "Se subieron {$count} fotos a la galería");
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function deletePhoto(string|int $id, string|int $photoId): void
    {
        try {
            $authId = (int) Flight::get('userId');
            $gymId = (int) $id;
            $pId = (int) $photoId;

            $this->service()->deletePhoto($authId, $gymId, $pId);

            Response::json(null, 'Foto eliminada de la galería exitosamente');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }
}

