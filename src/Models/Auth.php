<?php

declare(strict_types=1);

namespace App\Models;

class Auth
{
    public function __construct(
        public int $id,
        public int $roleId,
        public string $email,
        public string $passwordHash,
        public ?string $twoFactorSecret = null,
        public bool $twoFactorEnabled = false,
        public bool $firstSession = true,
        public bool $fullProfile = false,
        public bool $emailConfirmed = false,
        public bool $active = true,
        public ?string $token = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?Role $role = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            roleId: (int) ($data['role_id'] ?? 0),
            email: (string) ($data['email'] ?? ''),
            passwordHash: (string) ($data['password_hash'] ?? ''),
            twoFactorSecret: $data['two_factor_secret'] ?? null,
            twoFactorEnabled: (bool) ($data['two_factor_enabled'] ?? false),
            firstSession: (bool) ($data['first_session'] ?? true),
            fullProfile: (bool) ($data['full_profile'] ?? false),
            emailConfirmed: (bool) ($data['email_confirmed'] ?? false),
            active: (bool) ($data['active'] ?? true),
            token: $data['token'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            role: isset($data['role']) && is_array($data['role']) ? Role::fromArray($data['role']) : null
        );
    }

    public function toPublicArray(): array
    {
        return [
            'id'                 => $this->id,
            'role_id'            => $this->roleId,
            'email'              => $this->email,
            'two_factor_enabled' => $this->twoFactorEnabled,
            'first_session'      => $this->firstSession,
            'full_profile'       => $this->fullProfile,
            'email_confirmed'    => $this->emailConfirmed,
            'active'             => $this->active,
            'token'              => $this->token,
            'created_at'         => $this->createdAt,
            'updated_at'         => $this->updatedAt,
            'role'               => $this->role?->toArray(),
        ];
    }
}

