<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\OfertaAcademica;
use App\Models\Programa;
use App\Models\Usuario;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CU8 · Reportes administrativos, académicos y financieros.
 *
 * Cada reporte devuelve la misma estructura (titulo, columnas, filas, resumen),
 * así el cliente lo muestra, imprime o exporta sin lógica especial por reporte.
 * El acceso depende del permiso de su grupo; los reportes del docente se
 * limitan a los módulos que tiene asignados.
 */
class ReporteService
{
    /** clave => [título, grupo, permiso, filtros que admite] */
    private const CATALOGO = [
        'estudiantes' => ['Estudiantes registrados', 'Administrativos', 'reportes.administrativos', ['desde', 'hasta']],
        'preinscripciones' => ['Preinscripciones', 'Administrativos', 'reportes.administrativos', ['gestion', 'id_programa', 'id_oferta', 'desde', 'hasta']],
        'matriculas' => ['Matrículas', 'Administrativos', 'reportes.administrativos', ['gestion', 'id_programa', 'id_oferta', 'desde', 'hasta']],
        'certificados' => ['Certificados emitidos', 'Administrativos', 'reportes.administrativos', ['gestion', 'id_programa', 'id_oferta', 'desde', 'hasta']],
        'rendimiento' => ['Rendimiento académico por módulo', 'Académicos', 'reportes.academicos', ['gestion', 'id_programa', 'id_oferta']],
        'asistencia' => ['Asistencia por módulo', 'Académicos', 'reportes.academicos', ['gestion', 'id_programa', 'id_oferta', 'desde', 'hasta']],
        'aprobados_reprobados' => ['Estudiantes aprobados y reprobados', 'Académicos', 'reportes.academicos', ['gestion', 'id_programa', 'id_oferta']],
        'docentes' => ['Docentes y módulos asignados', 'Académicos', 'reportes.academicos', ['gestion', 'id_programa', 'id_oferta']],
        'ingresos' => ['Ingresos por pagos confirmados', 'Financieros', 'reportes.financieros', ['gestion', 'id_programa', 'id_oferta', 'desde', 'hasta']],
        'pagos_pendientes' => ['Pagos pendientes de conciliación', 'Financieros', 'reportes.financieros', ['gestion', 'id_programa', 'id_oferta', 'desde', 'hasta']],
        'mensualidades' => ['Mensualidades y cuotas', 'Financieros', 'reportes.financieros', ['gestion', 'id_programa', 'id_oferta', 'desde', 'hasta']],
        'deudas' => ['Deudas por estudiante', 'Financieros', 'reportes.financieros', ['gestion', 'id_programa', 'id_oferta']],
        'becas' => ['Becas asignadas', 'Financieros', 'reportes.financieros', ['gestion', 'id_programa', 'id_oferta']],
        'mis_calificaciones' => ['Calificaciones de mis módulos', 'Docente', 'reportes.docente', ['gestion', 'id_oferta']],
        'mi_asistencia' => ['Asistencia de mis módulos', 'Docente', 'reportes.docente', ['gestion', 'id_oferta']],
    ];

    /** Reportes que el rol activo puede generar, más las opciones de los filtros. */
    public function catalogo(Usuario $usuario): array
    {
        $reportes = collect(self::CATALOGO)
            ->filter(fn (array $r) => $usuario->tienePermiso($r[2]))
            ->map(fn (array $r, string $clave) => ['clave' => $clave, 'titulo' => $r[0], 'grupo' => $r[1], 'filtros' => $r[3]])
            ->values();

        return [
            'reportes' => $reportes,
            'opciones' => [
                'gestiones' => OfertaAcademica::distinct()->orderByDesc('gestion')->pluck('gestion'),
                'programas' => Programa::orderBy('nombre')->get(['id_programa', 'nombre']),
                'ofertas' => OfertaAcademica::with('malla.programa:id_programa,nombre')->orderByDesc('gestion')->get()
                    ->map(fn ($o) => [
                        'id_oferta' => $o->id_oferta,
                        'codigo' => $o->codigo,
                        'gestion' => $o->gestion,
                        'id_programa' => $o->malla->id_programa,
                        'programa' => $o->malla->programa->nombre,
                    ]),
            ],
        ];
    }

    public function generar(string $clave, array $filtros, Usuario $usuario): array
    {
        $definicion = self::CATALOGO[$clave] ?? null;

        if (! $definicion) {
            throw new ReglaNegocioException('El reporte solicitado no existe.', 404);
        }

        if (! $usuario->tienePermiso($definicion[2])) {
            throw new ReglaNegocioException('Su rol no tiene permiso para generar este reporte.', 403);
        }

        $filtros = array_filter(array_intersect_key($filtros, array_flip($definicion[3])), fn ($v) => filled($v));
        $metodo = 'reporte'.str_replace('_', '', ucwords($clave, '_'));
        $resultado = $this->{$metodo}($filtros, $usuario);

        return [
            'clave' => $clave,
            'titulo' => $definicion[0],
            'grupo' => $definicion[1],
            'filtros_aplicados' => $this->describirFiltros($filtros),
            'columnas' => $resultado['columnas'],
            'filas' => collect($resultado['filas'])->map(fn ($f) => (array) $f)->values(),
            'resumen' => $resultado['resumen'] ?? [],
            'generado' => now()->format('Y-m-d H:i:s'),
        ];
    }

    // ------------------------------------------------------------------
    // Administrativos
    // ------------------------------------------------------------------

    private function reporteEstudiantes(array $f): array
    {
        $filas = DB::table('usuario as u')
            ->whereExists(fn ($s) => $s->from('usuario_rol as ur')->join('rol as r', 'r.id_rol', '=', 'ur.id_rol')
                ->whereColumn('ur.id_usuario', 'u.id_usuario')->where('r.nombre', 'Estudiante')->where('ur.activo', true))
            ->when($f['desde'] ?? null, fn ($q, $d) => $q->whereDate('u.fecha_registro', '>=', $d))
            ->when($f['hasta'] ?? null, fn ($q, $h) => $q->whereDate('u.fecha_registro', '<=', $h))
            ->orderBy('u.apellidos')->orderBy('u.nombres')
            ->selectRaw("u.ci, u.apellidos || ', ' || u.nombres AS estudiante, u.correo, u.telefono, u.estado,
                to_char(u.fecha_registro, 'YYYY-MM-DD') AS fecha_registro,
                (SELECT COUNT(*) FROM matricula m WHERE m.id_usuario = u.id_usuario AND m.estado <> 'ANULADA') AS matriculas")
            ->get();

        return [
            'columnas' => $this->columnas(['ci' => 'Documento', 'estudiante' => 'Estudiante', 'correo' => 'Correo', 'telefono' => 'Teléfono',
                'estado' => 'Estado', 'fecha_registro' => ['Registro', 'fecha'], 'matriculas' => ['Matrículas', 'numero']]),
            'filas' => $filas,
            'resumen' => [$this->dato('Total de estudiantes', $filas->count()), $this->dato('Activos', $filas->where('estado', 'ACTIVO')->count())],
        ];
    }

    private function reportePreinscripciones(array $f): array
    {
        $filas = $this->filtrarOferta($this->baseOferta('preinscripcion as x'), $f, 'x.fecha')
            ->join('usuario as u', 'u.id_usuario', '=', 'x.id_usuario')
            ->orderByDesc('x.fecha')
            ->selectRaw("x.fecha, u.apellidos || ', ' || u.nombres AS estudiante, u.ci, p.nombre AS programa, o.codigo AS oferta, o.gestion, x.estado, x.observacion")
            ->get();

        return [
            'columnas' => $this->columnas(['fecha' => ['Fecha', 'fecha'], 'estudiante' => 'Estudiante', 'ci' => 'Documento', 'programa' => 'Programa',
                'oferta' => 'Oferta', 'gestion' => 'Gestión', 'estado' => 'Estado', 'observacion' => 'Observación']),
            'filas' => $filas,
            'resumen' => $this->conteoPorEstado($filas),
        ];
    }

    private function reporteMatriculas(array $f): array
    {
        $filas = $this->filtrarOferta($this->baseOferta('matricula as x'), $f, 'x.fecha')
            ->join('usuario as u', 'u.id_usuario', '=', 'x.id_usuario')
            ->orderByDesc('x.fecha')->orderBy('u.apellidos')
            ->selectRaw("x.numero_registro, x.fecha, u.apellidos || ', ' || u.nombres AS estudiante, u.ci, p.nombre AS programa, o.codigo AS oferta,
                o.gestion, x.tipo_cobro, x.monto_final, x.estado")
            ->get();

        return [
            'columnas' => $this->columnas(['numero_registro' => 'N.º registro', 'fecha' => ['Fecha', 'fecha'], 'estudiante' => 'Estudiante', 'ci' => 'Documento',
                'programa' => 'Programa', 'oferta' => 'Oferta', 'gestion' => 'Gestión', 'tipo_cobro' => 'Plan de pago',
                'monto_final' => ['Monto (Bs.)', 'moneda'], 'estado' => 'Estado']),
            'filas' => $filas,
            'resumen' => [...$this->conteoPorEstado($filas), $this->moneda('Monto total', $filas->where('estado', '<>', 'ANULADA')->sum('monto_final'))],
        ];
    }

    private function reporteCertificados(array $f): array
    {
        $filas = $this->filtrarOferta(
            DB::table('certificado as x')->join('matricula as m', 'm.id_matricula', '=', 'x.id_matricula')
                ->join('oferta_academica as o', 'o.id_oferta', '=', 'm.id_oferta')
                ->join('malla_curricular as ma', 'ma.id_malla', '=', 'o.id_malla')
                ->join('programa as p', 'p.id_programa', '=', 'ma.id_programa'),
            $f, 'x.fecha_emision')
            ->join('usuario as u', 'u.id_usuario', '=', 'm.id_usuario')
            ->leftJoin('usuario as a', 'a.id_usuario', '=', 'x.id_usuario_autorizador')
            ->orderByDesc('x.fecha_emision')
            ->selectRaw("x.codigo_unico, x.fecha_emision, u.apellidos || ', ' || u.nombres AS estudiante, u.ci, p.nombre AS programa, o.gestion,
                a.nombres || ' ' || a.apellidos AS autorizado_por, x.estado")
            ->get();

        return [
            'columnas' => $this->columnas(['codigo_unico' => 'Código único', 'fecha_emision' => ['Emisión', 'fecha'], 'estudiante' => 'Estudiante', 'ci' => 'Documento',
                'programa' => 'Programa', 'gestion' => 'Gestión', 'autorizado_por' => 'Autorizado por', 'estado' => 'Estado']),
            'filas' => $filas,
            'resumen' => $this->conteoPorEstado($filas),
        ];
    }

    // ------------------------------------------------------------------
    // Académicos
    // ------------------------------------------------------------------

    private function reporteRendimiento(array $f): array
    {
        $filas = $this->filtrarOferta($this->baseModulo(), $f)
            ->leftJoin('inscripcion_modulo as im', fn ($j) => $j->on('im.id_oferta_modulo', '=', 'om.id_oferta_modulo')->where('im.estado', '<>', 'RETIRADO'))
            ->groupBy('p.nombre', 'o.codigo', 'o.gestion', 'mo.nombre', 'om.estado', 'mm.orden', 'om.id_oferta_modulo')
            ->orderByDesc('o.gestion')->orderBy('o.codigo')->orderBy('mm.orden')
            ->selectRaw("p.nombre AS programa, o.codigo AS oferta, o.gestion, mo.nombre AS modulo, om.estado,
                COUNT(im.id_inscripcion_modulo) AS inscritos,
                COUNT(*) FILTER (WHERE im.estado = 'APROBADO') AS aprobados,
                COUNT(*) FILTER (WHERE im.estado = 'REPROBADO') AS reprobados,
                ROUND(AVG(im.nota_final), 2) AS promedio,
                CASE WHEN COUNT(*) FILTER (WHERE im.estado IN ('APROBADO', 'REPROBADO')) > 0
                     THEN ROUND(COUNT(*) FILTER (WHERE im.estado = 'APROBADO') * 100.0 / COUNT(*) FILTER (WHERE im.estado IN ('APROBADO', 'REPROBADO')), 1) END AS aprobacion")
            ->get();

        return [
            'columnas' => $this->columnas(['programa' => 'Programa', 'oferta' => 'Oferta', 'gestion' => 'Gestión', 'modulo' => 'Módulo', 'estado' => 'Estado',
                'inscritos' => ['Inscritos', 'numero'], 'aprobados' => ['Aprobados', 'numero'], 'reprobados' => ['Reprobados', 'numero'],
                'promedio' => ['Promedio', 'numero'], 'aprobacion' => ['Aprobación', 'porcentaje']]),
            'filas' => $filas,
            'resumen' => [
                $this->dato('Módulos', $filas->count()),
                $this->dato('Aprobados', $filas->sum('aprobados')),
                $this->dato('Reprobados', $filas->sum('reprobados')),
            ],
        ];
    }

    private function reporteAsistencia(array $f): array
    {
        return $this->asistenciaPorModulo($f, null);
    }

    private function reporteAprobadosReprobados(array $f): array
    {
        return $this->resultadosPorEstudiante($f, null);
    }

    private function asistenciaPorModulo(array $f, ?Usuario $docente): array
    {
        $filas = $this->filtrarOferta($this->baseModulo($docente), $f)
            ->join('inscripcion_modulo as im', 'im.id_oferta_modulo', '=', 'om.id_oferta_modulo')
            ->join('asistencia as a', 'a.id_inscripcion_modulo', '=', 'im.id_inscripcion_modulo')
            ->when($f['desde'] ?? null, fn ($q, $d) => $q->whereDate('a.fecha', '>=', $d))
            ->when($f['hasta'] ?? null, fn ($q, $h) => $q->whereDate('a.fecha', '<=', $h))
            ->groupBy('p.nombre', 'o.codigo', 'o.gestion', 'mo.nombre', 'mm.orden', 'om.id_oferta_modulo')
            ->orderByDesc('o.gestion')->orderBy('o.codigo')->orderBy('mm.orden')
            ->selectRaw("p.nombre AS programa, o.codigo AS oferta, o.gestion, mo.nombre AS modulo,
                COUNT(DISTINCT a.fecha) AS sesiones,
                COUNT(DISTINCT im.id_inscripcion_modulo) AS estudiantes,
                COUNT(*) FILTER (WHERE a.estado <> 'AUSENTE') AS asistencias,
                COUNT(*) FILTER (WHERE a.estado = 'AUSENTE') AS ausencias,
                ROUND(COUNT(*) FILTER (WHERE a.estado <> 'AUSENTE') * 100.0 / COUNT(*), 1) AS porcentaje")
            ->get();

        return [
            'columnas' => $this->columnas(['programa' => 'Programa', 'oferta' => 'Oferta', 'gestion' => 'Gestión', 'modulo' => 'Módulo',
                'sesiones' => ['Sesiones', 'numero'], 'estudiantes' => ['Estudiantes', 'numero'], 'asistencias' => ['Asistencias', 'numero'],
                'ausencias' => ['Ausencias', 'numero'], 'porcentaje' => ['Asistencia', 'porcentaje']]),
            'filas' => $filas,
            'resumen' => [
                $this->dato('Sesiones registradas', $filas->sum('sesiones')),
                $this->dato('Asistencia promedio', $filas->isEmpty() ? '—' : round($filas->avg('porcentaje'), 1).'%'),
            ],
        ];
    }

    private function resultadosPorEstudiante(array $f, ?Usuario $docente): array
    {
        $filas = $this->filtrarOferta($this->baseModulo($docente), $f)
            ->join('inscripcion_modulo as im', 'im.id_oferta_modulo', '=', 'om.id_oferta_modulo')
            ->join('matricula as m', 'm.id_matricula', '=', 'im.id_matricula')
            ->join('usuario as u', 'u.id_usuario', '=', 'm.id_usuario')
            ->when(! $docente, fn ($q) => $q->whereIn('im.estado', ['APROBADO', 'REPROBADO']), fn ($q) => $q->where('im.estado', '<>', 'RETIRADO'))
            ->orderByDesc('o.gestion')->orderBy('o.codigo')->orderBy('mm.orden')->orderBy('u.apellidos')
            ->selectRaw("u.apellidos || ', ' || u.nombres AS estudiante, u.ci, p.nombre AS programa, o.codigo AS oferta, mo.nombre AS modulo,
                im.nota_final, im.porcentaje_asistencia, im.estado")
            ->get();

        return [
            'columnas' => $this->columnas(['estudiante' => 'Estudiante', 'ci' => 'Documento', 'programa' => 'Programa', 'oferta' => 'Oferta', 'modulo' => 'Módulo',
                'nota_final' => ['Nota final', 'numero'], 'porcentaje_asistencia' => ['Asistencia', 'porcentaje'], 'estado' => 'Resultado']),
            'filas' => $filas,
            'resumen' => $this->conteoPorEstado($filas),
        ];
    }

    private function reporteDocentes(array $f): array
    {
        $filas = $this->filtrarOferta($this->baseModulo(), $f)
            ->join('asignacion_docente as ad', 'ad.id_oferta_modulo', '=', 'om.id_oferta_modulo')
            ->join('usuario as u', 'u.id_usuario', '=', 'ad.id_usuario_docente')
            ->orderBy('u.apellidos')->orderByDesc('o.gestion')->orderBy('mm.orden')
            ->selectRaw("u.apellidos || ', ' || u.nombres AS docente, p.nombre AS programa, o.codigo AS oferta, o.gestion, mo.nombre AS modulo,
                mo.horas, ad.tipo_contrato, ad.fecha_contrato, ad.monto_honorario, ad.estado")
            ->get();

        return [
            'columnas' => $this->columnas(['docente' => 'Docente', 'programa' => 'Programa', 'oferta' => 'Oferta', 'gestion' => 'Gestión', 'modulo' => 'Módulo',
                'horas' => ['Horas', 'numero'], 'tipo_contrato' => 'Contrato', 'fecha_contrato' => ['Fecha contrato', 'fecha'],
                'monto_honorario' => ['Honorario (Bs.)', 'moneda'], 'estado' => 'Estado']),
            'filas' => $filas,
            'resumen' => [
                $this->dato('Docentes', $filas->unique('docente')->count()),
                $this->dato('Asignaciones', $filas->count()),
                $this->moneda('Honorarios comprometidos', $filas->where('estado', 'ACTIVO')->sum('monto_honorario')),
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Financieros
    // ------------------------------------------------------------------

    private function basePagos(array $f, array $estados): Builder
    {
        return $this->filtrarOferta(
            DB::table('pago as x')->join('matricula as m', 'm.id_matricula', '=', 'x.id_matricula')
                ->join('oferta_academica as o', 'o.id_oferta', '=', 'm.id_oferta')
                ->join('malla_curricular as ma', 'ma.id_malla', '=', 'o.id_malla')
                ->join('programa as p', 'p.id_programa', '=', 'ma.id_programa'),
            $f, 'x.fecha')
            ->join('usuario as u', 'u.id_usuario', '=', 'm.id_usuario')
            ->join('metodo_pago as mp', 'mp.id_metodo_pago', '=', 'x.id_metodo_pago')
            ->join('pagador as pg', 'pg.id_pagador', '=', 'x.id_pagador')
            ->leftJoin('mensualidad as c', 'c.id_mensualidad', '=', 'x.id_mensualidad')
            ->whereIn('x.estado_conciliacion', $estados)
            ->orderByDesc('x.fecha')
            ->selectRaw("to_char(x.fecha, 'YYYY-MM-DD') AS fecha, m.numero_registro, u.apellidos || ', ' || u.nombres AS estudiante, p.nombre AS programa,
                o.codigo AS oferta, c.numero_cuota AS cuota, mp.nombre AS metodo, pg.nombres || ' ' || pg.apellidos AS pagador,
                x.referencia, x.monto, x.estado_conciliacion AS estado");
    }

    private function reporteIngresos(array $f): array
    {
        $filas = $this->basePagos($f, ['CONFIRMADO'])->get();

        return [
            'columnas' => $this->columnas(['fecha' => ['Fecha', 'fecha'], 'numero_registro' => 'Matrícula', 'estudiante' => 'Estudiante', 'programa' => 'Programa',
                'oferta' => 'Oferta', 'cuota' => ['Cuota', 'numero'], 'metodo' => 'Método', 'pagador' => 'Pagador', 'referencia' => 'Referencia',
                'monto' => ['Monto (Bs.)', 'moneda']]),
            'filas' => $filas,
            'resumen' => [
                $this->dato('Pagos confirmados', $filas->count()),
                $this->moneda('Total recaudado', $filas->sum('monto')),
                ...$filas->groupBy('metodo')->map(fn ($g, $metodo) => $this->moneda($metodo, $g->sum('monto')))->values(),
            ],
        ];
    }

    private function reportePagosPendientes(array $f): array
    {
        $filas = $this->basePagos($f, ['PENDIENTE', 'OBSERVADO'])->get();

        return [
            'columnas' => $this->columnas(['fecha' => ['Fecha', 'fecha'], 'numero_registro' => 'Matrícula', 'estudiante' => 'Estudiante', 'programa' => 'Programa',
                'cuota' => ['Cuota', 'numero'], 'metodo' => 'Método', 'pagador' => 'Pagador', 'referencia' => 'Referencia',
                'monto' => ['Monto (Bs.)', 'moneda'], 'estado' => 'Estado']),
            'filas' => $filas,
            'resumen' => [$this->dato('Pagos por conciliar', $filas->count()), $this->moneda('Monto en revisión', $filas->sum('monto'))],
        ];
    }

    /** Subconsulta con lo pagado (confirmado) de cada cuota. */
    private const PAGADO_CUOTA = "(SELECT COALESCE(SUM(pg.monto), 0) FROM pago pg WHERE pg.id_mensualidad = x.id_mensualidad AND pg.estado_conciliacion = 'CONFIRMADO')";

    private function baseCuotas(array $f): Builder
    {
        return $this->filtrarOferta(
            DB::table('mensualidad as x')->join('matricula as m', 'm.id_matricula', '=', 'x.id_matricula')
                ->join('oferta_academica as o', 'o.id_oferta', '=', 'm.id_oferta')
                ->join('malla_curricular as ma', 'ma.id_malla', '=', 'o.id_malla')
                ->join('programa as p', 'p.id_programa', '=', 'ma.id_programa'),
            $f, 'x.fecha_vencimiento')
            ->join('usuario as u', 'u.id_usuario', '=', 'm.id_usuario')
            ->where('m.estado', '<>', 'ANULADA');
    }

    private function reporteMensualidades(array $f): array
    {
        $pagado = self::PAGADO_CUOTA;

        $filas = $this->baseCuotas($f)
            ->where('x.estado', '<>', 'ANULADA')
            ->orderBy('x.fecha_vencimiento')->orderBy('u.apellidos')
            ->selectRaw("m.numero_registro, u.apellidos || ', ' || u.nombres AS estudiante, p.nombre AS programa, o.codigo AS oferta, x.numero_cuota,
                x.fecha_vencimiento, x.monto_original, x.monto_descuento, x.monto_final, {$pagado} AS pagado,
                CASE WHEN x.estado = 'PENDIENTE' THEN GREATEST(x.monto_final - {$pagado}, 0) ELSE 0 END AS saldo,
                CASE WHEN x.estado = 'PENDIENTE' AND x.fecha_vencimiento < CURRENT_DATE THEN 'VENCIDA' ELSE x.estado END AS estado")
            ->get();

        return [
            'columnas' => $this->columnas(['numero_registro' => 'Matrícula', 'estudiante' => 'Estudiante', 'programa' => 'Programa', 'oferta' => 'Oferta',
                'numero_cuota' => ['Cuota', 'numero'], 'fecha_vencimiento' => ['Vencimiento', 'fecha'], 'monto_original' => ['Monto (Bs.)', 'moneda'],
                'monto_descuento' => ['Descuento', 'moneda'], 'monto_final' => ['A pagar', 'moneda'], 'pagado' => ['Pagado', 'moneda'],
                'saldo' => ['Saldo', 'moneda'], 'estado' => 'Estado']),
            'filas' => $filas,
            'resumen' => [
                ...$this->conteoPorEstado($filas),
                $this->moneda('Total pagado', $filas->sum('pagado')),
                $this->moneda('Saldo por cobrar', $filas->sum('saldo')),
            ],
        ];
    }

    private function reporteDeudas(array $f): array
    {
        $pagado = self::PAGADO_CUOTA;

        $filas = $this->baseCuotas($f)
            ->where('x.estado', 'PENDIENTE')
            ->groupBy('m.id_matricula', 'm.numero_registro', 'u.apellidos', 'u.nombres', 'u.ci', 'u.telefono', 'u.correo', 'p.nombre', 'o.codigo')
            ->orderByDesc('saldo')
            ->selectRaw("m.numero_registro, u.apellidos || ', ' || u.nombres AS estudiante, u.ci, u.telefono, u.correo, p.nombre AS programa, o.codigo AS oferta,
                COUNT(*) AS cuotas_pendientes,
                COUNT(*) FILTER (WHERE x.fecha_vencimiento < CURRENT_DATE) AS cuotas_vencidas,
                MIN(x.fecha_vencimiento) AS vencimiento_mas_antiguo,
                SUM(GREATEST(x.monto_final - {$pagado}, 0)) AS saldo")
            ->get();

        return [
            'columnas' => $this->columnas(['numero_registro' => 'Matrícula', 'estudiante' => 'Estudiante', 'ci' => 'Documento', 'telefono' => 'Teléfono', 'correo' => 'Correo',
                'programa' => 'Programa', 'oferta' => 'Oferta', 'cuotas_pendientes' => ['Cuotas pend.', 'numero'], 'cuotas_vencidas' => ['Vencidas', 'numero'],
                'vencimiento_mas_antiguo' => ['Vence desde', 'fecha'], 'saldo' => ['Saldo (Bs.)', 'moneda']]),
            'filas' => $filas,
            'resumen' => [
                $this->dato('Estudiantes con deuda', $filas->count()),
                $this->dato('Con cuotas vencidas', $filas->where('cuotas_vencidas', '>', 0)->count()),
                $this->moneda('Deuda total', $filas->sum('saldo')),
            ],
        ];
    }

    private function reporteBecas(array $f): array
    {
        $filas = $this->filtrarOferta(
            DB::table('beca_estudiante as x')->join('beca as b', 'b.id_beca', '=', 'x.id_beca')
                ->join('matricula as m', 'm.id_matricula', '=', 'x.id_matricula')
                ->join('oferta_academica as o', 'o.id_oferta', '=', 'm.id_oferta')
                ->join('malla_curricular as ma', 'ma.id_malla', '=', 'o.id_malla')
                ->join('programa as p', 'p.id_programa', '=', 'ma.id_programa'),
            $f)
            ->join('usuario as u', 'u.id_usuario', '=', 'm.id_usuario')
            ->orderByDesc('x.fecha_asignacion')
            ->selectRaw("x.fecha_asignacion, u.apellidos || ', ' || u.nombres AS estudiante, m.numero_registro, p.nombre AS programa, b.nombre AS beca,
                b.institucion_financiadora, x.porcentaje_aplicado, ROUND(m.monto_original * x.porcentaje_aplicado / 100, 2) AS monto_beca, x.estado")
            ->get();

        return [
            'columnas' => $this->columnas(['fecha_asignacion' => ['Asignación', 'fecha'], 'estudiante' => 'Estudiante', 'numero_registro' => 'Matrícula',
                'programa' => 'Programa', 'beca' => 'Beca', 'institucion_financiadora' => 'Financiador', 'porcentaje_aplicado' => ['Porcentaje', 'porcentaje'],
                'monto_beca' => ['Monto beca (Bs.)', 'moneda'], 'estado' => 'Estado']),
            'filas' => $filas,
            'resumen' => [
                $this->dato('Becas asignadas', $filas->count()),
                $this->moneda('Monto becado (activas)', $filas->where('estado', 'ACTIVA')->sum('monto_beca')),
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Docente (solo sus módulos asignados)
    // ------------------------------------------------------------------

    private function reporteMisCalificaciones(array $f, Usuario $usuario): array
    {
        return $this->resultadosPorEstudiante($f, $usuario);
    }

    private function reporteMiAsistencia(array $f, Usuario $usuario): array
    {
        return $this->asistenciaPorModulo($f, $usuario);
    }

    // ------------------------------------------------------------------
    // Piezas comunes
    // ------------------------------------------------------------------

    /** tabla x  ->  oferta o  ->  malla ma  ->  programa p */
    private function baseOferta(string $tabla): Builder
    {
        return DB::table($tabla)
            ->join('oferta_academica as o', 'o.id_oferta', '=', 'x.id_oferta')
            ->join('malla_curricular as ma', 'ma.id_malla', '=', 'o.id_malla')
            ->join('programa as p', 'p.id_programa', '=', 'ma.id_programa');
    }

    /** oferta_modulo om con su módulo (mo), oferta (o) y programa (p); opcionalmente solo los de un docente. */
    private function baseModulo(?Usuario $docente = null): Builder
    {
        return DB::table('oferta_modulo as om')
            ->join('malla_modulo as mm', 'mm.id_malla_modulo', '=', 'om.id_malla_modulo')
            ->join('modulo as mo', 'mo.id_modulo', '=', 'mm.id_modulo')
            ->join('oferta_academica as o', 'o.id_oferta', '=', 'om.id_oferta')
            ->join('malla_curricular as ma', 'ma.id_malla', '=', 'o.id_malla')
            ->join('programa as p', 'p.id_programa', '=', 'ma.id_programa')
            ->when($docente, fn (Builder $q) => $q->whereExists(fn ($s) => $s->from('asignacion_docente as asg')
                ->whereColumn('asg.id_oferta_modulo', 'om.id_oferta_modulo')
                ->where('asg.id_usuario_docente', $docente->id_usuario)
                ->where('asg.estado', 'ACTIVO')));
    }

    private function filtrarOferta(Builder $q, array $f, ?string $campoFecha = null): Builder
    {
        return $q
            ->when($f['gestion'] ?? null, fn ($q, $v) => $q->where('o.gestion', $v))
            ->when($f['id_programa'] ?? null, fn ($q, $v) => $q->where('p.id_programa', $v))
            ->when($f['id_oferta'] ?? null, fn ($q, $v) => $q->where('o.id_oferta', $v))
            ->when($campoFecha && ($f['desde'] ?? null), fn ($q) => $q->whereDate($campoFecha, '>=', $f['desde']))
            ->when($campoFecha && ($f['hasta'] ?? null), fn ($q) => $q->whereDate($campoFecha, '<=', $f['hasta']));
    }

    private function describirFiltros(array $f): array
    {
        $texto = [];

        if (isset($f['gestion'])) {
            $texto['Gestión'] = $f['gestion'];
        }
        if (isset($f['id_programa'])) {
            $texto['Programa'] = Programa::find($f['id_programa'])?->nombre ?? $f['id_programa'];
        }
        if (isset($f['id_oferta'])) {
            $texto['Oferta'] = OfertaAcademica::find($f['id_oferta'])?->codigo ?? $f['id_oferta'];
        }
        if (isset($f['desde'])) {
            $texto['Desde'] = $f['desde'];
        }
        if (isset($f['hasta'])) {
            $texto['Hasta'] = $f['hasta'];
        }

        return $texto;
    }

    /** @param  array<string, string|array{0:string,1:string}>  $definicion */
    private function columnas(array $definicion): array
    {
        return collect($definicion)->map(fn ($valor, $clave) => [
            'clave' => $clave,
            'titulo' => is_array($valor) ? $valor[0] : $valor,
            'tipo' => is_array($valor) ? $valor[1] : 'texto',
        ])->values()->all();
    }

    private function dato(string $etiqueta, mixed $valor): array
    {
        return ['etiqueta' => $etiqueta, 'valor' => (string) $valor];
    }

    private function moneda(string $etiqueta, mixed $valor): array
    {
        return $this->dato($etiqueta, 'Bs. '.number_format((float) $valor, 2));
    }

    private function conteoPorEstado(Collection $filas): array
    {
        return [
            $this->dato('Total', $filas->count()),
            ...$filas->countBy('estado')->map(fn ($n, $estado) => $this->dato(ucfirst(strtolower(str_replace('_', ' ', $estado))), $n))->values(),
        ];
    }
}
