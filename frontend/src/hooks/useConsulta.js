import { useCallback, useEffect, useRef, useState } from 'react'
import api, { mensajeError } from '../api/cliente'

/**
 * Consulta GET a la API con estados de carga y error.
 *
 *   const { datos, cargando, error, recargar } = useConsulta('/programas', { q })
 *
 * Se vuelve a ejecutar cuando cambian la URL o los parámetros.
 * Pase url = null para no consultar todavía.
 */
export function useConsulta(url, params = null) {
  const [datos, setDatos] = useState(null)
  const [cargando, setCargando] = useState(Boolean(url))
  const [error, setError] = useState(null)
  const turno = useRef(0)
  const clave = JSON.stringify(params ?? {})

  const recargar = useCallback(async () => {
    if (!url) return null
    const miTurno = ++turno.current
    setCargando(true)
    setError(null)

    try {
      const limpios = Object.fromEntries(Object.entries(JSON.parse(clave)).filter(([, v]) => v !== '' && v !== null))
      const { data } = await api.get(url, { params: limpios })
      // Si llegó una respuesta más nueva antes que esta, se descarta.
      if (miTurno === turno.current) setDatos(data)

      return data
    } catch (e) {
      if (miTurno === turno.current) setError(mensajeError(e, 'No se pudo cargar la información.'))

      return null
    } finally {
      if (miTurno === turno.current) setCargando(false)
    }
  }, [url, clave])

  useEffect(() => {
    recargar()
  }, [recargar])

  return { datos, cargando, error, recargar, setDatos }
}

/** Valor que solo cambia cuando el usuario deja de escribir. */
export function useRetardo(valor, ms = 350) {
  const [retardado, setRetardado] = useState(valor)

  useEffect(() => {
    const id = setTimeout(() => setRetardado(valor), ms)

    return () => clearTimeout(id)
  }, [valor, ms])

  return retardado
}
