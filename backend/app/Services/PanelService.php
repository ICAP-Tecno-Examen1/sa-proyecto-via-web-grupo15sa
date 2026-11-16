<?php

namespace App\Services;

use App\Models\Bitacora;
use App\Models\Matricula;
use App\Models\Usuario;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CU8 · Panel de estadísticas (requisito 8).
 *
 * El panel se arma por bloques y cada bloque depende de un permiso, de modo
 * que cada rol ve únicamente los indicadores que le corresponden:
 *
 *   reportes.administrativos -> matrículas, programas, preinscripciones, certificados
 *   reportes.financieros     -> ingresos, deudas, conciliación
 *   reportes.academicos      -> rendimiento y correcciones
 *   docencia.ver             -> módulos propios del docente
 *   estudios.propios         -> avance y cuotas del estudiante
 *   estadisticas.accesos     -> flujo de accesos al sitio
 */
class PanelService
{
    public function __construct(
        private readonly MatriculaService $matriculas,
        private readonly PagoService $pagos,
        private readonly EvaluacionService $evaluaciones,
    ) {}

    public function panel(Usuario $usuario): array
    {
        $bloques = [];

        if ($usuario->tienePermiso('reportes.administrativos')) {
            $bloques[] = $this->bloqueInstitucional();
        }
        if ($usuario->tienePermiso('reportes.financieros')) {
            $bloques[] = $this->bloqueFinanciero();
        }
        if ($usuario->tienePermiso('reportes.academicos')) {
            $bloques[] = $this->bloqueAcademico();
        }
        if ($usuario->tienePermiso('docencia.ver') && ! $usuario->tienePermiso('academico.supervisar')) {
            $bloques[] = $this->bloqueDocente($usuario);
        }
        if ($usuario->tienePermiso('estudios.propios')) {
            $bloques[] = $this->bloqueEstudiante($usuario);
        }
        if ($usuario->tienePermiso('estadisticas.accesos')) {
            $bloques[] = $this->bloqueAccesos();
        }

        return [
            'rol' => $usuario->rolActivo()?->nombre,
            'gestion' => now()->year,
            'bloques' => $bloques,
        ];
    }

    // ------------------------------------------------------------------
    // Bloques de negocio
    // ------------------------------------------------------------------

    private function bloqueInstitucional(): array
    {
        $porPrograma = DB::table('matricula as m')
            ->join('oferta_academica as o', 'o.id_oferta', '=', 'm.id_oferta')
            ->join('malla_curricular as ma', 'ma.id_malla', '=', 'o.id_malla')
            ->join('programa as p', 'p.id_programa', '=', 'ma.id_programa')
            ->where('m.estado', '<>', Matricula::ANULADA)
            ->groupBy('p.nombre')->orderByDesc('total')->limit(8)
            ->selectRaw('p.nombre AS etiqueta, COUNT(*) AS total')->get();

        $porEstado = DB::table('preinscripcion')->groupBy('estado')
            ->selectRaw('estado AS etiqueta, COUNT(*) AS total')->orderBy('estado')->get();

        return [
            'titulo' => 'Gestión institucional',
            'tarjetas' => [
                $this->tarjeta('Estudiantes matriculados', DB::table('matricula')->whereIn('estado', ['ACTIVA', 'PENDIENTE_PAGO'])->distinct()->count('id_usuario'), 'usuarios', 'Con matrícula activa o pendiente de pago'),
                $this->tarjeta('Programas activos', DB::table('programa')->where('estado', 'ACTIVO')->count(), 'libro'),
                $this->tarjeta('Ofertas abiertas', DB::table('oferta_academica')->where('estado', 'ABIERTA')->count(), 'calendario', 'Reciben preinscripciones'),
                $this->tarjeta('Docentes', $this->usuariosConRol('Docente'), 'docente'),
                $this->tarjeta('Preinscripciones por revisar', DB::table('preinscripcion')->where('estado', 'PENDIENTE')->count(), 'bandeja'),
                $this->tarjeta('Certificados emitidos', DB::table('certificado')->where('estado', 'EMITIDO')->count(), 'certificado'),
            ],
            'graficos' => [
                $this->grafico('Matrículas por programa', 'barras', $porPrograma, [['clave' => 'total', 'nombre' => 'Matrículas']]),
                $this->grafico('Preinscripciones por estado', 'dona', $this->legible($porEstado), [['clave' => 'total', 'nombre' => 'Preinscripciones']]),
            ],
        ];
    }

    private function bloqueFinanciero(): array
    {
        $pagado = "(SELECT COALESCE(SUM(pg.monto), 0) FROM pago pg WHERE pg.id_mensualidad = c.id_mensualidad AND pg.estado_conciliacion = 'CONFIRMADO')";

        $deuda = DB::table('mensualidad as c')->join('matricula as m', 'm.id_matricula', '=', 'c.id_matricula')
            ->where('c.estado', 'PENDIENTE')->where('m.estado', '<>', Matricula::ANULADA)
            ->selectRaw("COALESCE(SUM(GREATEST(c.monto_final - {$pagado}, 0)), 0) AS total,
                COALESCE(SUM(GREATEST(c.monto_final - {$pagado}, 0)) FILTER (WHERE c.fecha_vencimiento < CURRENT_DATE), 0) AS vencida")
            ->first();

        $porMes = DB::table('pago')->where('estado_conciliacion', 'CONFIRMADO')
            ->where('fecha', '>=', now()->subMonths(11)->startOfMonth())
            ->groupByRaw("to_char(fecha, 'YYYY-MM')")
            ->selectRaw("to_char(fecha, 'YYYY-MM') AS mes, SUM(monto) AS total")->pluck('total', 'mes');

        $meses = collect(CarbonPeriod::create(now()->subMonths(11)->startOfMonth(), '1 month', now()->startOfMonth()))
            ->map(fn ($mes) => [
                'etiqueta' => ucfirst($mes->locale('es')->isoFormat('MMM YY')),
                'total' => round((float) ($porMes[$mes->format('Y-m')] ?? 0), 2),
            ]);

        $porMetodo = DB::table('pago as x')->join('metodo_pago as mp', 'mp.id_metodo_pago', '=', 'x.id_metodo_pago')
            ->where('x.estado_conciliacion', 'CONFIRMADO')->groupBy('mp.nombre')
            ->selectRaw('mp.nombre AS etiqueta, SUM(x.monto) AS total')->orderByDesc('total')->get();

        return [
            'titulo' => 'Finanzas',
            'tarjetas' => [
                $this->tarjeta('Ingresos de la gestión', DB::table('pago')->where('estado_conciliacion', 'CONFIRMADO')->whereYear('fecha', now()->year)->sum('monto'), 'ingresos', 'Pagos confirmados en '.now()->year, 'moneda'),
                $this->tarjeta('Saldo por cobrar', $deuda->total, 'deuda', 'Cuotas pendientes', 'moneda'),
                $this->tarjeta('Deuda vencida', $deuda->vencida, 'alerta', 'Cuotas con fecha cumplida', 'moneda'),
                $this->tarjeta('Pagos por conciliar', DB::table('pago as x')->join('metodo_pago as mp', 'mp.id_metodo_pago', '=', 'x.id_metodo_pago')
                    ->where('x.estado_conciliacion', 'PENDIENTE')->where('mp.tipo', '<>', 'PASARELA')->count(), 'bandeja'),
                $this->tarjeta('Becas activas', DB::table('beca_estudiante')->where('estado', 'ACTIVA')->count(), 'beca'),
            ],
            'graficos' => [
                $this->grafico('Ingresos confirmados por mes', 'lineas', $meses, [['clave' => 'total', 'nombre' => 'Ingresos (Bs.)']], 'moneda'),
                $this->grafico('Ingresos por método de pago', 'dona', $porMetodo, [['clave' => 'total', 'nombre' => 'Ingresos (Bs.)']], 'moneda'),
            ],
        ];
    }

    private function bloqueAcademico(): array
    {
        $porPrograma = DB::table('inscripcion_modulo as im')
            ->join('matricula as m', 'm.id_matricula', '=', 'im.id_matricula')
            ->join('oferta_academica as o', 'o.id_oferta', '=', 'm.id_oferta')
            ->join('malla_curricular as ma', 'ma.id_malla', '=', 'o.id_malla')
            ->join('programa as p', 'p.id_programa', '=', 'ma.id_programa')
            ->whereIn('im.estado', ['APROBADO', 'REPROBADO'])
            ->groupBy('p.nombre')->orderBy('p.nombre')->limit(8)
            ->selectRaw("p.nombre AS etiqueta, COUNT(*) FILTER (WHERE im.estado = 'APROBADO') AS aprobados, COUNT(*) FILTER (WHERE im.estado = 'REPROBADO') AS reprobados")
            ->get();

        $resultados = DB::table('inscripcion_modulo')->selectRaw(
            "COUNT(*) FILTER (WHERE estado = 'APROBADO') AS aprobados, COUNT(*) FILTER (WHERE estado = 'REPROBADO') AS reprobados,
             COUNT(*) FILTER (WHERE estado = 'INSCRITO') AS en_curso, ROUND(AVG(nota_final), 1) AS promedio"
        )->first();

        return [
            'titulo' => 'Actividad académica',
            'tarjetas' => [
                $this->tarjeta('Módulos en curso', DB::table('oferta_modulo as om')->join('oferta_academica as o', 'o.id_oferta', '=', 'om.id_oferta')
                    ->where('om.estado', 'ACTIVO')->whereIn('o.estado', ['ABIERTA', 'EN_CURSO'])->count(), 'libro'),
                $this->tarjeta('Inscripciones aprobadas', $resultados->aprobados, 'aprobado'),
                $this->tarjeta('Inscripciones reprobadas', $resultados->reprobados, 'alerta'),
                $this->tarjeta('Promedio general', $resultados->promedio ?? 0, 'promedio', 'Nota final de módulos cerrados'),
                $this->tarjeta('Correcciones por autorizar', DB::table('correccion_nota')->where('estado', 'PENDIENTE')->count(), 'bandeja'),
            ],
            'graficos' => [
                $this->grafico('Aprobados y reprobados por programa', 'barras', $porPrograma, [
                    ['clave' => 'aprobados', 'nombre' => 'Aprobados'],
                    ['clave' => 'reprobados', 'nombre' => 'Reprobados'],
                ]),
            ],
        ];
    }

    private function bloqueDocente(Usuario $docente): array
    {
        $modulos = DB::table('asignacion_docente as ad')
            ->join('oferta_modulo as om', 'om.id_oferta_modulo', '=', 'ad.id_oferta_modulo')
            ->join('malla_modulo as mm', 'mm.id_malla_modulo', '=', 'om.id_malla_modulo')
            ->join('modulo as mo', 'mo.id_modulo', '=', 'mm.id_modulo')
            ->leftJoin('inscripcion_modulo as im', fn ($j) => $j->on('im.id_oferta_modulo', '=', 'om.id_oferta_modulo')->where('im.estado', '<>', 'RETIRADO'))
            ->where('ad.id_usuario_docente', $docente->id_usuario)->where('ad.estado', 'ACTIVO')
            ->groupBy('om.id_oferta_modulo', 'mo.nombre', 'om.estado')
            ->selectRaw("mo.nombre AS etiqueta, om.estado, COUNT(im.id_inscripcion_modulo) AS estudiantes,
                COUNT(*) FILTER (WHERE im.estado = 'APROBADO') AS aprobados, COUNT(*) FILTER (WHERE im.estado = 'REPROBADO') AS reprobados")
            ->get();

        return [
            'titulo' => 'Mi actividad docente',
            'tarjetas' => [
                $this->tarjeta('Módulos asignados', $modulos->count(), 'libro'),
                $this->tarjeta('Módulos en curso', $modulos->where('estado', 'ACTIVO')->count(), 'calendario'),
                $this->tarjeta('Estudiantes a cargo', $modulos->sum('estudiantes'), 'usuarios'),
                $this->tarjeta('Correcciones pendientes', DB::table('correccion_nota')->where('id_usuario_solicitante', $docente->id_usuario)->where('estado', 'PENDIENTE')->count(), 'bandeja', 'En espera del Coordinador Académico'),
            ],
            'graficos' => [
                $this->grafico('Estudiantes por módulo', 'barras', $modulos, [['clave' => 'estudiantes', 'nombre' => 'Estudiantes']]),
            ],
        ];
    }

    private function bloqueEstudiante(Usuario $estudiante): array
    {
        $matriculas = Matricula::where('id_usuario', $estudiante->id_usuario)
            ->where('estado', '<>', Matricula::ANULADA)->with('oferta.malla.programa')->get();

        $saldo = 0.0;
        $proxima = null;
        $avances = [];

        foreach ($matriculas as $matricula) {
            $cuenta = $this->pagos->estadoCuenta($matricula)['resumen'];
            $saldo += $cuenta['saldo_pendiente'];

            if ($cuenta['proxima_cuota'] && (! $proxima || $cuenta['proxima_cuota']['fecha_vencimiento'] < $proxima['fecha_vencimiento'])) {
                $proxima = $cuenta['proxima_cuota'];
            }

            $avances[] = [
                'etiqueta' => $matricula->oferta->malla->programa->nombre,
                'avance' => $this->matriculas->progreso($matricula, $this->evaluaciones)['resumen']['porcentaje_avance'],
            ];
        }

        return [
            'titulo' => 'Mi avance',
            'tarjetas' => [
                $this->tarjeta('Mis programas', $matriculas->count(), 'libro'),
                $this->tarjeta('Saldo pendiente', $saldo, 'deuda', 'Suma de cuotas por pagar', 'moneda'),
                $this->tarjeta('Próxima cuota', $proxima['saldo'] ?? 0, 'calendario', $proxima ? 'Vence el '.date('d/m/Y', strtotime($proxima['fecha_vencimiento'])) : 'Sin cuotas pendientes', 'moneda'),
                $this->tarjeta('Certificados', DB::table('certificado as c')->join('matricula as m', 'm.id_matricula', '=', 'c.id_matricula')
                    ->where('m.id_usuario', $estudiante->id_usuario)->where('c.estado', 'EMITIDO')->count(), 'certificado'),
            ],
            'graficos' => $avances ? [
                $this->grafico('Avance por programa (%)', 'barras', collect($avances), [['clave' => 'avance', 'nombre' => 'Módulos obligatorios aprobados (%)']], 'porcentaje'),
            ] : [],
        ];
    }

    private function bloqueAccesos(): array
    {
        $accesos = $this->accesos(14);

        return [
            'titulo' => 'Accesos al sitio',
            'tarjetas' => $accesos['tarjetas'],
            'graficos' => [$accesos['graficos'][0], $accesos['graficos'][1]],
        ];
    }

    // ------------------------------------------------------------------
    // Estadísticas de acceso (bitácora + contador de visitas)
    // ------------------------------------------------------------------

    public function accesos(int $dias = 30): array
    {
        $desde = now()->subDays($dias - 1)->startOfDay();
        $periodo = collect(CarbonPeriod::create($desde, now()));

        $visitas = DB::table('visita')->where('fecha', '>=', $desde)
            ->groupByRaw("to_char(fecha, 'YYYY-MM-DD')")
            ->selectRaw("to_char(fecha, 'YYYY-MM-DD') AS dia, COUNT(*) AS total")->pluck('total', 'dia');

        $logins = DB::table('bitacora')->where('fecha', '>=', $desde)
            ->whereIn('tipo', [Bitacora::LOGIN_EXITOSO, Bitacora::LOGIN_FALLIDO])
            ->groupByRaw("to_char(fecha, 'YYYY-MM-DD'), tipo")
            ->selectRaw("to_char(fecha, 'YYYY-MM-DD') AS dia, tipo, COUNT(*) AS total")->get()
            ->groupBy('dia');

        $visitasPorDia = $periodo->map(fn ($d) => [
            'etiqueta' => $d->format('d/m'),
            'total' => (int) ($visitas[$d->format('Y-m-d')] ?? 0),
        ]);

        $loginsPorDia = $periodo->map(fn ($d) => [
            'etiqueta' => $d->format('d/m'),
            'exitosos' => (int) ($logins->get($d->format('Y-m-d'))?->firstWhere('tipo', Bitacora::LOGIN_EXITOSO)?->total ?? 0),
            'fallidos' => (int) ($logins->get($d->format('Y-m-d'))?->firstWhere('tipo', Bitacora::LOGIN_FALLIDO)?->total ?? 0),
        ]);

        $paginas = DB::table('contador_visita')->orderByDesc('total')->limit(10)
            ->selectRaw("COALESCE(titulo, ruta) AS etiqueta, ruta, total, to_char(ultima_visita, 'YYYY-MM-DD HH24:MI') AS ultima_visita")->get();

        $recursos = DB::table('bitacora')->where('fecha', '>=', $desde)->whereNotNull('recurso')
            ->whereIn('tipo', [Bitacora::ACCION, Bitacora::ACCESO_DENEGADO])
            ->groupBy('recurso', 'metodo')->orderByDesc('total')->limit(10)
            ->selectRaw("metodo, recurso, COUNT(*) AS total, COUNT(*) FILTER (WHERE tipo = 'ACCESO_DENEGADO') AS denegados")->get();

        $ips = DB::table('bitacora')->where('fecha', '>=', $desde)
            ->groupBy('ip')->orderByDesc('total')->limit(10)
            ->selectRaw("ip, COUNT(*) AS total,
                COUNT(*) FILTER (WHERE tipo = 'LOGIN_EXITOSO') AS exitosos,
                COUNT(*) FILTER (WHERE tipo = 'LOGIN_FALLIDO') AS fallidos,
                COUNT(*) FILTER (WHERE tipo = 'ACCESO_DENEGADO') AS denegados,
                to_char(MAX(fecha), 'YYYY-MM-DD HH24:MI') AS ultimo")->get();

        $usuarios = DB::table('bitacora as b')->join('usuario as u', 'u.id_usuario', '=', 'b.id_usuario')
            ->where('b.fecha', '>=', $desde)->groupBy('u.id_usuario', 'u.nombres', 'u.apellidos')
            ->orderByDesc('total')->limit(10)
            ->selectRaw("u.nombres || ' ' || u.apellidos AS usuario, COUNT(*) AS total,
                COUNT(*) FILTER (WHERE b.tipo = 'LOGIN_EXITOSO') AS sesiones, to_char(MAX(b.fecha), 'YYYY-MM-DD HH24:MI') AS ultimo")->get();

        $exitosos = $loginsPorDia->sum('exitosos');
        $fallidos = $loginsPorDia->sum('fallidos');

        return [
            'dias' => $dias,
            'tarjetas' => [
                $this->tarjeta('Visitas totales', (int) DB::table('contador_visita')->sum('total'), 'visitas', 'Acumulado de todas las páginas'),
                $this->tarjeta("Visitas ({$dias} días)", $visitasPorDia->sum('total'), 'tendencia'),
                $this->tarjeta('Inicios de sesión', $exitosos, 'aprobado', "Exitosos en {$dias} días"),
                $this->tarjeta('Intentos fallidos', $fallidos, 'alerta', "Fallidos en {$dias} días"),
            ],
            'graficos' => [
                $this->grafico('Visitas por día', 'lineas', $visitasPorDia, [['clave' => 'total', 'nombre' => 'Visitas']]),
                $this->grafico('Inicios de sesión por día', 'barras', $loginsPorDia, [
                    ['clave' => 'exitosos', 'nombre' => 'Exitosos'],
                    ['clave' => 'fallidos', 'nombre' => 'Fallidos'],
                ]),
                $this->grafico('Páginas más visitadas', 'barras_horizontales', $paginas, [['clave' => 'total', 'nombre' => 'Visitas']]),
            ],
            'paginas' => $paginas,
            'recursos' => $recursos,
            'ips' => $ips,
            'usuarios' => $usuarios,
        ];
    }

    // ------------------------------------------------------------------

    private function usuariosConRol(string $rol): int
    {
        return DB::table('usuario_rol as ur')->join('rol as r', 'r.id_rol', '=', 'ur.id_rol')
            ->join('usuario as u', 'u.id_usuario', '=', 'ur.id_usuario')
            ->where('r.nombre', $rol)->where('ur.activo', true)->where('u.estado', 'ACTIVO')
            ->distinct()->count('ur.id_usuario');
    }

    private function tarjeta(string $titulo, mixed $valor, string $icono, ?string $detalle = null, string $formato = 'numero'): array
    {
        return ['titulo' => $titulo, 'valor' => round((float) $valor, 2), 'icono' => $icono, 'detalle' => $detalle, 'formato' => $formato];
    }

    private function grafico(string $titulo, string $tipo, Collection $datos, array $series, string $formato = 'numero'): array
    {
        $claves = array_column($series, 'clave');

        $datos = $datos->map(function ($fila) use ($claves) {
            $fila = (array) $fila;
            foreach ($claves as $clave) {
                $fila[$clave] = round((float) ($fila[$clave] ?? 0), 2);
            }

            return $fila;
        })->values();

        return ['titulo' => $titulo, 'tipo' => $tipo, 'series' => $series, 'datos' => $datos, 'formato' => $formato];
    }

    /** PENDIENTE_PAGO -> "Pendiente pago" */
    private function legible(Collection $filas): Collection
    {
        return $filas->map(function ($fila) {
            $fila->etiqueta = ucfirst(strtolower(str_replace('_', ' ', $fila->etiqueta)));

            return $fila;
        });
    }
}
