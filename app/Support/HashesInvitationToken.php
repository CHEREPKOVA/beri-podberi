<?php

namespace App\Support;

use Illuminate\Support\Str;

trait HashesInvitationToken
{
    /**
     * Сгенерировать plaintext-токен для ссылки в письме.
     */
    public static function createPlainToken(): string
    {
        return Str::random(64);
    }

    /**
     * Хеш для хранения в БД (plaintext недоступен администраторам и в дампах).
     */
    public static function hashToken(string $plainToken): string
    {
        return hash_hmac('sha256', $plainToken, (string) config('app.key'));
    }

    public static function findByPlainToken(string $plainToken): ?static
    {
        if ($plainToken === '') {
            return null;
        }

        return static::query()
            ->where('token', static::hashToken($plainToken))
            ->first();
    }
}
