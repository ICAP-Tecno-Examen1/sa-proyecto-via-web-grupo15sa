import { Check, ChevronDown } from 'lucide-react'
import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react'

/** Texto comparable: sin tildes ni mayúsculas ("Computación" ≈ "computacion"). */
const normalizar = (texto) => String(texto).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

/** Resalta en negrita la parte que coincide con lo escrito. */
function Resaltado({ texto, busqueda }) {
  const i = busqueda ? normalizar(texto).indexOf(normalizar(busqueda)) : -1
  if (i < 0) return texto

  return (
    <>
      {texto.slice(0, i)}
      <mark className="rounded-sm bg-primario-suave font-bold text-primario">{texto.slice(i, i + busqueda.length)}</mark>
      {texto.slice(i + busqueda.length)}
    </>
  )
}

/**
 * Lista desplegable con buscador (combobox accesible). Reemplaza al <select>:
 * se escribe y debajo aparecen solo las opciones que contienen ese texto,
 * sin importar tildes ni mayúsculas. Se maneja con el ratón o con el teclado
 * (↑ ↓ para moverse, Enter para elegir, Escape para cerrar).
 *
 * La lista se dibuja con posición fija para que no la recorten las ventanas
 * modales ni los contenedores con desplazamiento.
 */
export default function Selector({ id, valor, opciones, alCambiar, alSalir, vacio, disabled, className = '', ...resto }) {
  const idLista = useId()
  const entrada = useRef(null)
  const [abierto, setAbierto] = useState(false)
  const [busqueda, setBusqueda] = useState('')
  const [activo, setActivo] = useState(0)
  const [posicion, setPosicion] = useState(null)

  const lista = opciones.map((o) => (typeof o === 'object' ? o : { valor: o, etiqueta: o }))
  const elegida = lista.find((o) => String(o.valor) === String(valor ?? ''))

  // Sin búsqueda se ofrece también la opción vacía ("Todos los tipos", "Seleccione…").
  const visibles = [
    ...(vacio !== null && !busqueda ? [{ valor: '', etiqueta: vacio, esVacio: true }] : []),
    ...lista.filter((o) => normalizar(o.etiqueta).includes(normalizar(busqueda))),
  ]

  const abrir = () => {
    if (disabled) return
    setBusqueda('')
    setActivo(Math.max(0, visibles.findIndex((o) => String(o.valor) === String(valor ?? ''))))
    setAbierto(true)
  }

  const elegir = (opcion) => {
    alCambiar(String(opcion.valor))
    setAbierto(false)
    setBusqueda('')
  }

  // Ubica la lista debajo del campo (o encima, si abajo no hay espacio).
  useLayoutEffect(() => {
    if (!abierto) return undefined

    const ubicar = () => {
      const r = entrada.current?.getBoundingClientRect()
      if (!r) return
      const arriba = window.innerHeight - r.bottom < 260 && r.top > 260
      setPosicion({ left: r.left, width: r.width, ...(arriba ? { bottom: window.innerHeight - r.top + 4 } : { top: r.bottom + 4 }) })
    }
    ubicar()
    window.addEventListener('resize', ubicar)
    window.addEventListener('scroll', ubicar, true)

    return () => {
      window.removeEventListener('resize', ubicar)
      window.removeEventListener('scroll', ubicar, true)
    }
  }, [abierto])

  // Mantiene visible la opción activa al moverse con el teclado.
  useEffect(() => {
    if (abierto) document.getElementById(`${idLista}-${activo}`)?.scrollIntoView({ block: 'nearest' })
  }, [abierto, activo, idLista])

  const alTeclear = (e) => {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault()
      if (!abierto) return abrir()
      const salto = e.key === 'ArrowDown' ? 1 : -1
      setActivo((a) => Math.min(Math.max(a + salto, 0), visibles.length - 1))
    } else if (e.key === 'Enter' && abierto) {
      e.preventDefault()
      if (visibles[activo]) elegir(visibles[activo])
    } else if (e.key === 'Escape' && abierto) {
      // Solo cierra la lista: no debe cerrar también la ventana modal que la contiene.
      e.preventDefault()
      e.nativeEvent.stopImmediatePropagation()
      setAbierto(false)
    } else if (e.key === 'Tab') {
      setAbierto(false)
    }
  }

  return (
    <div className={`relative ${className}`}>
      <input
        ref={entrada}
        id={id}
        type="text"
        role="combobox"
        autoComplete="off"
        aria-expanded={abierto}
        aria-controls={idLista}
        aria-autocomplete="list"
        aria-activedescendant={abierto && visibles[activo] ? `${idLista}-${activo}` : undefined}
        disabled={disabled}
        {...resto}
        className="campo-control cursor-pointer pr-9 text-left focus:cursor-text"
        placeholder={abierto ? (elegida ? `${elegida.etiqueta} — escriba para buscar…` : 'Escriba para buscar…') : (vacio ?? 'Seleccione…')}
        value={abierto ? busqueda : (elegida?.etiqueta ?? '')}
        onChange={(e) => { setBusqueda(e.target.value); setActivo(0); setAbierto(true) }}
        onMouseDown={() => !abierto && abrir()}
        onFocus={() => !abierto && abrir()}
        onKeyDown={alTeclear}
        onBlur={() => { setAbierto(false); alSalir?.() }}
      />
      <ChevronDown className={`pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-tenue transition-transform ${abierto ? 'rotate-180' : ''}`} aria-hidden />

      {abierto && posicion && (
        <ul
          id={idLista}
          role="listbox"
          style={{ position: 'fixed', ...posicion }}
          className="tarjeta z-[60] max-h-64 overflow-y-auto p-1 shadow-xl"
          // Evita que el campo pierda el foco antes de registrar el clic en una opción.
          onMouseDown={(e) => e.preventDefault()}
        >
          {visibles.length === 0 && <li className="px-3 py-2 text-sm text-tenue">Sin coincidencias para «{busqueda}»</li>}
          {visibles.map((o, i) => {
            const seleccionada = !o.esVacio && String(o.valor) === String(valor ?? '')

            return (
              <li
                key={`${o.valor}-${i}`}
                id={`${idLista}-${i}`}
                role="option"
                aria-selected={seleccionada}
                onMouseEnter={() => setActivo(i)}
                onClick={() => elegir(o)}
                className={`flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm ${i === activo ? 'bg-suave' : ''} ${o.esVacio ? 'text-tenue italic' : ''}`}
              >
                <span className="min-w-0 flex-1">{o.esVacio ? o.etiqueta : <Resaltado texto={o.etiqueta} busqueda={busqueda} />}</span>
                {seleccionada && <Check className="size-4 shrink-0 text-primario" aria-hidden />}
              </li>
            )
          })}
        </ul>
      )}
    </div>
  )
}
