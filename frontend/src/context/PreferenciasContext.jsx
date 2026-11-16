import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'

/**
 * Temas y accesibilidad (requisito 5).
 *
 *   tema       ninos | jovenes | adultos
 *   modo       auto | dia | noche      ("auto" sigue la hora: noche de 19:00 a 06:59)
 *   contraste  normal | alto
 *   escala     tamaño de letra (0.85 a 1.5)
 *
 * Las preferencias se guardan en el navegador y se aplican como atributos de
 * <html>; las hojas de estilo hacen el resto.
 */
const CLAVE = 'icap.preferencias'
const INICIAL = { tema: 'adultos', modo: 'auto', contraste: 'normal', escala: 1 }

export const TEMAS = [
  { clave: 'ninos', nombre: 'Niños', detalle: 'Colores alegres y letra redondeada' },
  { clave: 'jovenes', nombre: 'Jóvenes', detalle: 'Moderno y vibrante' },
  { clave: 'adultos', nombre: 'Adultos', detalle: 'Sobrio e institucional' },
]

export const ESCALA = { min: 0.85, max: 1.5, paso: 0.05 }

const esDeNoche = () => {
  const hora = new Date().getHours()

  return hora >= 19 || hora < 7
}

function leer() {
  try {
    return { ...INICIAL, ...JSON.parse(localStorage.getItem(CLAVE) ?? '{}') }
  } catch {
    return INICIAL
  }
}

const PreferenciasContext = createContext(null)

export function PreferenciasProvider({ children }) {
  const [preferencias, setPreferencias] = useState(leer)
  const [noche, setNoche] = useState(esDeNoche)

  // En modo automático se revisa la hora cada minuto para conmutar día/noche.
  useEffect(() => {
    const id = setInterval(() => setNoche(esDeNoche()), 60_000)

    return () => clearInterval(id)
  }, [])

  const modoEfectivo = preferencias.modo === 'auto' ? (noche ? 'noche' : 'dia') : preferencias.modo

  useEffect(() => {
    const raiz = document.documentElement
    raiz.dataset.tema = preferencias.tema
    raiz.dataset.modo = modoEfectivo
    raiz.dataset.contraste = preferencias.contraste
    raiz.style.setProperty('--escala-fuente', String(preferencias.escala))
    localStorage.setItem(CLAVE, JSON.stringify(preferencias))
  }, [preferencias, modoEfectivo])

  const cambiar = useCallback((cambios) => setPreferencias((p) => ({ ...p, ...cambios })), [])

  const valor = useMemo(
    () => ({
      ...preferencias,
      modoEfectivo,
      cambiar,
      restablecer: () => setPreferencias(INICIAL),
    }),
    [preferencias, modoEfectivo, cambiar],
  )

  return <PreferenciasContext.Provider value={valor}>{children}</PreferenciasContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export const usePreferencias = () => useContext(PreferenciasContext)
