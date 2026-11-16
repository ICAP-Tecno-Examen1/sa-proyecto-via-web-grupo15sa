import { ChevronDown, Eye, LogOut, Menu, UserRound, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import Buscador from '../components/layout/Buscador'
import ControlesVisuales from '../components/layout/ControlesVisuales'
import Icono from '../components/layout/Icono'
import Boton from '../components/ui/Boton'
import { useAuth } from '../context/AuthContext'
import { useAvisos } from '../context/AvisosContext'
import { useVisitas } from '../hooks/useVisitas'
import { cantidad } from '../utils/formato'

/**
 * Marco único de todo el sitio: encabezado (con la búsqueda global), barra de
 * navegación dinámica según el rol, contenido y pie con el contador de visitas.
 */
export default function LayoutSitio() {
  const auth = useAuth()
  const { contador, titulo } = useVisitas()
  const [menuAbierto, setMenuAbierto] = useState(false)
  const { pathname } = useLocation()

  useEffect(() => setMenuAbierto(false), [pathname])

  return (
    <div className="flex min-h-screen flex-col">
      <a href="#contenido" className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-primario focus:p-2 focus:text-sobre-primario">
        Saltar al contenido
      </a>

      <Encabezado alAbrirMenu={() => setMenuAbierto((a) => !a)} menuAbierto={menuAbierto} />

      <div className="flex flex-1">
        {auth.autenticado && <BarraLateral abierta={menuAbierto} alCerrar={() => setMenuAbierto(false)} />}

        <main id="contenido" className="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8">
          <div className="mx-auto w-full max-w-7xl">
            <Outlet />
          </div>
        </main>
      </div>

      <footer className="no-imprimir border-t bg-tarjeta px-4 py-4 text-sm">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
          <p className="text-tenue">
            <strong className="text-texto">ICAP · UAGRM</strong> — Instituto de Capacitación Popular · INF513 Tecnología Web
          </p>
          {/* Contador de visitas persistente de ESTA página (tabla contador_visita) */}
          <p className="flex items-center gap-2 rounded-full border bg-suave px-3 py-1" aria-live="polite">
            <Eye className="size-4 text-primario" aria-hidden />
            <span>
              Visitas a «{titulo}»: <strong className="tabular">{contador ? cantidad(contador.total) : '…'}</strong>
            </span>
            <span className="text-tenue">· Sitio: <span className="tabular">{contador ? cantidad(contador.total_sitio) : '…'}</span></span>
          </p>
        </div>
      </footer>
    </div>
  )
}

function Encabezado({ alAbrirMenu, menuAbierto }) {
  const auth = useAuth()

  return (
    <header className="no-imprimir sticky top-0 z-30 border-b bg-tarjeta">
      <div className="flex items-center gap-3 px-4 py-2.5 sm:px-6">
        {auth.autenticado && (
          <button onClick={alAbrirMenu} aria-label={menuAbierto ? 'Cerrar menú' : 'Abrir menú'} aria-expanded={menuAbierto} className="rounded-tema border p-2 hover:bg-suave lg:hidden">
            {menuAbierto ? <X className="size-5" /> : <Menu className="size-5" />}
          </button>
        )}

        <Link to="/" className="flex shrink-0 items-center gap-2.5" aria-label="ICAP UAGRM, ir al inicio">
          <span className="grid size-10 place-items-center rounded-tema bg-primario text-sobre-primario">
            <Icono nombre="estudiante" className="size-6" />
          </span>
          <span className="hidden leading-tight sm:block">
            <span className="block text-base font-extrabold">ICAP</span>
            <span className="block text-xs text-tenue">UAGRM</span>
          </span>
        </Link>

        <div className="flex flex-1 justify-center">
          <Buscador />
        </div>

        <ControlesVisuales />

        {auth.autenticado ? (
          <MenuUsuario />
        ) : (
          <div className="flex gap-2">
            <Boton a="/ingresar" variante="secundario" className="hidden sm:inline-flex">Ingresar</Boton>
            <Boton a="/registro">Registrarme</Boton>
          </div>
        )}
      </div>

      {!auth.autenticado && (
        <nav className="flex gap-1 overflow-x-auto border-t px-4 sm:px-6" aria-label="Navegación principal">
          {[['/', 'Inicio'], ['/catalogo', 'Catálogo de programas'], ['/verificar', 'Verificar certificado'], ['/ingresar', 'Ingresar']].map(([ruta, texto]) => (
            <NavLink key={ruta} to={ruta} end className={({ isActive }) =>
              `border-b-2 px-3 py-2 text-sm font-semibold whitespace-nowrap ${isActive ? 'border-primario text-primario' : 'border-transparent text-tenue hover:text-texto'}`}>
              {texto}
            </NavLink>
          ))}
        </nav>
      )}
    </header>
  )
}

function MenuUsuario() {
  const auth = useAuth()
  const avisar = useAvisos()
  const navegar = useNavigate()
  const [abierto, setAbierto] = useState(false)
  const contenedor = useRef(null)

  useEffect(() => {
    const cerrar = (e) => !contenedor.current?.contains(e.target) && setAbierto(false)
    document.addEventListener('mousedown', cerrar)

    return () => document.removeEventListener('mousedown', cerrar)
  }, [])

  const cambiarRol = async (idRol) => {
    try {
      await auth.cambiarRol(idRol)
      setAbierto(false)
      navegar('/panel')
    } catch (e) {
      avisar.error(e)
    }
  }

  const salir = async () => {
    await auth.cerrarSesion()
    navegar('/')
  }

  return (
    <div ref={contenedor} className="relative">
      <button onClick={() => setAbierto((a) => !a)} aria-expanded={abierto} className="flex items-center gap-2 rounded-tema border bg-tarjeta px-2.5 py-1.5 hover:bg-suave">
        <span className="grid size-7 place-items-center rounded-full bg-primario-suave text-primario">
          <UserRound className="size-4" aria-hidden />
        </span>
        <span className="hidden text-left leading-tight md:block">
          <span className="block max-w-40 truncate text-sm font-bold">{auth.usuario.nombres}</span>
          <span className="block text-xs text-tenue">{auth.rolActivo?.nombre}</span>
        </span>
        <ChevronDown className="size-4 text-tenue" aria-hidden />
      </button>

      {abierto && (
        <div className="tarjeta absolute top-full right-0 z-40 mt-1 w-64 overflow-hidden shadow-xl">
          <div className="border-b px-4 py-3">
            <p className="text-sm font-bold">{auth.usuario.nombre_completo}</p>
            <p className="truncate text-xs text-tenue">{auth.usuario.correo}</p>
          </div>

          {auth.roles.length > 1 && (
            <div className="border-b px-2 py-2">
              <p className="px-2 pb-1 text-xs font-bold tracking-wide text-tenue uppercase">Trabajar como</p>
              {auth.roles.map((rol) => (
                <button key={rol.id_rol} onClick={() => cambiarRol(rol.id_rol)}
                  className={`block w-full rounded px-2 py-1.5 text-left text-sm ${rol.id_rol === auth.rolActivo?.id_rol ? 'bg-primario-suave font-bold text-primario' : 'hover:bg-suave'}`}>
                  {rol.nombre}
                </button>
              ))}
            </div>
          )}

          <Link to="/perfil" onClick={() => setAbierto(false)} className="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-suave">
            <UserRound className="size-4" aria-hidden /> Mi perfil
          </Link>
          <button onClick={salir} className="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-peligro hover:bg-peligro-suave">
            <LogOut className="size-4" aria-hidden /> Cerrar sesión
          </button>
        </div>
      )}
    </div>
  )
}

/** Menú lateral: se dibuja tal como llega de la API (tabla menu_item filtrada por permisos). */
function BarraLateral({ abierta, alCerrar }) {
  const { menu, rolActivo } = useAuth()

  return (
    <>
      {abierta && <div className="fixed inset-0 z-20 bg-black/50 lg:hidden" onClick={alCerrar} aria-hidden />}
      <aside
        className={`no-imprimir fixed inset-y-0 left-0 z-20 w-64 shrink-0 overflow-y-auto bg-barra pt-16 text-sobre-barra transition-transform lg:sticky lg:top-[57px] lg:z-0 lg:h-[calc(100vh-57px)] lg:translate-x-0 lg:pt-0 ${abierta ? 'translate-x-0' : '-translate-x-full'}`}
        aria-label="Menú del sistema"
      >
        <p className="px-4 pt-4 pb-2 text-xs font-bold tracking-widest uppercase opacity-70">{rolActivo?.nombre}</p>
        <nav className="px-2 pb-6">
          {menu.map((seccion) => (
            <div key={seccion.id} className="mb-3">
              <p className="flex items-center gap-2 px-2 py-1.5 text-xs font-bold tracking-wide uppercase opacity-70">
                <Icono nombre={seccion.icono} className="size-3.5" /> {seccion.etiqueta}
              </p>
              {seccion.hijos.map((opcion) => (
                <NavLink key={opcion.id} to={opcion.ruta} className={({ isActive }) =>
                  `flex items-center gap-2.5 rounded-tema px-3 py-2 text-sm transition ${isActive ? 'bg-white/20 font-bold' : 'opacity-90 hover:bg-white/10'}`}>
                  <Icono nombre={opcion.icono} /> {opcion.etiqueta}
                </NavLink>
              ))}
            </div>
          ))}
        </nav>
      </aside>
    </>
  )
}
