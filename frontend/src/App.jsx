import { Suspense } from 'react'
import { BrowserRouter, HashRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom'
import { Alerta, Cargando } from './components/ui/Basicos'
import Boton from './components/ui/Boton'
import { AuthProvider, useAuth } from './context/AuthContext'
import { AvisosProvider } from './context/AvisosContext'
import { PreferenciasProvider } from './context/PreferenciasContext'
import LayoutSitio from './layouts/LayoutSitio'
import { RUTAS } from './routes/rutas'

// "hash" funciona en cualquier servidor; "browser" da URLs limpias (requiere .htaccess).
const Enrutador = import.meta.env.VITE_ROUTER === 'hash' ? HashRouter : BrowserRouter
const base = import.meta.env.VITE_ROUTER === 'hash' ? undefined : import.meta.env.BASE_URL.replace(/\/$/, '') || undefined

/** Aplica la matriz de acceso en el cliente antes de mostrar una página. */
function Guardia({ definicion }) {
  const auth = useAuth()
  const ubicacion = useLocation()
  const Pagina = definicion.pagina

  if (auth.cargando) return <Cargando texto="Verificando sesión…" />

  // Al iniciar sesión (o registrarse) siempre se entra primero al panel. Si la persona venía
  // de otra página (por ejemplo, preinscribirse en un programa), el panel le ofrece volver.
  if (definicion.invitado && auth.autenticado) {
    return <Navigate to="/panel" replace state={ubicacion.state?.desde ? { continuar: ubicacion.state.desde } : undefined} />
  }

  if (!definicion.publica && !auth.autenticado) {
    return <Navigate to="/ingresar" replace state={{ desde: ubicacion.pathname + ubicacion.search }} />
  }

  if (definicion.permiso && !auth.puede(definicion.permiso)) {
    return (
      <div className="mx-auto max-w-xl py-10">
        <Alerta tipo="peligro" titulo="Acceso restringido">
          Su rol ({auth.rolActivo?.nombre}) no tiene permiso para abrir esta página.
        </Alerta>
        <Boton a="/panel" className="mt-4">Volver al panel</Boton>
      </div>
    )
  }

  return <Pagina />
}

function NoEncontrada() {
  return (
    <div className="mx-auto max-w-xl py-16 text-center">
      <p className="text-6xl font-extrabold text-primario">404</p>
      <h1 className="mt-2 text-2xl font-bold">Página no encontrada</h1>
      <p className="mt-2 text-tenue">La dirección que abrió no existe o fue movida.</p>
      <Boton a="/" className="mt-6">Ir al inicio</Boton>
    </div>
  )
}

export default function App() {
  return (
    <PreferenciasProvider>
      <AvisosProvider>
        <AuthProvider>
          <Enrutador basename={base}>
            <Suspense fallback={<Cargando />}>
              <Routes>
                <Route element={<LayoutSitio />}>
                  {RUTAS.map((definicion) => (
                    <Route
                      key={definicion.ruta}
                      path={definicion.ruta}
                      element={
                        <Suspense fallback={<Cargando />}>
                          <Guardia definicion={definicion} />
                        </Suspense>
                      }
                    />
                  ))}
                  <Route path="*" element={<NoEncontrada />} />
                </Route>
              </Routes>
            </Suspense>
          </Enrutador>
        </AuthProvider>
      </AvisosProvider>
    </PreferenciasProvider>
  )
}
