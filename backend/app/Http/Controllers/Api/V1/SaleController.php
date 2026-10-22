<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DocumentType;
use App\Exceptions\BillingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportPeriodRequest;
use App\Http\Resources\SaleResource;
use App\Models\Company;
use App\Models\Sale;
use App\Models\User;
use App\Services\SunatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registro de comprobantes emitidos y descarga del XML firmado y del CDR de SUNAT.
 */
final class SaleController extends Controller
{
    public function __construct(private readonly SunatService $sunat) {}

    public function index(ReportPeriodRequest $request): JsonResponse
    {
        $request->validate([
            'document_type' => ['nullable', Rule::enum(DocumentType::class)],
            'search' => ['nullable', 'string', 'max:40'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $company = Company::query()->findOrFail($user->company_id);
        $period = $request->period($company);
        $search = $request->string('search')->trim()->upper()->toString();

        $sales = Sale::query()
            ->with(['company', 'relatedSale', 'creditNote', 'order.diningTable'])
            ->where('issued_at', '>=', $period->start)
            ->where('issued_at', '<', $period->end)
            ->when($request->filled('document_type'), static fn ($query) => $query->where('document_type', $request->string('document_type')->toString()))
            ->when($search !== '', static function ($query) use ($search): void {
                $query->where(static function ($query) use ($search): void {
                    $query->whereRaw("series || '-' || lpad(correlative::text, 8, '0') like ?", ['%'.$search.'%'])
                        ->orWhere('customer_document_number', 'like', $search.'%')
                        ->orWhereRaw('upper(customer_name) like ?', ['%'.$search.'%']);
                });
            })
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return $this->success(SaleResource::collection($sales), meta: ['period' => $period->toArray()]);
    }

    public function xml(Sale $sale): Response
    {
        return $this->download($sale, $sale->sunat_xml_path, 'XML firmado', 'application/xml', sprintf('%s.xml', $this->baseName($sale)));
    }

    public function cdr(Sale $sale): Response
    {
        return $this->download($sale, $sale->sunat_cdr_path, 'CDR de SUNAT', 'application/zip', sprintf('R-%s.zip', $this->baseName($sale)));
    }

    private function download(Sale $sale, ?string $path, string $label, string $contentType, string $filename): Response
    {
        $disk = Storage::disk($this->sunat->disk());

        if ($path === null || ! $disk->exists($path)) {
            throw BillingException::fileNotAvailable($label);
        }

        return response((string) $disk->get($path), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function baseName(Sale $sale): string
    {
        return sprintf('%s-%s', $sale->document_type->value, $sale->documentNumber());
    }
}
