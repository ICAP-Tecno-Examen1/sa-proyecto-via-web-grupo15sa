// Formato de valores para mostrar (Bolivia, español).

const numero = new Intl.NumberFormat('es-BO', { maximumFractionDigits: 2 })
const dinero = new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

export const moneda = (valor) => `Bs. ${dinero.format(Number(valor ?? 0))}`
export const cantidad = (valor) => numero.format(Number(valor ?? 0))
export const porcentaje = (valor) => (valor === null || valor === undefined ? '—' : `${numero.format(Number(valor))}%`)

/** '2026-11-15' -> '15/11/2026' (sin pasar por zonas horarias). */
export function fecha(valor) {
  if (!valor) return '—'
  const [a, m, d] = String(valor).slice(0, 10).split('-')

  return `${d}/${m}/${a}`
}

export function fechaHora(valor) {
  if (!valor) return '—'

  return `${fecha(valor)} ${String(valor).slice(11, 16)}`
}

export const hoy = () => {
  const d = new Date()

  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

/** PENDIENTE_PAGO -> 'Pendiente pago' */
// Palabras que los códigos de la API guardan sin tilde (TECNICO_MEDIO → «Técnico medio»).
const TILDES = { tecnico: 'técnico', academico: 'académico', unico: 'único', electronico: 'electrónico' }

export function legible(valor) {
  if (!valor) return '—'
  const texto = String(valor).replaceAll('_', ' ').toLowerCase().replace(/[a-z]+/g, (p) => TILDES[p] ?? p)

  return texto.charAt(0).toUpperCase() + texto.slice(1)
}

export function tamanoArchivo(bytes) {
  if (!bytes) return '—'
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`

  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

/** Tono visual de cada estado del sistema (para <Insignia>). */
const TONOS = {
  exito: ['ACTIVO', 'ACTIVA', 'APROBADO', 'APROBADA', 'CONFIRMADO', 'PAGADA', 'EMITIDO', 'ABIERTA', 'VIGENTE', 'PRESENTE', 'LOGIN_EXITOSO', 'REAL'],
  alerta: ['PENDIENTE', 'PENDIENTE_PAGO', 'OBSERVADO', 'OBSERVADA', 'PLANIFICADA', 'TARDANZA', 'LICENCIA', 'VENCIDA', 'ACCION'],
  peligro: ['RECHAZADO', 'RECHAZADA', 'REPROBADO', 'ANULADA', 'ANULADO', 'CANCELADA', 'INACTIVO', 'AUSENTE', 'REVOCADA', 'LOGIN_FALLIDO', 'ACCESO_DENEGADO'],
  informacion: ['EN_CURSO', 'INSCRITO', 'FINALIZADA', 'CERRADO', 'ARCHIVADO', 'LOGOUT', 'RETIRADO'],
}

export function tonoDeEstado(estado) {
  return Object.keys(TONOS).find((tono) => TONOS[tono].includes(estado)) ?? 'neutro'
}

/** Texto corto de un plan de pago (arancel). */
export const describirPlan = (plan) =>
  plan.tipo_cobro === 'MENSUALIDAD'
    ? `${plan.numero_cuotas} cuotas de ${moneda(plan.monto / plan.numero_cuotas)} aprox.`
    : 'Un solo pago'
