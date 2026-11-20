<?php

declare(strict_types=1);

namespace App\Shared\Utils;

class Password
{
    private static ?string $dummyHash = null;

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function burnPasswordCheck(string $password): void
    {
        if (self::$dummyHash === null) {
            self::$dummyHash = password_hash('fitbase-dummy-password', PASSWORD_BCRYPT, ['cost' => 10]);
        }
        password_verify($password, self::$dummyHash);
    }
}

