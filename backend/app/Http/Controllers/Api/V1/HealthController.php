<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Sonda pública de disponibilidad para terminales POS/KDS. No expone detalles
 * de infraestructura (versiones, base de datos, colas).
 */
final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return $this->success([
            'status' => 'ok',
            'api_version' => 'v1',
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
