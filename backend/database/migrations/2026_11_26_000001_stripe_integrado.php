<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clave publicable de Stripe para mostrar el pago con tarjeta dentro del sistema (database/sql/07_stripe_integrado.sql).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/07_stripe_integrado.sql')));
    }

    public function down(): void
    {
        // Intencionalmente vacío: los datos de la base compartida no se borran desde una migración.
    }
};
