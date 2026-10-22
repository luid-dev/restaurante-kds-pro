<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Certificado autofirmado para firmar XML en local y en el entorno beta de SUNAT.
 * En producción se usa el certificado digital de la empresa (SUNAT_CERTIFICATE_PATH).
 */
#[Signature('sunat:dev-certificate {--force : Reemplaza el certificado existente}')]
#[Description('Genera un certificado autofirmado (PEM) para firmar comprobantes en local/beta')]
final class GenerateSunatDevCertificate extends Command
{
    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('No se generan certificados autofirmados en producción.');

            return self::FAILURE;
        }

        $path = config()->string('sunat.certificate_path');

        if (is_file($path) && ! $this->option('force')) {
            $this->info("Ya existe un certificado en {$path}.");

            return self::SUCCESS;
        }

        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0700, true)) {
            throw new RuntimeException("No se pudo crear el directorio de {$path}.");
        }

        file_put_contents($path, self::selfSignedPem('20609876543'));
        chmod($path, 0600);

        $this->info("Certificado de desarrollo generado en {$path}.");

        return self::SUCCESS;
    }

    /**
     * PEM con clave privada RSA 2048 y certificado X.509 autofirmado (1 año).
     */
    public static function selfSignedPem(string $ruc): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if ($key === false) {
            throw new RuntimeException('OpenSSL no pudo generar la clave privada.');
        }

        $csr = openssl_csr_new(['countryName' => 'PE', 'organizationName' => "RUC {$ruc} DESARROLLO", 'commonName' => 'restaurante-kds-pro beta'], $key, ['digest_alg' => 'sha256']);

        if (! $csr instanceof \OpenSSLCertificateSigningRequest) {
            throw new RuntimeException('OpenSSL no pudo generar la solicitud de certificado.');
        }

        $certificate = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);

        if ($certificate === false || ! openssl_x509_export($certificate, $certificatePem) || ! openssl_pkey_export($key, $keyPem)) {
            throw new RuntimeException('OpenSSL no pudo exportar el certificado.');
        }

        return $keyPem.$certificatePem;
    }
}
