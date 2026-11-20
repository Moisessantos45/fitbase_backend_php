<?php

declare(strict_types=1);

namespace App\Config;

class Mailer
{
    public readonly string $smtpHost;
    public readonly int $smtpPort;
    public readonly string $smtpUser;
    public readonly string $smtpPass;
    public readonly string $smtpPassKey;
    public readonly string $fromEmail;
    public readonly string $fromName;

    public function __construct()
    {
        $this->smtpHost    = self::env(['SMTP_HOST'], 'smtp.gmail.com');
        $this->smtpPort    = (int) self::env(['SMTP_PORT'], '587');
        $this->smtpUser    = self::env(['SMTP_USER'], 'moisessantoshdz45@gmail.com');
        $this->smtpPass    = self::env(['API_KEY_SMTP', 'SMTP_PASS'], '');
        $this->smtpPassKey = self::env(['API_KEY_SMTP_KEY'], '');
        $this->fromEmail   = self::env(['ADMIN_EMAIL', 'MAIL_FROM_ADDRESS', 'EMAIL_FROM', 'SMTP_USER'], 'moisessantoshdz45@gmail.com');
        $this->fromName    = self::env(['MAIL_FROM_NAME'], 'Fitbase');
    }

    public static function load(): self
    {
        return new self();
    }

    private static function env(array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
            if ($val !== false && $val !== null && $val !== '') {
                $trimmed = trim((string)$val);
                if ((str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"')) ||
                    (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'"))) {
                    return substr($trimmed, 1, -1);
                }
                return $trimmed;
            }
        }
        return $default;
    }
}
