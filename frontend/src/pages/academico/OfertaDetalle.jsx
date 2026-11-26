import { ArrowLeft, CalendarDays, Pencil, Trash2, UserPlus } from 'lucide-react'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api from '../../api/cliente'
import { Alerta, Dato, Estado, Insignia, Tarjeta } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import Modal, { Confirmar } from '../../components/ui/Modal'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { describirPlan, fecha, hoy, legible, moneda } from '../../utils/formato'
import { maximo, noAnteriorA, noNegativo, numero, requerido } from '../../utils/validacion'
import { CamposOferta, cuerpoOferta, reglasOferta } from './Ofertas'

const CONTRATOS = ['POR_MODULO', 'POR_HORAS', 'CONSULTORIA', 'PLANTA'].map((c) => ({ valor: c, etiqueta: legible(c) }))

/** Transiciones que tienen sentido desde cada estado de la oferta. */
const SIGUIENTES = {
  PLANIFICADA: [['ABIERTA', 'Abrir preinscripciones', 'exito'], ['CANCELADA', 'Cancelar oferta', 'peligro']],
  ABIERTA: [['EN_CURSO', 'Iniciar clases', 'primario'], ['PLANIFICADA', 'Volver a planificación', 'secundario'], ['CANCELADA', 'Cancelar oferta', 'peligro']],
  EN_CURSO: [['FINALIZADA', 'Finalizar curso', 'primario'], ['ABIERTA', 'Reabrir preinscripciones', 'secundario']],
  FINALIZADA: [['EN_CURSO', 'Reabrir oferta', 'secundario']],
  CANCELADA: [['PLANIFICADA', 'Reactivar', 'secundario']],
}

/** CU2 · Detalle de la oferta: datos, materias, docentes asignados y costos. */
export default function OfertaDetalle() {
  const { id } = useParams()
  const auth = useAuth()
  const avisar = useAvisos()
  const consulta = useConsulta(`/ofertas/${id}`)
  const gestiona = auth.puede('ofertas.gestionar')
  const asigna = auth.puede('docentes.asignar')
  const docentes = useConsulta(asigna ? '/personas/docentes' : null)

  const [editando, setEditando] = useState(false)
  const [moduloFechas, setModuloFechas] = useState(null)
  const [asignacion, setAsignacion] = useState(null) // { modulo, actual? }
  const [quitando, setQuitando] = useState(null)
  const [cambioEstado, setCambioEstado] = useState(null)

  const fOferta = useFormulario({}, reglasOferta)
  const fFechas = useFormulario({ fecha_inicio: '', fecha_fin: '' }, { fecha_inicio: [requerido()], fecha_fin: [noAnteriorA('fecha_inicio', 'la fecha de inicio')] })
  const fDocente = useFormulario({}, {
    id_usuario_docente: [requerido('Seleccione al docente.')],
    tipo_contrato: [requerido()],
    fecha_contrato: [requerido()],
    fecha_inicio: [requerido()],
    fecha_fin: [noAnteriorA('fecha_inicio', 'la fecha de inicio')],
    monto_honorario: [requerido(), numero(), noNegativo()],
    observacion: [maximo(500)],
  })

  const aplicar = (data, mensaje) => (consulta.setDatos(data), avisar.exito(mensaje))

  const guardarOferta = fOferta.enviar(async (valores) => {
    const { data } = await api.put(`/ofertas/${id}`, cuerpoOferta(valores))
    aplicar(data, 'Oferta actualizada.')
    setEditando(false)
  })

  const guardarFechas = fFechas.enviar(async (valores) => {
    const { data } = await api.put(`/ofertas-modulo/${moduloFechas.id_oferta_modulo}`, { ...valores, fecha_fin: valores.fecha_fin || null })
    aplicar(data, 'Fechas de la materia actualizadas.')
    setModuloFechas(null)
  })

  const guardarDocente = fDocente.enviar(async (valores) => {
    const cuerpo = { ...valores, monto_honorario: Number(valores.monto_honorario), fecha_fin: valores.fecha_fin || null }

    if (asignacion.actual) await api.put(`/asignaciones-docente/${asignacion.actual.id_asignacion_docente}`, cuerpo)
    else await api.post('/asignaciones-docente', { ...cuerpo, id_oferta_modulo: asignacion.modulo.id_oferta_modulo, id_usuario_docente: Number(valores.id_usuario_docente) })

    avisar.exito(asignacion.actual ? 'Contratación actualizada.' : 'Docente asignado a la materia.')
    setAsignacion(null)
    consulta.recargar()
  })

  const quitarDocente = async () => {
    try {
      const { data } = await api.delete(`/asignaciones-docente/${quitando.id_asignacion_docente}`)
      avisar.exito(data.message)
      consulta.recargar()
    } catch (e) {
      avisar.error(e)
    } finally {
      setQuitando(null)
    }
  }

  const [resumenFinal, setResumenFinal] = useState(null)

  const confirmarEstado = async () => {
    try {
      const { data } = await api.patch(`/ofertas/${id}/estado`, { estado: cambioEstado[0] })
      const r = data.resumen_finalizacion
      aplicar(data, r
        ? `Curso finalizado: ${r.finalizados} estudiante(s); ${r.aprobados.length} aprobaron todo y pueden certificarse${r.con_pendientes.length ? `; ${r.con_pendientes.length} con materias por recuperar` : ''}.`
        : `La oferta pasó a «${legible(cambioEstado[0])}».`)
      if (r) setResumenFinal(r)
    } catch (e) {
      avisar.error(e)
    } finally {
      setCambioEstado(null)
    }
  }

  const abrirAsignacion = (modulo, actual = null) => {
    fDocente.reiniciar(actual
      ? { id_usuario_docente: actual.id_usuario_docente, tipo_contrato: actual.tipo_contrato, fecha_contrato: actual.fecha_contrato, fecha_inicio: actual.fecha_inicio,
          fecha_fin: actual.fecha_fin ?? '', monto_honorario: actual.monto_honorario, observacion: actual.observacion ?? '', estado: actual.estado }
      : { id_usuario_docente: '', tipo_contrato: 'POR_MODULO', fecha_contrato: hoy(), fecha_inicio: modulo.fecha_inicio, fecha_fin: modulo.fecha_fin ?? '', monto_honorario: '', observacion: '', estado: 'ACTIVO' })
    setAsignacion({ modulo, actual })
  }

  return (
    <>
      <Link to="/ofertas" className="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primario hover:underline">
        <ArrowLeft className="size-4" aria-hidden /> Volver a ofertas
      </Link>

      <Estado consulta={consulta}>
        {(o) => (
          <div className="space-y-5">
            <div>
              <h1 className="text-2xl font-bold">{o.programa.nombre}</h1>
              <p className="text-sm text-tenue">Oferta {o.codigo} · Gestión {o.gestion}</p>
            </div>
            <Tarjeta
              titulo={<span className="flex flex-wrap items-center gap-2">Datos de la oferta <Insignia estado={o.estado} /></span>}
              acciones={gestiona && (
                <>
                  <Boton variante="secundario" icono={Pencil} onClick={() => (fOferta.reiniciar({ codigo: o.codigo, modalidad: o.modalidad, fecha_inicio: o.fecha_inicio, fecha_fin: o.fecha_fin, cupo_minimo: o.cupo_minimo, cupo_maximo: o.cupo_maximo, gestion: o.gestion }), setEditando(true))}>Editar</Boton>
                  {SIGUIENTES[o.estado].map((s) => <Boton key={s[0]} variante={s[2]} onClick={() => setCambioEstado(s)}>{s[1]}</Boton>)}
                </>
              )}
            >
              <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <Dato etiqueta="Modalidad">{legible(o.modalidad)}</Dato>
                <Dato etiqueta="Periodo">{fecha(o.fecha_inicio)} – {fecha(o.fecha_fin)}</Dato>
                <Dato etiqueta="Gestión">{o.gestion}</Dato>
                <Dato etiqueta="Malla">Versión {o.malla.version} · {o.malla.horas_totales} h</Dato>
                <Dato etiqueta="Cupos">{o.cupos_ocupados} ocupados de {o.cupo_maximo} (mínimo {o.cupo_minimo})</Dato>
                <Dato etiqueta="Disponibles">{o.cupos_disponibles}</Dato>
              </dl>
              {o.estado === 'PLANIFICADA' && (
                <Alerta tipo={o.planes_pago.length ? 'informacion' : 'alerta'} className="mt-4">
                  {o.planes_pago.length
                    ? 'La oferta ya tiene costos configurados: puede abrir las preinscripciones.'
                    : 'Oferta en planificación. Asigne los docentes; el Coordinador Financiero debe configurar los costos (Finanzas › Aranceles) antes de abrir preinscripciones.'}
                </Alerta>
              )}
            </Tarjeta>

            <Tarjeta titulo="Costos y planes de pago" acciones={auth.puede('aranceles.ver') && <Boton a="/aranceles" variante="fantasma">Ir a aranceles</Boton>}>
              {o.planes_pago.length === 0 ? <p className="text-sm text-tenue">Aún no se configuraron costos para esta oferta.</p> : (
                <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                  {o.planes_pago.map((p) => (
                    <li key={p.id_arancel} className="rounded-tema border p-3">
                      <p className="text-sm font-bold">{p.concepto}</p>
                      <p className="tabular text-xl font-extrabold text-primario">{moneda(p.monto)}</p>
                      <p className="text-xs text-tenue">{describirPlan(p)}</p>
                    </li>
                  ))}
                </ul>
              )}
            </Tarjeta>

            <Tarjeta titulo="Materias y docentes" relleno={false}>
              <ul>
                {o.modulos.map((m) => (
                  <li key={m.id_oferta_modulo} className="border-b p-4 last:border-b-0">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                      <div>
                        <p className="font-bold">{m.orden}. {m.nombre} <span className="text-xs font-normal text-tenue">· {m.codigo} · {m.horas} h · {m.obligatorio ? 'Obligatorio' : 'Optativo'}</span></p>
                        <p className="mt-0.5 flex items-center gap-1.5 text-xs text-tenue">
                          <CalendarDays className="size-3.5" aria-hidden /> {fecha(m.fecha_inicio)} – {fecha(m.fecha_fin)} <Insignia estado={m.estado} />
                        </p>
                      </div>
                      <div className="flex gap-1">
                        {gestiona && <Boton tamano="sm" variante="secundario" icono={CalendarDays} onClick={() => (fFechas.reiniciar({ fecha_inicio: m.fecha_inicio, fecha_fin: m.fecha_fin ?? '' }), setModuloFechas(m))}>Fechas</Boton>}
                        {asigna && m.estado !== 'CERRADO' && <Boton tamano="sm" icono={UserPlus} onClick={() => abrirAsignacion(m)}>Asignar docente</Boton>}
                      </div>
                    </div>

                    {m.asignaciones ? (
                      m.asignaciones.length === 0 ? <p className="mt-2 text-sm text-alerta">Sin docente asignado.</p> : (
                        <ul className="mt-2 space-y-1.5">
                          {m.asignaciones.map((a) => (
                            <li key={a.id_asignacion_docente} className="flex flex-wrap items-center justify-between gap-2 rounded-tema bg-suave px-3 py-2 text-sm">
                              <span>
                                <strong>{a.docente.nombre_completo}</strong> <Insignia estado={a.estado} />
                                <span className="block text-xs text-tenue">
                                  {legible(a.tipo_contrato)} · Contrato del {fecha(a.fecha_contrato)} · Honorario {moneda(a.monto_honorario)}
                                </span>
                              </span>
                              {asigna && (
                                <span className="flex gap-1">
                                  <Boton tamano="sm" variante="secundario" icono={Pencil} onClick={() => abrirAsignacion(m, a)}>Contrato</Boton>
                                  <Boton tamano="sm" variante="fantasma" icono={Trash2} className="text-peligro" onClick={() => setQuitando(a)} aria-label="Quitar docente" />
                                </span>
                              )}
                            </li>
                          ))}
                        </ul>
                      )
                    ) : <p className="mt-2 text-sm text-tenue">Docente: {m.docentes.join(', ') || 'por designar'}</p>}
                  </li>
                ))}
              </ul>
            </Tarjeta>

            <Modal abierto={editando} titulo="Editar oferta" alCerrar={() => setEditando(false)}
              pie={<><Boton variante="secundario" onClick={() => setEditando(false)}>Cancelar</Boton><Boton onClick={guardarOferta} cargando={fOferta.enviando}>Guardar</Boton></>}>
              <form onSubmit={guardarOferta} noValidate className="grid gap-4 sm:grid-cols-2">
                {fOferta.errorGeneral && <Alerta tipo="peligro" className="sm:col-span-2">{fOferta.errorGeneral}</Alerta>}
                <CamposOferta f={fOferta} />
              </form>
            </Modal>

            <Modal abierto={Boolean(moduloFechas)} titulo={`Fechas de ${moduloFechas?.nombre ?? ''}`} alCerrar={() => setModuloFechas(null)} ancho="sm"
              pie={<><Boton variante="secundario" onClick={() => setModuloFechas(null)}>Cancelar</Boton><Boton onClick={guardarFechas} cargando={fFechas.enviando}>Guardar</Boton></>}>
              <form onSubmit={guardarFechas} noValidate className="space-y-4">
                {fFechas.errorGeneral && <Alerta tipo="peligro">{fFechas.errorGeneral}</Alerta>}
                <Campo {...fFechas.campo('fecha_inicio')} tipo="date" etiqueta="Inicio de la materia" requerido />
                <Campo {...fFechas.campo('fecha_fin')} tipo="date" etiqueta="Fin de la materia" />
              </form>
            </Modal>

            <Modal abierto={Boolean(asignacion)} titulo={asignacion?.actual ? 'Datos de contratación' : `Asignar docente · ${asignacion?.modulo.nombre ?? ''}`} alCerrar={() => setAsignacion(null)}
              pie={<><Boton variante="secundario" onClick={() => setAsignacion(null)}>Cancelar</Boton><Boton onClick={guardarDocente} cargando={fDocente.enviando}>Guardar</Boton></>}>
              <form onSubmit={guardarDocente} noValidate className="grid gap-4 sm:grid-cols-2">
                {fDocente.errorGeneral && <Alerta tipo="peligro" className="sm:col-span-2">{fDocente.errorGeneral}</Alerta>}
                <Campo {...fDocente.campo('id_usuario_docente')} tipo="select" etiqueta="Docente" requerido disabled={Boolean(asignacion?.actual)} className="sm:col-span-2"
                  opciones={(docentes.datos ?? []).map((d) => ({ valor: d.id_usuario, etiqueta: `${d.apellidos}, ${d.nombres} · ${d.ci}` }))} />
                <Campo {...fDocente.campo('tipo_contrato')} tipo="select" etiqueta="Tipo de contrato" opciones={CONTRATOS} vacio={null} requerido />
                <Campo {...fDocente.campo('fecha_contrato')} tipo="date" etiqueta="Fecha del contrato" requerido />
                <Campo {...fDocente.campo('fecha_inicio')} tipo="date" etiqueta="Inicio de funciones" requerido />
                <Campo {...fDocente.campo('fecha_fin')} tipo="date" etiqueta="Fin de funciones" />
                <Campo {...fDocente.campo('monto_honorario')} tipo="number" min={0} step="0.01" etiqueta="Honorario (Bs.)" requerido />
                {asignacion?.actual && <Campo {...fDocente.campo('estado')} tipo="select" etiqueta="Estado" vacio={null} opciones={[{ valor: 'ACTIVO', etiqueta: 'Activo' }, { valor: 'INACTIVO', etiqueta: 'Inactivo' }]} />}
                <Campo {...fDocente.campo('observacion')} tipo="textarea" etiqueta="Observación" className="sm:col-span-2" />
              </form>
            </Modal>

            <Confirmar abierto={Boolean(quitando)} titulo="Quitar docente" mensaje={`¿Quitar a ${quitando?.docente.nombre_completo} de esta materia?`} textoConfirmar="Quitar" alConfirmar={quitarDocente} alCerrar={() => setQuitando(null)} />
            <Confirmar abierto={Boolean(cambioEstado)} titulo={cambioEstado?.[1]} variante={cambioEstado?.[2] === 'peligro' ? 'peligro' : 'primario'} textoConfirmar="Confirmar"
              mensaje={cambioEstado?.[0] === 'FINALIZADA'
                ? `Se cerrará el curso ${o.codigo}: todas sus materias deben estar cerradas por los docentes. Se finalizará la matrícula de cada estudiante. Para el certificado deben tener TODAS las materias aprobadas (nota mínima y 70 % de asistencia); quien reprobó alguna deberá recuperarla.`
                : `La oferta ${o.codigo} pasará al estado «${legible(cambioEstado?.[0])}».`}
              alConfirmar={confirmarEstado} alCerrar={() => setCambioEstado(null)} />

            <Modal abierto={Boolean(resumenFinal)} titulo="Curso finalizado" alCerrar={() => setResumenFinal(null)} pie={<Boton onClick={() => setResumenFinal(null)}>Entendido</Boton>}>
              {resumenFinal && (
                <div className="space-y-4 text-sm">
                  <Alerta tipo="exito" titulo={`Aprobaron todo: ${resumenFinal.aprobados.length}`}>
                    {resumenFinal.aprobados.length ? resumenFinal.aprobados.join(', ') : 'Nadie aprobó todas las materias.'}
                    <span className="mt-1 block">Siguen en «Requisitos y certificados» para validar y emitir su certificado.</span>
                  </Alerta>
                  {resumenFinal.con_pendientes.length > 0 && (
                    <Alerta tipo="alerta" titulo="Sin certificado hasta recuperar">
                      <ul className="list-disc pl-5">{resumenFinal.con_pendientes.map((p) => <li key={p.estudiante}>{p.estudiante}: {p.materias.join(', ')}</li>)}</ul>
                    </Alerta>
                  )}
                  {resumenFinal.omitidos.length > 0 && <Alerta titulo="No se finalizaron">{resumenFinal.omitidos.join(' · ')}</Alerta>}
                </div>
              )}
            </Modal>
          </div>
        )}
      </Estado>
    </>
  )
}
