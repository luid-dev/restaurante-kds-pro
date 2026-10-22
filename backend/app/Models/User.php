<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Auth\PinFingerprint;
use App\Traits\AuditableModel;
use App\Traits\BelongsToCompany;
use App\Traits\HasPublicUuid;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use LogicException;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

/**
 * Personal del restaurante (administrador, cajero, mozo, cocina).
 *
 * El personal operativo puede no tener correo ni contraseña: en terminales
 * compartidas se identifica con un PIN numérico de 6 dígitos. El PIN nunca se
 * asigna por mass assignment: solo mediante setPin(), que guarda hash y huella.
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property string $name
 * @property string|null $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string|null $password
 * @property string|null $pin_code
 * @property string|null $pin_lookup
 * @property CarbonImmutable|null $pin_changed_at
 * @property bool $is_active
 * @property CarbonImmutable|null $last_login_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Company $company
 */
#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['id', 'company_id', 'password', 'pin_code', 'pin_lookup', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use AuditableModel, BelongsToCompany, HasApiTokens, HasFactory, HasPublicUuid, HasRoles, Notifiable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin_code' => 'hashed',
            'pin_changed_at' => 'datetime',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * El correo se normaliza a minúsculas (índice único sobre lower(email)).
     *
     * @return Attribute<string|null, string|null>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: static fn (?string $value): ?string => $value === null ? null : Str::lower(trim($value)),
        );
    }

    /**
     * Asigna el PIN: hash bcrypt para validarlo y huella HMAC para ubicarlo.
     * Requiere company_id porque la huella es por empresa.
     */
    public function setPin(string $pin): static
    {
        $companyId = $this->getAttribute('company_id');

        if (! is_int($companyId)) {
            throw new LogicException('Asigne la empresa del usuario antes de su PIN.');
        }

        $this->forceFill([
            'pin_code' => Hash::make($pin),
            'pin_lookup' => PinFingerprint::make($companyId, $pin),
            'pin_changed_at' => now(),
        ]);

        return $this;
    }

    public function hasPin(): bool
    {
        return $this->pin_code !== null;
    }

    public function verifyPin(string $pin): bool
    {
        return $this->pin_code !== null && Hash::check($pin, $this->pin_code);
    }

    /**
     * Las notificaciones broadcast usan el canal privado por UUID, nunca por id interno.
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'users.'.$this->uuid;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('audit')
            ->logOnly(['name', 'email', 'is_active', 'pin_changed_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
