<?php

namespace Database\Seeders;

use App\Models\Beca;
use App\Models\MetodoPago;
use App\Models\PasarelaPago;
use Illuminate\Database\Seeder;

/**
 * Métodos de pago, pasarela PagoFácil y becas iniciales (CU4). Idempotente.
 */
class FinanzasSeeder extends Seeder
{
    public function run(): void
    {
        MetodoPago::firstOrCreate(['nombre' => 'Efectivo'], [
            'tipo' => MetodoPago::EFECTIVO,
            'descripcion' => 'Pago en ventanilla del ICAP. Lo registra el personal autorizado.',
            'activo' => true,
        ]);

        MetodoPago::firstOrCreate(['nombre' => 'Transferencia o QR bancario'], [
            'tipo' => MetodoPago::COMPROBANTE,
            'descripcion' => 'Transfiera a la cuenta institucional del ICAP y adjunte el comprobante para su conciliación.',
            'activo' => true,
        ]);

        $qr = MetodoPago::firstOrCreate(['nombre' => 'QR PagoFácil'], [
            'tipo' => MetodoPago::PASARELA,
            'descripcion' => 'Pago en línea con QR. Se confirma automáticamente.',
            'activo' => true,
        ]);

        // Con credenciales en el .env la pasarela nace en modo REAL; sin ellas, SIMULADO.
        $service = config('icap.pagofacil.tc_token_service');
        $secret = config('icap.pagofacil.tc_token_secret');

        PasarelaPago::firstOrCreate(['id_metodo_pago' => $qr->id_metodo_pago], [
            'proveedor' => 'PAGOFACIL',
            'modo' => filled($service) && filled($secret) ? PasarelaPago::REAL : PasarelaPago::SIMULADO,
            'tc_token_service' => $service,
            'tc_token_secret' => $secret,
            'callback_url' => config('icap.pagofacil.callback_url'),
            'activo' => true,
        ]);

        foreach ([
            ['Beca Excelencia Académica', 50, 'UAGRM', 'Para estudiantes con promedio sobresaliente.'],
            ['Beca Socioeconómica', 30, 'ICAP', 'Apoyo a estudiantes de escasos recursos.'],
            ['Descuento Funcionario UAGRM', 20, 'UAGRM', 'Descuento para funcionarios universitarios.'],
            ['Beca Completa Convenio Municipal', 100, 'Gobierno Autónomo Municipal', 'Cobertura total por convenio interinstitucional.'],
        ] as [$nombre, $porcentaje, $institucion, $descripcion]) {
            Beca::firstOrCreate(['nombre' => $nombre], [
                'porcentaje' => $porcentaje, 'institucion_financiadora' => $institucion, 'descripcion' => $descripcion,
            ]);
        }
    }
}
