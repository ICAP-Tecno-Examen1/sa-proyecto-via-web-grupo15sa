import { X } from 'lucide-react'
import { useEffect, useRef } from 'react'
import Boton from './Boton'

const ANCHOS = { sm: 'max-w-md', md: 'max-w-2xl', lg: 'max-w-4xl', xl: 'max-w-6xl' }

/** Ventana modal accesible: se cierra con Escape y devuelve el foco al cerrar. */
export default function Modal({ abierto, titulo, alCerrar, children, pie, ancho = 'md' }) {
  const caja = useRef(null)

  useEffect(() => {
    if (!abierto) return undefined

    const anterior = document.activeElement
    const alTeclear = (e) => e.key === 'Escape' && alCerrar()
    document.addEventListener('keydown', alTeclear)
    document.body.style.overflow = 'hidden'
    caja.current?.focus()

    return () => {
      document.removeEventListener('keydown', alTeclear)
      document.body.style.overflow = ''
      anterior?.focus?.()
    }
  }, [abierto, alCerrar])

  if (!abierto) return null

  return (
    <div className="no-imprimir fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/55 p-4 sm:items-center" onMouseDown={(e) => e.target === e.currentTarget && alCerrar()}>
      <div ref={caja} tabIndex={-1} role="dialog" aria-modal="true" aria-label={titulo} className={`tarjeta my-4 w-full shadow-2xl outline-none ${ANCHOS[ancho]}`}>
        <header className="flex items-center justify-between gap-4 border-b px-5 py-3">
          <h2 className="text-lg font-bold">{titulo}</h2>
          <button onClick={alCerrar} aria-label="Cerrar" className="rounded p-1 text-tenue hover:bg-suave hover:text-texto">
            <X className="size-5" />
          </button>
        </header>
        <div className="max-h-[70vh] overflow-y-auto px-5 py-4">{children}</div>
        {pie && <footer className="flex flex-wrap justify-end gap-2 border-t px-5 py-3">{pie}</footer>}
      </div>
    </div>
  )
}

/** Confirmación de acciones delicadas (eliminar, anular, cerrar módulo…). */
export function Confirmar({ abierto, titulo = 'Confirmar acción', mensaje, textoConfirmar = 'Confirmar', variante = 'peligro', alConfirmar, alCerrar, cargando }) {
  return (
    <Modal
      abierto={abierto}
      titulo={titulo}
      alCerrar={alCerrar}
      ancho="sm"
      pie={
        <>
          <Boton variante="secundario" onClick={alCerrar}>Cancelar</Boton>
          <Boton variante={variante} onClick={alConfirmar} cargando={cargando}>{textoConfirmar}</Boton>
        </>
      }
    >
      <p className="text-sm leading-relaxed">{mensaje}</p>
    </Modal>
  )
}
