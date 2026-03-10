<?php

namespace App\Enums;

enum UserType: string
{
    case Cmswdo = 'cmswdo';
    case DswdLgu = 'dswd_lgu';
    case Ldrrmo = 'ldrrmo';
    case ProvincialDswd = 'provincial_dswd';
    case Pdrrmo = 'pdrrmo';
    case Pswdo = 'pswdo';
    case Drims = 'drims';
    case Rros = 'rros';
    case DivisionChief = 'division_chief';

    public function label(): string
    {
        return match ($this) {
            self::Cmswdo => 'C/MSWDO',
            self::DswdLgu => 'DSWD (LGU)',
            self::Ldrrmo => 'LDRRMO',
            self::ProvincialDswd => 'Provincial DSWD',
            self::Pdrrmo => 'PDRRMO',
            self::Pswdo => 'PSWDO',
            self::Drims => 'DRIMS',
            self::Rros => 'RROS',
            self::DivisionChief => 'Division Chief',
        };
    }

    /** @return list<self> */
    public static function forRole(UserRole $role): array
    {
        return match ($role) {
            UserRole::Lgu => [self::Cmswdo, self::DswdLgu, self::Ldrrmo],
            UserRole::Provincial => [self::ProvincialDswd, self::Pdrrmo, self::Pswdo],
            UserRole::Regional => [self::Drims, self::Rros, self::DivisionChief],
            default => [],
        };
    }

    public static function defaultForRole(UserRole $role): ?self
    {
        return match ($role) {
            UserRole::Lgu => self::Cmswdo,
            UserRole::Provincial => self::ProvincialDswd,
            UserRole::Regional => self::Drims,
            default => null,
        };
    }
}
