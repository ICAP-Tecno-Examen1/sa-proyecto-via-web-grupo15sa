import { CircleCheck, CircleX, Info, X } from 'lucide-react'
import { createContext, useCallback, useContext, useMemo, useState } from 'react'
import { mensajeError } from '../api/cliente'

/**
 * Avisos breves (éxito / error) que aparecen en la esquina de la pantalla.
 *   const avisar = useAvisos()
 *   avisar.exito('Programa registrado.')   ·   avisar.error(errorDeAxios)
 */
const AvisosContext = createContext(null)

const ESTILOS = {
  exito: { clase: 'border-exito bg-exito-suave text-exito', Icono: CircleCheck },
  error: { clase: 'border-peligro bg-peligro-suave text-peligro', Icono: CircleX },
  info: { clase: 'border-informacion bg-informacion-suave text-informacion', Icono: Info },
}

export function AvisosProvider({ children }) {
  const [avisos, setAvisos] = useState([])

  const quitar = useCallback((id) => setAvisos((lista) => lista.filter((a) => a.id !== id)), [])

  const mostrar = useCallback(
    (tipo, texto) => {
      const id = Date.now() + Math.random()
      setAvisos((lista) => [...lista.slice(-3), { id, tipo, texto }])
      setTimeout(() => quitar(id), tipo === 'error' ? 8000 : 4500)
    },
    [quitar],
  )

  const valor = useMemo(
    () => ({
      exito: (texto) => mostrar('exito', texto),
      info: (texto) => mostrar('info', texto),
      error: (error) => mostrar('error', typeof error === 'string' ? error : mensajeError(error)),
    }),
    [mostrar],
  )

  return (
    <AvisosContext.Provider value={valor}>
      {children}
      <div className="no-imprimir fixed right-4 bottom-4 z-[60] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2" aria-live="polite">
        {avisos.map(({ id, tipo, texto }) => {
          const { clase, Icono } = ESTILOS[tipo]

          return (
            <div key={id} role={tipo === 'error' ? 'alert' : 'status'} className={`flex items-start gap-3 rounded-tema border p-3 text-sm shadow-lg ${clase}`}>
              <Icono className="mt-0.5 size-5 shrink-0" aria-hidden />
              <p className="flex-1 text-texto">{texto}</p>
              <button onClick={() => quitar(id)} aria-label="Cerrar aviso" className="text-tenue hover:text-texto">
                <X className="size-4" />
              </button>
            </div>
          )
        })}
      </div>
    </AvisosContext.Provider>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useAvisos = () => useContext(AvisosContext)
