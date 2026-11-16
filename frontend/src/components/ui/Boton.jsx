import { LoaderCircle } from 'lucide-react'
import { Link } from 'react-router-dom'

const VARIANTES = {
  primario: 'bg-primario text-sobre-primario hover:opacity-90 border-transparent',
  secundario: 'bg-tarjeta text-texto border-borde hover:bg-suave',
  peligro: 'bg-peligro text-white hover:opacity-90 border-transparent',
  exito: 'bg-exito text-white hover:opacity-90 border-transparent',
  fantasma: 'bg-transparent text-primario border-transparent hover:bg-primario-suave',
}

const TAMANOS = {
  sm: 'px-2.5 py-1.5 text-xs gap-1.5',
  md: 'px-4 py-2 text-sm gap-2',
  lg: 'px-5 py-3 text-base gap-2',
}

/**
 * Botón único del sistema. Con "a" se comporta como enlace interno.
 *   <Boton icono={Plus} onClick={...}>Nuevo</Boton>
 *   <Boton a="/catalogo" variante="secundario">Ver catálogo</Boton>
 */
export default function Boton({
  children,
  variante = 'primario',
  tamano = 'md',
  icono: Icono,
  cargando = false,
  a,
  className = '',
  type = 'button',
  ...resto
}) {
  const clases = `inline-flex items-center justify-center rounded-tema border font-semibold transition
    disabled:cursor-not-allowed disabled:opacity-55 ${VARIANTES[variante]} ${TAMANOS[tamano]} ${className}`

  const contenido = (
    <>
      {cargando ? <LoaderCircle className="size-4 animate-spin" aria-hidden /> : Icono && <Icono className="size-4 shrink-0" aria-hidden />}
      {children}
    </>
  )

  if (a) {
    return (
      <Link to={a} className={clases} {...resto}>
        {contenido}
      </Link>
    )
  }

  return (
    <button type={type} className={clases} disabled={cargando || resto.disabled} {...resto}>
      {contenido}
    </button>
  )
}
