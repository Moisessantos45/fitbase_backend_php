<?php

declare(strict_types=1);

namespace App\Features\Auth;

use App\Controllers\BaseController;
use App\Shared\Helpers\ErrorHandler;
use App\Shared\Utils\Response;
use App\Shared\Utils\Validator;
use Flight;
use Throwable;

class AuthController extends BaseController
{
    private AuthService $service;

    public function __construct(?AuthService $service = null)
    {
        $this->service = $service ?? new AuthService();
    }

    public function register(): void
    {
        try {
            $data = $this->getRequestBody();
            Validator::validateRequired($data, ['email', 'password']);

            $this->service->register((string) $data['email'], (string) $data['password']);

            Response::json(
                data: null,
                message: 'Usuario registrado exitosamente. Por favor verifica tu correo para activar tu cuenta.',
                status: 201
            );
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function registerOwner(): void
    {
        try {
            $data = $this->getRequestBody();
            Validator::validateRequired($data, ['email', 'password']);

            $this->service->registerOwner((string) $data['email'], (string) $data['password']);

            Response::json(
                data: null,
                message: 'Dueño registrado exitosamente. Por favor verifica tu correo para activar tu cuenta.',
                status: 201
            );
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function login(): void
    {
        try {
            $data = $this->getRequestBody();
            Validator::validateRequired($data, ['email', 'password']);

            $result = $this->service->login((string) $data['email'], (string) $data['password'], 'web');

            if ($result['is_2fa']) {
                $this->setHttpCookie('pre_auth_session', $result['token_or_session'], 300);
                Response::json(
                    data: ['session_id' => $result['token_or_session']],
                    message: 'Se requiere verificación de dos factores',
                    status: 202
                );
                return;
            }

            $this->setHttpCookie('refresh_token', $result['token_or_session'], 28800);

            Response::json(
                data: $result['user']?->toPublicArray(),
                message: 'Login exitoso',
                status: 200
            );
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function loginMobile(): void
    {
        try {
            $data = $this->getRequestBody();
            Validator::validateRequired($data, ['email', 'password']);

            $result = $this->service->login((string) $data['email'], (string) $data['password'], 'mobile');

            if ($result['is_2fa']) {
                Response::json(
                    data: ['session_id' => $result['token_or_session']],
                    message: 'Se requiere verificación de dos factores',
                    status: 202
                );
                return;
            }

            $user = $result['user'];
            Response::json(
                data: [
                    'user'          => $user?->toPublicArray(),
                    'token'         => $user?->token,
                    'access_token'  => $user?->token,
                    'refresh_token' => $result['token_or_session'],
                ],
                message: 'Login exitoso',
                status: 200
            );
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function logout(): void
    {
        try {
            $token = $this->extractToken();
            $this->service->logout($token);

            $this->clearHttpCookie('refresh_token');
            $this->clearHttpCookie('pre_auth_session');

            Response::json(null, 'Sesión cerrada exitosamente');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function refreshToken(): void
    {
        try {
            $token = Flight::request()->cookies['refresh_token']
                ?? $_COOKIE['refresh_token']
                ?? null;

            if (!$token) {
                $cookieHeader = Flight::request()->getHeader('Cookie') ?: '';
                if (preg_match('/refresh_token=([^;]+)/', $cookieHeader, $matches)) {
                    $token = urldecode(trim($matches[1]));
                }
            }

            if (!$token) {
                $data = $this->getRequestBody();
                $token = $data['refresh_token'] ?? null;
            }

            if (empty($token)) {
                Response::error('No refresh token provided', 400, 'UNAUTHORIZED');
                return;
            }

            $newAccessToken = $this->service->refreshToken((string) $token, 'web');

            Response::json([
                'token'        => $newAccessToken,
                'access_token' => $newAccessToken,
            ], 'Token refreshed successfully');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function refreshTokenMobile(): void
    {
        try {
            $authHeader = Flight::request()->getHeader('Authorization') ?: '';
            $token = '';
            if (str_starts_with($authHeader, 'Bearer ')) {
                $token = trim(substr($authHeader, 7));
            } else {
                $data = $this->getRequestBody();
                $token = $data['refresh_token'] ?? '';
            }

            if (empty($token)) {
                Response::error('No refresh token provided', 400, 'UNAUTHORIZED');
                return;
            }

            $tokens = $this->service->refreshTokenMobile((string) $token);

            Response::json([
                'token'         => $tokens['access_token'],
                'access_token'  => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
            ], 'Token refreshed successfully');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function confirmAccount(): void
    {
        try {
            $userId = (int) Flight::get('userId');
            $token = Flight::request()->query->token ?? Flight::get('token') ?? '';

            $this->service->confirmAccount($userId, (string) $token);

            Response::json(null, 'Cuenta confirmada exitosamente');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function forwardEmailVerification(): void
    {
        try {
            $data = $this->getRequestBody();
            Validator::validateRequired($data, ['email']);

            $this->service->forwardEmailVerification((string) $data['email']);

            Response::json(null, 'Correo de verificación reenviado exitosamente');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function sendPasswordReset(): void
    {
        try {
            $data = $this->getRequestBody();
            Validator::validateRequired($data, ['email']);

            $this->service->sendPasswordReset((string) $data['email']);

            Response::json(null, 'Si el correo existe en nuestro sistema, recibirás un enlace de restablecimiento');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function resetPassword(): void
    {
        try {
            $userId = (int) Flight::get('userId');
            $data = $this->getRequestBody();
            Validator::validateRequired($data, ['new_password']);

            $this->service->resetPassword($userId, (string) $data['new_password']);

            Response::json(null, 'Contraseña restablecida exitosamente');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function updatePassword(): void
    {
        try {
            $userId = (int) Flight::get('userId');
            $data = $this->getRequestBody();
            Validator::validateRequired($data, ['current_password', 'new_password']);

            $this->service->updatePassword($userId, (string) $data['current_password'], (string) $data['new_password']);

            Response::json(null, 'Contraseña actualizada exitosamente');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    public function getSession(): void
    {
        try {
            $userId = (int) Flight::get('userId');
            $user = $this->service->getSession($userId);
            $user->token = Flight::get('token') ?: $user->token;

            Response::json($user->toPublicArray(), 'Sesión obtenida correctamente');
        } catch (Throwable $e) {
            ErrorHandler::handle($e);
        }
    }

    private function extractToken(): string
    {
        $authHeader = Flight::request()->getHeader('Authorization') ?: '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            return trim(substr($authHeader, 7));
        }

        $cookieToken = Flight::request()->cookies['refresh_token']
            ?? $_COOKIE['refresh_token']
            ?? null;

        if (!$cookieToken) {
            $cookieHeader = Flight::request()->getHeader('Cookie') ?: '';
            if (preg_match('/refresh_token=([^;]+)/', $cookieHeader, $matches)) {
                $cookieToken = urldecode(trim($matches[1]));
            }
        }

        return (string) ($cookieToken ?? '');
    }

    private function setHttpCookie(string $name, string $value, int $expireSeconds): void
    {
        $isSecure = ($_ENV['GO_ENV'] ?? $_SERVER['GO_ENV'] ?? '') === 'prod';
        $domain = $_ENV['COOKIE_DOMAIN'] ?? $_SERVER['COOKIE_DOMAIN'] ?? null;

        $options = [
            'expires'  => time() + $expireSeconds,
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        if (!empty($domain)) {
            $options['domain'] = $domain;
        }

        setcookie($name, $value, $options);
    }

    private function clearHttpCookie(string $name): void
    {
        $domain = $_ENV['COOKIE_DOMAIN'] ?? $_SERVER['COOKIE_DOMAIN'] ?? null;

        $options = [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        if (!empty($domain)) {
            $options['domain'] = $domain;
        }

        setcookie($name, '', $options);
    }
}

