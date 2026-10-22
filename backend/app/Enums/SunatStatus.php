<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado del comprobante frente a SUNAT (CDR).
 */
enum SunatStatus: string
{
    case NotApplicable = 'NOT_APPLICABLE';
    case Pending = 'PENDING';
    case Accepted = 'ACCEPTED';
    case Observed = 'OBSERVED';
    case Rejected = 'REJECTED';
}
