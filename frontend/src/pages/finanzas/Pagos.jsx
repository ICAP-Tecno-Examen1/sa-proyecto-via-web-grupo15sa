import { Eye, Plus, Search } from 'lucide-react'
import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import api, { abrirArchivo } from '../../api/cliente'
import { Alerta, Cargando, Dato, EncabezadoPagina, Insignia, Pestanas, Tarjeta } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Campo from '../../components/ui/Campo'
import Modal from '../../components/ui/Modal'
import Tabla, { Paginacion } from '../../components/ui/Tabla'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta, useRetardo } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { fecha, fechaHora, legible, moneda } from '../../utils/formato'
import { maximo, minimo, requerido, requeridoSi } from '../../utils/validacion'

const ESTADOS = ['PENDIENTE', 'CONFIRMADO', 'OBSERVADO', 'RECHAZADO']
const DECISIONES = [
  { valor: 'CONFIRMADO', etiqueta: 'Confirmar el pago' },
  { valor: 'OBSERVADO', etiqueta: 'Observar (falta información)' },
  { valor: 'RECHAZADO', etiqueta: 'Rechazar el pago' },
]
const DECISIONES_EFECTIVO = [
  { valor: 'CONFIRMADO', etiqueta: 'Cobrado: registrar el pago en efectivo' },
  { valor: 'RECHAZADO', etiqueta: 'No se presentó / anular el aviso' },
]

// Vistas de la pantalla (también se abren desde la campana de avisos: /pagos?vista=efectivo)
const VISTAS = {
  todos: { etiqueta: 'Todos', filtro: {} },
  efectivo: { etiqueta: 'Efectivo por cobrar', filtro: { estado: 'PENDIENTE', tipo: 'EFECTIVO' } },
  comprobantes: { etiqueta: 'Comprobantes por revisar', filtro: { estado: 'PENDIENTE', tipo: 'COMPROBANTE' } },
  confirmados: { etiqueta: 'Confirmados', filtro: { estado: 'CONFIRMADO' } },
}

/**
 * CU4 · Pagos y conciliación (Secretaría y Coordinador Financiero).
 *  - Efectivo por cobrar: el estudiante avisó que pagará en caja; al cobrar se confirma.
 *  - Comprobantes por revisar: transferencias o QR bancario que el estudiante adjuntó.
 *  - La tarjeta (Stripe) se confirma sola.
 * Al confirmar, el sistema actualiza la cuota, el saldo y, si es el pago inicial, activa la matrícula.
 */
export default function Pagos() {
  const auth = useAuth()
  const avisar = useAvisos()
  const concilia = auth.puede('pagos.conciliar')
  const [parametros, setParametros] = useSearchParams()
  const vista = VISTAS[parametros.get('vista')] ? parametros.get('vista') : 'todos'
  const [filtros, setFiltros] = useState({ q: '', estado: '', desde: '', hasta: '' })
  const [pagina, setPagina] = useState(1)
  const lista = useConsulta('/pagos', { ...filtros, ...VISTAS[vista].filtro, q: useRetardo(filtros.q), page: pagina })
  const avisos = useConsulta(concilia ? '/pagos/pendientes' : null)
  const cambiar = (campo) => (valor) => (setFiltros((f) => ({ ...f, [campo]: valor })), setPagina(1))
  const cambiarVista = (clave) => (setParametros(clave === 'todos' ? {} : { vista: clave }, { replace: true }), setPagina(1))

  const [pago, setPago] = useState(null)
  const [registrando, setRegistrando] = useState(false)
  const f = useFormulario({ decision: '', observacion: '' }, {
    decision: [requerido('Seleccione una decisión.')],
    observacion: [requeridoSi((v) => v.decision && v.decision !== 'CONFIRMADO', 'Explique el motivo.'), minimo(5), maximo(500)],
  })

  const pendiente = pago?.estado_conciliacion === 'PENDIENTE'
  const esPasarela = pago?.metodo.tipo === 'PASARELA'
  const esEfectivo = pago?.metodo.tipo === 'EFECTIVO'

  const conciliar = f.enviar(async (valores) => {
    await api.post(`/pagos/${pago.id_pago}/conciliar`, { decision: valores.decision, observacion: valores.observacion || null })
    avisar.exito(valores.decision === 'CONFIRMADO'
      ? (esEfectivo ? 'Cobro registrado: la cuota quedó pagada.' : 'Pago confirmado: la cuota y la matrícula fueron actualizadas.')
      : 'Pago conciliado.')
    setPago(null)
    lista.recargar()
    avisos.recargar()
  })

  const abrir = (p) => {
    // En el efectivo lo habitual es «cobrado»: se deja preseleccionado.
    f.reiniciar({ decision: p.metodo.tipo === 'EFECTIVO' && p.estado_conciliacion === 'PENDIENTE' ? 'CONFIRMADO' : '', observacion: '' })
    setPago(p)
  }

  return (
    <>
      <EncabezadoPagina titulo="Pagos y conciliación" descripcion="Cobre el efectivo que los estudiantes avisaron y revise los comprobantes. Los pagos con tarjeta (Stripe) se confirman solos.">
        {auth.puede('pagos.registrar') && <Boton icono={Plus} onClick={() => setRegistrando(true)}>Registrar pago</Boton>}
      </EncabezadoPagina>

      <Pestanas activa={vista} alCambiar={cambiarVista}
        pestanas={Object.entries(VISTAS).map(([clave, v]) => ({ clave, etiqueta: v.etiqueta, contador: clave === 'efectivo' ? avisos.datos?.efectivo : clave === 'comprobantes' ? avisos.datos?.comprobantes : 0 }))} />

      <Tarjeta relleno={false}>
        <div className="flex flex-wrap items-end gap-3 border-b p-3">
          <div className="relative min-w-56 flex-1">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tenue" aria-hidden />
            <input type="search" value={filtros.q} onChange={(e) => cambiar('q')(e.target.value)} placeholder="Buscar por estudiante, matrícula o referencia…" aria-label="Buscar pago" className="campo-control pl-9" />
          </div>
          {vista === 'todos' && (
            <Campo tipo="select" nombre="estado" valor={filtros.estado} alCambiar={cambiar('estado')} vacio="Todos los estados" aria-label="Estado de conciliación" className="w-48"
              opciones={ESTADOS.map((e) => ({ valor: e, etiqueta: legible(e) }))} />
          )}
          <Campo tipo="date" nombre="desde" etiqueta="Desde" valor={filtros.desde} alCambiar={cambiar('desde')} className="w-40" />
          <Campo tipo="date" nombre="hasta" etiqueta="Hasta" valor={filtros.hasta} alCambiar={cambiar('hasta')} className="w-40" />
        </div>

        {lista.error ? <div className="p-4"><Alerta tipo="peligro">{lista.error}</Alerta></div> : (
          <Tabla
            id="id_pago"
            filas={lista.datos?.data}
            cargando={lista.cargando}
            vacio={vista === 'efectivo' ? 'No hay pagos en efectivo por cobrar.' : vista === 'comprobantes' ? 'No hay comprobantes por revisar.' : 'No hay pagos con este filtro.'}
            alSeleccionar={abrir}
            columnas={[
              { clave: 'fecha', titulo: 'Fecha', render: (p) => fechaHora(p.fecha) },
              { clave: 'estudiante', titulo: 'Estudiante', render: (p) => (<><span className="font-semibold">{p.matricula.estudiante}</span><span className="block text-xs text-tenue">{p.matricula.numero_registro} · {p.matricula.programa}</span></>) },
              { clave: 'cuota', titulo: 'Cuota', alinear: 'centro', render: (p) => p.cuota?.numero_cuota ?? '—' },
              { clave: 'metodo', titulo: 'Método', render: (p) => p.metodo.nombre },
              { clave: 'referencia', titulo: 'Referencia', clase: 'max-w-40 truncate' },
              { clave: 'monto', titulo: 'Monto', alinear: 'derecha', render: (p) => <strong>{moneda(p.monto)}</strong> },
              { clave: 'estado', titulo: 'Conciliación', render: (p) => <Insignia estado={p.estado_conciliacion} /> },
            ]}
          />
        )}
        <Paginacion pagina={lista.datos} alCambiar={setPagina} />
      </Tarjeta>

      <Modal abierto={Boolean(pago)} titulo={`Pago #${pago?.id_pago ?? ''}`} alCerrar={() => setPago(null)}
        pie={concilia && pendiente && !esPasarela
          ? (
            <>
              <Boton variante="secundario" onClick={() => setPago(null)}>Cancelar</Boton>
              <Boton variante={esEfectivo && f.valores.decision === 'CONFIRMADO' ? 'exito' : 'primario'} onClick={conciliar} cargando={f.enviando}>
                {esEfectivo && f.valores.decision === 'CONFIRMADO' ? `Registrar cobro de ${moneda(pago.monto)}` : 'Registrar conciliación'}
              </Boton>
            </>
          )
          : <Boton onClick={() => setPago(null)}>Cerrar</Boton>}>
        {pago && (
          <div className="space-y-5">
            <dl className="grid grid-cols-2 gap-4 sm:grid-cols-3">
              <Dato etiqueta="Estudiante">{pago.matricula.estudiante}</Dato>
              <Dato etiqueta="Matrícula">{pago.matricula.numero_registro}</Dato>
              <Dato etiqueta="Programa">{pago.matricula.programa}</Dato>
              <Dato etiqueta="Cuota">{pago.cuota ? `N.º ${pago.cuota.numero_cuota} · ${moneda(pago.cuota.monto_final)}` : '—'}</Dato>
              <Dato etiqueta="Monto pagado"><span className="text-lg font-extrabold text-primario">{moneda(pago.monto)}</span></Dato>
              <Dato etiqueta="Estado"><Insignia estado={pago.estado_conciliacion} /></Dato>
              <Dato etiqueta="Método">{pago.metodo.nombre}</Dato>
              <Dato etiqueta="Referencia">{pago.referencia}</Dato>
              <Dato etiqueta="Fecha">{fechaHora(pago.fecha)}</Dato>
              <Dato etiqueta="Pagador">{pago.pagador.nombre_completo}{pago.parentesco_pagador ? ` (${pago.parentesco_pagador})` : ''}</Dato>
              <Dato etiqueta="Documento del pagador">{pago.pagador.tipo_documento} {pago.pagador.numero_documento}</Dato>
              <Dato etiqueta="Contacto del pagador">{pago.pagador.telefono ?? pago.pagador.correo}</Dato>
              <Dato etiqueta="Registrado por">{pago.registrado_por}</Dato>
              <Dato etiqueta="Conciliado por">{pago.conciliado_por}</Dato>
              <Dato etiqueta="Fecha de conciliación">{pago.fecha_conciliacion ? fechaHora(pago.fecha_conciliacion) : '—'}</Dato>
            </dl>

            {pago.observacion_conciliacion && <Alerta tipo={pago.estado_conciliacion === 'CONFIRMADO' ? 'informacion' : 'alerta'} titulo="Observación de la conciliación">{pago.observacion_conciliacion}</Alerta>}

            {pago.tiene_comprobante
              ? <Boton variante="secundario" icono={Eye} onClick={() => abrirArchivo(`/pagos/${pago.id_pago}/comprobante`).catch(avisar.error)}>Ver comprobante adjunto</Boton>
              : <p className="text-sm text-tenue">{esPasarela ? 'Pago con tarjeta (Stripe): no requiere comprobante.' : 'Pago en efectivo: no lleva comprobante.'}</p>}

            {pendiente && esPasarela && <Alerta tipo="alerta">El estudiante empezó a pagar con tarjeta y no terminó. Se confirmará solo si completa el pago en Stripe; no requiere acción.</Alerta>}
            {pendiente && esEfectivo && <Alerta tipo="alerta" titulo="Efectivo por cobrar">El estudiante avisó que pagará {moneda(pago.monto)} en caja. Cuando reciba el dinero, registre el cobro: la cuota quedará pagada.</Alerta>}

            {concilia && pendiente && !esPasarela && (
              <form onSubmit={conciliar} noValidate className="space-y-4 rounded-tema border bg-suave p-4">
                <h3 className="text-sm font-bold">{esEfectivo ? 'Cobro en caja' : 'Revisión del comprobante'}</h3>
                {f.errorGeneral && <Alerta tipo="peligro">{f.errorGeneral}</Alerta>}
                <Campo {...f.campo('decision')} tipo="select" etiqueta="Decisión" opciones={esEfectivo ? DECISIONES_EFECTIVO : DECISIONES} requerido />
                {f.valores.decision && <Campo {...f.campo('observacion')} tipo="textarea" etiqueta={f.valores.decision === 'CONFIRMADO' ? (esEfectivo ? 'N.º de recibo o nota (opcional)' : 'Nota (opcional)') : 'Motivo'} requerido={f.valores.decision !== 'CONFIRMADO'} />}
              </form>
            )}
          </div>
        )}
      </Modal>

      {registrando && <BuscarMatricula alCerrar={() => setRegistrando(false)} />}
    </>
  )
}

/** Para registrar un pago primero se ubica la matrícula del estudiante. */
function BuscarMatricula({ alCerrar }) {
  const navegar = useNavigate()
  const [texto, setTexto] = useState('')
  const q = useRetardo(texto.trim())
  const lista = useConsulta(q.length >= 2 ? '/matriculas' : null, { q, por_pagina: 8 })

  return (
    <Modal abierto titulo="Registrar pago" alCerrar={alCerrar} pie={<Boton variante="secundario" onClick={alCerrar}>Cerrar</Boton>}>
      <p className="mb-3 text-sm">Busque al estudiante por nombre, documento o número de registro. En su matrícula podrá registrar el pago de la cuota (efectivo o comprobante).</p>
      <div className="relative mb-3">
        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tenue" aria-hidden />
        <input type="search" autoFocus value={texto} onChange={(e) => setTexto(e.target.value)} placeholder="Nombre, documento o N.º de registro…" aria-label="Buscar matrícula" className="campo-control pl-9" />
      </div>
      {q.length < 2 ? <p className="text-sm text-tenue">Escriba al menos 2 caracteres.</p> : lista.cargando ? <Cargando className="py-6" /> : (
        <ul className="space-y-1.5">
          {(lista.datos?.data ?? []).length === 0 && <li className="text-sm text-tenue">No se encontraron matrículas.</li>}
          {(lista.datos?.data ?? []).map((m) => (
            <li key={m.id_matricula}>
              <button onClick={() => navegar(`/matriculas/${m.id_matricula}?pestana=pagos`)} className="flex w-full flex-wrap items-center justify-between gap-2 rounded-tema border px-3 py-2 text-left hover:bg-primario-suave">
                <span>
                  <strong>{m.estudiante.nombre_completo}</strong>
                  <span className="block text-xs text-tenue">{m.numero_registro} · {m.programa.nombre} · {fecha(m.fecha)}</span>
                </span>
                <Insignia estado={m.estado} />
              </button>
            </li>
          ))}
        </ul>
      )}
    </Modal>
  )
}
