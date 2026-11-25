import {
  Award, BookOpen, Briefcase, Building2, ClipboardCheck, Cpu, GraduationCap, Laptop, MonitorSmartphone,
  Presentation, QrCode, ShieldCheck, Sparkles, UserRoundCheck, Users, Wallet, Wrench,
} from 'lucide-react'

/*
 * TEXTOS DE LA PORTADA
 * --------------------
 * Todo lo que se lee en la página de inicio está aquí. Para cambiar un texto,
 * un paso o una pestaña, edite este archivo: los componentes solo lo dibujan.
 */

export const PORTADA = {
  antetitulo: 'Formación Técnica Universitaria',
  titulo: 'Aprende una profesión técnica que',
  resaltado: 'cambiará tu vida.',
  descripcion:
    'Cursos, talleres, diplomados y carreras técnicas de la UAGRM. Inscríbete, paga y sigue tu avance desde un solo lugar.',
  accionPrincipal: 'Empieza hoy',
  accionSecundaria: 'Ver programas',
  garantias: [
    { Icono: QrCode, texto: 'Pago con QR' },
    { Icono: ShieldCheck, texto: 'Certificado verificable' },
    { Icono: Laptop, texto: 'Trámites 100 % en línea' },
  ],
}

/** Frases de la cinta cuando todavía no hay ofertas abiertas. */
export const FRASES_CINTA = ['Cursos', 'Talleres', 'Seminarios', 'Diplomados', 'Técnico Medio', 'Técnico Superior', 'Programas técnicos']

/** Ruta de aprendizaje (la "senda" de la portada). El último paso se dibuja como trofeo. */
export const PASOS = [
  { Icono: BookOpen, titulo: 'Elige tu programa', texto: 'Compara costos, duración, modalidad y módulos sin crear una cuenta.' },
  { Icono: UserRoundCheck, titulo: 'Preinscríbete', texto: 'Regístrate y adjunta tu CI, título y fotografía una sola vez.' },
  { Icono: Wallet, titulo: 'Paga en línea', texto: 'Con QR o subiendo tu comprobante. Tu matrícula se activa al confirmarse.' },
  { Icono: ClipboardCheck, titulo: 'Aprende y aprueba', texto: 'Consulta tu asistencia, tus notas y el avance de cada módulo.' },
  { Icono: Award, titulo: 'Obtén tu certificado', texto: 'Digital, con código único y QR que cualquier persona puede verificar.' },
]

/** Pestañas "Una plataforma para todos". */
export const PERFILES = [
  {
    clave: 'estudiantes',
    etiqueta: 'Estudiantes',
    Icono: GraduationCap,
    titulo: 'Todo tu programa en un solo lugar',
    puntos: [
      'Catálogo con costos, horarios y cupos disponibles',
      'Preinscripción en línea y expediente digital',
      'Pago con QR o comprobante, con plan de cuotas',
      'Notas, asistencia y certificados con QR',
    ],
  },
  {
    clave: 'docentes',
    etiqueta: 'Docentes',
    Icono: Presentation,
    titulo: 'Menos papeleo, más enseñanza',
    puntos: [
      'Tus módulos y estudiantes asignados',
      'Registro de asistencia por fecha',
      'Evaluaciones ponderadas y planilla de notas',
      'Solicitud de corrección de notas con seguimiento',
    ],
  },
  {
    clave: 'administracion',
    etiqueta: 'Administración',
    Icono: Building2,
    titulo: 'Control académico y financiero',
    puntos: [
      'Programas, mallas, ofertas y asignación docente',
      'Matrículas, becas y conciliación de pagos',
      'Reportes, estadísticas y bitácora de auditoría',
      'Roles y permisos configurables',
    ],
  },
]

/** Ícono de cada tipo de programa (asistente de la portada). */
export const ICONO_TIPO = {
  CURSO: BookOpen,
  TALLER: Wrench,
  SEMINARIO: Presentation,
  DIPLOMADO: GraduationCap,
  TECNICO_MEDIO: Cpu,
  TECNICO_SUPERIOR: Briefcase,
  PROGRAMA_TECNICO: Award,
}

/** Ícono de cada modalidad. */
export const ICONO_MODALIDAD = {
  PRESENCIAL: Users,
  VIRTUAL: MonitorSmartphone,
  SEMIPRESENCIAL: Laptop,
}

export const ICONO_CUALQUIERA = Sparkles
