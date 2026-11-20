<?php

declare(strict_types=1);

namespace App\Shared\Utils;

use App\Shared\Errors\AppException;
use App\Shared\Errors\UnauthorizedException;
use DateTime;
use DateTimeImmutable;
use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Keys\Version4\SymmetricKey;
use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\Protocol\Version4;
use ParagonIE\Paseto\ProtocolCollection;
use Throwable;

class PasetoMaker
{
    private SymmetricKey $key;

    public function __construct(?string $secretKey = null)
    {
        $raw = $secretKey ?? $_ENV['SECRET_KEY_JWT'] ?? $_SERVER['SECRET_KEY_JWT'] ?? getenv('SECRET_KEY_JWT') ?: 'fitbase-default-super-secret-key-32b!';
        $keyBytes = hash('sha256', $raw, true);
        $this->key = new SymmetricKey($keyBytes);
    }

    public function newToken(string|int $userId, int $durationSeconds = 86400, string $platform = 'web'): string
    {
        $jti = bin2hex(random_bytes(16));
        $exp = (new DateTimeImmutable())->modify("+{$durationSeconds} seconds");

        return Builder::getLocal($this->key, new Version4())
            ->setSubject((string) $userId)
            ->setJti($jti)
            ->setClaims([
                'id'       => $jti,
                'sub'      => (string) $userId,
                'platform' => $platform,
            ])
            ->setExpiration($exp)
            ->toString();
    }

    /**
     * Verifica y extrae el contenido del token.
     *
     * @param string $token
     * @return array{id: string, sub: string, platform: string, exp: string}
     * @throws UnauthorizedException
     */
    public function verifyToken(string $token): array
    {
        try {
            $parser = Parser::getLocal($this->key, ProtocolCollection::v4());
            $parsed = $parser->parse($token);
            $claims = $parsed->getClaims();

            return [
                'id'       => (string) ($claims['id'] ?? $claims['jti'] ?? ''),
                'sub'      => (string) ($claims['sub'] ?? ''),
                'platform' => (string) ($claims['platform'] ?? 'web'),
                'exp'      => isset($claims['exp']) ? (string) $claims['exp'] : '',
            ];
        } catch (Throwable $e) {
            throw new UnauthorizedException('Token inválido o expirado: ' . $e->getMessage());
        }
    }
}
