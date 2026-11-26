<?php

namespace Tests\Feature;

use App\Exceptions\ReglaNegocioException;
use App\Models\InscripcionModulo;
use App\Models\Matricula;
use App\Models\Modulo;
use App\Models\OfertaAcademica;
use App\Models\OfertaModulo;
use App\Models\Programa;
use App\Models\Usuario;
use App\Models\ValidacionRequisito;
use App\Services\CertificacionService;
use App\Services\EvaluacionService;
use App\Services\MallaService;
use App\Services\OfertaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Normas del certificado: un curso se finaliza desde la oferta cuando los docentes cerraron
 * todas sus materias; para el certificado hay que aprobar TODAS las materias (nota mínima y
 * asistencia mínima del 70 %). Quien reprueba una materia no recibe el certificado hasta que
 * la vuelve a cursar (en otra oferta) y la aprueba.
 */
class RecuperacionYCertificadoTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_materia_reprobada_bloquea_el_certificado_hasta_recuperarla(): void
    {
        // --- Un curso de dos materias y dos ofertas: la actual (A) y una posterior (B)
        $programa = Programa::create(['codigo' => 'REC-01', 'nombre' => 'Curso de Prueba', 'tipo' => 'CURSO', 'estado' => 'ACTIVO']);
        $m1 = Modulo::create(['codigo' => 'REC-M1', 'nombre' => 'Materia Uno', 'horas' => 20]);
        $m2 = Modulo::create(['codigo' => 'REC-M2', 'nombre' => 'Materia Dos', 'horas' => 20]);
        $malla = app(MallaService::class)->crear(['id_programa' => $programa->id_programa, 'version' => '2026-1', 'estado' => 'ACTIVO', 'modulos' => [
            ['id_modulo' => $m1->id_modulo, 'orden' => 1, 'obligatorio' => true],
            ['id_modulo' => $m2->id_modulo, 'orden' => 2, 'obligatorio' => true],
        ]]);
        $nuevaOferta = fn (string $codigo, string $inicio) => app(OfertaService::class)->crear([
            'id_malla' => $malla->id_malla, 'codigo' => $codigo, 'modalidad' => 'PRESENCIAL', 'fecha_inicio' => $inicio,
            'fecha_fin' => now()->parse($inicio)->addMonths(2)->toDateString(), 'cupo_maximo' => 20, 'cupo_minimo' => 1, 'gestion' => 2026,
        ]);
        $ofertaA = $nuevaOferta('REC-A', now()->subMonths(3)->toDateString());
        $ofertaA->update(['estado' => OfertaAcademica::EN_CURSO]);
        $ofertaB = $nuevaOferta('REC-B', now()->addMonth()->toDateString());
        $ofertaB->update(['estado' => OfertaAcademica::ABIERTA]);

        $alumno = Usuario::create(['nombres' => 'Lucía', 'apellidos' => 'Prueba', 'ci' => '5551234', 'tipo_documento' => 'CI', 'correo' => 'lucia@icap.edu.bo',
            'estado' => 'ACTIVO', 'fecha_registro' => now(), 'username' => 'lucia', 'password_hash' => Hash::make('Clave1234')]);
        $matricula = Matricula::create(['id_usuario' => $alumno->id_usuario, 'id_oferta' => $ofertaA->id_oferta, 'fecha' => now()->toDateString(),
            'estado' => Matricula::ACTIVA, 'numero_registro' => 'REC-2026-1', 'tipo_cobro' => 'PAGO_UNICO', 'monto_original' => 0, 'monto_descuento' => 0, 'monto_final' => 0]);
        $enA = $ofertaA->ofertaModulos()->with('mallaModulo')->get()->keyBy(fn ($om) => $om->mallaModulo->id_modulo);
        foreach ($enA as $om) {
            InscripcionModulo::create(['id_matricula' => $matricula->id_matricula, 'id_oferta_modulo' => $om->id_oferta_modulo, 'estado' => InscripcionModulo::INSCRITO]);
        }

        // --- No se puede finalizar el curso mientras haya materias sin cerrar
        try {
            app(OfertaService::class)->finalizarCurso($ofertaA);
            $this->fail('Debía exigir que los docentes cierren todas las materias');
        } catch (ReglaNegocioException $e) {
            $this->assertStringContainsString('Materia Uno', $e->getMessage());
        }

        // --- Los docentes cierran: aprueba Materia Uno; reprueba Materia Dos (asistencia 60 %)
        $resultado = fn (int $idModulo, string $estado, float $nota, float $asistencia) => InscripcionModulo::where('id_matricula', $matricula->id_matricula)
            ->where('id_oferta_modulo', $enA[$idModulo]->id_oferta_modulo)->update(['estado' => $estado, 'nota_final' => $nota, 'porcentaje_asistencia' => $asistencia]);
        $resultado($m1->id_modulo, InscripcionModulo::APROBADO, 80, 90);
        $resultado($m2->id_modulo, InscripcionModulo::REPROBADO, 75, 60);
        $ofertaA->ofertaModulos()->update(['estado' => OfertaModulo::CERRADO]);

        // --- «Finalizar curso» desde la oferta: finaliza al estudiante y lo marca con materias pendientes
        $resumen = app(OfertaService::class)->finalizarCurso($ofertaA);
        $this->assertSame(1, $resumen['finalizados']);
        $this->assertSame([], $resumen['aprobados']);
        $this->assertSame(['Materia Dos'], $resumen['con_pendientes'][0]['materias']);
        $this->assertSame(Matricula::FINALIZADA, $matricula->refresh()->estado);
        $this->assertSame(OfertaAcademica::FINALIZADA, $ofertaA->refresh()->estado);

        // --- Sin aprobar todas las materias no hay certificado
        $academico = fn () => app(CertificacionService::class)->verificaciones($matricula->refresh())[ValidacionRequisito::ACADEMICO];
        $this->assertFalse($academico()['cumple']);
        $this->assertStringContainsString('Debe recuperarla', implode(' ', $academico()['detalle']));

        // --- Recuperación: el Coordinador Académico la inscribe en la oferta B, que dicta la misma materia
        $token = Usuario::where('username', 'coord.academico')->first()->createToken('test')->plainTextToken;
        $api = fn () => $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json']);
        $opciones = collect($api()->getJson("/api/matriculas/{$matricula->id_matricula}/recuperacion")->assertOk()->json());
        $this->assertSame(['Materia Dos'], $opciones->pluck('materia')->all(), 'Solo se ofrece recuperar lo reprobado');
        $destino = $opciones->first()['opciones'][0]['id_oferta_modulo'];
        $this->assertSame('REC-B', $opciones->first()['opciones'][0]['oferta']);

        $idUno = $ofertaB->ofertaModulos()->whereHas('mallaModulo', fn ($q) => $q->where('id_modulo', $m1->id_modulo))->value('id_oferta_modulo');
        $api()->postJson("/api/matriculas/{$matricula->id_matricula}/recuperacion", ['id_oferta_modulo' => $idUno])->assertStatus(422); // Materia Uno ya está aprobada
        $api()->postJson("/api/matriculas/{$matricula->id_matricula}/recuperacion", ['id_oferta_modulo' => $destino])->assertStatus(201);
        $this->assertFalse($academico()['cumple'], 'Mientras la cursa, sigue sin certificado');

        // --- La aprueba en la oferta B: ahora sí cumple el requisito académico
        InscripcionModulo::where('id_matricula', $matricula->id_matricula)->where('id_oferta_modulo', $destino)
            ->update(['estado' => InscripcionModulo::APROBADO, 'nota_final' => 70, 'porcentaje_asistencia' => 75]);
        $this->assertTrue($academico()['cumple']);
        $this->assertStringContainsString('(recuperada)', implode(' ', $academico()['detalle']));
    }

    public function test_la_asistencia_minima_es_del_70_por_ciento(): void
    {
        $evaluaciones = app(EvaluacionService::class);

        $this->assertEquals(70, config('icap.academico.asistencia_minima'));
        $this->assertSame(InscripcionModulo::APROBADO, $evaluaciones->resultado(60, 70));
        $this->assertSame(InscripcionModulo::REPROBADO, $evaluaciones->resultado(60, 69.9));
        $this->assertSame(InscripcionModulo::REPROBADO, $evaluaciones->resultado(50, 100), 'También hace falta la nota mínima');
    }
}
