import { ChevronDown } from 'lucide-react'
import { useState } from 'react'
import { fecha, legible, porcentaje } from '../../utils/formato'
import { Alerta, Insignia, Vacio } from '../ui/Basicos'

/**
 * CU3 / CU5 · Avance académico de una matrícula: materias, notas por
 * evaluación, nota final y asistencia. Lo usan el estudiante y el personal.
 */
export default function ProgresoAcademico({ progreso, acciones }) {
  const [abierto, setAbierto] = useState(null)
  const { modulos, resumen } = progreso

  if (modulos.length === 0) {
    return <Vacio titulo="Aún no está inscrito en materias">La inscripción se realiza al activarse la matrícula (pago inicial confirmado).</Vacio>
  }

  return (
    <div className="space-y-4">
      <div>
        <div className="mb-1 flex justify-between text-sm">
          <span className="font-semibold">Avance del programa</span>
          <span className="tabular">{resumen.modulos_aprobados} de {resumen.modulos_obligatorios} materias obligatorias aprobadas · {porcentaje(resumen.porcentaje_avance)}</span>
        </div>
        <div className="h-3 overflow-hidden rounded-full bg-suave" role="progressbar" aria-valuenow={resumen.porcentaje_avance} aria-valuemin={0} aria-valuemax={100} aria-label="Avance del programa">
          <div className="h-full rounded-full bg-primario transition-all" style={{ width: `${resumen.porcentaje_avance}%` }} />
        </div>
      </div>

      {resumen.materias_pendientes?.length > 0 && resumen.modulos_en_curso === 0 && (
        <Alerta tipo="alerta" titulo="Le falta aprobar para el certificado">
          {resumen.materias_pendientes.join(', ')}. Debe aprobar todas las materias (nota mínima y 70 % de asistencia); una materia reprobada se recupera cursándola de nuevo.
        </Alerta>
      )}

      <ul className="space-y-2">
        {modulos.map((m) => {
          const expandido = abierto === m.id_inscripcion_modulo

          return (
            <li key={m.id_inscripcion_modulo} className="rounded-tema border">
              <div className="flex flex-wrap items-center gap-3 p-3">
                <button onClick={() => setAbierto(expandido ? null : m.id_inscripcion_modulo)} aria-expanded={expandido} className="flex min-w-48 flex-1 items-center gap-2 text-left">
                  <ChevronDown className={`size-4 shrink-0 transition ${expandido ? 'rotate-180' : ''}`} aria-hidden />
                  <span>
                    <span className="font-bold">{m.orden}. {m.modulo}</span>
                    <span className="block text-xs text-tenue">{m.horas} h · {m.docentes.join(', ') || 'Docente por designar'}{m.es_recuperacion ? ` · recuperación en ${m.oferta}` : ''}</span>
                  </span>
                </button>
                <span className="text-right text-xs text-tenue">
                  Nota {m.modulo_cerrado ? 'final' : 'parcial'}
                  <strong className="tabular block text-base text-texto">{m.nota_final ?? '—'}</strong>
                </span>
                <span className="text-right text-xs text-tenue">
                  Asistencia
                  <strong className="tabular block text-base text-texto">{m.sesiones ? porcentaje(m.porcentaje_asistencia) : '—'}</strong>
                </span>
                <Insignia estado={m.estado} />
                {acciones?.(m)}
              </div>

              {expandido && (
                <div className="grid gap-4 border-t bg-suave/50 p-3 md:grid-cols-2">
                  <div>
                    <h4 className="mb-1.5 text-xs font-bold tracking-wide text-tenue uppercase">Evaluaciones</h4>
                    {m.evaluaciones.length === 0 ? <p className="text-sm text-tenue">El docente aún no registró evaluaciones.</p> : (
                      <table className="w-full text-sm">
                        <thead><tr className="text-left text-xs text-tenue"><th className="py-1">Evaluación</th><th className="py-1 text-right">Peso</th><th className="py-1 text-right">Nota</th></tr></thead>
                        <tbody>
                          {m.evaluaciones.map((e) => (
                            <tr key={e.nombre + e.fecha} className="border-t">
                              <td className="py-1.5">{e.nombre}<span className="block text-xs text-tenue">{legible(e.tipo)} · {fecha(e.fecha)}</span></td>
                              <td className="tabular py-1.5 text-right">{porcentaje(e.ponderacion)}</td>
                              <td className="tabular py-1.5 text-right font-bold">{e.calificacion ?? '—'}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    )}
                  </div>
                  <div>
                    <h4 className="mb-1.5 text-xs font-bold tracking-wide text-tenue uppercase">Asistencia ({m.sesiones} sesiones)</h4>
                    {m.asistencias.length === 0 ? <p className="text-sm text-tenue">Sin registros de asistencia.</p> : (
                      <ul className="flex flex-wrap gap-1.5">
                        {m.asistencias.map((a) => (
                          <li key={a.fecha} title={a.observacion ?? undefined}><Insignia estado={a.estado}>{fecha(a.fecha).slice(0, 5)} · {legible(a.estado)}</Insignia></li>
                        ))}
                      </ul>
                    )}
                  </div>
                </div>
              )}
            </li>
          )
        })}
      </ul>
    </div>
  )
}
