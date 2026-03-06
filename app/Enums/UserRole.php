<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Regional = 'regional';
    case Provincial = 'provincial';
    case Lgu = 'lgu';
    case Escort = 'escort';
    case RegionalDirector = 'regional_director';
}
