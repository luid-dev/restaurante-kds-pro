<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Exceptions\BillingException;
use App\Exceptions\OrderFlowException;
use App\Exceptions\SessionExpiredException;
use App\Exceptions\TenantAccessDeniedException;
use App\Models\RestaurantTable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * El log solo registra incidentes: las reglas de negocio esperadas (4xx) no se
 * escriben como ERROR; los 5xx y las excepciones no controladas sí.
 */
final class ExceptionReportingTest extends TestCase
{
    #[Test]
    public function expected_business_rejections_are_not_reported(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);
        $table = new RestaurantTable;
        $table->code = 'M-01';

        foreach ([
            new SessionExpiredException,                    // 401
            TenantAccessDeniedException::inactiveUser(),    // 403
            OrderFlowException::tableNotFound(),            // 404
            OrderFlowException::tableNotAvailable($table),  // 409
            BillingException::paymentsMismatch('10.00', '9.00'), // 422
            ValidationException::withMessages(['field' => ['Inválido.']]),
            new NotFoundHttpException,
        ] as $exception) {
            $this->assertFalse($handler->shouldReport($exception), $exception::class.' no debe registrarse.');
        }
    }

    #[Test]
    public function infrastructure_failures_are_still_reported(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $this->assertTrue($handler->shouldReport(BillingException::certificateMissing()));
        $this->assertTrue($handler->shouldReport(BillingException::issuerAddressIncomplete()));
        $this->assertTrue($handler->shouldReport(new RuntimeException('SQLSTATE: conexión perdida')));
    }
}
