<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\AsCompanySettings;
use App\DTOs\Company\CompanySettingsData;
use App\Traits\AuditableModel;
use App\Traits\HasPublicUuid;
use Carbon\CarbonImmutable;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Empresa (tenant). Cada restaurante o pollería es una empresa con su RUC,
 * su configuración tributaria/operativa y su marca blanca.
 *
 * @property int $id
 * @property string $uuid
 * @property string $ruc
 * @property string $business_name
 * @property string|null $trade_name
 * @property string $slug
 * @property string|null $fiscal_address
 * @property string|null $ubigeo
 * @property string|null $department
 * @property string|null $province
 * @property string|null $district
 * @property string|null $urbanization
 * @property string|null $email
 * @property string|null $phone
 * @property CompanySettingsData $settings
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['ruc', 'business_name', 'trade_name', 'slug', 'fiscal_address', 'ubigeo', 'department', 'province', 'district', 'urbanization', 'email', 'phone', 'settings', 'is_active'])]
#[Hidden(['id'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use AuditableModel, HasFactory, HasPublicUuid, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => AsCompanySettings::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (Company $company): void {
            if (! array_key_exists('settings', $company->getAttributes())) {
                $company->settings = CompanySettingsData::defaults();
            }
        });
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function displayName(): string
    {
        return $this->trade_name ?? $this->business_name;
    }

    /**
     * El XML UBL 2.1 exige ubigeo, departamento, provincia, distrito y dirección del emisor.
     */
    public function hasCompleteFiscalAddress(): bool
    {
        foreach ([$this->fiscal_address, $this->ubigeo, $this->department, $this->province, $this->district] as $value) {
            if ($value === null || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * "Av. El Sol 520, Cusco - Cusco - Cusco" para la cabecera del ticket.
     */
    public function fiscalAddressLine(): ?string
    {
        if ($this->fiscal_address === null) {
            return null;
        }

        $locality = array_filter([$this->district, $this->province, $this->department], static fn (?string $part): bool => $part !== null && $part !== '');

        return $locality === [] ? $this->fiscal_address : $this->fiscal_address.', '.implode(' - ', $locality);
    }

    protected function auditCompanyId(): ?int
    {
        return $this->id;
    }
}
