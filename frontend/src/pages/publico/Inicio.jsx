import { ArrowRight, QrCode } from 'lucide-react'
import TarjetaOferta from '../../components/catalogo/TarjetaOferta'
import Cifras from '../../components/inicio/Cifras'
import Cinta from '../../components/inicio/Cinta'
import { PORTADA } from '../../components/inicio/contenido'
import Perfiles from '../../components/inicio/Perfiles'
import Senda from '../../components/inicio/Senda'
import LogoPortada from '../../components/inicio/LogoPortada'
import Logo from '../../components/marca/Logo'
import { Estado, Vacio } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import Revelar from '../../components/ui/Revelar'
import { useAuth } from '../../context/AuthContext'
import { useConsulta } from '../../hooks/useConsulta'

/**
 * Portada del sitio. Ocupa todo el ancho (ruta con "pantallaCompleta" en rutas.js).
 * Los textos están en components/inicio/contenido.js y los estilos en styles/index.css.
 */
export default function Inicio() {
  const auth = useAuth()
  const catalogo = useConsulta('/publico/catalogo')
  const ofertas = catalogo.datos?.ofertas

  return (
    <>
      {/* 1. PORTADA: mensaje + logo de la institución */}
      <section className="lienzo-marca overflow-hidden">
        <div className="contenedor grid items-center gap-12 py-14 sm:py-20 lg:grid-cols-[1.1fr_1fr] lg:py-24">
          <div className="animar-subir">
            <p className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-xs font-bold tracking-widest uppercase">
              <span className="size-2 rounded-full bg-acento" aria-hidden /> {PORTADA.antetitulo}
            </p>
            <h1 className="mt-6 text-4xl leading-[1.05] font-black sm:text-6xl">
              {PORTADA.titulo} <span className="resaltado">{PORTADA.resaltado}</span>
            </h1>
            <p className="mt-6 max-w-xl text-base opacity-85 sm:text-lg">{PORTADA.descripcion}</p>

            <div className="mt-9 flex flex-wrap gap-3">
              <Boton a={auth.autenticado ? '/panel' : '/registro'} variante="acento" tamano="lg">
                {auth.autenticado ? 'Ir a mi panel' : PORTADA.accionPrincipal} <ArrowRight className="size-5" aria-hidden />
              </Boton>
              <Boton a="/catalogo" variante="contorno" tamano="lg">{PORTADA.accionSecundaria}</Boton>
            </div>

            <ul className="mt-10 flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold opacity-85">
              {PORTADA.garantias.map(({ Icono, texto }) => (
                <li key={texto} className="flex items-center gap-2"><Icono className="size-4 text-acento" aria-hidden /> {texto}</li>
              ))}
            </ul>
          </div>

          <div className="animar-crecer [animation-delay:0.15s]">
            <LogoPortada />
          </div>
        </div>
      </section>

      {/* 2. CINTA con los programas abiertos */}
      <Cinta ofertas={ofertas} />

      {/* 3. RUTA DE APRENDIZAJE */}
      <section className="contenedor py-20 sm:py-24" aria-labelledby="titulo-ruta">
        <Revelar className="mx-auto max-w-2xl text-center">
          <p className="text-sm font-bold tracking-widest text-acento uppercase">Tu ruta</p>
          <h2 id="titulo-ruta" className="mt-2 text-3xl font-black sm:text-4xl">Del interés al certificado, paso a paso</h2>
          <p className="mt-3 text-tenue">Cada etapa se hace en línea y siempre sabes en qué punto estás.</p>
        </Revelar>
        <Senda />
      </section>

      {/* 4. CIFRAS reales del catálogo (solo si hay ofertas: mostrar ceros no motiva a nadie) */}
      {ofertas?.length > 0 && (
        <section className="lienzo-marca py-16" aria-label="El ICAP en cifras">
          <div className="contenedor">
            <Cifras ofertas={ofertas} />
          </div>
        </section>
      )}

      {/* 5. UNA PLATAFORMA PARA TODOS */}
      <section className="contenedor py-20 sm:py-24" aria-labelledby="titulo-perfiles">
        <Revelar className="mx-auto mb-10 max-w-2xl text-center">
          <p className="text-sm font-bold tracking-widest text-acento uppercase">Una plataforma para todos</p>
          <h2 id="titulo-perfiles" className="mt-2 text-3xl font-black sm:text-4xl">Estudiantes, docentes y administración, conectados</h2>
        </Revelar>
        <Perfiles />
      </section>

      {/* 6. OFERTAS ABIERTAS */}
      <section className="border-t bg-tarjeta py-20" aria-labelledby="titulo-ofertas">
        <div className="contenedor">
          <Revelar className="mb-10 flex flex-wrap items-end justify-between gap-4">
            <div>
              <p className="text-sm font-bold tracking-widest text-acento uppercase">Inscripciones abiertas</p>
              <h2 id="titulo-ofertas" className="mt-2 text-3xl font-black sm:text-4xl">Próximos inicios</h2>
            </div>
            <Boton a="/catalogo" variante="secundario">Ver todo el catálogo <ArrowRight className="size-4" aria-hidden /></Boton>
          </Revelar>
          <Estado consulta={catalogo}>
            {({ ofertas: lista }) => lista.length === 0
              ? <div className="tarjeta"><Vacio titulo="No hay ofertas abiertas en este momento">Vuelve pronto: publicamos nuevas ofertas cada gestión.</Vacio></div>
              : (
                <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                  {lista.slice(0, 3).map((o, i) => <Revelar key={o.id_oferta} retardo={i * 0.1} className="flex"><TarjetaOferta oferta={o} /></Revelar>)}
                </div>
              )}
          </Estado>
        </div>
      </section>

      {/* 7. LLAMADA FINAL */}
      <section className="contenedor py-20">
        <Revelar className="lienzo-marca relative overflow-hidden rounded-[calc(var(--radio)*1.5)] px-6 py-12 text-center sm:px-12 sm:py-16">
          <Logo variante="marca" placa className="mx-auto h-16 w-auto" />
          <h2 className="mx-auto mt-6 max-w-2xl text-3xl font-black sm:text-4xl">Tu próximo paso empieza hoy</h2>
          <p className="mx-auto mt-3 max-w-xl opacity-85">Crea tu cuenta en un minuto y preinscríbete en el programa que elijas.</p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            {!auth.autenticado && <Boton a="/registro" variante="acento" tamano="lg">Crear mi cuenta gratis</Boton>}
            <Boton a="/verificar" variante="claro" tamano="lg" icono={QrCode}>Verificar un certificado</Boton>
          </div>
        </Revelar>
      </section>
    </>
  )
}
