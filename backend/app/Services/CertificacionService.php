<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Certificado;
use App\Models\InscripcionModulo;
use App\Models\Matricula;
use App\Models\Mensualidad;
use App\Models\Pago;
use App\Models\Usuario;
use App\Models\ValidacionRequisito;
use App\Support\Frontend;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CU6 · Validación de requisitos y emisión de certificados digitales.
 *
 * Camino del certificado:
 *   matrícula FINALIZADA
 *     -> Coordinador Académico valida el requisito ACADEMICO
 *     -> Coordinador Financiero valida el requisito FINANCIERO
 *     -> Secretaria valida el requisito DOCUMENTAL
 *     -> Director autoriza -> PDF con código único y QR de verificación pública
 *
 * Cada validación se apoya en una verificación automática del sistema: nadie
 * puede aprobar un requisito que los datos no respaldan.
 */
class CertificacionService
{
    public function __construct(
        private readonly DocumentoService $documentos,
        private readonly MatriculaService $matriculas,
        private readonly CertificadoPdfService $pdf,
    ) {}

    /** Permiso que habilita a validar cada tipo de requisito. */
    public const PERMISOS = [
        ValidacionRequisito::ACADEMICO => 'requisitos.academico',
        ValidacionRequisito::FINANCIERO => 'requisitos.financiero',
        ValidacionRequisito::DOCUMENTAL => 'requisitos.documental',
    ];

    // ------------------------------------------------------------------
    // Verificaciones automáticas
    // ------------------------------------------------------------------

    /** @return array<string, array{cumple: bool, detalle: array<int, string>}> */
    public function verificaciones(Matricula $matricula): array
    {
        return [
            ValidacionRequisito::ACADEMICO => $this->verificarAcademico($matricula),
            ValidacionRequisito::FINANCIERO => $this->verificarFinanciero($matricula),
            ValidacionRequisito::DOCUMENTAL => $this->verificarDocumental($matricula),
        ];
    }

    /**
     * Normas académicas del certificado: TODAS las materias obligatorias aprobadas (y las
     * optativas que cursó), cada una con nota mínima y asistencia mínima (70 %). Una materia
     * reprobada cuenta solo si el estudiante la volvió a cursar y la aprobó (recuperación).
     */
    private function verificarAcademico(Matricula $matricula): array
    {
        $situacion = $this->matriculas->situacionMaterias($matricula);
        $minimaNota = config('icap.academico.nota_minima_aprobacion');
        $minimaAsistencia = config('icap.academico.asistencia_minima');
        $detalle = ["• Normas: nota mínima {$minimaNota} y asistencia mínima {$minimaAsistencia}% en cada materia."];
        $cumple = $situacion->where('obligatorio', true)->isNotEmpty();

        foreach ($situacion as $m) {
            $exigida = $m['obligatorio'] || $m['intentos'] > 0;
            $recuperada = $m['intentos'] > 1 ? ' (recuperada)' : '';

            if ($m['estado'] === InscripcionModulo::APROBADO) {
                $detalle[] = "✔ {$m['materia']}: aprobada{$recuperada} con {$m['nota_final']} y {$m['porcentaje_asistencia']}% de asistencia";
            } elseif ($exigida) {
                $cumple = false;
                $detalle[] = match ($m['estado']) {
                    InscripcionModulo::REPROBADO => "✘ {$m['materia']}: reprobada (nota {$m['nota_final']}, asistencia {$m['porcentaje_asistencia']}%). Debe recuperarla.",
                    InscripcionModulo::INSCRITO => "✘ {$m['materia']}: en curso, aún no aprobada",
                    default => "✘ {$m['materia']}: no cursada",
                };
            }
        }

        return ['cumple' => $cumple, 'detalle' => $detalle];
    }

    /** Sin cuotas pendientes ni pagos por conciliar. */
    private function verificarFinanciero(Matricula $matricula): array
    {
        $pendientes = $matricula->mensualidades()->where('estado', Mensualidad::PENDIENTE)->get();
        $saldo = round($pendientes->sum(fn (Mensualidad $c) => $c->saldo()), 2);
        $porConciliar = $matricula->pagos()->where('estado_conciliacion', Pago::PENDIENTE)->count();

        $detalle = [
            ($saldo <= 0 ? '✔' : '✘').' Saldo pendiente: Bs. '.number_format($saldo, 2),
            ($pendientes->isEmpty() ? '✔' : '✘').' Cuotas sin pagar: '.$pendientes->count(),
        ];

        if ($porConciliar > 0) {
            $detalle[] = "• Pagos en espera de conciliación: {$porConciliar}";
        }

        return ['cumple' => $pendientes->isEmpty() && $saldo <= 0, 'detalle' => $detalle];
    }

    /** Expediente con todos los documentos requeridos. */
    private function verificarDocumental(Matricula $matricula): array
    {
        $faltantes = $this->documentos->requisitosFaltantes($matricula->usuario);
        $detalle = [];

        foreach (config('icap.documentos_requeridos') as $tipo => $etiqueta) {
            $detalle[] = (isset($faltantes[$tipo]) ? '✘' : '✔').' '.$etiqueta;
        }

        return ['cumple' => empty($faltantes), 'detalle' => $detalle];
    }

    // ------------------------------------------------------------------
    // Validación de requisitos
    // ------------------------------------------------------------------

    public function validar(Matricula $matricula, string $tipo, string $estado, ?string $observacion, Usuario $validador): ValidacionRequisito
    {
        if ($matricula->estado !== Matricula::FINALIZADA) {
            throw new ReglaNegocioException('Solo se validan requisitos de estudiantes que finalizaron su programa.');
        }

        if ($this->certificadoVigente($matricula)) {
            throw new ReglaNegocioException('El certificado ya fue emitido; las validaciones no pueden modificarse.');
        }

        if ($estado === ValidacionRequisito::APROBADO && ! $this->verificaciones($matricula)[$tipo]['cumple']) {
            throw new ReglaNegocioException('El sistema detecta que este requisito aún no se cumple. Revise el detalle y regístrelo como observado.');
        }

        return ValidacionRequisito::updateOrCreate(
            ['id_matricula' => $matricula->id_matricula, 'tipo' => $tipo],
            [
                'estado' => $estado,
                'observacion' => $observacion,
                'id_usuario_validador' => $validador->id_usuario,
                'fecha_validacion' => now(),
            ],
        );
    }

    // ------------------------------------------------------------------
    // Emisión
    // ------------------------------------------------------------------

    /** El Director autoriza la emisión: se genera el PDF con código único y QR. */
    public function emitir(Matricula $matricula, Usuario $director): Certificado
    {
        if ($matricula->estado !== Matricula::FINALIZADA) {
            throw new ReglaNegocioException('El estudiante aún no finalizó su programa.');
        }

        if ($this->certificadoVigente($matricula)) {
            throw new ReglaNegocioException('Esta matrícula ya tiene un certificado emitido.');
        }

        $aprobadas = $matricula->validaciones()->where('estado', ValidacionRequisito::APROBADO)->pluck('tipo');
        $faltan = array_diff(ValidacionRequisito::TIPOS, $aprobadas->all());

        if ($faltan) {
            throw new ReglaNegocioException('Faltan validaciones aprobadas: '.Str::lower(implode(', ', $faltan)).'.');
        }

        // Los datos pudieron cambiar después de la validación: se comprueba otra vez.
        foreach ($this->verificaciones($matricula) as $tipo => $resultado) {
            if (! $resultado['cumple']) {
                throw new ReglaNegocioException('El requisito '.Str::lower($tipo).' dejó de cumplirse. Debe validarse nuevamente.');
            }
        }

        return DB::transaction(function () use ($matricula, $director) {
            $codigo = $this->nuevoCodigo($matricula);

            $certificado = Certificado::create([
                'id_matricula' => $matricula->id_matricula,
                'id_usuario_autorizador' => $director->id_usuario,
                'codigo_unico' => $codigo,
                'codigo_qr' => Frontend::url('/verificar/'.$codigo),
                'tipo' => Certificado::TIPO_APROBACION,
                'estado' => Certificado::EMITIDO,
                'fecha_emision' => now()->toDateString(),
            ]);

            $ruta = $this->generarPdf($certificado);
            $certificado->update(['url_archivo' => $ruta]);

            // CU7: el certificado queda archivado en el expediente del estudiante.
            $this->documentos->registrar([
                'nombre' => 'Certificado '.$codigo,
                'descripcion' => 'Certificado digital de '.$matricula->oferta->malla->programa->nombre.'.',
                'tipo' => 'CERTIFICADO',
                'categoria' => 'ACADEMICO',
                'gestion' => $matricula->oferta->gestion,
                'url_archivo' => $ruta,
                'tipo_mime' => 'application/pdf',
            ], null, $director, [$matricula->id_usuario]);

            return $certificado;
        });
    }

    public function anular(Certificado $certificado): Certificado
    {
        if ($certificado->estado === Certificado::ANULADO) {
            throw new ReglaNegocioException('El certificado ya está anulado.');
        }

        $certificado->update(['estado' => Certificado::ANULADO]);

        return $certificado;
    }

    /** Ruta del PDF en el disco privado; lo vuelve a generar si el archivo no existe. */
    public function rutaPdf(Certificado $certificado): string
    {
        if (! $certificado->url_archivo || ! Storage::exists($certificado->url_archivo)) {
            $certificado->update(['url_archivo' => $this->generarPdf($certificado)]);
        }

        return $certificado->url_archivo;
    }

    /** Datos que se muestran en el PDF y en la verificación pública. */
    public function datos(Certificado $certificado): array
    {
        $certificado->loadMissing(['matricula.usuario', 'matricula.oferta.malla.programa', 'autorizador']);
        $matricula = $certificado->matricula;
        $oferta = $matricula->oferta;

        $modulos = $matricula->inscripciones()
            ->where('estado', InscripcionModulo::APROBADO)
            ->with('ofertaModulo.mallaModulo.modulo')->get()
            ->sortBy(fn ($i) => $i->ofertaModulo->mallaModulo->orden)
            ->map(fn ($i) => [
                'nombre' => $i->ofertaModulo->mallaModulo->modulo->nombre,
                'horas' => $i->ofertaModulo->mallaModulo->modulo->horas,
                'nota_final' => $i->nota_final,
            ])->values();

        return [
            'codigo_unico' => $certificado->codigo_unico,
            'estado' => $certificado->estado,
            'valido' => $certificado->estado === Certificado::EMITIDO,
            'tipo' => $certificado->tipo,
            'fecha_emision' => $certificado->fecha_emision->format('Y-m-d'),
            'url_verificacion' => $certificado->codigo_qr,
            'estudiante' => $matricula->usuario->nombre_completo,
            'documento' => $matricula->usuario->ci,
            'programa' => $oferta->malla->programa->nombre,
            'tipo_programa' => $oferta->malla->programa->tipo,
            'oferta' => $oferta->codigo,
            'gestion' => $oferta->gestion,
            'modalidad' => $oferta->modalidad,
            'horas_totales' => $oferta->malla->horas_totales,
            'fecha_inicio' => $oferta->fecha_inicio?->format('Y-m-d'),
            'fecha_fin' => $oferta->fecha_fin?->format('Y-m-d'),
            'promedio' => $modulos->isNotEmpty() ? round($modulos->avg('nota_final'), 2) : null,
            'modulos' => $modulos,
            'autorizado_por' => $certificado->autorizador?->nombre_completo,
            'institucion' => config('icap.institucion'),
        ];
    }

    // ------------------------------------------------------------------

    private function certificadoVigente(Matricula $matricula): bool
    {
        return $matricula->certificados()->where('estado', Certificado::EMITIDO)->exists();
    }

    private function nuevoCodigo(Matricula $matricula): string
    {
        do {
            $codigo = 'ICAP-'.$matricula->oferta->gestion.'-'.Str::upper(Str::random(8));
        } while (Certificado::where('codigo_unico', $codigo)->exists());

        return $codigo;
    }

    private function generarPdf(Certificado $certificado): string
    {
        $datos = $this->datos($certificado);
        $ruta = 'certificados/'.$certificado->codigo_unico.'.pdf';

        // Formato oficial con la plantilla vigente (Certificación › Plantilla del certificado).
        Storage::put($ruta, $this->pdf->certificado($certificado, $datos));

        return $ruta;
    }
}
