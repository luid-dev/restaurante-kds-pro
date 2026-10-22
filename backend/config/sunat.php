<?php

declare(strict_types=1);

use Greenter\Ws\Services\SunatEndpoints;

/*
|--------------------------------------------------------------------------
| Facturación electrónica SUNAT (Greenter, UBL 2.1)
|--------------------------------------------------------------------------
|
| El certificado (PEM con clave privada y certificado) firma cada XML. Sin
| certificado no se emite ningún comprobante electrónico: el cobro falla en
| cerrado. En local, `php artisan sunat:dev-certificate` genera uno autofirmado
| (válido solo para el entorno beta de SUNAT).
|
| El envío a SUNAT ocurre en un job encolado y solo si SUNAT_SEND_ENABLED=true.
|
*/

return [
    'certificate_path' => env('SUNAT_CERTIFICATE_PATH', storage_path('app/private/sunat/certificate.pem')),

    'endpoint' => env('SUNAT_ENDPOINT', SunatEndpoints::FE_BETA),

    'sol_user' => env('SUNAT_SOL_USER', 'MODDATOS'),

    'sol_password' => env('SUNAT_SOL_PASSWORD', 'moddatos'),

    'send_enabled' => (bool) env('SUNAT_SEND_ENABLED', false),

    'xml_disk' => env('SUNAT_XML_DISK', 'local'),
];
