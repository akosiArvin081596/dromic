<?php

namespace App\Enums;

enum RequestLetterStatus: string
{
    case Pending = 'pending';
    case Endorsed = 'endorsed';
    case Approved = 'approved';
    case Delivering = 'delivering';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Endorsed => 'Endorsed',
            self::Approved => 'Approved',
            self::Delivering => 'Delivering',
            self::Completed => 'Completed',
        };
    }
}
