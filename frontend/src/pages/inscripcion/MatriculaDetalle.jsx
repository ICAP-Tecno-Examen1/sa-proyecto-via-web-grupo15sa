import { ArrowLeft, Award, Ban, FlagTriangleRight, FolderOpen, Percent, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import api from '../../api/cliente'
import Expediente from '../../components/inscripcion/Expediente'
import ProgresoAcademico from '../../components/inscripcion/ProgresoAcademico'
import EstadoCuenta from '../../components/pagos/EstadoCuenta'
import ModalPago from '../../components/pagos/ModalPago'
import { Alerta, Dato, Estado, Insignia, Pestanas } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import Modal, { Confirmar } from '../../components/ui/Modal'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { fecha, legible, porcentaje } from '../../utils/formato'
import { entre, numero, requerido } from '../../utils/validacion'

/**
 * CU3 / CU4 · Ficha de la matrícula. Cada rol ve las mismas pestañas, pero
 * solo las acciones que le concede la matriz de permisos:
 *   Secretaria: registrar pagos, expediente, anular
 *   Coord. Académico: inscribir en materias, finalizar programa
 *   Coord. Financiero: becas, registrar pagos
 */
export default function MatriculaDetalle() {
  const { id } = useParams()
  const auth = useAuth()
  const avisar = useAvisos()
  const consulta = useConsulta(`/matriculas/${id}`)
  const [parametros] = useSearchParams()
  const [pestana, setPestana] = useState(parametros.get('pestana') === 'pagos' ? 'pagos' : 'academico')
  const [accion, setAccion] = useState(null) // { tipo, dato? }
  const [procesando, setProcesando] = useState(false)
  const [cuotaAPagar, setCuotaAPagar] = useState(null)
  const [modulosElegidos, setModulosElegidos] = useState([])

  const becas = useConsulta(accion?.tipo === 'beca' ? '/becas' : null)
  const fBeca = useFormulario({ id_beca: '', porcentaje_aplicado: '' }, { id_beca: [requerido('Seleccione la beca.')], porcentaje_aplicado: [numero(), entre(0.01, 100)] })

  const ejecutar = async (peticion, mensaje) => {
    setProcesando(true)

    try {
      const { data } = await peticion()
      if (data?.id_matricula) consulta.setDatos(data)
      else await consulta.recargar()
      avisar.exito(data?.message ?? mensaje)
      setAccion(null)
    } catch (e) {
      avisar.error(e)
    } finally {
      setProcesando(false)
    }
  }

  const asignarBeca = fBeca.enviar(async (v) => {
    const { data } = await api.post(`/matriculas/${id}/becas`, { id_beca: Number(v.id_beca), porcentaje_aplicado: v.porcentaje_aplicado ? Number(v.porcentaje_aplicado) : null })
    avisar.exito(data.message)
    setAccion(null)
    consulta.recargar()
  })

  return (
    <>
      <Link to="/matriculas" className="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primario hover:underline">
        <ArrowLeft className="size-4" aria-hidden /> Volver a matrículas
      </Link>

      <Estado consulta={consulta}>
        {(m) => {
          const vigente = ['PENDIENTE_PAGO', 'ACTIVA'].includes(m.estado)
          const puedePagar = auth.puede('pagos.registrar') && vigente

          return (
            <div className="space-y-5">
              <section className="tarjeta p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h1 className="flex flex-wrap items-center gap-2 text-2xl font-bold">{m.estudiante.nombre_completo} <Insignia estado={m.estado} /></h1>
                    <p className="text-sm text-tenue">Matrícula {m.numero_registro} · {m.programa.nombre}</p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {auth.puede('expedientes.gestionar') && <Boton variante="secundario" icono={FolderOpen} onClick={() => setAccion({ tipo: 'expediente' })}>Expediente</Boton>}
                    {auth.puede('inscripciones.gestionar') && m.estado === 'ACTIVA' && <Boton icono={FlagTriangleRight} onClick={() => setAccion({ tipo: 'finalizar' })}>Finalizar programa</Boton>}
                    {auth.puede('certificacion.ver') && m.estado === 'FINALIZADA' && <Boton a={`/certificacion/${m.id_matricula}`} icono={Award}>Requisitos y certificado</Boton>}
                    {auth.puede('matriculas.gestionar') && vigente && <Boton variante="fantasma" className="text-peligro" icono={Ban} onClick={() => setAccion({ tipo: 'anular' })}>Anular</Boton>}
                  </div>
                </div>

                <dl className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                  <Dato etiqueta="Documento">{m.estudiante.ci}</Dato>
                  <Dato etiqueta="Contacto">{m.estudiante.correo}<br />{m.estudiante.telefono}</Dato>
                  <Dato etiqueta="Oferta">{m.oferta.codigo} · {legible(m.oferta.modalidad)}</Dato>
                  <Dato etiqueta="Periodo">{fecha(m.oferta.fecha_inicio)} – {fecha(m.oferta.fecha_fin)}</Dato>
                  <Dato etiqueta="Fecha de matrícula">{fecha(m.fecha)}</Dato>
                  <Dato etiqueta="Plan de pago">{legible(m.tipo_cobro)}</Dato>
                  <Dato etiqueta="Finalización">{fecha(m.fecha_finalizacion)}</Dato>
                  <Dato etiqueta="Certificado">{m.certificado?.codigo_unico ?? 'No emitido'}</Dato>
                </dl>

                {m.estado === 'PENDIENTE_PAGO' && <Alerta tipo="alerta" className="mt-4">Matrícula pendiente de pago: se activará, e inscribirá al estudiante en sus materias, cuando se confirme el pago inicial (cuota 1).</Alerta>}
              </section>

              <div>
                <Pestanas activa={pestana} alCambiar={setPestana} pestanas={[
                  { clave: 'academico', etiqueta: 'Seguimiento académico' },
                  { clave: 'pagos', etiqueta: 'Pagos y cuotas' },
                  { clave: 'becas', etiqueta: 'Becas', contador: m.becas.filter((b) => b.estado === 'ACTIVA').length },
                ]} />

                <div className="tarjeta p-5">
                  {pestana === 'academico' && (
                    <>
                      {auth.puede('inscripciones.gestionar') && m.estado === 'ACTIVA' && m.modulos_disponibles.length > 0 && (
                        <div className="mb-4 flex justify-end">
                          <Boton variante="secundario" icono={Plus} onClick={() => (setModulosElegidos([]), setAccion({ tipo: 'inscribir' }))}>Inscribir en materias</Boton>
                        </div>
                      )}
                      <ProgresoAcademico progreso={m.progreso} acciones={auth.puede('inscripciones.gestionar') && m.estado === 'ACTIVA' ? (mod) => mod.estado === 'INSCRITO' && (
                        <Boton tamano="sm" variante="fantasma" className="text-peligro" icono={Trash2} aria-label="Retirar de la materia" onClick={() => setAccion({ tipo: 'retirar', dato: mod })} />
                      ) : undefined} />
                    </>
                  )}

                  {pestana === 'pagos' && <EstadoCuenta cuenta={m.cuenta} alPagar={puedePagar ? setCuotaAPagar : undefined} />}

                  {pestana === 'becas' && (
                    <>
                      {auth.puede('becas.asignar') && vigente && (
                        <div className="mb-4 flex justify-end">
                          <Boton icono={Percent} onClick={() => (fBeca.reiniciar(), setAccion({ tipo: 'beca' }))}>Asignar beca</Boton>
                        </div>
                      )}
                      {m.becas.length === 0 ? <p className="text-sm text-tenue">Esta matrícula no tiene becas asignadas.</p> : (
                        <ul className="space-y-2">
                          {m.becas.map((b) => (
                            <li key={b.id_beca_estudiante} className="flex flex-wrap items-center justify-between gap-2 rounded-tema border px-4 py-3">
                              <span>
                                <strong>{b.beca.nombre}</strong> · {porcentaje(b.porcentaje_aplicado)} <Insignia estado={b.estado} />
                                <span className="block text-xs text-tenue">{b.beca.institucion_financiadora ?? 'ICAP'} · Asignada el {fecha(b.fecha_asignacion)}</span>
                              </span>
                              {auth.puede('becas.asignar') && b.estado === 'ACTIVA' && vigente && <Boton tamano="sm" variante="fantasma" className="text-peligro" onClick={() => setAccion({ tipo: 'revocar', dato: b })}>Revocar</Boton>}
                            </li>
                          ))}
                        </ul>
                      )}
                    </>
                  )}
                </div>
              </div>

              <ModalPago cuota={cuotaAPagar} titular={m.estudiante} alCerrar={() => setCuotaAPagar(null)} alTerminar={consulta.recargar} />

              <Modal abierto={accion?.tipo === 'expediente'} titulo={`Expediente de ${m.estudiante.nombre_completo}`} alCerrar={() => setAccion(null)} ancho="lg">
                <Expediente recurso={`/expedientes/${m.id_usuario}`} puedeSubir />
              </Modal>

              <Modal abierto={accion?.tipo === 'inscribir'} titulo="Inscribir en materias" alCerrar={() => setAccion(null)}
                pie={<><Boton variante="secundario" onClick={() => setAccion(null)}>Cancelar</Boton>
                  <Boton disabled={!modulosElegidos.length} cargando={procesando} onClick={() => ejecutar(() => api.post(`/matriculas/${id}/inscripciones`, { modulos_oferta: modulosElegidos }), 'Estudiante inscrito en las materias seleccionadas.')}>Inscribir</Boton></>}>
                <p className="mb-3 text-sm">Seleccione las materias de la oferta en los que se inscribirá al estudiante. Aparecerá en la lista del docente.</p>
                <ul className="space-y-1.5">
                  {m.modulos_disponibles.map((mod) => (
                    <li key={mod.id_oferta_modulo}>
                      <label className="flex cursor-pointer items-center gap-3 rounded-tema border px-3 py-2 text-sm hover:bg-suave">
                        <input type="checkbox" className="size-4 accent-[var(--primario)]" disabled={mod.estado === 'CERRADO'} checked={modulosElegidos.includes(mod.id_oferta_modulo)}
                          onChange={(e) => setModulosElegidos((p) => (e.target.checked ? [...p, mod.id_oferta_modulo] : p.filter((x) => x !== mod.id_oferta_modulo)))} />
                        <span className="flex-1">{mod.orden}. {mod.nombre} <span className="text-xs text-tenue">· {mod.horas} h · {mod.obligatorio ? 'Obligatorio' : 'Optativo'}{mod.estado === 'CERRADO' ? ' · materia cerrada' : ''}</span></span>
                      </label>
                    </li>
                  ))}
                </ul>
              </Modal>

              <Modal abierto={accion?.tipo === 'beca'} titulo="Asignar beca" alCerrar={() => setAccion(null)} ancho="sm"
                pie={<><Boton variante="secundario" onClick={() => setAccion(null)}>Cancelar</Boton><Boton onClick={asignarBeca} cargando={fBeca.enviando}>Asignar</Boton></>}>
                <form onSubmit={asignarBeca} noValidate className="space-y-4">
                  {fBeca.errorGeneral && <Alerta tipo="peligro">{fBeca.errorGeneral}</Alerta>}
                  <Campo {...fBeca.campo('id_beca')} tipo="select" etiqueta="Beca" requerido opciones={(becas.datos ?? []).map((b) => ({ valor: b.id_beca, etiqueta: `${b.nombre} (${b.porcentaje}%)` }))} />
                  <Campo {...fBeca.campo('porcentaje_aplicado')} tipo="number" min="0.01" max="100" step="0.01" etiqueta="Porcentaje a aplicar (opcional)" ayuda="Si lo deja vacío se usa el porcentaje de la beca. El descuento se aplica a las cuotas aún no pagadas." />
                </form>
              </Modal>

              <Confirmar abierto={accion?.tipo === 'finalizar'} titulo="Finalizar programa" variante="primario" textoConfirmar="Finalizar" cargando={procesando} alCerrar={() => setAccion(null)}
                mensaje="Se cerrará el ciclo académico del estudiante. Todas sus materias deben estar cerradas por los docentes. Después se validarán sus requisitos para el certificado."
                alConfirmar={() => ejecutar(() => api.post(`/matriculas/${id}/finalizar`), 'Programa finalizado. Ya se pueden validar los requisitos del certificado.')} />
              <Confirmar abierto={accion?.tipo === 'anular'} titulo="Anular matrícula" textoConfirmar="Anular" cargando={procesando} alCerrar={() => setAccion(null)}
                mensaje="La matrícula quedará anulada, sus cuotas pendientes se cancelarán y el cupo quedará libre. Esta acción no se puede deshacer."
                alConfirmar={() => ejecutar(() => api.post(`/matriculas/${id}/anular`), 'Matrícula anulada.')} />
              <Confirmar abierto={accion?.tipo === 'retirar'} titulo="Retirar de la materia" textoConfirmar="Retirar" cargando={procesando} alCerrar={() => setAccion(null)}
                mensaje={`¿Retirar al estudiante de la materia «${accion?.dato?.modulo}»?`}
                alConfirmar={() => ejecutar(() => api.delete(`/inscripciones/${accion.dato.id_inscripcion_modulo}`), 'Inscripción retirada.')} />
              <Confirmar abierto={accion?.tipo === 'revocar'} titulo="Revocar beca" textoConfirmar="Revocar" cargando={procesando} alCerrar={() => setAccion(null)}
                mensaje="Las cuotas que aún no fueron pagadas volverán a su monto sin descuento."
                alConfirmar={() => ejecutar(() => api.delete(`/becas-asignadas/${accion.dato.id_beca_estudiante}`), 'Beca revocada.')} />
            </div>
          )
        }}
      </Estado>
    </>
  )
}
