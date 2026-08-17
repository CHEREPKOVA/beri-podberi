<?php

namespace App\Support;

final class CompanyStatus
{
    public const ACTIVE = 'active';

    public const AWAITING_CONFIRMATION = 'awaiting_confirmation';

    public const PENDING = 'pending';

    public const REJECTED = 'rejected';

    public const BLOCKED = 'blocked';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::ACTIVE,
            self::AWAITING_CONFIRMATION,
            self::PENDING,
            self::REJECTED,
            self::BLOCKED,
        ];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::ACTIVE => 'Активна',
            self::AWAITING_CONFIRMATION => 'Ожидает подтверждения',
            self::PENDING => 'Ожидает одобрения',
            self::REJECTED => 'Отклонена',
            self::BLOCKED => 'Заблокирована',
            default => $status,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];
        foreach (self::all() as $status) {
            $labels[$status] = self::label($status);
        }

        return $labels;
    }
}
