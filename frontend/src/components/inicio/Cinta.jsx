import { FRASES_CINTA } from './contenido'

/** Cinta deslizante con los programas abiertos (o con los tipos de formación si aún no hay ofertas). */
export default function Cinta({ ofertas }) {
  const nombres = [...new Set((ofertas ?? []).map((o) => o.programa.nombre))]
  const frases = nombres.length >= 3 ? nombres : FRASES_CINTA

  const grupo = (oculto) => (
    <ul className="flex shrink-0 items-center" aria-hidden={oculto || undefined}>
      {frases.map((frase) => (
        <li key={frase} className="flex items-center gap-6 px-3 text-lg font-bold whitespace-nowrap sm:text-xl">
          {frase}
          <span className="size-2.5 rotate-45 bg-acento" aria-hidden />
        </li>
      ))}
    </ul>
  )

  return (
    <div className="cinta border-y bg-tarjeta py-4" aria-label="Programas disponibles">
      {/* Dos copias iguales: la animación desplaza la mitad y el bucle no se nota */}
      <div className="cinta-pista">
        {grupo(false)}
        {grupo(true)}
      </div>
    </div>
  )
}
