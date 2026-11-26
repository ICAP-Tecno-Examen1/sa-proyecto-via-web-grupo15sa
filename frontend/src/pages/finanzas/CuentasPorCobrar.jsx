import { Eye, Search, Wallet } from 'lucide-react'
import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import ModalPago from '../../components/pagos/ModalPago'
import { Alerta, EncabezadoPagina, Insignia, Pestanas, Tarjeta } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Tabla from '../../components/ui/Tabla'
import { useAuth } from '../../context/AuthContext'
import { useConsulta, useRetardo } from '../../hooks/useConsulta'
import { fecha, moneda } from '../../utils/formato'

const VISTAS = {
  todas: { etiqueta: 'Todas las deudas', situacion: '' },
  vencidas: { etiqueta: 'Con cuotas vencidas', situacion: 'vencidas' },
  por_vencer: { etiqueta: 'Vencen en 7 días', situacion: 'por_vencer' },
}

/**
 * CU4 · Cuotas por cobrar (Secretaría y Coordinador Financiero).
 * Una fila por matrícula con deuda: próxima cuota y su vencimiento, cuántas cuotas
 * debe y cuántas vencieron. Desde aquí se registra el cobro (por ejemplo, en caja).
 */
export default function CuentasPorCobrar() {
  const auth = useAuth()
  const navegar = useNavigate()
  const [parametros, setParametros] = useSearchParams()
  const vista = VISTAS[parametros.get('situacion')] ? parametros.get('situacion') : 'todas'
  const [texto, setTexto] = useState('')
  const consulta = useConsulta('/cuentas-por-cobrar', { q: useRetardo(texto), situacion: VISTAS[vista].situacion })
  const [cobro, setCobro] = useState(null) // { cuota, titular }
  const resumen = consulta.datos?.resumen

  return (
    <>
      <EncabezadoPagina titulo="Cuotas por cobrar" descripcion="Estudiantes con cuotas pendientes: cuál es la próxima, cuándo vence y cuánto deben en total. Las vencidas aparecen primero." />

      {resumen && (
        <div className="mb-5 grid gap-3 sm:grid-cols-3">
          {[
            ['Estudiantes con deuda', resumen.estudiantes, 'text-texto'],
            ['Saldo por cobrar', moneda(resumen.saldo_pendiente), 'text-primario'],
            [`Vencido (${resumen.con_vencidas} estudiantes)`, moneda(resumen.saldo_vencido), resumen.saldo_vencido > 0 ? 'text-peligro' : 'text-texto'],
          ].map(([etiqueta, valor, color]) => (
            <div key={etiqueta} className="tarjeta p-4">
              <p className="text-xs font-semibold tracking-wide text-tenue uppercase">{etiqueta}</p>
              <p className={`tabular mt-1 text-2xl font-black ${color}`}>{valor}</p>
            </div>
          ))}
        </div>
      )}

      <Pestanas activa={vista} alCambiar={(clave) => setParametros(clave === 'todas' ? {} : { situacion: clave }, { replace: true })}
        pestanas={Object.entries(VISTAS).map(([clave, v]) => ({ clave, etiqueta: v.etiqueta }))} />

      <Tarjeta relleno={false}>
        <div className="border-b p-3">
          <div className="relative max-w-md">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tenue" aria-hidden />
            <input type="search" value={texto} onChange={(e) => setTexto(e.target.value)} placeholder="Buscar por estudiante, CI o matrícula…" aria-label="Buscar deuda" className="campo-control pl-9" />
          </div>
        </div>

        {consulta.error ? <div className="p-4"><Alerta tipo="peligro">{consulta.error}</Alerta></div> : (
          <Tabla
            id="id_matricula"
            filas={consulta.datos?.filas}
            cargando={consulta.cargando}
            vacio={vista === 'vencidas' ? 'Nadie tiene cuotas vencidas. ¡Todo al día!' : 'No hay cuotas pendientes con este filtro.'}
            columnas={[
              { clave: 'estudiante', titulo: 'Estudiante', render: (d) => (<><span className="font-semibold">{d.estudiante.nombre_completo}</span><span className="block text-xs text-tenue">CI {d.estudiante.ci}{d.estudiante.telefono ? ` · ${d.estudiante.telefono}` : ''}</span></>) },
              { clave: 'programa', titulo: 'Programa', render: (d) => (<>{d.programa.nombre}<span className="block text-xs text-tenue">{d.numero_registro}</span></>) },
              {
                clave: 'proxima', titulo: 'Próxima cuota', render: (d) => (
                  <>
                    <span className="font-semibold">N.º {d.proxima_cuota.numero_cuota} · {moneda(d.proxima_cuota.saldo)}</span>
                    <span className={`block text-xs ${d.proxima_cuota.vencida ? 'font-bold text-peligro' : 'text-tenue'}`}>
                      {d.proxima_cuota.vencida ? 'Venció' : 'Vence'} el {fecha(d.proxima_cuota.fecha_vencimiento)}
                    </span>
                  </>
                ),
              },
              { clave: 'cuotas', titulo: 'Cuotas', alinear: 'centro', render: (d) => (<>{d.cuotas_pendientes} pend.{d.cuotas_vencidas > 0 && <span className="block"><Insignia tono="peligro">{d.cuotas_vencidas} vencida{d.cuotas_vencidas > 1 ? 's' : ''}</Insignia></span>}</>) },
              { clave: 'saldo', titulo: 'Saldo total', alinear: 'derecha', render: (d) => (<><strong className="tabular">{moneda(d.saldo_pendiente)}</strong>{d.monto_en_revision > 0 && <span className="block text-xs text-alerta">{moneda(d.monto_en_revision)} por confirmar</span>}</>) },
              {
                clave: '_acciones', titulo: 'Acciones', alinear: 'derecha', render: (d) => (
                  <div className="flex justify-end gap-1">
                    {auth.puede('matriculas.ver') && <Boton tamano="sm" variante="fantasma" icono={Eye} onClick={() => navegar(`/matriculas/${d.id_matricula}`)} aria-label="Ver matrícula" />}
                    {auth.puede('pagos.registrar') && d.proxima_cuota.monto_en_revision === 0 && (
                      <Boton tamano="sm" icono={Wallet} onClick={() => setCobro({ cuota: d.proxima_cuota, titular: d.estudiante })}>Cobrar</Boton>
                    )}
                  </div>
                ),
              },
            ]}
          />
        )}
      </Tarjeta>

      <ModalPago cuota={cobro?.cuota} titular={cobro?.titular} alCerrar={() => setCobro(null)} alTerminar={consulta.recargar} />
    </>
  )
}
