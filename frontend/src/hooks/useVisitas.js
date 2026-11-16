import { useEffect, useState } from 'react'
import { matchPath, useLocation } from 'react-router-dom'
import api from '../api/cliente'
import { RUTAS } from '../routes/rutas'

/**
 * Contador de visitas por página (requisito 7).
 *
 * Cada vez que cambia la página se avisa a la API con el PATRÓN de la ruta
 * (/matriculas/:id y no /matriculas/15), así todas las fichas suman al mismo
 * contador. La API responde con el acumulado, que se muestra en el pie.
 */
// En desarrollo React monta dos veces cada efecto: se evita contar la misma visita dos veces.
let ultima = { clave: '', momento: 0, respuesta: null }

export function useVisitas() {
  const { pathname } = useLocation()
  const [contador, setContador] = useState(null)
  const definicion = RUTAS.find((r) => matchPath({ path: r.ruta, end: true }, pathname))
  const ruta = definicion?.ruta ?? '/no-encontrada'
  const titulo = definicion?.titulo ?? 'Página no encontrada'

  useEffect(() => {
    document.title = `${titulo} · ICAP UAGRM`
    let vigente = true

    const repetida = ultima.clave === pathname && Date.now() - ultima.momento < 1500

    if (!repetida) {
      ultima = { clave: pathname, momento: Date.now(), respuesta: api.post('/visitas', { ruta, titulo }) }
    }

    ultima.respuesta
      .then(({ data }) => vigente && setContador(data))
      .catch(() => vigente && setContador(null))

    return () => {
      vigente = false
    }
  }, [ruta, titulo, pathname])

  return { contador, titulo }
}
