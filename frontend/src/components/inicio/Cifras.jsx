import { useAparecer, useContador } from '../../hooks/useAparecer'

/** Cifras de la portada calculadas con las ofertas abiertas reales; cuentan hacia arriba al aparecer. */
export default function Cifras({ ofertas }) {
  const [ref, visible] = useAparecer({ umbral: 0.4 })
  const lista = ofertas ?? []

  const cifras = [
    { valor: lista.length, texto: 'ofertas con inscripción abierta' },
    { valor: new Set(lista.map((o) => o.programa.nombre)).size, texto: 'programas distintos para elegir' },
    { valor: lista.reduce((suma, o) => suma + (o.cupos_disponibles ?? 0), 0), texto: 'cupos disponibles en este momento' },
  ]

  return (
    <div ref={ref} className="grid gap-8 text-center sm:grid-cols-3">
      {cifras.map((cifra) => <Cifra key={cifra.texto} {...cifra} activo={visible && Boolean(ofertas)} />)}
    </div>
  )
}

function Cifra({ valor, texto, activo }) {
  const actual = useContador(valor, activo)

  return (
    <div>
      <p className="tabular text-5xl font-black sm:text-6xl">{activo ? actual : '—'}</p>
      <p className="mt-2 text-sm opacity-80">{texto}</p>
    </div>
  )
}
