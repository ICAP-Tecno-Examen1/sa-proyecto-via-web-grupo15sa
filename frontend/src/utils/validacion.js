/**
 * Validación de formularios en el cliente (requisito 6).
 *
 * Cada regla recibe (valor, todosLosValores) y devuelve un mensaje en español
 * o null. El servidor vuelve a validar todo: esta capa solo evita viajes
 * innecesarios y guía al usuario mientras escribe.
 *
 * Uso:   const reglas = { correo: [requerido(), correo()], horas: [requerido(), entero(), entre(1, 1000)] }
 *        const errores = validar(valores, reglas)
 */

const vacio = (v) => v === null || v === undefined || v === '' || (Array.isArray(v) && v.length === 0)

export const requerido = (mensaje = 'Este campo es obligatorio.') => (v) => (vacio(v) ? mensaje : null)

export const requeridoSi = (condicion, mensaje = 'Este campo es obligatorio.') => (v, todos) =>
  condicion(todos) && vacio(v) ? mensaje : null

export const minimo = (n) => (v) => (!vacio(v) && String(v).trim().length < n ? `Debe tener al menos ${n} caracteres.` : null)

export const maximo = (n) => (v) => (!vacio(v) && String(v).length > n ? `No debe superar los ${n} caracteres.` : null)

export const correo = () => (v) =>
  !vacio(v) && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) ? 'Ingrese un correo electrónico válido.' : null

export const soloLetras = () => (v) =>
  !vacio(v) && !/^[\p{L}\s'.-]+$/u.test(v) ? 'Solo se permiten letras y espacios.' : null

export const codigo = () => (v) =>
  !vacio(v) && !/^[A-Za-z0-9._-]+$/.test(v) ? 'Use solo letras, números, puntos y guiones (sin espacios).' : null

export const documento = () => (v) =>
  !vacio(v) && !/^[A-Za-z0-9-]{4,30}$/.test(v) ? 'Ingrese un número de documento válido (sin espacios).' : null

export const telefono = () => (v) =>
  !vacio(v) && !/^[0-9+\s()-]{6,30}$/.test(v) ? 'Ingrese un teléfono válido (solo números, mínimo 6 dígitos).' : null

export const numero = () => (v) => (!vacio(v) && Number.isNaN(Number(v)) ? 'Debe ser un número.' : null)

export const entero = () => (v) => (!vacio(v) && !Number.isInteger(Number(v)) ? 'Debe ser un número entero.' : null)

export const entre = (min, max) => (v) =>
  !vacio(v) && (Number(v) < min || Number(v) > max) ? `Debe estar entre ${min} y ${max}.` : null

export const mayorQue = (n) => (v) => (!vacio(v) && Number(v) <= n ? `Debe ser mayor que ${n}.` : null)

export const noNegativo = () => (v) => (!vacio(v) && Number(v) < 0 ? 'No puede ser negativo.' : null)

export const fechaPasada = () => (v) =>
  !vacio(v) && new Date(v) >= new Date(new Date().toDateString()) ? 'Debe ser una fecha anterior a hoy.' : null

export const fechaNoFutura = () => (v) =>
  !vacio(v) && new Date(`${v}T00:00:00`) > new Date() ? 'No puede ser una fecha futura.' : null

export const posteriorA = (campo, etiqueta) => (v, todos) =>
  !vacio(v) && !vacio(todos[campo]) && v <= todos[campo] ? `Debe ser posterior a ${etiqueta}.` : null

export const noAnteriorA = (campo, etiqueta) => (v, todos) =>
  !vacio(v) && !vacio(todos[campo]) && v < todos[campo] ? `No puede ser anterior a ${etiqueta}.` : null

export const igualA = (campo, mensaje = 'Los valores no coinciden.') => (v, todos) => (v !== todos[campo] ? mensaje : null)

export const password = () => (v) => {
  if (vacio(v)) return null
  if (String(v).length < 8) return 'La contraseña debe tener al menos 8 caracteres.'
  if (!/[A-Za-z]/.test(v) || !/[0-9]/.test(v)) return 'La contraseña debe combinar letras y números.'

  return null
}

export const usuario = () => (v) =>
  !vacio(v) && !/^[A-Za-z0-9._-]{4,100}$/.test(v) ? 'Mínimo 4 caracteres: letras, números, puntos o guiones.' : null

export const archivo = (maxMb = 4, extensiones = ['pdf', 'jpg', 'jpeg', 'png', 'webp']) => (v) => {
  if (!(v instanceof File)) return null
  const ext = v.name.split('.').pop()?.toLowerCase()
  if (!extensiones.includes(ext)) return `Formato no permitido. Use: ${extensiones.join(', ')}.`
  if (v.size > maxMb * 1024 * 1024) return `El archivo no debe superar los ${maxMb} MB.`

  return null
}

/** Lee un valor con ruta de puntos: obtener(obj, 'pagador.nombres') */
export const obtener = (objeto, ruta) => ruta.split('.').reduce((o, clave) => o?.[clave], objeto)

/** Devuelve { campo: 'primer mensaje de error' } solo para los campos con error. */
export function validar(valores, reglas) {
  const errores = {}

  for (const [campo, lista] of Object.entries(reglas)) {
    for (const regla of lista) {
      const mensaje = regla(obtener(valores, campo), valores)

      if (mensaje) {
        errores[campo] = mensaje
        break
      }
    }
  }

  return errores
}
