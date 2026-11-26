<?php

namespace Tests\Feature;

use App\Models\MetodoPagoElectronico;
use App\Models\Permiso;
use App\Models\Usuario;
use Database\Seeders\RolPermisoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Recorre, a través de la API, el flujo general del segundo parcial:
 * desde la creación del programa hasta el certificado digital y los reportes.
 *
 * Requiere la base de pruebas:  CREATE DATABASE db_icap_test;
 */
class FlujoCompletoTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private array $tokens = [];

    private function como(string $username): static
    {
        $this->tokens[$username] ??= Usuario::where('username', $username)->firstOrFail()->createToken('test')->plainTextToken;
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['Authorization' => 'Bearer '.$this->tokens[$username], 'Accept' => 'application/json']);
    }

    private function ok(TestResponse $r, int $estado = 200): array
    {
        if ($r->status() !== $estado) {
            $this->fail("Se esperaba {$estado} y llegó {$r->status()}: ".substr($r->getContent(), 0, 600));
        }

        return $r->json() ?? [];
    }

    public function test_flujo_general_del_icap(): void
    {
        Storage::fake('local');
        $pagador = ['nombres' => 'Rosa', 'apellidos' => 'Égüez', 'tipo_documento' => 'CI', 'numero_documento' => '2233445'];

        // --- 0. Seguridad: login fallido, login correcto y matriz de acceso
        $this->postJson('/api/auth/login', ['usuario' => 'director', 'password' => 'mala'])->assertStatus(422);
        $sesion = $this->ok($this->postJson('/api/auth/login', ['usuario' => 'director', 'password' => UsuarioSeeder::PASSWORD]));
        $this->assertSame('Director', $sesion['rol_activo']['nombre']);
        $this->assertNotEmpty($sesion['menu']);
        $this->assertDatabaseHas('bitacora', ['tipo' => 'LOGIN_FALLIDO']);
        $this->assertDatabaseHas('bitacora', ['tipo' => 'LOGIN_EXITOSO']);
        $this->como('estudiante')->getJson('/api/usuarios')->assertStatus(403);
        $this->como('docente')->postJson('/api/programas', [])->assertStatus(403);
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/panel')->assertStatus(401);

        // --- 1. El Director registra un usuario y le asigna rol
        $roles = collect($this->ok($this->como('director')->getJson('/api/roles')))->pluck('id_rol', 'nombre');
        $this->como('director')->postJson('/api/usuarios', ['nombres' => 'x'])->assertStatus(422)->assertJsonPath('errors.apellidos.0', 'El apellido es obligatorio.');
        $this->ok($this->como('director')->postJson('/api/usuarios', [
            'nombres' => 'Nuevo', 'apellidos' => 'Docente Prueba', 'tipo_documento' => 'CI', 'ci' => '1112223', 'correo' => 'nuevo@icap.bo',
            'username' => 'nuevo.docente', 'password' => 'Clave1234', 'password_confirmation' => 'Clave1234', 'roles' => [$roles['Docente']],
        ]), 201);

        // --- 2-3. Coordinador Académico: programa, módulos y malla
        $programa = $this->ok($this->como('coord.academico')->postJson('/api/programas', [
            'codigo' => 'OFI-01', 'nombre' => 'Ofimática Profesional', 'tipo' => 'CURSO', 'estado' => 'ACTIVO',
        ]), 201);
        $m1 = $this->ok($this->como('coord.academico')->postJson('/api/modulos', ['codigo' => 'M-WORD', 'nombre' => 'Procesador de textos', 'horas' => 40]), 201);
        $m2 = $this->ok($this->como('coord.academico')->postJson('/api/modulos', ['codigo' => 'M-EXCEL', 'nombre' => 'Hojas de cálculo', 'horas' => 60]), 201);
        $malla = $this->ok($this->como('coord.academico')->postJson('/api/mallas', [
            'id_programa' => $programa['id_programa'], 'version' => '2026-1', 'estado' => 'ACTIVO',
            'modulos' => [
                ['id_modulo' => $m1['id_modulo'], 'orden' => 1, 'obligatorio' => true],
                ['id_modulo' => $m2['id_modulo'], 'orden' => 2, 'obligatorio' => true],
            ],
        ]), 201);
        $this->assertSame(100, $malla['horas_totales']);
        $this->ok($this->como('coord.academico')->postJson("/api/mallas/{$malla['id_malla']}/versionar", ['version' => '2026-2']), 201);

        // --- 4. Oferta académica y asignación de docente
        $oferta = $this->ok($this->como('coord.academico')->postJson('/api/ofertas', [
            'id_malla' => $malla['id_malla'], 'codigo' => 'OFI-2026-A', 'modalidad' => 'PRESENCIAL',
            'fecha_inicio' => now()->addDays(10)->toDateString(), 'fecha_fin' => now()->addMonths(3)->toDateString(),
            'cupo_maximo' => 20, 'cupo_minimo' => 5, 'gestion' => now()->year,
        ]), 201);
        $this->assertSame('PLANIFICADA', $oferta['estado']);
        $this->assertCount(2, $oferta['modulos']);
        $idOferta = $oferta['id_oferta'];
        $docente = Usuario::where('username', 'docente')->first();

        foreach ($oferta['modulos'] as $modulo) {
            $this->ok($this->como('coord.academico')->postJson('/api/asignaciones-docente', [
                'id_oferta_modulo' => $modulo['id_oferta_modulo'], 'id_usuario_docente' => $docente->id_usuario, 'tipo_contrato' => 'POR_MODULO',
                'fecha_contrato' => now()->toDateString(), 'fecha_inicio' => now()->toDateString(), 'monto_honorario' => 1500,
            ]), 201);
        }

        // No se puede abrir sin costos configurados
        $this->como('coord.academico')->patchJson("/api/ofertas/{$idOferta}/estado", ['estado' => 'ABIERTA'])->assertStatus(422);

        // --- 5. Coordinador Financiero: costos y modalidades
        $this->ok($this->como('coord.financiero')->postJson('/api/aranceles', [
            'id_oferta' => $idOferta, 'concepto' => 'Pago al contado', 'monto' => 900, 'tipo_cobro' => 'PAGO_UNICO', 'estado' => 'ACTIVO',
        ]), 201);
        $plan = $this->ok($this->como('coord.financiero')->postJson('/api/aranceles', [
            'id_oferta' => $idOferta, 'concepto' => 'Tres mensualidades', 'monto' => 1000, 'tipo_cobro' => 'MENSUALIDAD', 'numero_cuotas' => 3, 'estado' => 'ACTIVO',
        ]), 201);
        $this->ok($this->como('coord.academico')->patchJson("/api/ofertas/{$idOferta}/estado", ['estado' => 'ABIERTA']));

        // --- 6-7. Catálogo público, registro del estudiante y preinscripción
        $this->app['auth']->forgetGuards();
        $catalogo = $this->ok($this->withHeaders(['Authorization' => ''])->getJson('/api/publico/catalogo'));
        $this->assertCount(1, $catalogo['ofertas']);
        $this->assertEquals(900, $catalogo['ofertas'][0]['costo_desde']);

        $registro = $this->ok($this->postJson('/api/auth/registro', [
            'nombres' => 'Camila', 'apellidos' => 'Rojas Vaca', 'tipo_documento' => 'CI', 'ci' => '7654321', 'correo' => 'camila@correo.bo',
            'username' => 'camila', 'password' => 'Clave1234', 'password_confirmation' => 'Clave1234',
        ]), 201);
        $this->tokens['camila'] = $registro['token'];
        $this->assertSame('Estudiante', $registro['rol_activo']['nombre']);

        $pre = $this->ok($this->como('camila')->postJson('/api/preinscripciones', ['id_oferta' => $idOferta, 'id_arancel' => $plan['id_arancel']]), 201);
        $idPre = $pre['id_preinscripcion'];

        // --- 8. La Secretaria no puede aprobar con expediente incompleto: observa
        $this->como('secretaria')->postJson("/api/preinscripciones/{$idPre}/revisar", ['decision' => 'APROBADA', 'id_arancel' => $plan['id_arancel']])
            ->assertStatus(422);
        // El Coordinador Académico también revisa preinscripciones; el Docente no
        $this->como('docente')->postJson("/api/preinscripciones/{$idPre}/revisar", ['decision' => 'OBSERVADA', 'observacion' => 'Falta subir sus documentos.'])->assertStatus(403);
        $this->ok($this->como('coord.academico')->postJson("/api/preinscripciones/{$idPre}/revisar", ['decision' => 'OBSERVADA', 'observacion' => 'Falta subir sus documentos.']));

        foreach (['CI', 'TITULO', 'FOTOGRAFIA'] as $tipo) {
            $this->ok($this->como('camila')->post('/api/mi-expediente', ['tipo' => $tipo, 'archivo' => UploadedFile::fake()->create("$tipo.pdf", 120, 'application/pdf')]), 201);
        }
        $this->ok($this->como('camila')->postJson("/api/preinscripciones/{$idPre}/reenviar"));

        // --- 9. Matrícula pendiente de pago con sus cuotas
        $aprobada = $this->ok($this->como('secretaria')->postJson("/api/preinscripciones/{$idPre}/revisar", ['decision' => 'APROBADA', 'id_arancel' => $plan['id_arancel']]));
        $idMatricula = $aprobada['matricula']['id_matricula'];
        $this->assertSame('PENDIENTE_PAGO', $aprobada['matricula']['estado']);

        // --- 10a. Beca de convenio con una empresa (la institución va en la propia beca), limitada a esta oferta
        $nuevaBeca = ['nombre' => 'Convenio Constructora Sur', 'porcentaje' => 25, 'estado' => 'INACTIVA', 'institucion_financiadora' => 'Constructora Sur S.R.L.',
            'ofertas' => [$idOferta], 'requisitos' => 'Credencial de empleado o carta de la empresa.'];
        $this->como('secretaria')->postJson('/api/becas', $nuevaBeca)->assertStatus(403);
        $becaConvenio = $this->ok($this->como('coord.financiero')->postJson('/api/becas', $nuevaBeca), 201);
        $disponibles = fn () => collect($this->ok($this->como('camila')->getJson("/api/becas/disponibles?id_matricula={$idMatricula}")));
        $this->assertNull($disponibles()->firstWhere('id_beca', $becaConvenio['id_beca']), 'Una beca inactiva no se ofrece');
        $this->ok($this->como('coord.financiero')->patchJson("/api/becas/{$becaConvenio['id_beca']}/estado"));
        $this->assertTrue($disponibles()->firstWhere('id_beca', $becaConvenio['id_beca'])['requiere_respaldo'], 'Activa se ofrece; al ser de una institución exige documento');
        $this->como('camila')->postJson("/api/matriculas/{$idMatricula}/solicitar-beca", ['id_beca' => $becaConvenio['id_beca']])->assertStatus(422); // sin el documento de la institución
        $this->ok($this->como('coord.financiero')->patchJson("/api/becas/{$becaConvenio['id_beca']}/estado"));
        $this->assertNull($disponibles()->firstWhere('id_beca', $becaConvenio['id_beca']), 'Desactivada, la beca ya no se ofrece');

        // --- 10b. Beca del 30 %: la estudiante la solicita con su respaldo y el Coordinador Financiero la revisa y aprueba
        $beca30 = $disponibles()->firstWhere('porcentaje', 30);
        $this->assertNotEmpty($beca30['requisitos'], 'Cada beca indica qué documento hay que presentar');
        $pedir = fn () => $this->como('camila')->post("/api/matriculas/{$idMatricula}/solicitar-beca", [
            'id_beca' => $beca30['id_beca'], 'motivo' => 'Ingresos familiares bajos.',
            'respaldo' => UploadedFile::fake()->create('informe-socioeconomico.pdf', 90, 'application/pdf'),
        ]);
        $solicitud = $this->ok($pedir(), 201);
        $this->assertSame('SOLICITADA', $solicitud['estado']);
        $pedir()->assertStatus(422); // no se duplica mientras está en revisión
        $this->assertEquals(1000, $this->ok($this->como('camila')->getJson('/api/mis-pagos'))[0]['monto_final'], 'Una beca solicitada todavía no descuenta');
        $this->assertSame('SOLICITADA', $this->ok($this->como('camila')->getJson('/api/mis-pagos'))[0]['solicitudes_beca'][0]['estado']);

        $this->assertSame(1, $this->ok($this->como('coord.financiero')->getJson('/api/pagos/pendientes'))['becas']);
        $this->assertNull($this->ok($this->como('secretaria')->getJson('/api/pagos/pendientes'))['becas'], 'La Secretaría no aprueba becas');
        $idSolicitud = $solicitud['id_beca_estudiante'];
        $this->como('coord.financiero')->get("/api/becas/solicitudes/{$idSolicitud}/respaldo")->assertOk();
        $this->como('docente')->get("/api/becas/solicitudes/{$idSolicitud}/respaldo")->assertStatus(403);
        $this->como('secretaria')->postJson("/api/becas/solicitudes/{$idSolicitud}/aprobar")->assertStatus(403);
        $this->ok($this->como('coord.financiero')->postJson("/api/becas/solicitudes/{$idSolicitud}/aprobar", ['observacion' => 'Informe verificado.']));
        $this->assertSame(0, $this->ok($this->como('coord.financiero')->getJson('/api/pagos/pendientes'))['becas']);

        // La matrícula aparece en «Cuotas por cobrar» con su próxima cuota ya rebajada por la beca
        $deudora = collect($this->ok($this->como('secretaria')->getJson('/api/cuentas-por-cobrar?q=camila'))['filas'])->firstWhere('id_matricula', $idMatricula);
        $this->assertSame(3, $deudora['cuotas_pendientes']);
        $this->assertEquals(233.33, $deudora['proxima_cuota']['saldo']);

        $misPagos = $this->ok($this->como('camila')->getJson('/api/mis-pagos'));
        $cuotas = $misPagos[0]['cuenta']['cuotas'];
        $this->assertCount(3, $cuotas);
        $this->assertEquals(700, $misPagos[0]['monto_final']);
        $this->assertEquals(233.33, $cuotas[0]['monto_final']);

        // --- 11-13. Pago inicial con comprobante; el Coordinador Financiero lo confirma
        $metodos = collect($this->ok($this->como('camila')->getJson('/api/metodos-pago/disponibles')));
        $this->assertNotNull($metodos->firstWhere('tipo', 'EFECTIVO'), 'El estudiante puede avisar que pagará en efectivo');
        $pago = $this->ok($this->como('camila')->post('/api/pagos', [
            'id_mensualidad' => $cuotas[0]['id_mensualidad'], 'id_metodo_pago' => $metodos->firstWhere('tipo', 'COMPROBANTE')['id_metodo_pago'],
            'monto' => 233.33, 'referencia' => 'TRX-001', 'parentesco_pagador' => 'Madre', 'pagador' => $pagador,
            'comprobante' => UploadedFile::fake()->create('comprobante.pdf', 80, 'application/pdf'),
        ]), 201);
        $this->como('docente')->postJson("/api/pagos/{$pago['id_pago']}/conciliar", ['decision' => 'CONFIRMADO'])->assertStatus(403);
        // El comprobante llega como aviso a la Secretaría y al Coordinador Financiero; cualquiera de los dos lo confirma
        $this->assertSame(1, $this->ok($this->como('secretaria')->getJson('/api/pagos/pendientes'))['comprobantes']);
        $this->assertSame(1, $this->ok($this->como('coord.financiero')->getJson('/api/pagos/pendientes'))['comprobantes']);
        $this->como('camila')->getJson('/api/pagos/pendientes')->assertStatus(403);
        $this->ok($this->como('secretaria')->postJson("/api/pagos/{$pago['id_pago']}/conciliar", ['decision' => 'CONFIRMADO']));

        // --- 13-14. Matrícula activa e inscripción automática en los módulos obligatorios
        $detalle = $this->ok($this->como('secretaria')->getJson("/api/matriculas/{$idMatricula}"));
        $this->assertSame('ACTIVA', $detalle['estado']);
        $this->assertSame('PAGADA', $detalle['cuenta']['cuotas'][0]['estado']);
        $this->assertCount(2, $detalle['progreso']['modulos']);

        // --- Stripe para la segunda cuota: API simulada con Http::fake (mismo camino que en producción)
        MetodoPagoElectronico::query()->update(['stripe_clave_secreta' => 'sk_test_prueba', 'stripe_secreto_webhook' => 'whsec_prueba', 'activo' => true]);
        Http::fake([
            'api.stripe.com/v1/checkout/sessions/*' => Http::response(['id' => 'cs_test_icap', 'payment_status' => 'unpaid', 'status' => 'open']),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_icap', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_icap']),
        ]);
        $enLinea = $this->ok($this->como('camila')->postJson('/api/pagos/en-linea', ['id_mensualidad' => $cuotas[1]['id_mensualidad'], 'pagador' => $pagador]), 201);
        $this->assertStringStartsWith('https://checkout.stripe.com/', $enLinea['pasarela']['url']);
        $this->assertSame('PRUEBA', $enLinea['pasarela']['modo']);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/checkout/sessions')
            && $r['line_items'][0]['price_data']['unit_amount'] === (int) round($enLinea['pasarela']['monto'] * 100)
            && $r->hasHeader('Authorization', 'Bearer sk_test_prueba'));
        // Mientras Stripe no confirme, el pago sigue pendiente
        $this->assertSame('PENDIENTE', $this->ok($this->como('camila')->getJson("/api/pagos/{$enLinea['pago']['id_pago']}/estado"))['estado']);

        // Webhook firmado como lo firma Stripe (HMAC-SHA256 de "t.cuerpo" con whsec_…)
        $evento = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_test_icap', 'payment_status' => 'paid', 'payment_intent' => 'pi_test_icap',
            'client_reference_id' => $enLinea['pago']['numero_transaccion'], 'metadata' => ['id_pago' => (string) $enLinea['pago']['id_pago']],
        ]]]);
        $webhook = function (string $secreto) use ($evento) {
            $this->app['auth']->forgetGuards(); // el webhook llega sin sesión
            $marca = time();

            return $this->call('POST', '/api/pagos/stripe/webhook', [], [], [],
                ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t={$marca},v1=".hash_hmac('sha256', "{$marca}.{$evento}", $secreto)], $evento);
        };
        $webhook('whsec_falso')->assertStatus(400);
        $webhook('whsec_prueba')->assertOk();
        $webhook('whsec_prueba')->assertOk(); // repetido: idempotente
        $this->assertSame('CONFIRMADO', $this->ok($this->como('camila')->getJson("/api/pagos/{$enLinea['pago']['id_pago']}/estado"))['estado']);

        // Tercera cuota en efectivo: la estudiante avisa que pagará en caja y la Secretaría cobra
        $efectivo = $metodos->firstWhere('tipo', 'EFECTIVO');
        $pago3 = $this->ok($this->como('camila')->postJson('/api/pagos', [
            'id_mensualidad' => $cuotas[2]['id_mensualidad'], 'id_metodo_pago' => $efectivo['id_metodo_pago'], 'monto' => 1, 'pagador' => $pagador,
        ]), 201);
        $this->assertSame('PENDIENTE', $pago3['estado_conciliacion']);
        $this->assertEquals($cuotas[2]['monto_final'], $pago3['monto'], 'El aviso de efectivo es por el saldo completo de la cuota');
        $this->assertSame(1, $this->ok($this->como('secretaria')->getJson('/api/pagos/pendientes'))['efectivo']);
        $this->ok($this->como('secretaria')->postJson("/api/pagos/{$pago3['id_pago']}/conciliar", ['decision' => 'CONFIRMADO']));
        $this->assertSame(0, $this->ok($this->como('secretaria')->getJson('/api/pagos/pendientes'))['efectivo']);

        // Cuotas por cobrar: con las tres cuotas pagadas, la matrícula ya no figura como deudora
        $porCobrar = $this->ok($this->como('coord.financiero')->getJson('/api/cuentas-por-cobrar'));
        $this->assertNull(collect($porCobrar['filas'])->firstWhere('id_matricula', $idMatricula));
        $this->ok($this->como('secretaria')->getJson('/api/cuentas-por-cobrar?situacion=vencidas'));
        $this->como('camila')->getJson('/api/cuentas-por-cobrar')->assertStatus(403);

        // Roles: si el Director marca permisos de más a un rol base, puede devolverlo a la matriz original
        $rolDocente = collect($this->ok($this->como('director')->getJson('/api/roles')))->firstWhere('nombre', 'Docente');
        $extra = Permiso::where('accion', 'pagos.ver')->value('id_permiso');
        $this->ok($this->como('director')->putJson("/api/roles/{$rolDocente['id_rol']}", ['nombre' => 'Docente', 'permisos' => [...$rolDocente['permisos'], $extra]]));
        $this->como('secretaria')->postJson("/api/roles/{$rolDocente['id_rol']}/restablecer")->assertStatus(403);
        $this->ok($this->como('director')->postJson("/api/roles/{$rolDocente['id_rol']}/restablecer"));
        $this->assertCount(count(RolPermisoSeeder::MATRIZ['Docente'][1]), collect($this->ok($this->como('director')->getJson('/api/roles')))->firstWhere('nombre', 'Docente')['permisos']);

        // --- 15. El docente registra asistencia, evaluaciones y notas en sus dos módulos
        $modulos = $this->ok($this->como('docente')->getJson('/api/docencia/modulos'));
        $this->assertCount(2, $modulos);
        $this->como('docente2')->getJson("/api/docencia/modulos/{$modulos[0]['id_oferta_modulo']}")->assertStatus(403);
        $idNota = null;

        foreach ($modulos as $modulo) {
            $om = $modulo['id_oferta_modulo'];
            $planilla = $this->ok($this->como('docente')->getJson("/api/docencia/modulos/{$om}"));
            $inscripcion = $planilla['estudiantes'][0]['id_inscripcion_modulo'];

            $this->ok($this->como('docente')->putJson("/api/docencia/modulos/{$om}/asistencia", [
                'fecha' => now()->toDateString(), 'asistencias' => [['id_inscripcion_modulo' => $inscripcion, 'estado' => 'PRESENTE']],
            ]));
            $this->ok($this->como('docente')->postJson("/api/docencia/modulos/{$om}/evaluaciones", ['nombre' => 'Práctico', 'tipo' => 'PRACTICO', 'ponderacion' => 40, 'fecha' => now()->toDateString()]), 201);
            $this->como('docente')->postJson("/api/docencia/modulos/{$om}/evaluaciones", ['nombre' => 'Excede', 'tipo' => 'EXAMEN', 'ponderacion' => 70, 'fecha' => now()->toDateString()])->assertStatus(422);
            $planilla = $this->ok($this->como('docente')->postJson("/api/docencia/modulos/{$om}/evaluaciones", ['nombre' => 'Examen final', 'tipo' => 'EXAMEN_FINAL', 'ponderacion' => 60, 'fecha' => now()->toDateString()]), 201);

            foreach ($planilla['evaluaciones'] as $i => $evaluacion) {
                $planilla = $this->ok($this->como('docente')->putJson("/api/docencia/evaluaciones/{$evaluacion['id_evaluacion']}/notas", [
                    'notas' => [['id_inscripcion_modulo' => $inscripcion, 'calificacion' => $i === 0 ? 80 : 30]],
                ]));
            }

            // 80*0.4 + 30*0.6 = 50 -> reprobado
            $planilla = $this->ok($this->como('docente')->postJson("/api/docencia/modulos/{$om}/cerrar"));
            $this->assertEquals(50, $planilla['estudiantes'][0]['nota_final']);
            $this->assertSame('REPROBADO', $planilla['estudiantes'][0]['estado']);
            $final = collect($planilla['evaluaciones'])->firstWhere('tipo', 'EXAMEN_FINAL');
            $idNota[$om] = $planilla['estudiantes'][0]['notas'][$final['id_evaluacion']]['id_nota'];

            // Cerrado: ya no admite cambios directos
            $this->como('docente')->putJson("/api/docencia/evaluaciones/{$final['id_evaluacion']}/notas", ['notas' => [['id_inscripcion_modulo' => $inscripcion, 'calificacion' => 90]]])->assertStatus(422);
        }

        // --- 16-17. Corrección de nota: el docente solicita, el Coordinador Académico aprueba
        foreach ($idNota as $nota) {
            $correccion = $this->ok($this->como('docente')->postJson('/api/correcciones', ['id_nota' => $nota, 'nota_nueva' => 70, 'motivo' => 'Error de transcripción del examen final.']), 201);
            $this->como('docente')->postJson("/api/correcciones/{$correccion['id_correccion']}/resolver", ['aprobar' => true])->assertStatus(403);
            $this->ok($this->como('coord.academico')->postJson("/api/correcciones/{$correccion['id_correccion']}/resolver", ['aprobar' => true]));
        }

        // --- 18. El estudiante consulta sus resultados (80*0.4 + 70*0.6 = 74 -> aprobado)
        $estudios = $this->ok($this->como('camila')->getJson("/api/mis-estudios/{$idMatricula}"));
        $this->assertEquals(74, $estudios['progreso']['modulos'][0]['nota_final']);
        $this->assertEquals(100, $estudios['progreso']['resumen']['porcentaje_avance']);

        // --- 19-20. Finalización y validación de requisitos
        $this->ok($this->como('coord.academico')->postJson("/api/matriculas/{$idMatricula}/finalizar"));
        $this->como('director')->postJson("/api/certificacion/{$idMatricula}/emitir")->assertStatus(422);
        $this->como('secretaria')->postJson("/api/certificacion/{$idMatricula}/validar", ['tipo' => 'ACADEMICO', 'estado' => 'APROBADO'])->assertStatus(403);
        $this->ok($this->como('coord.academico')->postJson("/api/certificacion/{$idMatricula}/validar", ['tipo' => 'ACADEMICO', 'estado' => 'APROBADO']));
        $this->ok($this->como('coord.financiero')->postJson("/api/certificacion/{$idMatricula}/validar", ['tipo' => 'FINANCIERO', 'estado' => 'APROBADO']));
        $this->ok($this->como('secretaria')->postJson("/api/certificacion/{$idMatricula}/validar", ['tipo' => 'DOCUMENTAL', 'estado' => 'APROBADO']));

        // --- 21-23. El Director emite; el estudiante descarga; verificación pública
        $emitido = $this->ok($this->como('director')->postJson("/api/certificacion/{$idMatricula}/emitir"));
        $codigo = $emitido['certificado']['codigo_unico'];
        $certificados = $this->ok($this->como('camila')->getJson('/api/mis-certificados'));
        $this->assertCount(1, $certificados);
        $pdf = $this->como('camila')->get("/api/certificados/{$certificados[0]['id_certificado']}/pdf");
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->streamedContent());

        $this->app['auth']->forgetGuards();
        $verificacion = $this->ok($this->withHeaders(['Authorization' => ''])->getJson("/api/publico/certificados/{$codigo}"));
        $this->assertTrue($verificacion['valido']);
        $this->getJson('/api/publico/certificados/NO-EXISTE')->assertStatus(404);

        // --- 24. Repositorio: certificado y comprobante archivados; acceso según rol
        $repoDirector = $this->ok($this->como('director')->getJson('/api/documentos'));
        $this->assertGreaterThanOrEqual(5, $repoDirector['total']);
        $financiero = collect($this->ok($this->como('coord.financiero')->getJson('/api/documentos'))['data'])->pluck('categoria')->unique()->values()->all();
        $this->assertSame(['FINANCIERO'], $financiero);
        $this->assertSame(0, $this->ok($this->como('docente')->getJson('/api/documentos'))['total']);

        // --- 25. Panel, reportes, búsqueda, visitas y bitácora
        $panel = $this->ok($this->como('director')->getJson('/api/panel'));
        $this->assertGreaterThanOrEqual(4, count($panel['bloques']));
        foreach (['coord.academico', 'coord.financiero', 'secretaria', 'docente', 'camila'] as $u) {
            $this->ok($this->como($u)->getJson('/api/panel'));
        }

        foreach (collect($this->ok($this->como('director')->getJson('/api/reportes'))['reportes'])->pluck('clave') as $clave) {
            $this->ok($this->como('director')->getJson("/api/reportes/{$clave}?gestion=".now()->year));
        }
        foreach (['mis_calificaciones', 'mi_asistencia'] as $clave) {
            $this->assertNotEmpty($this->ok($this->como('docente')->getJson("/api/reportes/{$clave}"))['filas']);
        }
        $this->como('docente')->getJson('/api/reportes/ingresos')->assertStatus(403);
        $this->assertEquals(700, collect($this->ok($this->como('coord.financiero')->getJson('/api/reportes/ingresos'))['filas'])->sum('monto'));
        $this->como('director')->get('/api/reportes/matriculas?formato=csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringStartsWith('%PDF', $this->como('director')->get('/api/reportes/matriculas?formato=pdf')->assertOk()->getContent());

        $this->assertGreaterThan(0, $this->ok($this->como('director')->getJson('/api/busqueda?q=camila'))['total']);
        $visita = $this->ok($this->postJson('/api/visitas', ['ruta' => '/catalogo', 'titulo' => 'Catálogo']));
        $visita = $this->ok($this->postJson('/api/visitas', ['ruta' => '/catalogo', 'titulo' => 'Catálogo']));
        $this->assertSame(2, $visita['total']);

        $this->ok($this->como('director')->getJson('/api/estadisticas/accesos'));
        $this->assertGreaterThan(10, $this->ok($this->como('director')->getJson('/api/bitacora'))['total']);
    }
}
