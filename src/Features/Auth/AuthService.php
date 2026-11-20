<?php

declare(strict_types=1);

namespace App\Features\Auth;

use App\Config\Redis;
use App\Models\Auth;
use App\Models\Constants;
use App\Shared\Errors\AppException;
use App\Shared\Errors\ConflictException;
use App\Shared\Errors\NotFoundException;
use App\Shared\Errors\UnauthorizedException;
use App\Shared\Errors\ValidationException;
use App\Shared\Services\MailerService;
use App\Shared\Utils\PasetoMaker;
use App\Shared\Utils\Password;
use App\Shared\Utils\Validator;
use Predis\Client as RedisClient;
use Throwable;

class AuthService
{
    private ?AuthRepository $repo = null;
    private ?RedisClient $redis = null;
    private ?PasetoMaker $paseto = null;
    private ?MailerService $mailer = null;
    private string $frontendUrl;

    public function __construct(
        ?AuthRepository $repo = null,
        ?RedisClient $redis = null,
        ?PasetoMaker $paseto = null,
        ?MailerService $mailer = null,
        ?string $frontendUrl = null
    ) {
        $this->repo = $repo;
        $this->redis = $redis;
        $this->paseto = $paseto;
        $this->mailer = $mailer;
        $this->frontendUrl = $frontendUrl ?? ($_ENV['HOST_URL_PROD'] ?? $_SERVER['HOST_URL_PROD'] ?? 'http://localhost:5173');
    }

    private function repo(): AuthRepository
    {
        return $this->repo ??= new AuthRepository();
    }

    private function redis(): RedisClient
    {
        return $this->redis ??= Redis::getClient();
    }

    private function paseto(): PasetoMaker
    {
        return $this->paseto ??= new PasetoMaker();
    }

    private function mailer(): MailerService
    {
        return $this->mailer ??= new MailerService();
    }

    public function register(string $email, string $password): Auth
    {
        return $this->registerUserWithRole($email, $password, Constants::ROLE_USER_ID);
    }

    public function registerOwner(string $email, string $password): Auth
    {
        return $this->registerUserWithRole($email, $password, Constants::ROLE_OWNER_ID);
    }

    private function registerUserWithRole(string $email, string $password, int $roleId): Auth
    {
        $cleanEmail = Validator::validateEmail($email);

        if (strlen($password) < 6 || strlen($password) > 24) {
            throw new ValidationException('Contraseña inválida', [
                'password' => 'La contraseña debe tener entre 6 y 24 caracteres'
            ]);
        }

        if ($this->repo()->existsByEmail($cleanEmail)) {
            throw new ConflictException('El correo electrónico ya se encuentra registrado');
        }

        $hashedPassword = Password::hash($password);

        $auth = new Auth(
            id: 0,
            roleId: $roleId,
            email: $cleanEmail,
            passwordHash: $hashedPassword,
            fullProfile: false,
            emailConfirmed: false,
            active: true
        );

        $this->repo()->withTransaction(function (AuthRepository $txRepo) use ($auth) {
            $txRepo->register($auth);

            $token = $this->paseto()->newToken($auth->id, 900, 'confirm_account');
            $confirmKey = "confirm_account:{$auth->id}";

            try {
                $this->redis()->setex($confirmKey, 900, $token);
            } catch (Throwable $e) {
                error_log('[Redis Error] Error caching confirmation token: ' . $e->getMessage());
            }

            $confirmLink = "{$this->frontendUrl}/confirm-account/{$token}";
            $html = $this->mailer()->renderTemplate('confirm_account.html', [
                'Name'        => $auth->email,
                'ConfirmLink' => $confirmLink,
            ]);

            $this->mailer()->send($auth->email, 'Confirmación de cuenta Fitbase', $html);
        });

        return $auth;
    }

    /**
     * Inicia sesión validando credenciales y creando sesión distribuida en Redis.
     *
     * @return array{user: ?Auth, token_or_session: string, is_2fa: bool}
     */
    public function login(string $email, string $password, string $platform = 'web'): array
    {
        $cleanEmail = trim($email);
        if ($cleanEmail === '' || strlen($password) < 6) {
            throw new UnauthorizedException('Correo o contraseña incorrectos');
        }

        $check = $this->repo()->getActiveUserByEmail($cleanEmail);
        $user = $check['user'];
        $status = $check['status'];

        if ($status === 'account_inactive') {
            Password::burnPasswordCheck($password);
            throw new UnauthorizedException('Correo o contraseña incorrectos');
        }

        if ($status === 'email_not_verified') {
            throw new AppException('Correo no verificado', 403, 'EMAIL_NOT_VERIFIED');
        }

        if ($user === null || !Password::verify($password, $user->passwordHash)) {
            Password::burnPasswordCheck($password);
            throw new UnauthorizedException('Correo o contraseña incorrectos');
        }

        if ($user->twoFactorEnabled) {
            $sessionId = bin2hex(random_bytes(16));
            $preAuthKey = "preauth:{$sessionId}";
            $this->redis()->hmset($preAuthKey, [
                'user_id'            => (string) $user->id,
                'two_factor_enabled' => 'true',
                'auth_stage'         => 'pending_2fa',
                'platform'           => $platform,
            ]);
            $this->redis()->expire($preAuthKey, 300);
            return [
                'user'             => null,
                'token_or_session' => $sessionId,
                'is_2fa'           => true,
            ];
        }

        $accessToken = $this->paseto()->newToken($user->id, 900, $platform);
        $refreshToken = $this->paseto()->newToken($user->id, 36000, $platform);

        $this->createSession($user, $accessToken, $refreshToken, $platform);
        $user->token = $accessToken;

        return [
            'user'             => $user,
            'token_or_session' => $refreshToken,
            'is_2fa'           => false,
        ];
    }

    private function createSession(Auth $user, string $accessToken, string $refreshToken, string $platform): string
    {
        $platformPrefix = $platform ?: 'web';
        $userSessionKey = "user_session:{$platformPrefix}:{$user->id}";

        try {
            $oldSessionId = $this->redis()->get($userSessionKey);
            if ($oldSessionId) {
                $this->redis()->del(["session:{$oldSessionId}"]);
            }

            $sessionId = bin2hex(random_bytes(32));
            $sessionKey = "session:{$sessionId}";
            $ttl = 36000;
            $this->redis()->hmset($sessionKey, [
                'user_id'       => (string) $user->id,
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'platform'      => $platformPrefix,
                'auth_stage'    => 'full',
            ]);
            $this->redis()->expire($sessionKey, $ttl);
            $this->redis()->setex($userSessionKey, $ttl, $sessionId);

            return $sessionId;
        } catch (Throwable $e) {
            error_log('[Redis Session Error] ' . $e->getMessage());
            return bin2hex(random_bytes(16));
        }
    }

    public function refreshToken(string $token, string $platform = 'web'): string
    {
        $payload = $this->paseto()->verifyToken($token);
        $userId = $payload['sub'] ?? '';
        if ($userId === '') {
            throw new UnauthorizedException('Token de refresco inválido');
        }

        $tokenPlatform = !empty($payload['platform']) ? $payload['platform'] : $platform;
        $userSessionKey = "user_session:{$tokenPlatform}:{$userId}";
        $sessionId = $this->redis()->get($userSessionKey);

        if (!$sessionId) {
            $sessionId = $this->redis()->get("user_session:{$userId}");
        }

        if (!$sessionId) {
            throw new UnauthorizedException('Sesión no encontrada o expirada');
        }

        $sessionKey = "session:{$sessionId}";
        $storedRefreshToken = $this->redis()->hget($sessionKey, 'refresh_token');

        if ($storedRefreshToken !== $token) {
            throw new UnauthorizedException('Token de refresco inválido o revocado');
        }

        $newAccessToken = $this->paseto()->newToken($userId, 900, $tokenPlatform);
        $this->redis()->hset($sessionKey, 'access_token', $newAccessToken);

        return $newAccessToken;
    }

    /**
     * Refresca tokens para clientes móviles retornando nuevo access y refresh token.
     *
     * @return array{access_token: string, refresh_token: string}
     */
    public function refreshTokenMobile(string $token): array
    {
        $payload = $this->paseto()->verifyToken($token);
        $userId = $payload['sub'] ?? '';
        if ($userId === '') {
            throw new UnauthorizedException('Token inválido');
        }

        $platform = 'mobile';
        $userSessionKey = "user_session:{$platform}:{$userId}";
        $sessionId = $this->redis()->get($userSessionKey);

        if (!$sessionId) {
            throw new UnauthorizedException('Sesión no encontrada o expirada');
        }

        $sessionKey = "session:{$sessionId}";
        $storedToken = $this->redis()->hget($sessionKey, 'refresh_token');

        if ($storedToken !== $token) {
            throw new UnauthorizedException('Token de refresco inválido o revocado');
        }

        $newAccessToken = $this->paseto()->newToken($userId, 900, $platform);
        $newRefreshToken = $this->paseto()->newToken($userId, 36000, $platform);

        $this->redis()->hmset($sessionKey, [
            'access_token'  => $newAccessToken,
            'refresh_token' => $newRefreshToken,
        ]);
        $this->redis()->expire($sessionKey, 36000);
        $this->redis()->expire($userSessionKey, 36000);

        return [
            'access_token'  => $newAccessToken,
            'refresh_token' => $newRefreshToken,
        ];
    }

    public function confirmAccount(int $userId, string $token): void
    {
        $confirmKey = "confirm_account:{$userId}";
        $storedToken = $this->redis()->get($confirmKey);

        if (!$storedToken || $storedToken !== $token) {
            throw new AppException('Token de confirmación inválido o expirado', 400, 'INVALID_TOKEN');
        }

        $this->repo()->updateEmailConfirm($userId);
        $this->redis()->del([$confirmKey]);
    }

    public function forwardEmailVerification(string $email): void
    {
        $cleanEmail = Validator::validateEmail($email);
        $user = $this->repo()->getByEmailAny($cleanEmail);

        if ($user === null) {
            throw new NotFoundException('No se encontró el correo electrónico');
        }

        if ($user->emailConfirmed) {
            throw new AppException('La cuenta ya se encuentra verificada', 400, 'ALREADY_VERIFIED');
        }

        $token = $this->paseto()->newToken($user->id, 900, 'confirm_account');
        $confirmKey = "confirm_account:{$user->id}";
        $this->redis()->setex($confirmKey, 900, $token);

        $confirmLink = "{$this->frontendUrl}/confirm-account/{$token}";
        $html = $this->mailer()->renderTemplate('confirm_account.html', [
            'Name'        => $user->email,
            'ConfirmLink' => $confirmLink,
        ]);

        $this->mailer()->send($user->email, 'Confirmación de cuenta Fitbase', $html);
    }

    public function sendPasswordReset(string $email): void
    {
        $cleanEmail = Validator::validateEmail($email);
        $user = $this->repo()->getByEmailAny($cleanEmail);

        if ($user === null || !$user->active) {
            return;
        }

        $token = $this->paseto()->newToken($user->id, 900, 'reset_password');
        $resetKey = "reset:{$user->id}";
        $this->redis()->setex($resetKey, 900, $token);

        $resetLink = "{$this->frontendUrl}/forgot-password/{$token}";
        $html = $this->mailer()->renderTemplate('password_reset.html', [
            'Name'      => $user->email,
            'ResetLink' => $resetLink,
        ]);

        $this->mailer()->send($user->email, 'Restablece tu contraseña - Fitbase', $html);
    }

    public function resetPassword(int $userId, string $newPassword): void
    {
        if (strlen($newPassword) < 6) {
            throw new ValidationException('La contraseña debe tener al menos 6 caracteres', [
                'password' => 'Mínimo 6 caracteres'
            ]);
        }

        $hashed = Password::hash($newPassword);
        $this->repo()->updatePassword($userId, $hashed);
        $this->redis()->del(["reset:{$userId}"]);
    }

    public function updatePassword(int $userId, string $currentPassword, string $newPassword): void
    {
        if (strlen($newPassword) < 6) {
            throw new ValidationException('La nueva contraseña debe tener al menos 6 caracteres');
        }

        $user = $this->repo()->getById($userId);
        if ($user === null) {
            throw new NotFoundException('Usuario no encontrado');
        }

        if (!Password::verify($currentPassword, $user->passwordHash)) {
            throw new AppException('La contraseña actual es incorrecta', 400, 'INVALID_CURRENT_PASSWORD');
        }

        $hashed = Password::hash($newPassword);
        $this->repo()->updatePassword($userId, $hashed);
    }

    public function getSession(int $userId): Auth
    {
        $user = $this->repo()->getById($userId);
        if ($user === null) {
            throw new NotFoundException('Sesión no encontrada');
        }
        return $user;
    }

    public function logout(string $token): void
    {
        if ($token === '') {
            return;
        }

        try {
            $payload = $this->paseto()->verifyToken($token);
            $userId = (int) ($payload['sub'] ?? 0);
            $platform = $payload['platform'] ?? 'web';

            if ($userId > 0) {
                $userSessionKey = "user_session:{$platform}:{$userId}";
                $sessionId = $this->redis()->get($userSessionKey);
                if ($sessionId) {
                    $this->redis()->del(["session:{$sessionId}"]);
                }
                $this->redis()->del([$userSessionKey]);
            }

            $this->redis()->setex("blacklist:{$token}", 36000, 'revoked');
        } catch (Throwable) {
        }
    }
}
