<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Canales privados (autorizados en /api/broadcasting/auth con auth:sanctum + tenant)
|--------------------------------------------------------------------------
|
| Todo canal se identifica por UUID públicos: nunca por ids internos. El
| middleware tenant ya fijó la empresa del usuario (y el team de Spatie).
|
*/

// Plano de mesas y avisos de comandas: todo el personal de la empresa.
Broadcast::channel('company.{companyUuid}', static fn (User $user, string $companyUuid): bool => $user->company->uuid === $companyUuid);

// Pantallas de cocina: además de la empresa exige permiso kds.view (las comandas viajan completas).
Broadcast::channel('company.{companyUuid}.kds', static fn (User $user, string $companyUuid): bool => $user->company->uuid === $companyUuid
    && $user->can('kds.view'));

Broadcast::channel('users.{userUuid}', static fn (User $user, string $userUuid): bool => $user->uuid === $userUuid);
