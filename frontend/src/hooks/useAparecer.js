import { useEffect, useRef, useState } from 'react'

const sinMovimiento = () => typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

/**
 * Indica cuándo un elemento entra en pantalla (una sola vez).
 *   const [ref, visible] = useAparecer()
 *   <div ref={ref} className={visible ? '...' : '...'} />
 * Sin IntersectionObserver o con "reducir movimiento", el elemento es visible desde el inicio.
 */
export function useAparecer({ umbral = 0.2 } = {}) {
  const ref = useRef(null)
  const [visible, setVisible] = useState(() => typeof IntersectionObserver === 'undefined' || sinMovimiento())

  useEffect(() => {
    const elemento = ref.current
    if (!elemento || visible) return undefined

    const observador = new IntersectionObserver(([entrada]) => {
      if (entrada.isIntersecting) {
        setVisible(true)
        observador.disconnect()
      }
    }, { threshold: umbral, rootMargin: '0px 0px -40px 0px' })

    observador.observe(elemento)

    return () => observador.disconnect()
  }, [visible, umbral])

  return [ref, visible]
}

/** Cuenta desde 0 hasta "objetivo" cuando "activo" pasa a true. */
export function useContador(objetivo, activo, duracion = 1200) {
  const [valor, setValor] = useState(0)

  useEffect(() => {
    if (!activo || objetivo == null) return undefined

    if (sinMovimiento()) {
      const id = requestAnimationFrame(() => setValor(objetivo))

      return () => cancelAnimationFrame(id)
    }

    let id
    const inicio = performance.now()
    const paso = (ahora) => {
      const t = Math.min(1, (ahora - inicio) / duracion)
      setValor(Math.round(objetivo * (1 - (1 - t) ** 3)))
      if (t < 1) id = requestAnimationFrame(paso)
    }
    id = requestAnimationFrame(paso)

    return () => cancelAnimationFrame(id)
  }, [objetivo, activo, duracion])

  return valor
}
