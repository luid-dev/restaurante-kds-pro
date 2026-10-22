<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Tokens de dispositivo (p. ej. pantallas KDS). El dueño del token se resuelve
 * antes de conocer el tenant, por eso su relación omite CompanyScope; el tenant
 * se fija después en EnsureTenantScope a partir de ese usuario.
 *
 * No es final: Sanctum::actingAs() lo reemplaza por un mock en las pruebas.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo('tokenable')->withoutGlobalScope(CompanyScope::class);
    }
}
