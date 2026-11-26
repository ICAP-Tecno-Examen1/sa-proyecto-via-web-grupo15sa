import { EmbeddedCheckout, EmbeddedCheckoutProvider } from '@stripe/react-stripe-js'
import { loadStripe } from '@stripe/stripe-js'
import { CircleCheck, LoaderCircle } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import api from '../../api/cliente'
import { moneda } from '../../utils/formato'

// Stripe.js se carga una sola vez por clave publicable.
const cargados = new Map()
const stripeDe = (clavePublica) => {
  if (!cargados.has(clavePublica)) cargados.set(clavePublica, loadStripe(clavePublica))

  return cargados.get(clavePublica)
}

const CADA_MS = 2000
const INTENTOS = 60

/**
 * Formulario de tarjeta de Stripe DENTRO del sistema (Embedded Checkout).
 * Los datos de la tarjeta se escriben en un marco seguro de Stripe: el ICAP no los ve.
 * Al completarse, espera la confirmación del pago (webhook o consulta a Stripe).
 */
export default function PagoTarjeta({ pasarela, idPago, alConfirmar }) {
  const [fase, setFase] = useState('tarjeta') // tarjeta · confirmando · confirmado · demora
  const opciones = useMemo(() => ({ clientSecret: pasarela.client_secret, onComplete: () => setFase('confirmando') }), [pasarela.client_secret])

  useEffect(() => {
    if (fase !== 'confirmando') return undefined
    let intentos = 0
    const id = setInterval(async () => {
      try {
        const { data } = await api.get(`/pagos/${idPago}/estado`)
        if (data.estado === 'CONFIRMADO') {
          clearInterval(id)
          setFase('confirmado')
          alConfirmar?.()

          return
        }
      } catch {
        /* se reintenta */
      }
      if (++intentos >= INTENTOS) {
        clearInterval(id)
        setFase('demora')
      }
    }, CADA_MS)

    return () => clearInterval(id)
  }, [fase, idPago, alConfirmar])

  if (fase === 'confirmado') {
    return (
      <div className="animar-crecer rounded-tema border-2 border-exito bg-exito-suave p-8 text-center" role="status">
        <CircleCheck className="animar-rebote mx-auto size-16 text-exito" aria-hidden />
        <p className="mt-3 text-2xl font-black">¡Pago confirmado!</p>
        <p className="mt-1 text-sm">Se recibió el pago de {moneda(pasarela.monto)}. La cuota y la matrícula ya fueron actualizadas.</p>
      </div>
    )
  }

  if (fase === 'confirmando' || fase === 'demora') {
    return (
      <div className="p-8 text-center" role="status" aria-live="polite">
        <LoaderCircle className={`mx-auto size-12 text-primario ${fase === 'confirmando' ? 'animate-spin' : ''}`} aria-hidden />
        <p className="mt-3 text-lg font-bold">{fase === 'confirmando' ? 'Confirmando su pago…' : 'El pago aún no se confirma'}</p>
        <p className="mt-1 text-sm text-tenue">
          {fase === 'confirmando'
            ? 'Stripe está procesando el cobro. Tarda solo unos segundos.'
            : 'Si el cobro se completó, la cuota se marcará como pagada en unos minutos. Puede cerrar esta ventana.'}
        </p>
      </div>
    )
  }

  return (
    <div>
      {pasarela.modo === 'PRUEBA' && (
        <p className="mb-3 rounded-tema bg-alerta-suave px-3 py-2 text-xs">
          <strong>Modo de prueba:</strong> use la tarjeta 4242 4242 4242 4242, cualquier fecha futura y cualquier CVC. No se cobra dinero real.
        </p>
      )}
      {/* Stripe dibuja su formulario sobre fondo claro: se le da una base blanca también en modo noche */}
      <div className="overflow-hidden rounded-tema bg-white">
        <EmbeddedCheckoutProvider stripe={stripeDe(pasarela.clave_publica)} options={opciones}>
          <EmbeddedCheckout />
        </EmbeddedCheckoutProvider>
      </div>
    </div>
  )
}
