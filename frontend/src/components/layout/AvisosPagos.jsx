import { Banknote, Bell, CalendarClock, CalendarX, FileCheck, Percent } from 'lucide-react'
import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import api from '../../api/cliente'
import { fechaHora, moneda } from '../../utils/formato'

const CADA_MS = 60_000

/**
 * Campana de avisos para la Secretaría y el Coordinador Financiero.
 * El número cuenta lo que espera una acción: efectivo por cobrar, comprobantes por
 * revisar y (Coordinador Financiero) solicitudes de beca. Además informa las cuotas
 * vencidas y las que vencen en 7 días. Se actualiza cada minuto y al cambiar de pantalla.
 */
export default function AvisosPagos() {
  const [datos, setDatos] = useState(null)
  const [abierto, setAbierto] = useState(false)
  const contenedor = useRef(null)
  const { pathname, search } = useLocation()

  const consultar = useCallback(async () => {
    try {
      const { data } = await api.get('/pagos/pendientes')
      setDatos(data)
    } catch {
      /* sin avisos si falla: se reintenta en el siguiente ciclo */
    }
  }, [])

  useEffect(() => {
    consultar()
    const id = setInterval(consultar, CADA_MS)

    return () => clearInterval(id)
  }, [consultar, pathname, search])

  useEffect(() => {
    const cerrar = (e) => !contenedor.current?.contains(e.target) && setAbierto(false)
    document.addEventListener('mousedown', cerrar)

    return () => document.removeEventListener('mousedown', cerrar)
  }, [])

  const total = (datos?.efectivo ?? 0) + (datos?.comprobantes ?? 0) + (datos?.becas ?? 0)
  const fila = (a, Icono, color, texto, valor, borde = false) => (
    <Link to={a} onClick={() => setAbierto(false)} className={`flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-suave ${borde ? 'border-b' : ''}`}>
      <Icono className={`size-5 ${color}`} aria-hidden />
      <span className="flex-1">{texto}</span>
      <strong className="tabular">{valor ?? '…'}</strong>
    </Link>
  )

  return (
    <div ref={contenedor} className="relative">
      <button
        type="button"
        onClick={() => setAbierto((a) => !a)}
        aria-expanded={abierto}
        aria-label={total ? `${total} pagos esperan su revisión` : 'Avisos de pagos'}
        className="relative grid size-10 place-items-center rounded-tema border-2 bg-tarjeta hover:bg-suave"
      >
        <Bell className={`size-5 ${total ? 'text-primario' : 'text-tenue'}`} aria-hidden />
        {total > 0 && (
          <span className="animar-latido absolute -top-1.5 -right-1.5 grid min-w-5 place-items-center rounded-full bg-acento px-1 text-[0.7rem] font-black text-sobre-acento">
            {total > 99 ? '99+' : total}
          </span>
        )}
      </button>

      {abierto && (
        <div className="tarjeta absolute top-full right-0 z-40 mt-2 w-80 overflow-hidden shadow-xl">
          <p className="border-b px-4 py-3 text-sm font-bold">Pagos que esperan acción</p>

          {fila('/pagos?vista=efectivo', Banknote, 'text-alerta', 'Efectivo por cobrar', datos?.efectivo)}
          {fila('/pagos?vista=comprobantes', FileCheck, 'text-informacion', 'Comprobantes por revisar', datos?.comprobantes)}
          {datos?.becas != null && fila('/becas', Percent, 'text-exito', 'Becas por revisar', datos.becas)}
          <p className="border-t bg-suave px-4 pt-2 pb-1 text-[0.7rem] font-bold tracking-wide text-tenue uppercase">Cuotas de los estudiantes</p>
          {fila('/cuentas-por-cobrar?situacion=vencidas', CalendarX, 'text-peligro', 'Cuotas vencidas', datos?.cuotas_vencidas)}
          {fila('/cuentas-por-cobrar?situacion=por_vencer', CalendarClock, 'text-tenue', 'Vencen en los próximos 7 días', datos?.cuotas_por_vencer, true)}

          {datos?.recientes?.length > 0 ? (
            <ul className="max-h-64 overflow-y-auto">
              {datos.recientes.map((p) => (
                <li key={p.id_pago}>
                  <Link to={`/pagos?vista=${p.metodo.tipo === 'EFECTIVO' ? 'efectivo' : 'comprobantes'}`} onClick={() => setAbierto(false)} className="block px-4 py-2 text-xs hover:bg-suave">
                    <span className="flex justify-between gap-2">
                      <strong className="truncate">{p.matricula.estudiante}</strong>
                      <span className="tabular shrink-0 font-bold">{moneda(p.monto)}</span>
                    </span>
                    <span className="text-tenue">{p.metodo.nombre} · cuota {p.cuota?.numero_cuota ?? '—'} · {fechaHora(p.fecha)}</span>
                  </Link>
                </li>
              ))}
            </ul>
          ) : <p className="px-4 py-4 text-center text-sm text-tenue">No hay pagos pendientes. ¡Todo al día!</p>}
        </div>
      )}
    </div>
  )
}
