<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Correlativo diario de comandas (CMD-0001) por empresa. El lock consultivo de
 * PostgreSQL serializa la numeración dentro de la transacción en curso: dos
 * mozos que abren mesas a la vez nunca reciben el mismo número.
 */
final class OrderNumberGenerator
{
    public function next(Company $company, string $businessDate): string
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ["orders:{$company->id}:{$businessDate}"]);

        $last = Order::query()
            ->where('business_date', $businessDate)
            ->max(DB::raw('CAST(substring(order_number FROM 5) AS integer)'));

        return sprintf('CMD-%04d', (is_numeric($last) ? (int) $last : 0) + 1);
    }
}
