import {
  correo, documento, fechaPasada, igualA, maximo, minimo, password, requerido, soloLetras, telefono, usuario,
} from '../../utils/validacion'

/**
 * Definición única del formulario de datos personales (CU1). La comparten el
 * registro público, la gestión de usuarios, el registro de estudiantes y el perfil.
 */
export const TIPOS_DOCUMENTO = [
  { valor: 'CI', etiqueta: 'Cédula de identidad' },
  { valor: 'PASAPORTE', etiqueta: 'Pasaporte' },
  { valor: 'CARNET_EXTRANJERO', etiqueta: 'Carnet de extranjero' },
]

export const personaInicial = {
  nombres: '', apellidos: '', tipo_documento: 'CI', ci: '', correo: '', telefono: '', direccion: '', fecha_nacimiento: '', lugar_nacimiento: '',
  username: '', password: '', password_confirmation: '',
}

export const camposPersonales = [
  { nombre: 'nombres', etiqueta: 'Nombres', requerido: true, autoComplete: 'given-name' },
  { nombre: 'apellidos', etiqueta: 'Apellidos', requerido: true, autoComplete: 'family-name' },
  { nombre: 'tipo_documento', etiqueta: 'Tipo de documento', tipo: 'select', opciones: TIPOS_DOCUMENTO, vacio: null, requerido: true },
  { nombre: 'ci', etiqueta: 'Número de documento', requerido: true },
  { nombre: 'correo', etiqueta: 'Correo electrónico', tipo: 'email', requerido: true, autoComplete: 'email' },
  { nombre: 'telefono', etiqueta: 'Teléfono', tipo: 'tel', autoComplete: 'tel' },
  { nombre: 'fecha_nacimiento', etiqueta: 'Fecha de nacimiento', tipo: 'date' },
  { nombre: 'lugar_nacimiento', etiqueta: 'Lugar de nacimiento', ayuda: 'Ej.: Santa Cruz - Bolivia. Figura en el certificado.' },
  { nombre: 'direccion', etiqueta: 'Dirección' },
]

export const camposAcceso = [
  { nombre: 'username', etiqueta: 'Nombre de usuario', requerido: true, autoComplete: 'username', ayuda: 'Con él iniciará sesión.' },
  { nombre: 'password', etiqueta: 'Contraseña', tipo: 'password', autoComplete: 'new-password', ayuda: 'Mínimo 8 caracteres, con letras y números.' },
  { nombre: 'password_confirmation', etiqueta: 'Repetir contraseña', tipo: 'password', autoComplete: 'new-password' },
]

export const reglasPersonales = {
  nombres: [requerido(), minimo(2), maximo(150), soloLetras()],
  apellidos: [requerido(), minimo(2), maximo(150), soloLetras()],
  tipo_documento: [requerido()],
  ci: [requerido(), documento()],
  correo: [requerido(), correo(), maximo(150)],
  telefono: [telefono()],
  direccion: [maximo(300)],
  fecha_nacimiento: [fechaPasada()],
  lugar_nacimiento: [maximo(150)],
}

/** Al editar, la contraseña es opcional (solo se cambia si se escribe una nueva). */
export const reglasConAcceso = (editando = false) => ({
  ...reglasPersonales,
  username: [requerido(), usuario()],
  password: editando ? [password()] : [requerido(), password()],
  password_confirmation: [(v, todos) => (todos.password ? igualA('password', 'Las contraseñas no coinciden.')(v, todos) : null)],
})

/** Quita lo que no debe viajar vacío (contraseña sin cambios, fechas en blanco). */
export function limpiarPersona(valores) {
  const datos = { ...valores }
  if (!datos.password) {
    delete datos.password
    delete datos.password_confirmation
  }
  for (const campo of ['telefono', 'direccion', 'fecha_nacimiento', 'lugar_nacimiento']) if (datos[campo] === '') datos[campo] = null

  return datos
}
