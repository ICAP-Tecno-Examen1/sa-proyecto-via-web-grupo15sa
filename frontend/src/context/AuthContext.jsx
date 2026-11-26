import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import api, { alCambiarPermisos, alExpirarSesion, sesionLocal } from '../api/cliente'

// El menú y los permisos se vuelven a pedir a la API (los cambia el Director en «Roles y permisos»):
const REFRESCO_MS = 2 * 60_000 // cada 2 minutos mientras la pestaña está visible
const MINIMO_ENTRE_REFRESCOS_MS = 15_000

/**
 * Sesión del usuario: datos personales, roles, ROL ACTIVO, permisos y el menú
 * dinámico que la API arma desde la base de datos para ese rol.
 */
const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [sesion, setSesion] = useState(null)
  const [cargando, setCargando] = useState(Boolean(sesionLocal.token()))

  const aplicar = useCallback((datos) => {
    if (datos.token) sesionLocal.guardarToken(datos.token)
    if (datos.rol_activo) sesionLocal.guardarRol(datos.rol_activo.id_rol)
    setSesion({
      usuario: datos.usuario,
      roles: datos.roles ?? [],
      rolActivo: datos.rol_activo,
      permisos: datos.permisos ?? [],
      menu: datos.menu ?? [],
    })
  }, [])

  const refrescar = useCallback(async () => {
    const { data } = await api.get('/auth/yo')
    aplicar(data)
  }, [aplicar])

  useEffect(() => {
    alExpirarSesion(() => setSesion(null))

    if (!sesionLocal.token()) return

    refrescar()
      .catch(() => sesionLocal.limpiar())
      .finally(() => setCargando(false))
  }, [refrescar])

  /*
   * Los permisos se aplican en la API en cada petición; el MENÚ se mantiene al día solo:
   * al volver a la pestaña, cada 2 minutos y cuando la API responde 403 (se quitó un permiso).
   */
  useEffect(() => {
    let ultimo = Date.now()
    const actualizar = () => {
      if (!sesionLocal.token() || document.visibilityState !== 'visible' || Date.now() - ultimo < MINIMO_ENTRE_REFRESCOS_MS) return
      ultimo = Date.now()
      refrescar().catch(() => {})
    }

    alCambiarPermisos(actualizar)
    document.addEventListener('visibilitychange', actualizar)
    window.addEventListener('focus', actualizar)
    const id = setInterval(actualizar, REFRESCO_MS)

    return () => {
      alCambiarPermisos(() => {})
      document.removeEventListener('visibilitychange', actualizar)
      window.removeEventListener('focus', actualizar)
      clearInterval(id)
    }
  }, [refrescar])

  const valor = useMemo(() => {
    const permisos = sesion?.permisos ?? []

    return {
      ...sesion,
      autenticado: Boolean(sesion),
      cargando,
      /** puede('usuarios.ver')  ·  puede('pagos.propios|pagos.registrar') -> basta uno */
      puede: (permiso) => !permiso || permiso.split('|').some((p) => permisos.includes(p)),
      esRol: (nombre) => sesion?.rolActivo?.nombre === nombre,

      async iniciarSesion(credenciales) {
        const { data } = await api.post('/auth/login', credenciales)
        aplicar(data)
      },

      async registrarse(datos) {
        const { data } = await api.post('/auth/registro', datos)
        aplicar(data)
      },

      async cerrarSesion() {
        try {
          await api.post('/auth/logout')
        } finally {
          sesionLocal.limpiar()
          setSesion(null)
        }
      },

      /** Cambia el rol con el que trabaja el usuario y recarga permisos y menú. */
      async cambiarRol(idRol) {
        sesionLocal.guardarRol(idRol)
        await refrescar()
      },

      refrescar,
    }
  }, [sesion, cargando, aplicar, refrescar])

  return <AuthContext.Provider value={valor}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export const useAuth = () => useContext(AuthContext)
