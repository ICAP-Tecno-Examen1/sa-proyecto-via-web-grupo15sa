import {
  Award, Banknote, BookOpen, CalendarDays, ChartColumn, Circle, ClipboardList, CreditCard, FilePen, FileText, FolderOpen,
  GraduationCap, HandCoins, History, House, Layers, LayoutDashboard, Percent, Puzzle, ScrollText, Settings, ShieldCheck,
  TrendingUp, UserRound, Users, Wallet, Inbox, TriangleAlert, CircleCheck, Coins, Target, Eye,
} from 'lucide-react'

/**
 * El menú llega de la base de datos con el NOMBRE del icono (menu_item.icono);
 * aquí se traduce al dibujo. Un nombre desconocido usa un icono neutro.
 */
const ICONOS = {
  inicio: House,
  panel: LayoutDashboard,
  catalogo: BookOpen,
  estudiante: GraduationCap,
  solicitud: ClipboardList,
  carpeta: FolderOpen,
  libro: BookOpen,
  pago: Wallet,
  certificado: Award,
  ajustes: Settings,
  usuarios: Users,
  escudo: ShieldCheck,
  bitacora: ScrollText,
  tendencia: TrendingUp,
  modulo: Puzzle,
  malla: Layers,
  calendario: CalendarDays,
  matricula: FileText,
  arancel: Banknote,
  beca: Percent,
  tarjeta: CreditCard,
  docente: UserRound,
  correccion: FilePen,
  reporte: ChartColumn,
  // indicadores del panel
  ingresos: HandCoins,
  deuda: Coins,
  alerta: TriangleAlert,
  bandeja: Inbox,
  aprobado: CircleCheck,
  promedio: Target,
  visitas: Eye,
  historial: History,
}

export default function Icono({ nombre, className = 'size-4', ...resto }) {
  const Componente = ICONOS[nombre] ?? Circle

  return <Componente className={className} aria-hidden {...resto} />
}
