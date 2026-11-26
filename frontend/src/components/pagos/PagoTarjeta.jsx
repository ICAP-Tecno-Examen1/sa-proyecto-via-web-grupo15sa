import { loadStripe } from '@stripe/stripe-js'
import { CircleCheck, LoaderCircle } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import api from '../../api/cliente'
import { moneda } from '../../utils/formato'
import { Alerta } from '../ui/Basicos'

// Stripe.js se carga una sola vez por clave publicable.
const cargados = new Map()
const stripeDe = (clavePublica) => {
  if (!cargados.has(clavePublica)) cargados.set(clavePublica, loadStripe(clavePublica))

  return cargados.get(clavePublica)
}

/*
 * Stripe solo permite UNA instancia de Embedded Checkout a la vez, y crearla es asíncrono.
 * Si la ventana se cierra mientras se crea (o React monta dos veces en desarrollo), la
 * anterior debe destruirse antes de crear otra: por eso las creaciones van en fila y
 * siempre se destruye la vigente primero.
 */
let enFila = Promise.resolve()
let vigente = null

function montarCheckout({ clavePublica, clientSecret, onComplete, elemento, control }) {
  enFila = enFila
    .then(async () => {
      if (control.cancelado) return
      const stripe = await stripeDe(clavePublica)
      if (vigente) {
        vigente.destroy()
        vigente = null
      }
      const instancia = await stripe.createEmbeddedCheckoutPage({ clientSecret, onComplete })
      if (control.cancelado) {
        instancia.destroy()

        return
      }
      vigente = instancia
      control.instancia = instancia
      instancia.mount(elemento)
    })
    .catch((e) => control.alError?.(e))
}

function desmontarCheckout(control) {
  control.cancelado = true
  if (control.instancia) {
    control.instancia.destroy()
    if (vigente === control.instancia) vigente = null
  }
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
  const [error, setError] = useState(null)
  const contenedor = useRef(null)

  // Crea el formulario de Stripe al abrir y lo destruye al cerrar (o al cambiar de sesión).
  useEffect(() => {
    if (fase !== 'tarjeta' || !contenedor.current) return undefined
    const control = { cancelado: false, instancia: null, alError: (e) => setError(e?.message ?? 'No se pudo cargar el formulario de Stripe.') }
    montarCheckout({
      clavePublica: pasarela.clave_publica,
      clientSecret: pasarela.client_secret,
      onComplete: () => setFase('confirmando'),
      elemento: contenedor.current,
      control,
    })

    return () => desmontarCheckout(control)
  }, [fase, pasarela.clave_publica, pasarela.client_secret])

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
      {error && <Alerta tipo="peligro" className="mb-3">{error} Cierre esta ventana e intente de nuevo.</Alerta>}
      {/* Stripe dibuja su formulario sobre fondo claro: se le da una base blanca también en modo noche */}
      <div ref={contenedor} className="min-h-40 overflow-hidden rounded-tema bg-white" />
    </div>
  )
}
