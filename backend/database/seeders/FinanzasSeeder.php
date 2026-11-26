<?php

namespace Database\Seeders;

use App\Models\Beca;
use App\Models\MetodoPago;
use App\Models\MetodoPagoElectronico;
use Illuminate\Database\Seeder;

/**
 * Métodos de pago, credenciales de PagoFácil y becas iniciales (CU4). Idempotente.
 */
class FinanzasSeeder extends Seeder
{
    public function run(): void
    {
        MetodoPago::firstOrCreate(['nombre' => 'Efectivo'], [
            'tipo' => MetodoPago::EFECTIVO,
            'descripcion' => 'Pague en la caja del ICAP. Se avisa a Secretaría y la cuota queda pagada cuando cobran.',
            'activo' => true,
        ]);

        MetodoPago::firstOrCreate(['nombre' => 'Transferencia o QR bancario'], [
            'tipo' => MetodoPago::COMPROBANTE,
            'descripcion' => 'Transfiera a la cuenta institucional del ICAP y adjunte el comprobante para su conciliación.',
            'activo' => true,
        ]);

        // Pasarela en línea: Stripe Checkout (pago con tarjeta)
        $tarjeta = MetodoPago::firstOrCreate(['nombre' => 'Tarjeta de crédito o débito (Stripe)'], [
            'tipo' => MetodoPago::PASARELA,
            'descripcion' => 'Pago en línea con tarjeta en la página segura de Stripe. Se confirma automáticamente.',
            'activo' => true,
        ]);

        // Las claves pueden venir del .env (STRIPE_SECRET_KEY / STRIPE_WEBHOOK_SECRET) o cargarse
        // desde Finanzas > Métodos de pago. Sin clave secreta el método no se ofrece al pagar.
        $electronico = MetodoPagoElectronico::firstOrNew(['id_metodo_pago' => $tarjeta->id_metodo_pago]);

        if (! $electronico->exists) {
            $electronico->id_metodo_pago = $tarjeta->id_metodo_pago;
            // Habilitado; igual solo se ofrece al pagar cuando hay una clave de Stripe (aquí o en el .env).
            $electronico->activo = true;
            $electronico->save();
        }

        foreach ([
            // nombre, %, institución, descripción, requisitos (documento que debe adjuntar quien la solicita)
            ['Beca Excelencia Académica', 50, 'UAGRM', 'Para estudiantes con promedio sobresaliente.', 'Certificado o kárdex de notas con promedio sobresaliente.'],
            ['Beca Socioeconómica', 30, 'ICAP', 'Apoyo a estudiantes de escasos recursos.', 'Declaración o informe socioeconómico con respaldo de los ingresos del hogar.'],
            ['Descuento Funcionario UAGRM', 20, 'UAGRM', 'Descuento para funcionarios universitarios.', 'Credencial o certificado de trabajo vigente de la UAGRM.'],
            ['Beca Completa Convenio Municipal', 100, 'Gobierno Autónomo Municipal', 'Cobertura total por convenio interinstitucional.', 'Nota o carta del Gobierno Autónomo Municipal que respalde el convenio.'],
        ] as [$nombre, $porcentaje, $institucion, $descripcion, $requisitos]) {
            Beca::firstOrCreate(['nombre' => $nombre], [
                'porcentaje' => $porcentaje, 'institucion_financiadora' => $institucion, 'descripcion' => $descripcion, 'requisitos' => $requisitos,
            ]);
        }
    }
}
