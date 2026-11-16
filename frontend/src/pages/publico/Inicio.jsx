import { Award, BookOpen, QrCode, Wallet } from 'lucide-react'
import TarjetaOferta from '../../components/catalogo/TarjetaOferta'
import { Estado, Vacio } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import { useAuth } from '../../context/AuthContext'
import { useConsulta } from '../../hooks/useConsulta'

const PASOS = [
  { Icono: BookOpen, titulo: '1. Elige tu programa', texto: 'Revisa el catálogo: costos, duración, modalidad y módulos.' },
  { Icono: Award, titulo: '2. Preinscríbete', texto: 'Regístrate, completa tus datos y adjunta tus documentos una sola vez.' },
  { Icono: Wallet, titulo: '3. Paga en línea', texto: 'Con QR o subiendo tu comprobante. Tu matrícula se activa al confirmarse.' },
  { Icono: QrCode, titulo: '4. Obtén tu certificado', texto: 'Digital, con código único y QR verificable por cualquier persona.' },
]

/** Página principal del sitio. */
export default function Inicio() {
  const auth = useAuth()
  const catalogo = useConsulta('/publico/catalogo')

  return (
    <div className="space-y-10">
      <section className="tarjeta overflow-hidden bg-primario p-8 text-sobre-primario sm:p-12">
        <p className="text-sm font-bold tracking-widest uppercase opacity-80">Universidad Autónoma Gabriel René Moreno</p>
        <h1 className="mt-2 max-w-3xl text-3xl leading-tight font-extrabold sm:text-5xl">Instituto de Capacitación Popular</h1>
        <p className="mt-4 max-w-2xl text-base opacity-90 sm:text-lg">
          Capacítate con programas cortos y certificados. Inscríbete, paga y sigue tus notas desde un solo lugar.
        </p>
        <div className="mt-6 flex flex-wrap gap-3">
          <Boton a="/catalogo" tamano="lg" className="!bg-sobre-primario !text-primario">Ver catálogo de programas</Boton>
          {auth.autenticado
            ? <Boton a="/panel" tamano="lg" variante="secundario" className="!border-sobre-primario !bg-transparent !text-sobre-primario">Ir a mi panel</Boton>
            : <Boton a="/registro" tamano="lg" variante="secundario" className="!border-sobre-primario !bg-transparent !text-sobre-primario">Crear mi cuenta</Boton>}
        </div>
      </section>

      <section aria-labelledby="como-funciona">
        <h2 id="como-funciona" className="mb-4 text-xl font-bold">¿Cómo funciona?</h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {PASOS.map(({ Icono, titulo, texto }) => (
            <div key={titulo} className="tarjeta p-5">
              <span className="mb-3 grid size-11 place-items-center rounded-tema bg-primario-suave text-primario"><Icono className="size-6" aria-hidden /></span>
              <h3 className="font-bold">{titulo}</h3>
              <p className="mt-1 text-sm text-tenue">{texto}</p>
            </div>
          ))}
        </div>
      </section>

      <section aria-labelledby="ofertas-abiertas">
        <div className="mb-4 flex flex-wrap items-end justify-between gap-2">
          <h2 id="ofertas-abiertas" className="text-xl font-bold">Inscripciones abiertas</h2>
          <Boton a="/catalogo" variante="fantasma">Ver todo el catálogo</Boton>
        </div>
        <Estado consulta={catalogo}>
          {({ ofertas }) => ofertas.length === 0
            ? <div className="tarjeta"><Vacio titulo="No hay ofertas abiertas en este momento">Vuelve pronto: publicamos nuevas ofertas cada gestión.</Vacio></div>
            : <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{ofertas.slice(0, 3).map((o) => <TarjetaOferta key={o.id_oferta} oferta={o} />)}</div>}
        </Estado>
      </section>

      <section className="tarjeta flex flex-wrap items-center justify-between gap-4 p-6">
        <div>
          <h2 className="text-lg font-bold">¿Tienes un certificado del ICAP?</h2>
          <p className="text-sm text-tenue">Comprueba su autenticidad con el código único o escaneando su QR. No necesitas cuenta.</p>
        </div>
        <Boton a="/verificar" icono={QrCode} variante="secundario">Verificar certificado</Boton>
      </section>
    </div>
  )
}
