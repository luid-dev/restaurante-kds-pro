<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportPeriodRequest;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\User;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rentabilidad gastronómica y reportes contables SUNAT (SIRE RVIE y kardex 13.1).
 */
final class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function dashboardMetrics(ReportPeriodRequest $request): JsonResponse
    {
        $company = $this->company($request);

        return $this->success($this->reports->dashboardMetrics($company, $request->period($company)));
    }

    public function salesSire(Request $request): Response
    {
        $request->validate(['period' => ['nullable', 'date_format:Ym']]);
        $company = $this->company($request);
        $period = $request->filled('period')
            ? $request->string('period')->toString()
            : CarbonImmutable::now($company->settings->timezone)->format('Ym');

        $file = $this->reports->salesSire($company, $period);

        return $this->attachment($file['content'], $file['filename'], 'text/plain; charset=UTF-8');
    }

    public function kardexFormat131(ReportPeriodRequest $request): Response
    {
        $company = $this->company($request);
        $ingredient = $request->filled('ingredient')
            ? Ingredient::query()->where('uuid', $request->string('ingredient')->toString())->firstOrFail()
            : null;

        $file = $this->reports->kardexFormat131($company, $request->period($company), $ingredient);

        return $this->attachment($file['content'], $file['filename'], 'text/csv; charset=UTF-8');
    }

    public function wasteAudit(ReportPeriodRequest $request): Response
    {
        $company = $this->company($request);
        $period = $request->period($company);
        $audit = $this->reports->wasteAudit($period);

        if ($request->string('format')->toString() === 'csv') {
            $filename = sprintf('MERMAS-%s-%s_%s.csv', $company->ruc, $period->from->format('Ymd'), $period->to->format('Ymd'));

            return $this->attachment($this->reports->wasteAuditCsv($company, $audit), $filename, 'text/csv; charset=UTF-8');
        }

        return $this->success([...$audit, 'period' => $period->toArray()]);
    }

    private function company(Request $request): Company
    {
        /** @var User $user */
        $user = $request->user();

        return Company::query()->findOrFail($user->company_id);
    }

    private function attachment(string $content, string $filename, string $contentType): Response
    {
        return response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
