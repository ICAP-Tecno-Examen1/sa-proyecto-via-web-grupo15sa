import { ArrowRight } from 'lucide-react'
import { useState } from 'react'
import api from '../../api/cliente'
import { Alerta, Dato, EncabezadoPagina, Estado, Insignia, Tarjeta } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import Modal from '../../components/ui/Modal'
import Tabla from '../../components/ui/Tabla'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { fechaHora, legible } from '../../utils/formato'
import { maximo, minimo, requerido, requeridoSi } from '../../utils/validacion'

/**
 * CU5 · Flujo de corrección de notas.
 * El docente solicita desde la planilla; aquí el Coordinador Académico aprueba
 * o rechaza. El registro conserva nota anterior, nota nueva, motivo y quién autorizó.
 */
export default function Correcciones() {
  const auth = useAuth()
  const avisar = useAvisos()
  const resuelve = auth.puede('correcciones.resolver')
  const [estado, setEstado] = useState(resuelve ? 'PENDIENTE' : '')
  const consulta = useConsulta('/correcciones', { estado })
  const [abierta, setAbierta] = useState(null)
  const f = useFormulario({ decision: '', observacion: '' }, {
    decision: [requerido('Seleccione una decisión.')],
    observacion: [requeridoSi((v) => v.decision === 'RECHAZAR', 'Explique el motivo del rechazo.'), minimo(5), maximo(500)],
  })

  const resolver = f.enviar(async (v) => {
    const { data } = await api.post(`/correcciones/${abierta.id_correccion}/resolver`, { aprobar: v.decision === 'APROBAR', observacion: v.observacion || null })
    avisar.exito(data.message)
    setAbierta(null)
    consulta.recargar()
  })

  const pendiente = abierta?.estado === 'PENDIENTE'

  return (
    <>
      <EncabezadoPagina titulo="Correcciones de nota" descripcion={resuelve
        ? 'Solicitudes de los docentes para modificar una calificación. Al aprobar, la nota cambia y se recalcula el resultado del estudiante.'
        : 'Sus solicitudes de corrección. Para crear una nueva, abra el módulo y use «Corregir» junto a la calificación.'}>
        <Campo tipo="select" nombre="estado" valor={estado} alCambiar={setEstado} vacio="Todas" aria-label="Estado" className="w-44"
          opciones={['PENDIENTE', 'APROBADA', 'RECHAZADA'].map((e) => ({ valor: e, etiqueta: legible(e) }))} />
      </EncabezadoPagina>

      <Tarjeta relleno={false}>
        <Estado consulta={consulta}>
          {(filas) => (
            <Tabla
              id="id_correccion"
              filas={filas}
              vacio="No hay solicitudes de corrección."
              alSeleccionar={(c) => (f.reiniciar(), setAbierta(c))}
              columnas={[
                { clave: 'fecha_solicitud', titulo: 'Solicitada', render: (c) => fechaHora(c.fecha_solicitud) },
                { clave: 'estudiante', titulo: 'Estudiante', clase: 'font-semibold' },
                { clave: 'modulo', titulo: 'Módulo / evaluación', render: (c) => (<>{c.modulo}<span className="block text-xs text-tenue">{c.evaluacion} · {c.oferta}</span></>) },
                { clave: 'cambio', titulo: 'Cambio', render: (c) => (
                  <span className="tabular inline-flex items-center gap-1.5 font-bold">{c.nota_anterior} <ArrowRight className="size-3.5 text-tenue" aria-hidden /> {c.nota_nueva}</span>
                ) },
                { clave: 'solicitante', titulo: 'Docente' },
                { clave: 'estado', titulo: 'Estado', render: (c) => <Insignia estado={c.estado} /> },
              ]}
            />
          )}
        </Estado>
      </Tarjeta>

      <Modal abierto={Boolean(abierta)} titulo="Corrección de nota" alCerrar={() => setAbierta(null)}
        pie={resuelve && pendiente ? <><Boton variante="secundario" onClick={() => setAbierta(null)}>Cancelar</Boton><Boton onClick={resolver} cargando={f.enviando}>Registrar decisión</Boton></> : <Boton onClick={() => setAbierta(null)}>Cerrar</Boton>}>
        {abierta && (
          <div className="space-y-4">
            <dl className="grid grid-cols-2 gap-4 sm:grid-cols-3">
              <Dato etiqueta="Estudiante">{abierta.estudiante}</Dato>
              <Dato etiqueta="Módulo">{abierta.modulo}</Dato>
              <Dato etiqueta="Evaluación">{abierta.evaluacion}</Dato>
              <Dato etiqueta="Nota anterior"><span className="text-xl font-extrabold">{abierta.nota_anterior}</span></Dato>
              <Dato etiqueta="Nota nueva"><span className="text-xl font-extrabold text-primario">{abierta.nota_nueva}</span></Dato>
              <Dato etiqueta="Estado"><Insignia estado={abierta.estado} /></Dato>
              <Dato etiqueta="Solicitó">{abierta.solicitante}</Dato>
              <Dato etiqueta="Fecha de solicitud">{fechaHora(abierta.fecha_solicitud)}</Dato>
              <Dato etiqueta="Autorizó">{abierta.autorizador ? `${abierta.autorizador} · ${fechaHora(abierta.fecha_autorizacion)}` : 'Pendiente'}</Dato>
            </dl>
            <div className="rounded-tema bg-suave p-3 text-sm"><strong className="block text-xs text-tenue uppercase">Motivo del docente</strong>{abierta.motivo}</div>
            {abierta.observacion_autorizador && <Alerta tipo={abierta.estado === 'RECHAZADA' ? 'peligro' : 'informacion'} titulo="Respuesta del Coordinador Académico">{abierta.observacion_autorizador}</Alerta>}

            {resuelve && pendiente && (
              <form onSubmit={resolver} noValidate className="space-y-4 rounded-tema border bg-suave p-4">
                {f.errorGeneral && <Alerta tipo="peligro">{f.errorGeneral}</Alerta>}
                <Campo {...f.campo('decision')} tipo="select" etiqueta="Decisión" requerido opciones={[{ valor: 'APROBAR', etiqueta: 'Aprobar: actualizar la nota' }, { valor: 'RECHAZAR', etiqueta: 'Rechazar: mantener la nota anterior' }]} />
                {f.valores.decision && <Campo {...f.campo('observacion')} tipo="textarea" etiqueta={f.valores.decision === 'RECHAZAR' ? 'Motivo del rechazo' : 'Comentario (opcional)'} requerido={f.valores.decision === 'RECHAZAR'} />}
              </form>
            )}
          </div>
        )}
      </Modal>
    </>
  )
}
