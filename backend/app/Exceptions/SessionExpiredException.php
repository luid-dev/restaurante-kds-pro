<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ErrorCode;

final class SessionExpiredException extends ApiException
{
    public function __construct()
    {
        parent::__construct(ErrorCode::SessionExpired->defaultMessage(), 401, ErrorCode::SessionExpired);
    }
}
