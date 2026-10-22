<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\DTOs\Reports\ReportPeriod;
use App\Http\Requests\Operations\BuildsDomainData;
use App\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Rango de fechas de negocio (YYYY-MM-DD). Sin parámetros: el día actual de la empresa.
 */
final class ReportPeriodRequest extends FormRequest
{
    use BuildsDomainData;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'ingredient' => ['nullable', 'uuid'],
            'format' => ['nullable', 'string', 'in:json,csv'],
        ];
    }

    public function period(Company $company): ReportPeriod
    {
        $timezone = $company->settings->timezone;
        $today = CarbonImmutable::now($timezone)->toDateString();

        return $this->buildData('from', fn (): ReportPeriod => ReportPeriod::fromDates(
            $this->filled('from') ? $this->string('from')->toString() : $today,
            $this->filled('to') ? $this->string('to')->toString() : $today,
            $timezone,
        ));
    }
}
