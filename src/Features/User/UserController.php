<?php

declare(strict_types=1);

namespace App\Features\User;

use App\Controllers\BaseController;
use App\Shared\Errors\AppException;
use App\Shared\Errors\ValidationException;
use App\Shared\Helpers\ErrorHandler;
use App\Shared\Utils\Response;
use App\Shared\Utils\Validator;
use Flight;
use Throwable;

class UserController extends BaseController
{
    private ?UserService $service = null;

    public function __construct(?UserService $service = null)
    {
        $this->service = $service;
    }

    private function service(): UserService
    {
        return $this->service ??= new UserService();
    }

    public function getMe(): void
    {
        try {
            $authId = (int) Flight::get('userId');
            $detail = $this->service()->getByAuthId($authId);

            if ($detail === null) {
                Response::error('Perfil no encontrado', 404, 'USER_NOT_FOUND');
                return;
            }

            Response::json($detail->toArray(), 'Perfil de usuario obtenido con éxito');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function completeProfile(): void
    {
        try {
            $authId = (int) Flight::get('userId');
            $data = $this->getRequestBody();
            Validator::validateRequired($data, ['first_name', 'last_name', 'phone']);

            $user = $this->service()->completeProfile(
                authId: $authId,
                firstName: (string) $data['first_name'],
                lastName: (string) $data['last_name'],
                phone: (string) $data['phone']
            );

            Response::json($user->toArray(), 'Perfil completado exitosamente', 201);
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function update(): void
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

            Response::json(null, 'Perfil actualizado exitosamente');
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
            $saved = \App\Shared\Utils\FileUtils::saveFile('users/avatars', $fileInfo);

            $this->service()->uploadAvatar($authId, $saved['slug'], $saved['path'], $saved['mime_type'], $saved['size']);

            Response::json([
                'slug' => $saved['slug'],
                'size' => $saved['size'],
            ], 'Avatar actualizado con éxito');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }
}

