<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cómo se cobra una parte de la comanda (división de cuenta).
 */
enum ChargeMode: string
{
    /** Todo el saldo pendiente de la mesa. */
    case Full = 'FULL';

    /** Platos y cantidades que elige cada comensal. */
    case Items = 'ITEMS';

    /** Monto libre o partes iguales: no se asocia a platos, solo admite glosa por consumo. */
    case Amount = 'AMOUNT';
}
