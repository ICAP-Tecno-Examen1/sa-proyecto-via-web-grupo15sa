import { CircleCheck, ShieldCheck } from 'lucide-react'
import { useState } from 'react'
import { useParams } from 'react-router-dom'
import api, { mensajeError } from '../../api/cliente'
import { Alerta, Dato, Estado } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import { useConsulta } from '../../hooks/useConsulta'
import { moneda } from '../../utils/formato'

/**
 * CU4 · Pasarela de pago SIMULADA.
 * Hace el papel de la aplicación del banco: se abre al escanear el QR cuando
 * la pasarela no tiene credenciales reales de PagoFácil. Al aprobar, el
 * sistema recibe la misma notificación (PedidoID + Estado = 2) que enviaría
 * PagoFácil a su webhook.
 */
export default function PasarelaSimulada() {
  const { numero } = useParams()
  const consulta = useConsulta(`/pagos/pasarela/simulada/${numero}`)
  const [enviando, setEnviando] = useState(false)
  const [resultado, setResultado] = useState(null)
  const [error, setError] = useState(null)

  const aprobar = async () => {
    setEnviando(true)
    setError(null)

    try {
      const { data } = await api.post(`/pagos/pasarela/simulada/${numero}`, { aprobar: true })
      setResultado(data)
    } catch (e) {
      setError(mensajeError(e))
    } finally {
      setEnviando(false)
    }
  }

  return (
    <div className="mx-auto max-w-md">
      <div className="tarjeta overflow-hidden">
        <header className="flex items-center gap-3 bg-primario p-5 text-sobre-primario">
          <ShieldCheck className="size-9" aria-hidden />
          <div>
            <h1 className="text-lg font-extrabold">Pasarela de pago simulada</h1>
            <p className="text-xs opacity-85">Entorno de demostración · no se mueve dinero real</p>
          </div>
        </header>

        <div className="p-5">
          <Estado consulta={consulta}>
            {(orden) => {
              const pagado = resultado?.estado === 'CONFIRMADO' || orden.estado === 'CONFIRMADO'

              return (
                <div className="space-y-4">
                  <dl className="grid grid-cols-2 gap-4">
                    <div className="col-span-2"><Dato etiqueta="Concepto">{orden.concepto}</Dato></div>
                    <Dato etiqueta="Matrícula">{orden.matricula}</Dato>
                    <Dato etiqueta="Pagador">{orden.pagador}</Dato>
                    <div className="col-span-2"><Dato etiqueta="N.º de pedido">{orden.numero_transaccion}</Dato></div>
                  </dl>

                  <div className="rounded-tema bg-suave p-4 text-center">
                    <p className="text-xs text-tenue">Monto a pagar</p>
                    <p className="tabular text-3xl font-extrabold text-primario">{moneda(orden.monto)}</p>
                  </div>

                  {error && <Alerta tipo="peligro">{error}</Alerta>}

                  {pagado ? (
                    <div className="rounded-tema border border-exito bg-exito-suave p-4 text-center" role="status">
                      <CircleCheck className="mx-auto size-10 text-exito" aria-hidden />
                      <p className="mt-1 font-bold">Pago aprobado</p>
                      <p className="text-sm">El ICAP ya recibió la confirmación. Puede cerrar esta ventana.</p>
                    </div>
                  ) : (
                    <Boton variante="exito" tamano="lg" className="w-full" onClick={aprobar} cargando={enviando}>Aprobar pago</Boton>
                  )}
                </div>
              )
            }}
          </Estado>
        </div>
      </div>
    </div>
  )
}
