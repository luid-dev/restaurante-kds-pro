<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El campo `id` expuesto es siempre el UUID público. La configuración se publica
 * sin la topología de red de impresoras.
 *
 * @mixin Company
 */
final class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'ruc' => $this->ruc,
            'business_name' => $this->business_name,
            'trade_name' => $this->trade_name,
            'display_name' => $this->displayName(),
            'slug' => $this->slug,
            'settings' => $this->settings->toPublicArray(),
        ];
    }
}
