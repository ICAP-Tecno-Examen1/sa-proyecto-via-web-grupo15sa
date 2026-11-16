import { Accessibility, Clock, Contrast, Moon, Palette, RotateCcw, Sun, Type } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { ESCALA, TEMAS, usePreferencias } from '../../context/PreferenciasContext'

/**
 * Selector de tema y herramientas de accesibilidad (requisito 5):
 *   - 3 temas por segmento: Niños, Jóvenes, Adultos
 *   - Modo Día / Noche manual, o Automático según la hora
 *   - Deslizador de tamaño de letra y conmutador de alto contraste
 */
export default function ControlesVisuales() {
  const p = usePreferencias()
  const [abierto, setAbierto] = useState(false)
  const contenedor = useRef(null)

  useEffect(() => {
    const cerrar = (e) => !contenedor.current?.contains(e.target) && setAbierto(false)
    const escape = (e) => e.key === 'Escape' && setAbierto(false)
    document.addEventListener('mousedown', cerrar)
    document.addEventListener('keydown', escape)

    return () => {
      document.removeEventListener('mousedown', cerrar)
      document.removeEventListener('keydown', escape)
    }
  }, [])

  const opcion = (activa) =>
    `flex flex-1 items-center justify-center gap-1.5 rounded-tema border px-2 py-1.5 text-xs font-semibold transition ${
      activa ? 'border-primario bg-primario text-sobre-primario' : 'bg-tarjeta hover:bg-suave'
    }`

  const modos = [
    { clave: 'auto', nombre: 'Automático', Icono: Clock },
    { clave: 'dia', nombre: 'Día', Icono: Sun },
    { clave: 'noche', nombre: 'Noche', Icono: Moon },
  ]

  return (
    <div ref={contenedor} className="relative">
      <button
        onClick={() => setAbierto((a) => !a)}
        aria-expanded={abierto}
        aria-label="Temas y accesibilidad"
        title="Temas y accesibilidad"
        className="flex items-center gap-1.5 rounded-tema border bg-tarjeta px-2.5 py-2 text-sm font-semibold hover:bg-suave"
      >
        <Palette className="size-4" aria-hidden />
        <Accessibility className="size-4" aria-hidden />
        <span className="hidden lg:inline">Apariencia</span>
      </button>

      {abierto && (
        <div className="tarjeta absolute top-full right-0 z-40 mt-1 w-80 max-w-[calc(100vw-1.5rem)] space-y-4 p-4 shadow-xl" role="dialog" aria-label="Temas y accesibilidad">
          <fieldset>
            <legend className="mb-2 flex items-center gap-1.5 text-xs font-bold tracking-wide text-tenue uppercase">
              <Palette className="size-3.5" aria-hidden /> Tema
            </legend>
            <div className="space-y-1.5">
              {TEMAS.map((t) => (
                <button key={t.clave} onClick={() => p.cambiar({ tema: t.clave })} aria-pressed={p.tema === t.clave}
                  className={`flex w-full items-center justify-between rounded-tema border px-3 py-2 text-left ${p.tema === t.clave ? 'border-primario bg-primario-suave' : 'hover:bg-suave'}`}>
                  <span>
                    <span className="block text-sm font-bold">{t.nombre}</span>
                    <span className="block text-xs text-tenue">{t.detalle}</span>
                  </span>
                  {p.tema === t.clave && <span className="text-xs font-bold text-primario">Activo</span>}
                </button>
              ))}
            </div>
          </fieldset>

          <fieldset>
            <legend className="mb-2 text-xs font-bold tracking-wide text-tenue uppercase">Modo</legend>
            <div className="flex gap-1.5">
              {modos.map(({ clave, nombre, Icono }) => (
                <button key={clave} onClick={() => p.cambiar({ modo: clave })} aria-pressed={p.modo === clave} className={opcion(p.modo === clave)}>
                  <Icono className="size-3.5" aria-hidden /> {nombre}
                </button>
              ))}
            </div>
            {p.modo === 'auto' && (
              <p className="mt-1.5 text-xs text-tenue">
                Según la hora: ahora se muestra el modo <strong>{p.modoEfectivo === 'noche' ? 'noche' : 'día'}</strong> (noche de 19:00 a 06:59).
              </p>
            )}
          </fieldset>

          <fieldset>
            <legend className="mb-2 flex items-center gap-1.5 text-xs font-bold tracking-wide text-tenue uppercase">
              <Type className="size-3.5" aria-hidden /> Tamaño de letra · {Math.round(p.escala * 100)}%
            </legend>
            <div className="flex items-center gap-2">
              <button className={opcion(false) + ' flex-none px-3'} aria-label="Reducir letra" onClick={() => p.cambiar({ escala: Math.max(ESCALA.min, +(p.escala - ESCALA.paso).toFixed(2)) })}>A−</button>
              <input type="range" min={ESCALA.min} max={ESCALA.max} step={ESCALA.paso} value={p.escala} aria-label="Tamaño de letra"
                onChange={(e) => p.cambiar({ escala: Number(e.target.value) })} className="flex-1 accent-[var(--primario)]" />
              <button className={opcion(false) + ' flex-none px-3 text-base'} aria-label="Aumentar letra" onClick={() => p.cambiar({ escala: Math.min(ESCALA.max, +(p.escala + ESCALA.paso).toFixed(2)) })}>A+</button>
            </div>
          </fieldset>

          <div className="flex gap-1.5">
            <button onClick={() => p.cambiar({ contraste: p.contraste === 'alto' ? 'normal' : 'alto' })} aria-pressed={p.contraste === 'alto'} className={opcion(p.contraste === 'alto')}>
              <Contrast className="size-3.5" aria-hidden /> Alto contraste
            </button>
            <button onClick={p.restablecer} className={opcion(false)}>
              <RotateCcw className="size-3.5" aria-hidden /> Restablecer
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
