import { Eye, Plus, Search } from 'lucide-react'
import { useState } from 'react'
import api, { abrirArchivo } from '../../api/cliente'
import { Alerta, Cargando, Dato, EncabezadoPagina, Insignia, Tarjeta } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import Modal from '../../components/ui/Modal'
import Tabla, { Paginacion } from '../../components/ui/Tabla'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta, useRetardo } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { describirPlan, fecha, legible, moneda } from '../../utils/formato'
import { maximo, minimo, requerido, requeridoSi } from '../../utils/validacion'

const ESTADOS = ['PENDIENTE', 'OBSERVADA', 'APROBADA', 'RECHAZADA']
const DECISIONES = [
  { valor: 'APROBADA', etiqueta: 'Aprobar y generar matrícula' },
  { valor: 'OBSERVADA', etiqueta: 'Observar (devolver al estudiante)' },
  { valor: 'RECHAZADA', etiqueta: 'Rechazar' },
]

/** CU3 · La Secretaria revisa preinscripciones: aprueba, observa o rechaza. */
export default function Preinscripciones() {
  const auth = useAuth()
  const avisar = useAvisos()
  const revisa = auth.puede('preinscripciones.revisar')
  const [filtros, setFiltros] = useState({ q: '', estado: 'PENDIENTE' })
  const [pagina, setPagina] = useState(1)
  const lista = useConsulta('/preinscripciones', { ...filtros, q: useRetardo(filtros.q), page: pagina })

  const [idAbierta, setIdAbierta] = useState(null)
  const detalle = useConsulta(idAbierta ? `/preinscripciones/${idAbierta}` : null)
  const [nueva, setNueva] = useState(false)

  const f = useFormulario({ decision: '', id_arancel: '', observacion: '' }, {
    decision: [requerido('Seleccione una decisión.')],
    id_arancel: [requeridoSi((v) => v.decision === 'APROBADA', 'Seleccione el plan de pago de la matrícula.')],
    observacion: [requeridoSi((v) => v.decision && v.decision !== 'APROBADA', 'Explique el motivo al estudiante.'), minimo(5), maximo(500)],
  })

  const abrir = (p) => {
    f.reiniciar({ decision: '', id_arancel: p.id_arancel ?? '', observacion: '' })
    setIdAbierta(p.id_preinscripcion)
  }

  const resolver = f.enviar(async (valores) => {
    const { data } = await api.post(`/preinscripciones/${idAbierta}/revisar`, {
      decision: valores.decision,
      id_arancel: valores.decision === 'APROBADA' ? Number(valores.id_arancel) : null,
      observacion: valores.observacion || null,
    })
    avisar.exito(data.message)
    setIdAbierta(null)
    lista.recargar()
  })

  const p = detalle.datos
  const revisable = p && ['PENDIENTE', 'OBSERVADA'].includes(p.estado)

  return (
    <>
      <EncabezadoPagina titulo="Preinscripciones" descripcion="Revise los datos y documentos de cada solicitud. Al aprobar se genera la matrícula pendiente de pago con su plan de cuotas.">
        {revisa && <Boton icono={Plus} onClick={() => setNueva(true)}>Preinscribir estudiante</Boton>}
      </EncabezadoPagina>

      <Tarjeta relleno={false}>
        <div className="flex flex-wrap items-end gap-3 border-b p-3">
          <div className="relative min-w-56 flex-1">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tenue" aria-hidden />
            <input type="search" value={filtros.q} onChange={(e) => (setFiltros((x) => ({ ...x, q: e.target.value })), setPagina(1))} placeholder="Buscar por estudiante o documento…" aria-label="Buscar preinscripción" className="campo-control pl-9" />
          </div>
          <Campo tipo="select" nombre="estado" valor={filtros.estado} alCambiar={(v) => (setFiltros((x) => ({ ...x, estado: v })), setPagina(1))} vacio="Todos los estados" aria-label="Estado" className="w-48"
            opciones={ESTADOS.map((e) => ({ valor: e, etiqueta: legible(e) }))} />
        </div>

        {lista.error ? <div className="p-4"><Alerta tipo="peligro">{lista.error}</Alerta></div> : (
          <Tabla
            id="id_preinscripcion"
            filas={lista.datos?.data}
            cargando={lista.cargando}
            alSeleccionar={abrir}
            vacio="No hay preinscripciones con este filtro."
            columnas={[
              { clave: 'fecha', titulo: 'Fecha', render: (x) => fecha(x.fecha) },
              { clave: 'estudiante', titulo: 'Estudiante', render: (x) => (<><span className="font-semibold">{x.estudiante.nombre_completo}</span><span className="block text-xs text-tenue">Doc. {x.estudiante.ci}</span></>) },
              { clave: 'programa', titulo: 'Programa', render: (x) => (<>{x.oferta.programa.nombre}<span className="block text-xs text-tenue">{x.oferta.codigo} · {x.oferta.cupos_disponibles} cupos libres</span></>) },
              { clave: 'plan', titulo: 'Plan preferido', render: (x) => x.plan_preferido?.concepto ?? '—' },
              { clave: 'estado', titulo: 'Estado', render: (x) => <Insignia estado={x.estado} /> },
              { clave: 'matricula', titulo: 'Matrícula', render: (x) => x.matricula?.numero_registro ?? '—' },
            ]}
          />
        )}
        <Paginacion pagina={lista.datos} alCambiar={setPagina} />
      </Tarjeta>

      <Modal abierto={Boolean(idAbierta)} titulo="Revisión de preinscripción" alCerrar={() => setIdAbierta(null)} ancho="lg"
        pie={revisa && revisable ? <><Boton variante="secundario" onClick={() => setIdAbierta(null)}>Cancelar</Boton><Boton onClick={resolver} cargando={f.enviando}>Registrar decisión</Boton></> : <Boton onClick={() => setIdAbierta(null)}>Cerrar</Boton>}>
        {detalle.cargando || !p ? (detalle.error ? <Alerta tipo="peligro">{detalle.error}</Alerta> : <Cargando />) : (
          <div className="space-y-5">
            <section>
              <h3 className="mb-2 flex items-center gap-2 text-sm font-bold">Datos del estudiante <Insignia estado={p.estado} /></h3>
              <dl className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <Dato etiqueta="Nombre">{p.estudiante.nombre_completo}</Dato>
                <Dato etiqueta="Documento">{p.estudiante.tipo_documento} {p.estudiante.ci}</Dato>
                <Dato etiqueta="Nacimiento">{fecha(p.estudiante.fecha_nacimiento)}</Dato>
                <Dato etiqueta="Correo">{p.estudiante.correo}</Dato>
                <Dato etiqueta="Teléfono">{p.estudiante.telefono}</Dato>
                <Dato etiqueta="Dirección">{p.estudiante.direccion}</Dato>
              </dl>
            </section>

            <section>
              <h3 className="mb-2 text-sm font-bold">Oferta solicitada</h3>
              <dl className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <Dato etiqueta="Programa">{p.oferta.programa.nombre}</Dato>
                <Dato etiqueta="Oferta">{p.oferta.codigo} · {legible(p.oferta.modalidad)}</Dato>
                <Dato etiqueta="Cupos disponibles">{p.oferta.cupos_disponibles} de {p.oferta.cupo_maximo}</Dato>
              </dl>
              {p.observacion && <p className="mt-2 rounded-tema bg-suave p-2 text-sm"><strong>Observación registrada:</strong> {p.observacion}</p>}
            </section>

            <section>
              <h3 className="mb-2 text-sm font-bold">Expediente de documentos</h3>
              {p.documentos_faltantes.length > 0
                ? <Alerta tipo="alerta" className="mb-2">Falta: {p.documentos_faltantes.join(', ')}. No se puede aprobar hasta que el expediente esté completo.</Alerta>
                : <Alerta tipo="exito" className="mb-2">El expediente tiene todos los documentos requeridos.</Alerta>}
              <ul className="space-y-1">
                {p.documentos.map((d) => (
                  <li key={d.id_documento} className="flex items-center justify-between gap-2 rounded-tema border px-3 py-2 text-sm">
                    <span>{d.nombre} <span className="text-xs text-tenue">· {legible(d.tipo)}</span></span>
                    {d.tiene_archivo && <Boton tamano="sm" variante="secundario" icono={Eye} onClick={() => abrirArchivo(`/documentos/${d.id_documento}/descargar`, { params: { ver: 1 } }).catch(avisar.error)}>Ver</Boton>}
                  </li>
                ))}
              </ul>
            </section>

            {revisa && revisable && (
              <form onSubmit={resolver} noValidate className="space-y-4 rounded-tema border bg-suave p-4">
                <h3 className="text-sm font-bold">Decisión</h3>
                {f.errorGeneral && <Alerta tipo="peligro">{f.errorGeneral}</Alerta>}
                <Campo {...f.campo('decision')} tipo="select" etiqueta="Resultado de la revisión" opciones={DECISIONES} requerido />
                {f.valores.decision === 'APROBADA' && (
                  <Campo {...f.campo('id_arancel')} tipo="select" etiqueta="Plan de pago de la matrícula" requerido ayuda="Con este plan se generan las cuotas y sus fechas de vencimiento."
                    opciones={p.oferta.planes_pago.map((a) => ({ valor: a.id_arancel, etiqueta: `${a.concepto} — ${moneda(a.monto)} (${describirPlan(a)})` }))} />
                )}
                {f.valores.decision && (
                  <Campo {...f.campo('observacion')} tipo="textarea" etiqueta={f.valores.decision === 'APROBADA' ? 'Nota interna (opcional)' : 'Motivo para el estudiante'} requerido={f.valores.decision !== 'APROBADA'} />
                )}
              </form>
            )}
          </div>
        )}
      </Modal>

      {nueva && <NuevaPreinscripcion alCerrar={() => setNueva(false)} alGuardar={() => (setNueva(false), lista.recargar())} />}
    </>
  )
}

/** La Secretaria preinscribe a un estudiante que se presenta en ventanilla. */
function NuevaPreinscripcion({ alCerrar, alGuardar }) {
  const avisar = useAvisos()
  const estudiantes = useConsulta('/personas/estudiantes')
  const ofertas = useConsulta('/ofertas', { estado: 'ABIERTA' })
  const f = useFormulario({ id_usuario: '', id_oferta: '', id_arancel: '' }, {
    id_usuario: [requerido('Seleccione al estudiante.')],
    id_oferta: [requerido('Seleccione la oferta.')],
  })
  const oferta = ofertas.datos?.find((o) => String(o.id_oferta) === String(f.valores.id_oferta))

  const guardar = f.enviar(async (v) => {
    await api.post('/preinscripciones', { id_usuario: Number(v.id_usuario), id_oferta: Number(v.id_oferta), id_arancel: v.id_arancel ? Number(v.id_arancel) : null })
    avisar.exito('Preinscripción registrada. Ya puede revisarla.')
    alGuardar()
  })

  return (
    <Modal abierto titulo="Preinscribir estudiante" alCerrar={alCerrar}
      pie={<><Boton variante="secundario" onClick={alCerrar}>Cancelar</Boton><Boton onClick={guardar} cargando={f.enviando}>Registrar</Boton></>}>
      <form onSubmit={guardar} noValidate className="space-y-4">
        {f.errorGeneral && <Alerta tipo="peligro">{f.errorGeneral}</Alerta>}
        <Campo {...f.campo('id_usuario')} tipo="select" etiqueta="Estudiante" requerido ayuda="Si aún no tiene cuenta, regístrelo primero en Administración › Estudiantes."
          opciones={(estudiantes.datos ?? []).map((e) => ({ valor: e.id_usuario, etiqueta: `${e.apellidos}, ${e.nombres} · ${e.ci}` }))} />
        <Campo {...f.campo('id_oferta')} tipo="select" etiqueta="Oferta académica abierta" requerido
          opciones={(ofertas.datos ?? []).map((o) => ({ valor: o.id_oferta, etiqueta: `${o.programa.nombre} · ${o.codigo} (${o.cupos_disponibles} cupos)` }))} />
        {oferta && (
          <Campo {...f.campo('id_arancel')} tipo="select" etiqueta="Plan de pago preferido" vacio="Por definir"
            opciones={oferta.planes_pago.map((a) => ({ valor: a.id_arancel, etiqueta: `${a.concepto} — ${moneda(a.monto)}` }))} />
        )}
      </form>
    </Modal>
  )
}
