<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Columnas y tablas adicionales del segundo parcial (database/sql/02_ajustes_parcial2.sql):
 * menú dinámico, bitácora, contador de visitas, pasarela de pagos, conciliación,
 * validación de requisitos y repositorio documental.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/02_ajustes_parcial2.sql')));
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS validacion_requisito, pasarela_pago, contador_visita, visita, bitacora, menu_item CASCADE;
            ALTER TABLE documento DROP COLUMN IF EXISTS categoria, DROP COLUMN IF EXISTS estado, DROP COLUMN IF EXISTS nombre_archivo,
                DROP COLUMN IF EXISTS tipo_mime, DROP COLUMN IF EXISTS tamano_bytes, DROP COLUMN IF EXISTS id_usuario_registro, DROP COLUMN IF EXISTS fecha_registro;
            ALTER TABLE pago DROP COLUMN IF EXISTS id_usuario_registro, DROP COLUMN IF EXISTS id_usuario_conciliador, DROP COLUMN IF EXISTS fecha_conciliacion,
                DROP COLUMN IF EXISTS observacion_conciliacion, DROP COLUMN IF EXISTS numero_transaccion, DROP COLUMN IF EXISTS pasarela_transaccion_id;
            ALTER TABLE metodo_pago DROP COLUMN IF EXISTS tipo;
            ALTER TABLE correccion_nota DROP COLUMN IF EXISTS observacion_autorizador;
            ALTER TABLE inscripcion_modulo DROP COLUMN IF EXISTS nota_final, DROP COLUMN IF EXISTS porcentaje_asistencia;
            ALTER TABLE matricula DROP COLUMN IF EXISTS id_preinscripcion, DROP COLUMN IF EXISTS fecha_finalizacion;
            ALTER TABLE preinscripcion DROP COLUMN IF EXISTS id_arancel, DROP COLUMN IF EXISTS id_usuario_revisor, DROP COLUMN IF EXISTS fecha_revision;
            ALTER TABLE arancel DROP COLUMN IF EXISTS numero_cuotas;
        SQL);
    }
};
