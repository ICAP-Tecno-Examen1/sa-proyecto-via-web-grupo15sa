<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stripe reemplaza a PagoFácil como pasarela de pago en línea (database/sql/06_stripe.sql).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/06_stripe.sql')));
    }

    public function down(): void
    {
        // Intencionalmente vacío: los datos de la base compartida no se borran desde una migración.
    }
};
