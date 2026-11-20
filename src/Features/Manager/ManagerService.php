<?php

declare(strict_types=1);

namespace App\Features\Manager;

use App\Config\Redis;
use App\Models\Auth;
use App\Models\CashRegister;
use App\Models\Constants;
use App\Models\FileModel;
use App\Models\GymManager;
use App\Models\Paginate;
use App\Shared\Errors\AppException;
use App\Shared\Errors\ConflictException;
use App\Shared\Errors\ForbiddenException;
use App\Shared\Errors\NotFoundException;
use App\Shared\Errors\ValidationException;
use App\Shared\Services\MailerService;
use App\Shared\Utils\PasetoMaker;
use App\Shared\Utils\Password;
use App\Shared\Utils\Validator;
use DateTime;
use Predis\Client as RedisClient;
use Throwable;

class ManagerService
{
    private const string CACHE_MANAGER_PREFIX = 'manager_profile_';
    private const int CACHE_TTL_SECONDS = 600;
    public function __construct(
        private ?ManagerRepository $repo = null,
        private ?RedisClient $redis = null,
        private ?PasetoMaker $paseto = null,
        private ?MailerService $mailer = null,
        private ?string $storageUrl = null,
        private ?string $frontendUrl = null
    ) {
        $this->storageUrl = $storageUrl ?? ($_ENV['HOST_URL_PROD'] ?? 'http://localhost:5173') . '/storage';
        $this->frontendUrl = $frontendUrl ?? ($_ENV['HOST_URL_PROD'] ?? 'http://localhost:5173');
    }

    private function repo(): ManagerRepository
    {
        return $this->repo ??= new ManagerRepository();
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

    public function create(
        int $authId,
        string $email,
        string $password,
        string $firstName,
        string $lastName,
        string $phone
    ): void {
        $cleanEmail = Validator::validateEmail($email);

        if (strlen($password) < 6) {
            throw new ValidationException('La contraseña debe tener al menos 6 caracteres');
        }

        $gymId = $this->repo()->getGymIdByOwnerAuthId($authId);
        if ($gymId === null) {
            throw new ForbiddenException('No tienes un gimnasio asignado o no eres dueño de gimnasio');
        }

        if ($this->repo()->existsAuthByEmail($cleanEmail)) {
            throw new ConflictException('El correo electrónico ya se encuentra registrado');
        }

        $hashedPassword = Password::hash($password);

        $authRecord = new Auth(
            id: 0,
            roleId: Constants::ROLE_GYM_MANAGER_ID,
            email: $cleanEmail,
            passwordHash: $hashedPassword,
            fullProfile: true,
            emailConfirmed: false,
            active: true
        );

        $managerModel = new GymManager(
            id: 0,
            gymId: $gymId,
            authId: 0,
            createdByAuthId: $authId,
            firstName: trim($firstName),
            lastName: trim($lastName),
            phone: trim($phone)
        );

        $this->repo()->createManagerWithAuth($authRecord, $managerModel);

        $token = $this->paseto()->newToken($authRecord->id, 86400, 'confirm_account');
        $confirmKey = "confirm_account:{$authRecord->id}";

        try {
            $this->redis()->setex($confirmKey, 86400, $token);
        } catch (Throwable) {
        }

        $confirmLink = "{$this->frontendUrl}/confirm-account/{$token}";
        $html = $this->mailer()->renderTemplate('confirm_account.html', [
            'Name'        => $managerModel->firstName,
            'ConfirmLink' => $confirmLink,
        ]);

        $this->mailer()->send($authRecord->email, 'Confirmación de cuenta como Encargado Fitbase', $html);
    }

    public function getAllManagersByGym(int $authId, int $page = 1, int $pageSize = 10, ?string $query = ''): array
    {
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $offset = ($page - 1) * $pageSize;
        $q = trim((string) ($query ?? ''));

        $res = $this->repo()->getAllByOwnerAuthId($authId, $offset, $pageSize, $q);

        $items = $res['data'];
        foreach ($items as $item) {
            if ($item->slug) {
                $item->avatarUrl = \App\Shared\Utils\UrlHelper::buildEvidenceUrl($item->slug);
            }
        }

        $total = (int) ($res['total'] ?? 0);
        $totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 1;
        $dataArray = array_map(fn($it) => $it->toArray(), $items);

        return [
            'data'     => $dataArray,
            'paginate' => (new Paginate($page, $pageSize, $total, max(1, $totalPages)))->toArray(),
        ];
    }

    public function getDetailByAuthId(int $authId): ManagerDetail
    {
        $cacheKey = self::CACHE_MANAGER_PREFIX . $authId;

        try {
            $cached = $this->redis()->get($cacheKey);
            if ($cached) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return ManagerDetail::fromArray($decoded);
                }
            }
        } catch (Throwable) {
        }

        $detail = $this->repo()->getDetailByAuthId($authId);
        if ($detail === null) {
            throw new NotFoundException('Perfil de encargado no encontrado');
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

        $this->repo()->update($authId, $updateData);
        $this->invalidateCache($authId);
    }

    public function uploadAvatar(int $authId, string $slug, string $path, string $mimeType, int $size): void
    {
        $file = new FileModel(
            id: 0,
            slug: $slug,
            path: $path,
            mimeType: $mimeType,
            size: $size,
            authorId: $authId
        );

        $this->repo()->withTransaction(function (ManagerRepository $txRepo) use ($file, $authId) {
            $fileId = $txRepo->createFile($file);
            $txRepo->updateAvatarByAuthId($authId, $fileId);
        });

        $this->invalidateCache($authId);
    }

    public function openCashRegister(int $authId, int $gymId, float $openCash): CashRegisterStatus
    {
        $active = $this->repo()->getActiveCashRegister($gymId, $authId);
        if ($active !== null) {
            throw new ConflictException('Ya existe una caja abierta para este turno en este gimnasio');
        }

        $register = new CashRegister(
            id: 0,
            gymId: $gymId,
            authId: $authId,
            openTime: date('c'),
            openCash: $openCash
        );

        $id = $this->repo()->openCashRegister($register);
        $register->id = $id;

        return new CashRegisterStatus(
            id: $register->id,
            gymId: $register->gymId,
            authId: $register->authId,
            openTime: $register->openTime,
            openCash: $register->openCash,
            isOpen: true
        );
    }

    public function closeCashRegister(int $authId, float $closeCash): CashRegisterStatus
    {
        $active = $this->repo()->getActiveCashRegisterByAuthId($authId);
        if ($active === null) {
            throw new NotFoundException('No hay ninguna caja abierta activa para este usuario');
        }

        $closeTime = date('c');
        $this->repo()->closeCashRegister($active->id, $closeCash, $closeTime);

        return new CashRegisterStatus(
            id: $active->id,
            gymId: $active->gymId,
            authId: $active->authId,
            openTime: $active->openTime,
            openCash: $active->openCash,
            closeTime: $closeTime,
            closeCash: $closeCash,
            isOpen: false
        );
    }

    public function getCurrentCashRegister(int $authId): CashRegisterStatus
    {
        $active = $this->repo()->getActiveCashRegisterByAuthId($authId);
        if ($active === null) {
            throw new NotFoundException('No hay ninguna caja abierta activa');
        }

        return new CashRegisterStatus(
            id: $active->id,
            gymId: $active->gymId,
            authId: $active->authId,
            openTime: $active->openTime,
            openCash: $active->openCash,
            closeTime: $active->closeTime,
            closeCash: $active->closeCash,
            isOpen: true
        );
    }

    private function invalidateCache(int $authId): void
    {
        try {
            $this->redis()->del([self::CACHE_MANAGER_PREFIX . $authId]);
        } catch (Throwable) {
        }
    }
}

