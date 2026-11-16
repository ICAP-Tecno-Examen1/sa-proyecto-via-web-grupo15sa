<?php

namespace App\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Genera códigos QR como imagen PNG embebible (data URI).
 * Se usa en los certificados digitales y en la pasarela simulada.
 */
class CodigoQr
{
    public static function dataUri(string $contenido, int $tamano = 320): string
    {
        $builder = new Builder(
            writer: new PngWriter,
            data: $contenido,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $tamano,
            margin: 12,
        );

        return $builder->build()->getDataUri();
    }
}
