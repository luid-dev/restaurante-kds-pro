<?php

declare(strict_types=1);

namespace App\Enums;

enum PrinterConnection: string
{
    /** Impresora ESC/POS en red (TCP, normalmente puerto 9100). */
    case Network = 'NETWORK';

    /** Impresora USB/Bluetooth atendida por un agente local en la terminal. */
    case Agent = 'AGENT';
}
