import { ArrowLeft, FilePen, Lock, Pencil, Plus, Save, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api from '../../api/cliente'
import { Alerta, Dato, Estado, Insignia, Pestanas, Vacio } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import Modal, { Confirmar } from '../../components/ui/Modal'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { fecha, hoy, legible, porcentaje } from '../../utils/formato'
import { entre, fechaNoFutura, maximo, minimo, numero, requerido } from '../../utils/validacion'

/**
 * CU5 · Planilla de la materia: calificaciones, asistencia y evaluaciones.
 * El docente asignado edita mientras la materia está en curso; al cerrarlo se
 * fijan las notas finales y los cambios pasan por una corrección de nota.
 */
export default function Planilla() {
  const { id } = useParams()
  const avisar = useAvisos()
  const consulta = useConsulta(`/docencia/modulos/${id}`)
  const [pestana, setPestana] = useState('notas')
  const [cerrando, setCerrando] = useState(false)
  const [procesando, setProcesando] = useState(false)

  const cerrar = async () => {
    setProcesando(true)

    try {
      const { data } = await api.post(`/docencia/modulos/${id}/cerrar`)
      consulta.setDatos({ ...consulta.datos, ...data })
      avisar.exito('Materia cerrada: se fijaron las notas finales y los resultados.')
    } catch (e) {
      avisar.error(e)
    } finally {
      setProcesando(false)
      setCerrando(false)
    }
  }

  return (
    <>
      <Link to="/docencia" className="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primario hover:underline">
        <ArrowLeft className="size-4" aria-hidden /> Volver a materias
      </Link>

      <Estado consulta={consulta}>
        {(p) => {
          const abierto = p.modulo.estado === 'ACTIVO'
          const edita = p.puede_editar && abierto
          const actualizar = (data) => consulta.setDatos({ ...p, ...data })

          return (
            <div className="space-y-5">
              <section className="tarjeta p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h1 className="flex flex-wrap items-center gap-2 text-2xl font-bold">{p.modulo.nombre} <Insignia estado={p.modulo.estado}>{abierto ? 'En curso' : 'Cerrado'}</Insignia></h1>
                    <p className="text-sm text-tenue">{p.modulo.programa} · Oferta {p.modulo.oferta} · Gestión {p.modulo.gestion}</p>
                  </div>
                  {edita && <Boton icono={Lock} onClick={() => setCerrando(true)}>Cerrar materia</Boton>}
                </div>
                <dl className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-5">
                  <Dato etiqueta="Docente">{p.modulo.docentes.join(', ') || 'Sin asignar'}</Dato>
                  <Dato etiqueta="Periodo">{fecha(p.modulo.fecha_inicio)} – {fecha(p.modulo.fecha_fin)}</Dato>
                  <Dato etiqueta="Estudiantes">{p.estudiantes.length}</Dato>
                  <Dato etiqueta="Sesiones dictadas">{p.sesiones.length}</Dato>
                  <Dato etiqueta="Ponderación acumulada"><span className={p.ponderacion_total === 100 ? 'text-exito' : 'text-alerta'}>{porcentaje(p.ponderacion_total)} de 100%</span></Dato>
                </dl>
                <p className="mt-3 text-xs text-tenue">
                  Reglas: aprueba con nota final ≥ {p.reglas.nota_minima_aprobacion} y asistencia ≥ {p.reglas.asistencia_minima}%. Nota final = Σ (calificación × ponderación).
                </p>
                {!p.puede_editar && <Alerta className="mt-3">Vista de supervisión: solo el docente asignado puede registrar información en esta materia.</Alerta>}
                {p.puede_editar && !abierto && <Alerta className="mt-3">La materia está cerrada. Para cambiar una calificación use «Corregir» junto a la nota: la solicitud la aprueba el Coordinador Académico.</Alerta>}
              </section>

              <div>
                <Pestanas activa={pestana} alCambiar={setPestana} pestanas={[
                  { clave: 'notas', etiqueta: 'Calificaciones' },
                  { clave: 'asistencia', etiqueta: 'Asistencia' },
                  { clave: 'evaluaciones', etiqueta: 'Evaluaciones' },
                ]} />
                <div className="tarjeta">
                  {p.estudiantes.length === 0 && pestana !== 'evaluaciones'
                    ? <Vacio titulo="Aún no hay estudiantes inscritos">Los estudiantes aparecen aquí cuando su matrícula se activa.</Vacio>
                    : pestana === 'notas' ? <Calificaciones p={p} edita={edita} alGuardar={actualizar} alCorregir={consulta.recargar} />
                    : pestana === 'asistencia' ? <Asistencia p={p} edita={edita} alGuardar={actualizar} />
                    : <Evaluaciones p={p} edita={edita} alGuardar={actualizar} />}
                </div>
              </div>

              <Confirmar abierto={cerrando} titulo="Cerrar materia" variante="primario" textoConfirmar="Cerrar materia" cargando={procesando} alConfirmar={cerrar} alCerrar={() => setCerrando(false)}
                mensaje="Se calcularán las notas finales y la asistencia de cada estudiante, y quedarán como aprobados o reprobados. Después del cierre, las notas solo cambian mediante una corrección autorizada. Las ponderaciones deben sumar 100%." />
            </div>
          )
        }}
      </Estado>
    </>
  )
}

// ----------------------------------------------------------------------------
// Calificaciones
// ----------------------------------------------------------------------------
function Calificaciones({ p, edita, alGuardar, alCorregir }) {
  const avisar = useAvisos()
  const [borrador, setBorrador] = useState({}) // { 'idEval:idInscripcion': valor }
  const [guardando, setGuardando] = useState(false)
  const [correccion, setCorreccion] = useState(null)
  const max = p.reglas.nota_maxima
  const clave = (e, i) => `${e}:${i}`
  const invalido = (v) => v !== '' && (Number.isNaN(Number(v)) || Number(v) < 0 || Number(v) > max)
  const hayErrores = Object.values(borrador).some(invalido)

  const guardar = async () => {
    setGuardando(true)

    try {
      let ultima = null
      const porEvaluacion = {}
      for (const [k, valor] of Object.entries(borrador)) {
        const [idEval, idInscripcion] = k.split(':')
        ;(porEvaluacion[idEval] ??= []).push({ id_inscripcion_modulo: Number(idInscripcion), calificacion: valor === '' ? null : Number(valor) })
      }
      for (const [idEval, notas] of Object.entries(porEvaluacion)) {
        ultima = (await api.put(`/docencia/evaluaciones/${idEval}/notas`, { notas })).data
      }
      if (ultima) alGuardar(ultima)
      setBorrador({})
      avisar.exito('Calificaciones guardadas.')
    } catch (e) {
      avisar.error(e)
    } finally {
      setGuardando(false)
    }
  }

  if (p.evaluaciones.length === 0) {
    return <Vacio titulo="Primero cree las evaluaciones">En la pestaña «Evaluaciones» defina qué se califica y cuánto pesa cada evaluación.</Vacio>
  }

  return (
    <>
      <div className="overflow-x-auto">
        <table className="w-full min-w-max text-sm">
          <thead>
            <tr className="bg-suave text-left text-xs uppercase">
              <th scope="col" className="px-3 py-2.5">Estudiante</th>
              {p.evaluaciones.map((e) => (
                <th key={e.id_evaluacion} scope="col" className="px-3 py-2.5 text-center normal-case">
                  <span className="block font-bold">{e.nombre}</span>
                  <span className="font-normal text-tenue">{porcentaje(e.ponderacion)} · {fecha(e.fecha)}</span>
                </th>
              ))}
              <th scope="col" className="px-3 py-2.5 text-right">Nota final</th>
              <th scope="col" className="px-3 py-2.5 text-right">Asistencia</th>
              <th scope="col" className="px-3 py-2.5">Resultado</th>
            </tr>
          </thead>
          <tbody>
            {p.estudiantes.map((est) => (
              <tr key={est.id_inscripcion_modulo} className="border-t">
                <th scope="row" className="px-3 py-2 text-left font-semibold">{est.estudiante}<span className="block text-xs font-normal text-tenue">{est.numero_registro}</span></th>
                {p.evaluaciones.map((e) => {
                  const nota = est.notas[e.id_evaluacion]
                  const k = clave(e.id_evaluacion, est.id_inscripcion_modulo)
                  const valor = borrador[k] ?? nota?.calificacion ?? ''

                  return (
                    <td key={e.id_evaluacion} className="px-2 py-1.5 text-center">
                      {edita && est.estado === 'INSCRITO' ? (
                        <input type="number" min="0" max={max} step="0.01" value={valor} aria-label={`Nota de ${est.estudiante} en ${e.nombre}`} aria-invalid={invalido(String(valor)) || undefined}
                          onChange={(ev) => setBorrador((b) => ({ ...b, [k]: ev.target.value }))} className="campo-control tabular w-20 px-2 py-1 text-center" />
                      ) : <span className="tabular font-semibold">{nota?.calificacion ?? '—'}</span>}
                      {p.puede_editar && nota && (
                        nota.correccion_pendiente
                          ? <span className="mt-0.5 block text-xs text-alerta">Corrección en trámite</span>
                          : <button onClick={() => setCorreccion({ est, e, nota })} className="mt-0.5 flex w-full items-center justify-center gap-1 text-xs font-semibold text-primario hover:underline"><FilePen className="size-3" aria-hidden /> Corregir</button>
                      )}
                    </td>
                  )
                })}
                <td className="tabular px-3 py-2 text-right text-base font-extrabold">{est.nota_final ?? '—'}</td>
                <td className="tabular px-3 py-2 text-right">{p.sesiones.length ? porcentaje(est.porcentaje_asistencia) : '—'}</td>
                <td className="px-3 py-2">
                  {est.estado === 'INSCRITO'
                    ? <span className="text-xs text-tenue">Previsto: <Insignia estado={est.resultado_previsto} /></span>
                    : <Insignia estado={est.estado} />}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {edita && (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t p-3">
          <p className="text-xs text-tenue">{hayErrores ? <span className="font-semibold text-peligro">Hay notas fuera del rango 0 a {max}.</span> : `Escala de 0 a ${max}. Deje la casilla vacía si el estudiante aún no tiene nota.`}</p>
          <Boton icono={Save} onClick={guardar} cargando={guardando} disabled={hayErrores || Object.keys(borrador).length === 0}>Guardar calificaciones</Boton>
        </div>
      )}

      {correccion && <SolicitarCorreccion datos={correccion} max={max} alCerrar={() => setCorreccion(null)} alEnviar={() => (setCorreccion(null), alCorregir())} />}
    </>
  )
}

function SolicitarCorreccion({ datos, max, alCerrar, alEnviar }) {
  const avisar = useAvisos()
  const f = useFormulario({ nota_nueva: '', motivo: '' }, {
    nota_nueva: [requerido(), numero(), entre(0, max), (v) => (Number(v) === Number(datos.nota.calificacion) ? 'Debe ser distinta a la nota actual.' : null)],
    motivo: [requerido('Justifique el cambio.'), minimo(10), maximo(1000)],
  })

  const enviar = f.enviar(async (v) => {
    await api.post('/correcciones', { id_nota: datos.nota.id_nota, nota_nueva: Number(v.nota_nueva), motivo: v.motivo })
    avisar.exito('Solicitud enviada. Queda pendiente hasta que el Coordinador Académico la autorice.')
    alEnviar()
  })

  return (
    <Modal abierto titulo="Solicitar corrección de nota" alCerrar={alCerrar} ancho="sm"
      pie={<><Boton variante="secundario" onClick={alCerrar}>Cancelar</Boton><Boton onClick={enviar} cargando={f.enviando}>Enviar solicitud</Boton></>}>
      <form onSubmit={enviar} noValidate className="space-y-4">
        {f.errorGeneral && <Alerta tipo="peligro">{f.errorGeneral}</Alerta>}
        <dl className="grid grid-cols-2 gap-3 rounded-tema bg-suave p-3">
          <Dato etiqueta="Estudiante">{datos.est.estudiante}</Dato>
          <Dato etiqueta="Evaluación">{datos.e.nombre}</Dato>
          <Dato etiqueta="Nota actual"><span className="text-lg font-extrabold">{datos.nota.calificacion}</span></Dato>
        </dl>
        <Campo {...f.campo('nota_nueva')} tipo="number" min="0" max={max} step="0.01" etiqueta="Nueva nota" requerido />
        <Campo {...f.campo('motivo')} tipo="textarea" etiqueta="Motivo del cambio" requerido ayuda="Mínimo 10 caracteres. Queda registrado en el historial de la corrección." />
      </form>
    </Modal>
  )
}

// ----------------------------------------------------------------------------
// Asistencia
// ----------------------------------------------------------------------------
function Asistencia({ p, edita, alGuardar }) {
  const avisar = useAvisos()
  const id = p.modulo.id_oferta_modulo
  const [dia, setDia] = useState(p.sesiones.at(-1) ?? hoy())
  const [lista, setLista] = useState({})
  const [guardando, setGuardando] = useState(false)
  const [eliminando, setEliminando] = useState(false)
  const registro = useConsulta(`/docencia/modulos/${id}/asistencia`, { fecha: dia })
  const activos = p.estudiantes.filter((e) => e.estado === 'INSCRITO' || !edita)
  const existe = p.sesiones.includes(dia)
  const errorFecha = fechaNoFutura()(dia)

  useEffect(() => {
    if (!registro.datos) return
    setLista(Object.fromEntries(p.estudiantes.map((e) => [e.id_inscripcion_modulo, registro.datos.registros[e.id_inscripcion_modulo]?.estado ?? 'PRESENTE'])))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [registro.datos])

  const guardar = async () => {
    setGuardando(true)

    try {
      const { data } = await api.put(`/docencia/modulos/${id}/asistencia`, {
        fecha: dia,
        asistencias: activos.map((e) => ({ id_inscripcion_modulo: e.id_inscripcion_modulo, estado: lista[e.id_inscripcion_modulo] ?? 'PRESENTE' })),
      })
      alGuardar(data)
      avisar.exito(`Asistencia del ${fecha(dia)} guardada.`)
    } catch (e) {
      avisar.error(e)
    } finally {
      setGuardando(false)
    }
  }

  const eliminar = async () => {
    try {
      const { data } = await api.delete(`/docencia/modulos/${id}/asistencia`, { params: { fecha: dia } })
      alGuardar(data)
      registro.recargar()
      avisar.exito('Sesión eliminada.')
    } catch (e) {
      avisar.error(e)
    } finally {
      setEliminando(false)
    }
  }

  return (
    <div className="p-4">
      <div className="mb-4 flex flex-wrap items-end gap-3">
        <Campo tipo="date" nombre="fecha" etiqueta="Fecha de la clase" valor={dia} alCambiar={setDia} max={hoy()} error={errorFecha} className="w-48" />
        {edita && <Boton variante="secundario" tamano="sm" onClick={() => setLista(Object.fromEntries(activos.map((e) => [e.id_inscripcion_modulo, 'PRESENTE'])))}>Marcar todos presentes</Boton>}
        <span className="text-sm text-tenue">{existe ? 'Sesión ya registrada: puede corregirla.' : 'Sesión nueva.'}</span>
      </div>

      {p.sesiones.length > 0 && (
        <div className="mb-4 flex flex-wrap items-center gap-1.5">
          <span className="text-xs font-semibold text-tenue">Sesiones registradas:</span>
          {p.sesiones.map((s) => (
            <button key={s} onClick={() => setDia(s)} className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${s === dia ? 'border-primario bg-primario text-sobre-primario' : 'hover:bg-suave'}`}>{fecha(s).slice(0, 5)}</button>
          ))}
        </div>
      )}

      <ul className="divide-y rounded-tema border">
        {activos.map((e) => (
          <li key={e.id_inscripcion_modulo} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
            <span className="text-sm font-semibold">{e.estudiante}<span className="block text-xs font-normal text-tenue">Asistencia acumulada: {p.sesiones.length ? porcentaje(e.porcentaje_asistencia) : '—'}</span></span>
            {edita ? (
              <div className="flex flex-wrap gap-1" role="radiogroup" aria-label={`Asistencia de ${e.estudiante}`}>
                {p.estados_asistencia.map((estado) => {
                  const activo = (lista[e.id_inscripcion_modulo] ?? 'PRESENTE') === estado

                  return (
                    <button key={estado} role="radio" aria-checked={activo} onClick={() => setLista((l) => ({ ...l, [e.id_inscripcion_modulo]: estado }))}
                      className={`rounded-tema border px-2.5 py-1 text-xs font-semibold ${activo ? 'border-primario bg-primario text-sobre-primario' : 'hover:bg-suave'}`}>{legible(estado)}</button>
                  )
                })}
              </div>
            ) : existe ? <Insignia estado={registro.datos?.registros[e.id_inscripcion_modulo]?.estado ?? 'AUSENTE'} /> : <span className="text-xs text-tenue">Sin registro</span>}
          </li>
        ))}
      </ul>

      {edita && (
        <div className="mt-4 flex flex-wrap justify-between gap-2">
          {existe ? <Boton variante="fantasma" className="text-peligro" icono={Trash2} onClick={() => setEliminando(true)}>Eliminar esta sesión</Boton> : <span />}
          <Boton icono={Save} onClick={guardar} cargando={guardando} disabled={Boolean(errorFecha) || activos.length === 0}>Guardar asistencia</Boton>
        </div>
      )}

      <Confirmar abierto={eliminando} titulo="Eliminar sesión" mensaje={`Se borrará la asistencia de todos los estudiantes del ${fecha(dia)}.`} textoConfirmar="Eliminar" alConfirmar={eliminar} alCerrar={() => setEliminando(false)} />
    </div>
  )
}

// ----------------------------------------------------------------------------
// Evaluaciones
// ----------------------------------------------------------------------------
function Evaluaciones({ p, edita, alGuardar }) {
  const avisar = useAvisos()
  const [editando, setEditando] = useState(null) // null · {} nueva · evaluación
  const [eliminando, setEliminando] = useState(null)
  const disponible = 100 - p.ponderacion_total + (editando?.ponderacion ?? 0)
  const f = useFormulario({ nombre: '', tipo: '', ponderacion: '', fecha: hoy() }, {
    nombre: [requerido(), minimo(3), maximo(200)],
    tipo: [requerido()],
    ponderacion: [requerido(), numero(), entre(1, 100), (v) => (Number(v) > disponible + 0.001 ? `Solo queda ${disponible}% por asignar.` : null)],
    fecha: [requerido()],
  })

  const guardar = f.enviar(async (v) => {
    const cuerpo = { ...v, ponderacion: Number(v.ponderacion) }
    const { data } = editando.id_evaluacion
      ? await api.put(`/docencia/evaluaciones/${editando.id_evaluacion}`, cuerpo)
      : await api.post(`/docencia/modulos/${p.modulo.id_oferta_modulo}/evaluaciones`, cuerpo)
    alGuardar(data)
    avisar.exito('Evaluación guardada.')
    setEditando(null)
  })

  const eliminar = async () => {
    try {
      const { data } = await api.delete(`/docencia/evaluaciones/${eliminando.id_evaluacion}`)
      alGuardar(data)
      avisar.exito('Evaluación eliminada.')
    } catch (e) {
      avisar.error(e)
    } finally {
      setEliminando(null)
    }
  }

  return (
    <div className="p-4">
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm">Ponderación asignada: <strong className={p.ponderacion_total === 100 ? 'text-exito' : 'text-alerta'}>{porcentaje(p.ponderacion_total)}</strong> de 100%. Para cerrar la materia debe sumar exactamente 100%.</p>
        {edita && <Boton icono={Plus} disabled={p.ponderacion_total >= 100} onClick={() => (f.reiniciar({ nombre: '', tipo: '', ponderacion: '', fecha: hoy() }), setEditando({}))}>Nueva evaluación</Boton>}
      </div>

      {p.evaluaciones.length === 0 ? <Vacio titulo="Sin evaluaciones">Cree las evaluaciones de la materia y asigne el peso de cada una.</Vacio> : (
        <ul className="divide-y rounded-tema border">
          {p.evaluaciones.map((e) => (
            <li key={e.id_evaluacion} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5">
              <span><strong>{e.nombre}</strong><span className="block text-xs text-tenue">{legible(e.tipo)} · {fecha(e.fecha)}</span></span>
              <span className="flex items-center gap-2">
                <span className="tabular text-lg font-extrabold">{porcentaje(e.ponderacion)}</span>
                {edita && (
                  <>
                    <Boton tamano="sm" variante="secundario" icono={Pencil} onClick={() => (f.reiniciar({ nombre: e.nombre, tipo: e.tipo, ponderacion: e.ponderacion, fecha: e.fecha }), setEditando(e))}>Editar</Boton>
                    <Boton tamano="sm" variante="fantasma" className="text-peligro" icono={Trash2} aria-label="Eliminar evaluación" onClick={() => setEliminando(e)} />
                  </>
                )}
              </span>
            </li>
          ))}
        </ul>
      )}

      <Modal abierto={Boolean(editando)} titulo={editando?.id_evaluacion ? 'Editar evaluación' : 'Nueva evaluación'} alCerrar={() => setEditando(null)} ancho="sm"
        pie={<><Boton variante="secundario" onClick={() => setEditando(null)}>Cancelar</Boton><Boton onClick={guardar} cargando={f.enviando}>Guardar</Boton></>}>
        <form onSubmit={guardar} noValidate className="space-y-4">
          {f.errorGeneral && <Alerta tipo="peligro">{f.errorGeneral}</Alerta>}
          <Campo {...f.campo('nombre')} etiqueta="Nombre" requerido ayuda="Ejemplo: Primer parcial" />
          <Campo {...f.campo('tipo')} tipo="select" etiqueta="Tipo" requerido opciones={p.tipos_evaluacion.map((t) => ({ valor: t, etiqueta: legible(t) }))} />
          <Campo {...f.campo('ponderacion')} tipo="number" min="1" max="100" step="0.01" etiqueta="Ponderación (%)" requerido ayuda={`Disponible: ${disponible}%`} />
          <Campo {...f.campo('fecha')} tipo="date" etiqueta="Fecha" requerido />
        </form>
      </Modal>

      <Confirmar abierto={Boolean(eliminando)} titulo="Eliminar evaluación" mensaje={`Se eliminará «${eliminando?.nombre}» junto con las calificaciones registradas en ella.`} textoConfirmar="Eliminar" alConfirmar={eliminar} alCerrar={() => setEliminando(null)} />
    </div>
  )
}
