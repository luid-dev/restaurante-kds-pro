<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

/**
 * Identificador público UUID v4 (aleatorio) independiente de la PK interna (bigint).
 * Rutas, canales y API Resources exponen solo `uuid`; el `id` no sale del backend.
 * A diferencia de UUIDv7, un v4 no revela la fecha de creación del registro.
 */
trait HasPublicUuid
{
    use HasUuids;

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
