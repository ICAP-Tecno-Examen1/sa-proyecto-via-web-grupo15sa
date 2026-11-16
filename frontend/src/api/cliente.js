import axios from 'axios'

/**
 * Cliente HTTP de la capa de presentación.
 * Toda la comunicación con la lógica de negocio pasa por aquí (API RESTful, JSON).
 *
 *  - Authorization: Bearer <token>   sesión (token de Sanctum)
 *  - X-Rol-Activo: <id_rol>          rol con el que trabaja el usuario
 */
const CLAVE_TOKEN = 'icap.token'
const CLAVE_ROL = 'icap.rol'

export const sesionLocal = {
  token: () => localStorage.getItem(CLAVE_TOKEN),
  rol: () => localStorage.getItem(CLAVE_ROL),
  guardarToken: (token) => localStorage.setItem(CLAVE_TOKEN, token),
  guardarRol: (idRol) => localStorage.setItem(CLAVE_ROL, String(idRol)),
  limpiar: () => {
    localStorage.removeItem(CLAVE_TOKEN)
    localStorage.removeItem(CLAVE_ROL)
  },
}

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api',
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = sesionLocal.token()
  const rol = sesionLocal.rol()

  if (token) config.headers.Authorization = `Bearer ${token}`
  if (rol) config.headers['X-Rol-Activo'] = rol

  return config
})

// La sesión venció o la cuenta fue desactivada: se avisa a la aplicación.
let alExpirar = () => {}
export const alExpirarSesion = (fn) => {
  alExpirar = fn
}

api.interceptors.response.use(
  (respuesta) => respuesta,
  (error) => {
    if (error.response?.status === 401 && sesionLocal.token() && !error.config?.url?.includes('auth/login')) {
      sesionLocal.limpiar()
      alExpirar()
    }

    return Promise.reject(error)
  },
)

/** Mensaje legible de un error de la API (siempre en español). */
export function mensajeError(error, porDefecto = 'No se pudo completar la operación. Intente nuevamente.') {
  if (!error?.response) return 'No hay conexión con el servidor. Verifique su red e intente nuevamente.'

  return error.response.data?.message || porDefecto
}

/** Errores de validación del servidor (422) como { campo: 'mensaje' }. */
export function erroresDeValidacion(error) {
  const errores = error?.response?.status === 422 ? error.response.data?.errors : null
  if (!errores) return {}

  return Object.fromEntries(Object.entries(errores).map(([campo, mensajes]) => [campo, mensajes[0]]))
}

/** Convierte un objeto (con archivos y objetos anidados) en FormData. */
export function aFormData(datos) {
  const fd = new FormData()

  const agregar = (clave, valor) => {
    if (valor === null || valor === undefined || valor === '') return
    if (valor instanceof File) fd.append(clave, valor)
    else if (Array.isArray(valor)) valor.forEach((v, i) => agregar(`${clave}[${i}]`, v))
    else if (typeof valor === 'object') Object.entries(valor).forEach(([k, v]) => agregar(`${clave}[${k}]`, v))
    else fd.append(clave, typeof valor === 'boolean' ? (valor ? '1' : '0') : valor)
  }

  Object.entries(datos).forEach(([clave, valor]) => agregar(clave, valor))

  return fd
}

/** Descarga (o abre en otra pestaña) un archivo protegido por la sesión. */
export async function abrirArchivo(url, { params, descargar = false, nombre } = {}) {
  const respuesta = await api.get(url, { params, responseType: 'blob' })
  const enlace = URL.createObjectURL(respuesta.data)

  if (descargar) {
    const a = document.createElement('a')
    const disposicion = respuesta.headers['content-disposition'] || ''
    a.href = enlace
    a.download = nombre || /filename="?([^";]+)"?/.exec(disposicion)?.[1] || 'archivo'
    document.body.appendChild(a)
    a.click()
    a.remove()
  } else {
    window.open(enlace, '_blank', 'noopener')
  }

  setTimeout(() => URL.revokeObjectURL(enlace), 60_000)
}

export default api
