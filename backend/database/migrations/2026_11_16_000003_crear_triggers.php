<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Triggers y contadores de la capa de persistencia (database/sql/03_triggers.sql).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/03_triggers.sql')));
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trg_pago_estado_mensualidad ON pago;
            DROP TRIGGER IF EXISTS trg_visita_contador ON visita;
            DROP FUNCTION IF EXISTS fn_actualizar_estado_mensualidad();
            DROP FUNCTION IF EXISTS fn_contar_visita();
        SQL);
    }
};
