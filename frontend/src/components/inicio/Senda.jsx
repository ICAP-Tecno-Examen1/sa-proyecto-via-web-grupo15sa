import { useAparecer } from '../../hooks/useAparecer'
import { PASOS } from './contenido'

/**
 * Ruta de aprendizaje: nodos redondos con relieve unidos por una línea punteada,
 * en zigzag, que aparecen uno a uno al hacer scroll.
 */
export default function Senda() {
  return (
    <ol className="relative mx-auto mt-12 max-w-3xl">
      {/* Línea punteada que une los pasos */}
      <span className="absolute top-8 bottom-8 left-8 border-l-4 border-dashed border-borde md:left-1/2 md:-ml-0.5" aria-hidden />
      {PASOS.map((paso, i) => <Paso key={paso.titulo} paso={paso} indice={i} ultimo={i === PASOS.length - 1} />)}
    </ol>
  )
}

function Paso({ paso: { Icono, titulo, texto }, indice, ultimo }) {
  const [ref, visible] = useAparecer({ umbral: 0.5 })
  const derecha = indice % 2 === 1

  return (
    <li ref={ref} className="relative grid grid-cols-[4rem_1fr] items-center gap-5 py-5 md:grid-cols-[1fr_4rem_1fr] md:gap-8">
      <div className={`revelar ${visible ? 'visible' : ''} order-2 md:order-none ${derecha ? 'md:col-start-3' : 'md:col-start-1 md:text-right'} md:row-start-1`}>
        <p className="text-xs font-bold tracking-widest text-acento uppercase">Paso {indice + 1}</p>
        <h3 className="mt-1 text-lg font-extrabold">{titulo}</h3>
        <p className="mt-1 text-sm text-tenue">{texto}</p>
      </div>

      <div className="relative order-1 md:order-none md:col-start-2 md:row-start-1">
        {indice === 0 && (
          <span className="animar-flotar absolute -top-11 left-1/2 z-10 -translate-x-1/2 rounded-xl border-2 bg-tarjeta px-3 py-1 text-xs font-extrabold whitespace-nowrap text-primario uppercase shadow-sm">
            ¡Empieza aquí!
          </span>
        )}
        <span
          className={`boton-relieve relative grid size-16 place-items-center rounded-full transition-transform duration-500 ${visible ? 'scale-100' : 'scale-50'}
            ${ultimo ? 'bg-acento text-sobre-acento [--relieve-local:var(--relieve-acento)]' : 'bg-primario text-sobre-primario'} ${indice === 0 && visible ? 'animar-latido' : ''}`}
        >
          <Icono className="size-7" aria-hidden />
        </span>
      </div>
    </li>
  )
}
