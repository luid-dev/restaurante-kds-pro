<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentType;
use App\Traits\AuditableModel;
use App\Traits\BelongsToCompany;
use App\Traits\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Serie de comprobantes (F001, B001, NV01). El correlativo se reserva con
 * lockForUpdate() dentro de la transacción de cobro.
 *
 * @property int $id
 * @property string $uuid
 * @property int $company_id
 * @property DocumentType $document_type
 * @property string $series
 * @property int $next_correlative
 * @property bool $is_active
 */
#[Table('document_series')]
#[Fillable(['document_type', 'series', 'next_correlative', 'is_active'])]
#[Hidden(['id', 'company_id'])]
class DocumentSeries extends Model
{
    use AuditableModel, BelongsToCompany, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'next_correlative' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Número completo del comprobante: ("B001", 450) -> "B001-00000450".
     */
    public function formatNumber(int $correlative): string
    {
        return sprintf('%s-%08d', $this->series, $correlative);
    }
}
