import { Eye, Wallet } from 'lucide-react'
import { abrirArchivo } from '../../api/cliente'
import { useAvisos } from '../../context/AvisosContext'
import { fecha, fechaHora, moneda } from '../../utils/formato'
import { Insignia } from '../ui/Basicos'
import Boton from '../ui/Boton'
import Tabla from '../ui/Tabla'

/**
 * CU4 · Estado de cuenta de una matrícula: resumen, plan de cuotas e historial
 * de pagos. Con "alPagar" cada cuota con saldo muestra el botón Pagar.
 */
export default function EstadoCuenta({ cuenta, alPagar }) {
  const avisar = useAvisos()
  const { resumen, cuotas } = cuenta
  const pagos = cuotas.flatMap((c) => c.pagos.map((p) => ({ ...p, numero_cuota: c.numero_cuota })))
  const verComprobante = (p) => abrirArchivo(`/pagos/${p.id_pago}/comprobante`).catch(avisar.error)

  return (
    <div className="space-y-4">
      <dl className="grid grid-cols-2 gap-3 lg:grid-cols-5">
        {[
          ['Costo', moneda(resumen.monto_original)],
          ['Descuento por beca', moneda(resumen.monto_descuento)],
          ['Total a pagar', moneda(resumen.monto_final)],
          ['Pagado', moneda(resumen.total_pagado)],
          ['Saldo pendiente', moneda(resumen.saldo_pendiente), resumen.saldo_vencido > 0 ? `${moneda(resumen.saldo_vencido)} vencido` : null],
        ].map(([etiqueta, valor, nota]) => (
          <div key={etiqueta} className="rounded-tema bg-suave p-3">
            <dt className="text-xs font-semibold text-tenue">{etiqueta}</dt>
            <dd className="tabular text-lg font-extrabold">{valor}</dd>
            {nota && <dd className="text-xs font-semibold text-peligro">{nota}</dd>}
          </div>
        ))}
      </dl>

      <div className="rounded-tema border">
        <Tabla
          id="id_mensualidad"
          filas={cuotas}
          vacio="La matrícula aún no tiene cuotas."
          columnas={[
            { clave: 'numero_cuota', titulo: 'Cuota', alinear: 'centro' },
            { clave: 'fecha_vencimiento', titulo: 'Vence', render: (c) => fecha(c.fecha_vencimiento) },
            { clave: 'monto_original', titulo: 'Monto', alinear: 'derecha', render: (c) => moneda(c.monto_original) },
            { clave: 'monto_descuento', titulo: 'Descuento', alinear: 'derecha', render: (c) => (c.monto_descuento > 0 ? `− ${moneda(c.monto_descuento)}` : '—') },
            { clave: 'monto_final', titulo: 'A pagar', alinear: 'derecha', render: (c) => <strong>{moneda(c.monto_final)}</strong> },
            { clave: 'saldo', titulo: 'Saldo', alinear: 'derecha', render: (c) => moneda(c.saldo) },
            { clave: 'estado', titulo: 'Estado', render: (c) => (
              <span className="flex flex-wrap gap-1">
                <Insignia estado={c.vencida ? 'VENCIDA' : c.estado} />
                {c.monto_en_revision > 0 && <Insignia tono="informacion">Pago en revisión</Insignia>}
              </span>
            ) },
            ...(alPagar ? [{ clave: 'accion', titulo: '', alinear: 'derecha', render: (c) => c.saldo > 0 && c.monto_en_revision === 0 && c.estado === 'PENDIENTE' && (
              <Boton tamano="sm" icono={Wallet} onClick={() => alPagar(c)}>Pagar</Boton>
            ) }] : []),
          ]}
        />
      </div>

      {pagos.length > 0 && (
        <div>
          <h4 className="mb-2 text-sm font-bold">Historial de pagos</h4>
          <div className="rounded-tema border">
            <Tabla
              id="id_pago"
              filas={pagos}
              columnas={[
                { clave: 'fecha', titulo: 'Fecha', render: (p) => fechaHora(p.fecha) },
                { clave: 'numero_cuota', titulo: 'Cuota', alinear: 'centro' },
                { clave: 'metodo', titulo: 'Método', render: (p) => p.metodo_pago?.nombre },
                { clave: 'pagador', titulo: 'Pagó', render: (p) => (<>{p.pagador?.nombre_completo}{p.parentesco_pagador && <span className="block text-xs text-tenue">{p.parentesco_pagador}</span>}</>) },
                { clave: 'monto', titulo: 'Monto', alinear: 'derecha', render: (p) => moneda(p.monto) },
                { clave: 'estado', titulo: 'Conciliación', render: (p) => (<><Insignia estado={p.estado_conciliacion} />{p.observacion_conciliacion && p.estado_conciliacion !== 'CONFIRMADO' && <span className="mt-1 block max-w-56 text-xs text-tenue">{p.observacion_conciliacion}</span>}</>) },
                { clave: 'comprobante', titulo: '', alinear: 'derecha', render: (p) => p.tiene_comprobante && <Boton tamano="sm" variante="secundario" icono={Eye} onClick={() => verComprobante(p)}>Comprobante</Boton> },
              ]}
            />
          </div>
        </div>
      )}
    </div>
  )
}
