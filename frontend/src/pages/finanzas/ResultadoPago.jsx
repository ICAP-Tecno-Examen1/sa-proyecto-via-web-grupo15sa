import { CircleCheck, CircleX, Clock, LoaderCircle } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import api from '../../api/cliente'
import Boton from '../../components/ui/Boton'
import { useAuth } from '../../context/AuthContext'
import { moneda } from '../../utils/formato'

const CADA_MS = 3000
const INTENTOS = 40 // ~2 minutos esperando la confirmación de Stripe

/**
 * CU4 · Regreso desde Stripe Checkout: /pago/resultado?pago={id}&estado=exito|cancelado
 * Consulta el estado del pago (la API pregunta a Stripe si el webhook aún no llegó)
 * hasta que queda CONFIRMADO.
 */
export default function ResultadoPago() {
  const auth = useAuth()
  const [parametros] = useSearchParams()
  const idPago = parametros.get('pago')
  const cancelado = parametros.get('estado') === 'cancelado'
  const [pago, setPago] = useState(null)
  const [fase, setFase] = useState(cancelado ? 'cancelado' : 'esperando') // esperando · confirmado · demora · cancelado · error

  useEffect(() => {
    if (cancelado || !idPago) return undefined
    let intentos = 0

    const consultar = async () => {
      try {
        const { data } = await api.get(`/pagos/${idPago}/estado`)
        setPago(data)
        if (data.estado === 'CONFIRMADO') return setFase('confirmado')
        if (data.estado === 'RECHAZADO') return setFase('error')
      } catch {
        /* se reintenta */
      }
      if (++intentos >= INTENTOS) setFase('demora')
    }

    consultar()
    const id = setInterval(() => (intentos < INTENTOS ? consultar() : clearInterval(id)), CADA_MS)

    return () => clearInterval(id)
  }, [idPago, cancelado])

  const destino = auth.puede('pagos.propios') ? '/mis-pagos' : pago?.id_matricula ? `/matriculas/${pago.id_matricula}` : '/panel'
  const etiquetaDestino = auth.puede('pagos.propios') ? 'Ver mis pagos' : 'Volver a la matrícula'

  const contenido = {
    esperando: {
      Icono: LoaderCircle, color: 'text-primario', animar: 'animate-spin',
      titulo: 'Confirmando su pago…', texto: 'Stripe está procesando el cobro. No cierre esta página; tarda solo unos segundos.',
    },
    confirmado: {
      Icono: CircleCheck, color: 'text-exito', animar: 'animar-rebote',
      titulo: '¡Pago confirmado!', texto: `Se recibió el pago${pago?.monto ? ` de ${moneda(pago.monto)}` : ''}. La cuota y la matrícula ya fueron actualizadas.`,
    },
    demora: {
      Icono: Clock, color: 'text-alerta',
      titulo: 'El pago aún no se confirma', texto: 'Si completó el pago en Stripe, se confirmará automáticamente en unos minutos. Puede revisar el estado en su lista de pagos.',
    },
    cancelado: {
      Icono: CircleX, color: 'text-tenue',
      titulo: 'Pago cancelado', texto: 'No se realizó ningún cobro. Puede intentarlo nuevamente cuando quiera.',
    },
    error: {
      Icono: CircleX, color: 'text-peligro',
      titulo: 'El pago no se pudo completar', texto: 'Stripe no confirmó el cobro. Intente con otra tarjeta o elija otro método de pago.',
    },
  }[fase]
  const { Icono } = contenido

  return (
    <div className="mx-auto max-w-lg py-10">
      <div className="tarjeta animar-crecer p-8 text-center" role="status" aria-live="polite">
        <Icono className={`mx-auto size-16 ${contenido.color} ${contenido.animar ?? ''}`} aria-hidden />
        <h1 className="mt-4 text-2xl font-black">{contenido.titulo}</h1>
        <p className="mt-2 text-tenue">{contenido.texto}</p>
        {fase !== 'esperando' && <Boton a={destino} className="mt-6" tamano="lg">{etiquetaDestino}</Boton>}
      </div>
    </div>
  )
}
