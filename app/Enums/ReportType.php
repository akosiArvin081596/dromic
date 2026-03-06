<?php

namespace App\Enums;

enum ReportType: string
{
    case Initial = 'initial';
    case Progress = 'progress';
    case Terminal = 'terminal';
}
