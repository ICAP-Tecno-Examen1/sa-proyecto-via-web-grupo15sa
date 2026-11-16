import { CircleCheck, QrCode, RefreshCw, Upload } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import api, { aFormData, mensajeError } from '../../api/cliente'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta } from '../../hooks/useConsulta'
import { useFormulario } from '../../hooks/useFormulario'
import { moneda } from '../../utils/formato'
import { archivo, correo, documento, maximo, mayorQue, minimo, numero, requerido, requeridoSi, soloLetras, telefono } from '../../utils/validacion'
import { TIPOS_DOCUMENTO } from '../acceso/persona'
import { Alerta, Cargando } from '../ui/Basicos'
import Boton from '../ui/Boton'
import Campo from '../ui/Campo'
import Modal from '../ui/Modal'

const PARENTESCOS = ['Titular', 'Padre', 'Madre', 'Tutor', 'Cónyuge', 'Empresa', 'Otro']
const SONDEO_MS = 5000

/**
 * CU4 · Pago de una cuota (requisito 10).
 *
 * Paso 1: se elige el método y se registran los datos de quien paga (no
 *         siempre es el estudiante).
 * Paso 2 según el método:
 *   - PASARELA (QR PagoFácil): se muestra el QR y la pantalla consulta el
 *     estado cada 5 segundos hasta que el webhook confirma el pago.
 *   - COMPROBANTE / EFECTIVO: queda registrado a la espera de conciliación.
 */
export default function ModalPago({ cuota, titular, alCerrar, alTerminar }) {
  const auth = useAuth()
  const avisar = useAvisos()
  const metodos = useConsulta(cuota ? '/metodos-pago/disponibles' : null)
  const [qr, setQr] = useState(null) // { pago, qr }
  const [pagado, setPagado] = useState(false)
  const [simulando, setSimulando] = useState(false)
  const sondeo = useRef(null)

  const f = useFormulario(
    {
      id_metodo_pago: '', monto: '', referencia: '', comprobante: null, parentesco_pagador: 'Titular',
      pagador: { nombres: '', apellidos: '', tipo_documento: 'CI', numero_documento: '', telefono: '', correo: '' },
    },
    {
      id_metodo_pago: [requerido('Seleccione cómo va a pagar.')],
      monto: [requeridoSi((v) => tipoDe(v) !== 'PASARELA'), numero(), mayorQue(0), (v) => (cuota && Number(v) > cuota.saldo + 0.001 ? `No puede superar el saldo de la cuota (${moneda(cuota.saldo)}).` : null)],
      comprobante: [requeridoSi((v) => tipoDe(v) === 'COMPROBANTE', 'Adjunte el comprobante del pago.'), archivo()],
      referencia: [maximo(100)],
      'pagador.nombres': [requerido(), minimo(2), soloLetras()],
      'pagador.apellidos': [requerido(), minimo(2), soloLetras()],
      'pagador.tipo_documento': [requerido()],
      'pagador.numero_documento': [requerido(), documento()],
      'pagador.telefono': [telefono()],
      'pagador.correo': [correo()],
    },
  )

  function tipoDe(valores) {
    return metodos.datos?.find((m) => String(m.id_metodo_pago) === String(valores.id_metodo_pago))?.tipo
  }

  const metodo = metodos.datos?.find((m) => String(m.id_metodo_pago) === String(f.valores.id_metodo_pago))

  // Al abrir: datos del titular como pagador por defecto y monto = saldo de la cuota.
  useEffect(() => {
    if (!cuota) return
    setQr(null)
    setPagado(false)
    f.reiniciar({
      id_metodo_pago: '', monto: cuota.saldo, referencia: '', comprobante: null, parentesco_pagador: 'Titular',
      pagador: {
        nombres: titular?.nombres ?? '', apellidos: titular?.apellidos ?? '', tipo_documento: 'CI',
        numero_documento: titular?.ci ?? '', telefono: titular?.telefono ?? '', correo: titular?.correo ?? '',
      },
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cuota?.id_mensualidad])

  // Sondeo del estado del pago mientras el QR está en pantalla.
  useEffect(() => {
    if (!qr || pagado) return undefined

    sondeo.current = setInterval(async () => {
      try {
        const { data } = await api.get(`/pagos/${qr.pago.id_pago}/estado`)
        if (data.estado === 'CONFIRMADO') setPagado(true)
      } catch {
        /* se reintenta en el siguiente ciclo */
      }
    }, SONDEO_MS)

    return () => clearInterval(sondeo.current)
  }, [qr, pagado])

  const cerrar = () => {
    clearInterval(sondeo.current)
    if (qr || pagado) alTerminar?.()
    alCerrar()
  }

  const pagar = f.enviar(async (valores) => {
    const base = { id_mensualidad: cuota.id_mensualidad, pagador: valores.pagador, parentesco_pagador: valores.parentesco_pagador }

    if (metodo.tipo === 'PASARELA') {
      const { data } = await api.post('/pagos/qr', base)
      setQr(data)

      return
    }

    await api.post('/pagos', aFormData({ ...base, id_metodo_pago: valores.id_metodo_pago, monto: valores.monto, referencia: valores.referencia, comprobante: valores.comprobante }))
    avisar.exito(auth.puede('pagos.conciliar') ? 'Pago registrado. Ya puede conciliarlo.' : 'Pago registrado. El Coordinador Financiero lo revisará y confirmará.')
    alTerminar?.()
    alCerrar()
  })

  const regenerar = async () => {
    try {
      const { data } = await api.post('/pagos/qr', { id_mensualidad: cuota.id_mensualidad, pagador: f.valores.pagador, parentesco_pagador: f.valores.parentesco_pagador })
      setQr(data)
    } catch (e) {
      avisar.error(e)
    }
  }

  /** Solo en modo SIMULADO: hace lo que haría el banco al aprobar el pago. */
  const simular = async () => {
    setSimulando(true)

    try {
      await api.post(`/pagos/pasarela/simulada/${qr.qr.numero_transaccion}`, { aprobar: true })
      setPagado(true)
    } catch (e) {
      avisar.error(mensajeError(e))
    } finally {
      setSimulando(false)
    }
  }

  if (!cuota) return null

  return (
    <Modal
      abierto
      titulo={`Pagar cuota ${cuota.numero_cuota} · saldo ${moneda(cuota.saldo)}`}
      alCerrar={cerrar}
      pie={qr
        ? <Boton onClick={cerrar}>{pagado ? 'Listo' : 'Cerrar'}</Boton>
        : <><Boton variante="secundario" onClick={cerrar}>Cancelar</Boton><Boton onClick={pagar} cargando={f.enviando} icono={metodo?.tipo === 'PASARELA' ? QrCode : Upload}>{metodo?.tipo === 'PASARELA' ? 'Generar código QR' : 'Registrar pago'}</Boton></>}
    >
      {qr ? (
        <div className="text-center">
          {pagado ? (
            <div className="rounded-tema border border-exito bg-exito-suave p-6" role="status">
              <CircleCheck className="mx-auto size-14 text-exito" aria-hidden />
              <p className="mt-2 text-xl font-extrabold">¡Pago confirmado!</p>
              <p className="text-sm">Se recibió el pago de {moneda(qr.qr.monto)}. La cuota y la matrícula ya fueron actualizadas.</p>
            </div>
          ) : (
            <>
              {qr.qr.modo === 'SIMULADO' && <Alerta tipo="alerta" className="mb-4 text-left">Pasarela en <strong>modo simulado</strong>: no se mueve dinero real. Escanee el QR con su teléfono o use el botón «Simular pago aprobado».</Alerta>}
              <p className="text-sm text-tenue">Escanee el código con la aplicación de su banco</p>
              <img src={qr.qr.imagen} alt="Código QR para pagar la cuota" className="mx-auto my-3 size-60 rounded-tema border-4 border-primario bg-white p-2" />
              <p className="tabular text-2xl font-extrabold text-primario">{moneda(qr.qr.monto)}</p>
              <p className="mt-1 text-xs text-tenue">Pedido {qr.qr.numero_transaccion}{qr.qr.expira ? ` · vence ${qr.qr.expira}` : ''}</p>

              <div className="mt-4 flex items-center justify-center gap-2 rounded-tema bg-alerta-suave p-3 text-sm" role="status">
                <RefreshCw className="size-4 animate-spin text-alerta" aria-hidden /> Esperando la confirmación del pago… esta ventana se actualiza sola.
              </div>

              <div className="mt-4 flex flex-wrap justify-center gap-2">
                <Boton variante="secundario" icono={RefreshCw} onClick={regenerar}>Generar nuevo QR</Boton>
                {qr.qr.modo === 'SIMULADO' && <Boton variante="exito" onClick={simular} cargando={simulando}>Simular pago aprobado</Boton>}
              </div>
            </>
          )}
        </div>
      ) : metodos.cargando ? <Cargando /> : (
        <form onSubmit={pagar} noValidate className="space-y-5">
          {f.errorGeneral && <Alerta tipo="peligro">{f.errorGeneral}</Alerta>}

          <fieldset>
            <legend className="mb-2 text-sm font-bold">1. ¿Cómo va a pagar?</legend>
            <div className="grid gap-2 sm:grid-cols-3">
              {(metodos.datos ?? []).map((m) => (
                <label key={m.id_metodo_pago} className={`cursor-pointer rounded-tema border p-3 text-sm ${String(f.valores.id_metodo_pago) === String(m.id_metodo_pago) ? 'border-primario bg-primario-suave' : 'hover:bg-suave'}`}>
                  <input type="radio" name="metodo" className="sr-only" checked={String(f.valores.id_metodo_pago) === String(m.id_metodo_pago)} onChange={() => f.cambiar('id_metodo_pago', m.id_metodo_pago)} />
                  <strong className="block">{m.nombre}</strong>
                  <span className="text-xs text-tenue">{m.descripcion}</span>
                </label>
              ))}
            </div>
            {f.errores.id_metodo_pago && <p className="mt-1 text-xs font-medium text-peligro" role="alert">{f.errores.id_metodo_pago}</p>}
          </fieldset>

          {metodo && metodo.tipo !== 'PASARELA' && (
            <fieldset className="grid gap-4 sm:grid-cols-2">
              <legend className="mb-2 text-sm font-bold">2. Datos del pago</legend>
              <Campo {...f.campo('monto')} tipo="number" min="0.01" step="0.01" etiqueta="Monto pagado (Bs.)" requerido />
              <Campo {...f.campo('referencia')} etiqueta="N.º de transacción o recibo" />
              {metodo.tipo === 'COMPROBANTE' && (
                <Campo {...f.campo('comprobante')} valor={undefined} tipo="file" etiqueta="Comprobante" requerido accept=".pdf,.jpg,.jpeg,.png,.webp" ayuda="Foto o PDF del comprobante, máximo 4 MB." className="sm:col-span-2" />
              )}
            </fieldset>
          )}

          {metodo && (
            <fieldset className="grid gap-4 sm:grid-cols-2">
              <legend className="mb-2 text-sm font-bold">{metodo.tipo === 'PASARELA' ? '2' : '3'}. ¿Quién realiza el pago?</legend>
              <Campo {...f.campo('pagador.nombres')} etiqueta="Nombres" requerido />
              <Campo {...f.campo('pagador.apellidos')} etiqueta="Apellidos" requerido />
              <Campo {...f.campo('pagador.tipo_documento')} tipo="select" etiqueta="Tipo de documento" vacio={null} opciones={[...TIPOS_DOCUMENTO, { valor: 'NIT', etiqueta: 'NIT' }]} />
              <Campo {...f.campo('pagador.numero_documento')} etiqueta="Número de documento" requerido />
              <Campo {...f.campo('pagador.telefono')} tipo="tel" etiqueta="Teléfono" />
              <Campo {...f.campo('pagador.correo')} tipo="email" etiqueta="Correo" />
              <Campo {...f.campo('parentesco_pagador')} tipo="select" etiqueta="Relación con el estudiante" vacio={null} opciones={PARENTESCOS} />
            </fieldset>
          )}
        </form>
      )}
    </Modal>
  )
}
