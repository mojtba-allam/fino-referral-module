<?php

namespace Modules\Referral\Enums;

enum TransactionStatus: string
{
    case Recorded = 'recorded';
    case Duplicate = 'duplicate';
    case Failed = 'failed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Recorded => 'Recorded',
            self::Duplicate => 'Duplicate',
            self::Failed => 'Failed',
        };
    }
}
