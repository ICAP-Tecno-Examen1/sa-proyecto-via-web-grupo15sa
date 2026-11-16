import { Table2 } from 'lucide-react'
import { useState } from 'react'
import {
  Bar, BarChart, CartesianGrid, Cell, Legend, Line, LineChart, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts'
import { cantidad, moneda, porcentaje } from '../../utils/formato'
import Icono from '../layout/Icono'
import { Vacio } from '../ui/Basicos'

/**
 * Gráfico del panel (requisito 8). Recibe la definición tal como la envía la API:
 *   { titulo, tipo: barras | barras_horizontales | lineas | dona, series: [{ clave, nombre }], datos, formato }
 *
 * Colores: paleta categórica fija (--grafico-1…8), asignada por posición de la
 * serie y validada para daltonismo. Cada gráfico ofrece además su tabla de datos.
 */
const COLORES = Array.from({ length: 8 }, (_, i) => `var(--grafico-${i + 1})`)
const FORMATOS = { moneda, porcentaje, numero: cantidad }

const compacto = (valor) => (Math.abs(valor) >= 1000 ? `${cantidad(valor / 1000)} mil` : cantidad(valor))
const ejes = { tick: { fill: 'var(--grafico-eje)', fontSize: 12 }, axisLine: { stroke: 'var(--grafico-rejilla)' }, tickLine: false }

function Emergente({ active, payload, label, formato }) {
  if (!active || !payload?.length) return null

  return (
    <div className="tarjeta px-3 py-2 text-xs shadow-lg">
      <p className="mb-1 font-bold">{label ?? payload[0].payload.etiqueta}</p>
      {payload.map((p) => (
        <p key={p.dataKey ?? p.name} className="flex items-center gap-2">
          <span className="size-2.5 rounded-sm" style={{ background: p.color ?? p.payload.fill }} aria-hidden />
          <span className="text-tenue">{p.name}:</span>
          <strong className="tabular">{formato(p.value)}</strong>
        </p>
      ))}
    </div>
  )
}

export default function Grafico({ grafico, alto = 280 }) {
  const [verTabla, setVerTabla] = useState(false)
  const { titulo, tipo, series, datos } = grafico
  const formato = FORMATOS[grafico.formato] ?? cantidad
  const sinDatos = !datos.length || datos.every((d) => series.every((s) => !d[s.clave]))
  const leyenda = series.length > 1 ? <Legend wrapperStyle={{ fontSize: 12, color: 'var(--texto)' }} formatter={(v) => <span style={{ color: 'var(--texto)' }}>{v}</span>} /> : null
  const emergente = <Tooltip content={<Emergente formato={formato} />} cursor={{ fill: 'var(--suave)', opacity: 0.6 }} />

  let dibujo

  if (tipo === 'dona') {
    const clave = series[0].clave
    const total = datos.reduce((suma, d) => suma + d[clave], 0)

    dibujo = (
      <PieChart>
        <Pie data={datos} dataKey={clave} nameKey="etiqueta" innerRadius="55%" outerRadius="85%" paddingAngle={2} stroke="var(--tarjeta)" strokeWidth={2}>
          {datos.map((d, i) => <Cell key={d.etiqueta} fill={COLORES[i % COLORES.length]} />)}
        </Pie>
        {emergente}
        <Legend wrapperStyle={{ fontSize: 12 }} formatter={(nombre) => {
          const valor = datos.find((d) => d.etiqueta === nombre)?.[clave] ?? 0

          return <span style={{ color: 'var(--texto)' }}>{nombre} · {total ? Math.round((valor * 100) / total) : 0}%</span>
        }} />
      </PieChart>
    )
  } else if (tipo === 'lineas') {
    dibujo = (
      <LineChart data={datos} margin={{ top: 8, right: 12, left: 0, bottom: 0 }}>
        <CartesianGrid stroke="var(--grafico-rejilla)" vertical={false} />
        <XAxis dataKey="etiqueta" {...ejes} interval="preserveStartEnd" />
        <YAxis {...ejes} axisLine={false} tickFormatter={compacto} width={56} allowDecimals={false} />
        {emergente}
        {leyenda}
        {series.map((s, i) => (
          <Line key={s.clave} type="monotone" dataKey={s.clave} name={s.nombre} stroke={COLORES[i]} strokeWidth={2} dot={datos.length <= 14 ? { r: 3, fill: COLORES[i] } : false} activeDot={{ r: 5 }} />
        ))}
      </LineChart>
    )
  } else {
    const horizontal = tipo === 'barras_horizontales'

    dibujo = (
      <BarChart data={datos} layout={horizontal ? 'vertical' : 'horizontal'} margin={{ top: 8, right: 12, left: 0, bottom: 0 }} barCategoryGap="22%" barGap={2}>
        <CartesianGrid stroke="var(--grafico-rejilla)" vertical={horizontal} horizontal={!horizontal} />
        {horizontal ? (
          <>
            <XAxis type="number" {...ejes} axisLine={false} tickFormatter={compacto} allowDecimals={false} />
            <YAxis type="category" dataKey="etiqueta" {...ejes} width={150} />
          </>
        ) : (
          <>
            <XAxis dataKey="etiqueta" {...ejes} interval={datos.length > 8 ? 'preserveStartEnd' : 0} minTickGap={12} tickFormatter={(v) => (String(v).length > 16 ? `${String(v).slice(0, 15)}…` : v)} />
            <YAxis {...ejes} axisLine={false} tickFormatter={compacto} width={56} allowDecimals={false} />
          </>
        )}
        {emergente}
        {leyenda}
        {series.map((s, i) => (
          <Bar key={s.clave} dataKey={s.clave} name={s.nombre} fill={COLORES[i]} radius={horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]} maxBarSize={44} />
        ))}
      </BarChart>
    )
  }

  return (
    <section className="tarjeta flex flex-col">
      <header className="flex items-center justify-between gap-2 border-b px-4 py-3">
        <h3 className="text-sm font-bold">{titulo}</h3>
        <button onClick={() => setVerTabla((v) => !v)} aria-pressed={verTabla} className="no-imprimir flex items-center gap-1 rounded px-2 py-1 text-xs font-semibold text-tenue hover:bg-suave hover:text-texto">
          <Table2 className="size-3.5" aria-hidden /> {verTabla ? 'Ver gráfico' : 'Ver tabla'}
        </button>
      </header>

      <div className="p-3">
        {sinDatos ? (
          <Vacio titulo="Aún no hay datos">Este indicador se llenará a medida que se use el sistema.</Vacio>
        ) : verTabla ? (
          <div className="overflow-auto" style={{ maxHeight: alto }}>
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-suave text-left text-xs uppercase">
                  <th className="px-3 py-2"> </th>
                  {series.map((s) => <th key={s.clave} className="px-3 py-2 text-right">{s.nombre}</th>)}
                </tr>
              </thead>
              <tbody>
                {datos.map((d) => (
                  <tr key={d.etiqueta} className="border-t">
                    <td className="px-3 py-1.5">{d.etiqueta}</td>
                    {series.map((s) => <td key={s.clave} className="tabular px-3 py-1.5 text-right">{formato(d[s.clave])}</td>)}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div style={{ height: alto }} role="img" aria-label={`Gráfico: ${titulo}. Use «Ver tabla» para leer los valores.`}>
            <ResponsiveContainer width="100%" height="100%">{dibujo}</ResponsiveContainer>
          </div>
        )}
      </div>
    </section>
  )
}

/** Indicador numérico del panel (no todo dato necesita un gráfico). */
export function Indicador({ tarjeta }) {
  const formato = FORMATOS[tarjeta.formato] ?? cantidad

  return (
    <div className="tarjeta flex items-start gap-3 p-4">
      <span className="grid size-10 shrink-0 place-items-center rounded-tema bg-primario-suave text-primario">
        <Icono nombre={tarjeta.icono} className="size-5" />
      </span>
      <div className="min-w-0">
        <p className="text-xs font-semibold text-tenue">{tarjeta.titulo}</p>
        <p className="tabular text-2xl leading-tight font-extrabold">{formato(tarjeta.valor)}</p>
        {tarjeta.detalle && <p className="mt-0.5 text-xs text-tenue">{tarjeta.detalle}</p>}
      </div>
    </div>
  )
}
