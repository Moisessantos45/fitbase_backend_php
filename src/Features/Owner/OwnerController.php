<?php

declare(strict_types=1);

namespace App\Features\Owner;

use App\Controllers\BaseController;
use App\Shared\Errors\AppException;
use App\Shared\Errors\ValidationException;
use App\Shared\Helpers\ErrorHandler;
use App\Shared\Utils\Response;
use App\Shared\Utils\Validator;
use Flight;
use Throwable;

class OwnerController extends BaseController
{
    private ?OwnerService $service = null;

    public function __construct(?OwnerService $service = null)
    {
        $this->service = $service;
    }

    private function service(): OwnerService
    {
        return $this->service ??= new OwnerService();
    }

    public function completeProfile(): void
    {
        try {
            $authId = (int) Flight::get('userId');
            $data = $this->getRequestBody();
            Validator::validateRequired($data, ['first_name', 'last_name', 'phone']);

            $profile = $this->service()->completeProfile(
                authId: $authId,
                firstName: (string) $data['first_name'],
                lastName: (string) $data['last_name'],
                phone: (string) $data['phone']
            );

            Response::json($profile->toArray(), 'Perfil de dueño completado exitosamente', 201);
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function getProfile(): void
    {
        try {
            $authId = (int) Flight::get('userId');
            $profile = $this->service()->getProfile($authId);

            Response::json($profile->toArray(), 'Perfil de dueño obtenido con éxito');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function updateProfile(): void
    {
        try {
            $authId = (int) Flight::get('userId');
            $data = $this->getRequestBody();

            $this->service()->update(
                authId: $authId,
                firstName: isset($data['first_name']) ? (string) $data['first_name'] : null,
                lastName: isset($data['last_name']) ? (string) $data['last_name'] : null,
                phone: isset($data['phone']) ? (string) $data['phone'] : null
            );

            Response::json(null, 'Perfil de dueño actualizado exitosamente');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function uploadAvatar(): void
    {
        try {
            $authId = (int) (Flight::get('auth_id') ?: Flight::get('userId') ?: 0);
            if ($authId <= 0) {
                throw new \App\Shared\Errors\UnauthorizedException('No autorizado');
            }

            $fileInfo = \App\Shared\Utils\FileUtils::extractImage(['image', 'avatar', 'file']);
            $saved = \App\Shared\Utils\FileUtils::saveFile('owners/avatars', $fileInfo);

            $this->service()->uploadAvatar($authId, $saved['slug'], $saved['path'], $saved['mime_type'], $saved['size']);

            Response::json([
                'slug' => $saved['slug'],
                'size' => $saved['size'],
            ], 'Avatar de dueño actualizado con éxito');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }
}

