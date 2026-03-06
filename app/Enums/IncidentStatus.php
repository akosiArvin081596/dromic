<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
}
