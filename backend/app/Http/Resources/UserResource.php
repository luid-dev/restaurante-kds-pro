<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El campo `id` expuesto es siempre el UUID público.
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => $this->is_active,
            'has_pin' => $this->hasPin(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'roles' => $this->whenLoaded('roles', fn (): array => $this->getRoleNames()->values()->all()),
            'permissions' => $this->whenLoaded('permissions', fn (): array => $this->getAllPermissions()->pluck('name')->unique()->sort()->values()->all()),
            'company' => CompanyResource::make($this->whenLoaded('company')),
        ];
    }
}
