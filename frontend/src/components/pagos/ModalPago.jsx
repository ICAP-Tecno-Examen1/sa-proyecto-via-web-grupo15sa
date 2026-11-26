import { Banknote, CreditCard, Lock, Upload } from 'lucide-react'
import { useEffect, useState } from 'react'
import api, { aFormData } from '../../api/cliente'
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
import PagoTarjeta from './PagoTarjeta'

const PARENTESCOS = ['Titular', 'Padre', 'Madre', 'Tutor', 'Cónyuge', 'Empresa', 'Otro']
const ICONO = { PASARELA: CreditCard, EFECTIVO: Banknote, COMPROBANTE: Upload }

/**
 * CU4 · Pago de una cuota (requisito 10).
 *
 * Paso 1: se elige el método y se registran los datos de quien paga.
 * Paso 2 según el método:
 *   - PASARELA (tarjeta con Stripe): el formulario de tarjeta aparece en esta misma
 *     ventana (PagoTarjeta.jsx). Sin clave publicable, se va a la página de Stripe.
 *   - EFECTIVO: el estudiante AVISA que pagará en caja (llega a Secretaría y al
 *     Coordinador Financiero); el personal registra el cobro y la cuota queda pagada.
 *   - COMPROBANTE: se adjunta el comprobante y queda a la espera de revisión.
 */
export default function ModalPago({ cuota, titular, alCerrar, alTerminar }) {
  const auth = useAuth()
  const avisar = useAvisos()
  const metodos = useConsulta(cuota ? '/metodos-pago/disponibles' : null)
  const esPersonal = auth.puede('pagos.registrar')
  const [tarjeta, setTarjeta] = useState(null) // { pasarela, idPago } mientras se paga con tarjeta
  const [pagado, setPagado] = useState(false)

  const tipoDe = (valores) => metodos.datos?.find((m) => String(m.id_metodo_pago) === String(valores.id_metodo_pago))?.tipo
  // El estudiante no escribe monto ni referencia para la tarjeta ni para el aviso de efectivo: es el saldo.
  const pideMonto = (valores) => tipoDe(valores) === 'COMPROBANTE' || (tipoDe(valores) === 'EFECTIVO' && esPersonal)

  const f = useFormulario(
    {
      id_metodo_pago: '', monto: '', referencia: '', comprobante: null, parentesco_pagador: 'Titular',
      pagador: { nombres: '', apellidos: '', tipo_documento: 'CI', numero_documento: '', telefono: '', correo: '' },
    },
    {
      id_metodo_pago: [requerido('Seleccione cómo va a pagar.')],
      monto: [requeridoSi(pideMonto), numero(), mayorQue(0), (v) => (cuota && Number(v) > cuota.saldo + 0.001 ? `No puede superar el saldo de la cuota (${moneda(cuota.saldo)}).` : null)],
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

  const metodo = metodos.datos?.find((m) => String(m.id_metodo_pago) === String(f.valores.id_metodo_pago))
  const tipo = metodo?.tipo

  // Al abrir: datos del titular como pagador por defecto y monto = saldo de la cuota.
  useEffect(() => {
    if (!cuota) return
    setTarjeta(null)
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

  const pagar = f.enviar(async (valores) => {
    const base = { id_mensualidad: cuota.id_mensualidad, pagador: valores.pagador, parentesco_pagador: valores.parentesco_pagador }

    try {
      if (tipo === 'PASARELA') {
        const { data } = await api.post('/pagos/en-linea', base)
        if (data.pasarela.presentacion === 'integrado') setTarjeta({ pasarela: data.pasarela, idPago: data.pago.id_pago })
        else window.location.assign(data.pasarela.url) // sin clave publicable: página de Stripe

        return
      }

      const monto = pideMonto(valores) ? valores.monto : cuota.saldo
      const { data } = await api.post('/pagos', aFormData({ ...base, id_metodo_pago: valores.id_metodo_pago, monto, referencia: valores.referencia, comprobante: valores.comprobante }))

      avisar.exito(
        tipo === 'EFECTIVO' && !esPersonal ? 'Listo: se avisó a Secretaría. Acérquese a la caja del ICAP; la cuota quedará pagada cuando registren el cobro.'
          : data.estado_conciliacion === 'CONFIRMADO' ? 'Cobro registrado: la cuota quedó pagada.'
            : auth.puede('pagos.conciliar') ? 'Pago registrado. Ya puede confirmarlo en «Pagos y conciliación».'
              : 'Comprobante enviado. La Secretaría o el Coordinador Financiero lo revisará.',
      )
      alTerminar?.()
      alCerrar()
    } catch (e) {
      // El formulario es largo: además del mensaje de arriba, se avisa donde se vea.
      avisar.error(e)
      throw e
    }
  })

  const cerrar = () => {
    if (tarjeta) alTerminar?.()
    alCerrar()
  }

  if (!cuota) return null

  const textoBoton = {
    PASARELA: `Continuar al pago con tarjeta · ${moneda(cuota.saldo)}`,
    EFECTIVO: esPersonal ? 'Registrar cobro en efectivo' : 'Avisar que pagaré en efectivo',
    COMPROBANTE: 'Enviar comprobante',
  }[tipo] ?? 'Continuar'

  return (
    <Modal
      abierto
      ancho={tarjeta ? 'lg' : 'md'}
      titulo={`Pagar cuota ${cuota.numero_cuota} · saldo ${moneda(cuota.saldo)}`}
      alCerrar={cerrar}
      pie={tarjeta
        ? <Boton variante={pagado ? 'primario' : 'secundario'} onClick={cerrar}>{pagado ? 'Listo' : 'Cerrar'}</Boton>
        : (
          <>
            <Boton variante="secundario" onClick={cerrar}>Cancelar</Boton>
            <Boton variante={tipo === 'PASARELA' ? 'acento' : 'primario'} onClick={pagar} cargando={f.enviando} icono={ICONO[tipo] ?? Upload}>{textoBoton}</Boton>
          </>
        )}
    >
      {tarjeta ? <PagoTarjeta pasarela={tarjeta.pasarela} idPago={tarjeta.idPago} alConfirmar={() => setPagado(true)} />
        : metodos.cargando ? <Cargando /> : (
          <form onSubmit={pagar} noValidate className="space-y-5">
            {f.errorGeneral && <Alerta tipo="peligro">{f.errorGeneral}</Alerta>}
            {cuota.monto_descuento > 0 && (
              <Alerta tipo="exito" titulo="Esta cuota ya incluye su beca">
                {moneda(cuota.monto_original)} − descuento {moneda(cuota.monto_descuento)} = <strong>{moneda(cuota.monto_final)}</strong>
                {cuota.saldo < cuota.monto_final ? ` (le quedan ${moneda(cuota.saldo)} por pagar)` : ''}
              </Alerta>
            )}

            <fieldset>
              <legend className="mb-2 text-sm font-bold">1. ¿Cómo va a pagar?</legend>
              <div className="grid gap-2 sm:grid-cols-3">
                {(metodos.datos ?? []).map((m) => {
                  const Icono = ICONO[m.tipo] ?? Upload
                  const elegido = String(f.valores.id_metodo_pago) === String(m.id_metodo_pago)

                  return (
                    <label key={m.id_metodo_pago} className={`cursor-pointer rounded-tema border-2 p-3 text-sm transition-colors ${elegido ? 'border-primario bg-primario-suave' : 'hover:bg-suave'}`}>
                      <input type="radio" name="metodo" className="sr-only" checked={elegido} onChange={() => f.cambiar('id_metodo_pago', m.id_metodo_pago)} />
                      <strong className="flex items-center gap-1.5"><Icono className="size-4 shrink-0 text-primario" aria-hidden />{m.nombre}</strong>
                      <span className="mt-0.5 block text-xs text-tenue">{m.descripcion}</span>
                    </label>
                  )
                })}
              </div>
              {f.errores.id_metodo_pago && <p className="mt-1 text-xs font-medium text-peligro" role="alert">{f.errores.id_metodo_pago}</p>}
            </fieldset>

            {tipo === 'PASARELA' && (
              <Alerta titulo="Pago seguro con Stripe">
                <span className="flex items-start gap-2">
                  <Lock className="mt-0.5 size-4 shrink-0" aria-hidden />
                  <span>Al continuar aparecerá el formulario de la tarjeta aquí mismo. Los datos de la tarjeta los recibe Stripe: el ICAP no los ve ni los guarda.</span>
                </span>
              </Alerta>
            )}

            {tipo === 'EFECTIVO' && !esPersonal && (
              <Alerta tipo="alerta" titulo={`Pago en caja: ${moneda(cuota.saldo)}`}>
                Al confirmar se avisa a la Secretaría y al Coordinador Financiero. Acérquese a la caja del ICAP con el monto;
                cuando registren el cobro, la cuota quedará pagada y lo verá en «Mis pagos».
              </Alerta>
            )}

            {tipo && pideMonto(f.valores) && (
              <fieldset className="grid gap-4 sm:grid-cols-2">
                <legend className="mb-2 text-sm font-bold">2. Datos del pago</legend>
                <Campo {...f.campo('monto')} tipo="number" min="0.01" step="0.01" etiqueta={tipo === 'EFECTIVO' ? 'Monto cobrado (Bs.)' : 'Monto pagado (Bs.)'} requerido />
                <Campo {...f.campo('referencia')} etiqueta={tipo === 'EFECTIVO' ? 'N.º de recibo' : 'N.º de transacción o recibo'} />
                {tipo === 'COMPROBANTE' && (
                  <Campo {...f.campo('comprobante')} valor={undefined} tipo="file" etiqueta="Comprobante" requerido accept=".pdf,.jpg,.jpeg,.png,.webp" ayuda="Foto o PDF del comprobante, máximo 4 MB." className="sm:col-span-2" />
                )}
              </fieldset>
            )}

            {tipo && (
              <fieldset className="grid gap-4 sm:grid-cols-2">
                <legend className="mb-2 text-sm font-bold">{pideMonto(f.valores) ? '3' : '2'}. ¿Quién realiza el pago?</legend>
                <Campo {...f.campo('pagador.nombres')} etiqueta="Nombres" requerido />
                <Campo {...f.campo('pagador.apellidos')} etiqueta="Apellidos" requerido />
                <Campo {...f.campo('pagador.tipo_documento')} tipo="select" etiqueta="Tipo de documento" vacio={null} opciones={[...TIPOS_DOCUMENTO, { valor: 'NIT', etiqueta: 'NIT' }]} />
                <Campo {...f.campo('pagador.numero_documento')} etiqueta="Número de documento" requerido />
                <Campo {...f.campo('pagador.telefono')} tipo="tel" etiqueta="Teléfono" />
                <Campo {...f.campo('pagador.correo')} tipo="email" etiqueta="Correo" ayuda={tipo === 'PASARELA' ? 'Stripe enviará aquí el recibo de la tarjeta.' : undefined} />
                <Campo {...f.campo('parentesco_pagador')} tipo="select" etiqueta="Relación con el estudiante" vacio={null} opciones={PARENTESCOS} />
              </fieldset>
            )}
          </form>
        )}
    </Modal>
  )
}
