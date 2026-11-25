<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guardia: las pruebas usan RefreshDatabase (borra todas las tablas).
 * Este test falla si alguien hace que apunten a la base compartida del grupo.
 */
class ConfiguracionPruebasTest extends TestCase
{
    public function test_las_pruebas_usan_solo_la_base_local(): void
    {
        $this->assertSame('127.0.0.1', config('database.connections.pgsql.host'));
        $this->assertSame('db_icap_test', config('database.connections.pgsql.database'));
    }
}
