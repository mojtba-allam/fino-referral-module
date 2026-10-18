<?php

namespace Modules\Referral\Enums;

enum GatewayStatus: string
{
    case Successful = 'successful';
    case Pending = 'pending';
    case Failed = 'failed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Successful => 'Successful',
            self::Pending => 'Pending',
            self::Failed => 'Failed',
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::Successful;
    }
}
