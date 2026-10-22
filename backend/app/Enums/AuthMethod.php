<?php

declare(strict_types=1);

namespace App\Enums;

enum AuthMethod: string
{
    case Password = 'PASSWORD';
    case Pin = 'PIN';
}
