import Logo from '../marca/Logo'

/**
 * Portada: el logo oficial del ICAP sobre una placa blanca (sobre el fondo azul las
 * letras azules del logo no se verían), con anillos decorativos y una leve flotación.
 */
export default function LogoPortada() {
  return (
    <div className="relative mx-auto grid aspect-square w-full max-w-[26rem] place-items-center">
      {/* Anillos y resplandor decorativos */}
      <span className="absolute inset-0 rounded-full border border-white/15" aria-hidden />
      <span className="absolute inset-[9%] rounded-full border border-dashed border-white/20" aria-hidden />
      <span className="absolute inset-[18%] rounded-full bg-white/10 blur-2xl" aria-hidden />

      <div className="animar-flotar relative rounded-[2rem] bg-white p-7 shadow-[0_30px_60px_-15px_rgba(0,0,0,0.55)] ring-4 ring-white/30 sm:p-9">
        <Logo variante="completo" className="h-auto w-56 sm:w-64" />
      </div>
    </div>
  )
}
