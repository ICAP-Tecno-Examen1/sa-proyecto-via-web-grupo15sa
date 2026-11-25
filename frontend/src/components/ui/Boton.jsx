import { LoaderCircle } from 'lucide-react'
import { Link } from 'react-router-dom'

// Las formas (relieve, sombra, radio) viven en index.css; aquí solo colores por variante.
const VARIANTES = {
  primario: 'bg-primario text-sobre-primario border-transparent boton-relieve',
  acento: 'bg-acento text-sobre-acento border-transparent boton-relieve [--relieve-local:var(--relieve-acento)]',
  secundario: 'bg-tarjeta text-texto border-borde hover:bg-suave boton-relieve-suave',
  peligro: 'bg-peligro text-white border-transparent boton-relieve [--relieve-local:color-mix(in_srgb,var(--peligro)_65%,#000)]',
  exito: 'bg-exito text-white border-transparent boton-relieve [--relieve-local:color-mix(in_srgb,var(--exito)_65%,#000)]',
  fantasma: 'bg-transparent text-primario border-transparent hover:bg-primario-suave transition-colors',
  // Para usar sobre las franjas oscuras de la marca
  claro: 'bg-white text-[#051874] border-transparent boton-relieve [--relieve-local:#b9c1e3]',
  contorno: 'bg-transparent text-sobre-barra border-white/40 hover:bg-white/10 transition-colors',
}

const TAMANOS = {
  sm: 'px-3 py-1.5 text-xs gap-1.5',
  md: 'px-5 py-2.5 text-sm gap-2',
  lg: 'px-7 py-3.5 text-base gap-2.5 font-bold',
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
  const clases = `inline-flex items-center justify-center rounded-tema border-2 font-semibold
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
