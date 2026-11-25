import { useAparecer } from '../../hooks/useAparecer'

/**
 * Hace aparecer su contenido (sube y se aclara) cuando entra en pantalla.
 *   <Revelar retardo={0.1}>...</Revelar>
 * El efecto se define en la clase .revelar de index.css.
 */
export default function Revelar({ como: Etiqueta = 'div', retardo = 0, className = '', children, ...resto }) {
  const [ref, visible] = useAparecer()

  return (
    <Etiqueta ref={ref} className={`revelar ${visible ? 'visible' : ''} ${className}`} style={{ transitionDelay: `${retardo}s` }} {...resto}>
      {children}
    </Etiqueta>
  )
}
