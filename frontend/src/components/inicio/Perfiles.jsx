import { CircleCheck } from 'lucide-react'
import { useState } from 'react'
import { PERFILES } from './contenido'

/** Pestañas "Una plataforma para todos": qué hace el sistema para cada tipo de usuario. */
export default function Perfiles() {
  const [activa, setActiva] = useState(PERFILES[0].clave)
  const perfil = PERFILES.find((p) => p.clave === activa)

  return (
    <div>
      <div role="tablist" aria-label="Tipo de usuario" className="mx-auto flex w-fit flex-wrap justify-center gap-1 rounded-full border-2 bg-tarjeta p-1.5">
        {PERFILES.map(({ clave, etiqueta, Icono }) => (
          <button
            key={clave}
            role="tab"
            id={`pestana-${clave}`}
            aria-selected={activa === clave}
            aria-controls="panel-perfil"
            onClick={() => setActiva(clave)}
            className={`flex items-center gap-2 rounded-full px-4 py-2 text-sm font-bold transition-colors sm:px-5 ${activa === clave ? 'bg-primario text-sobre-primario' : 'text-tenue hover:text-texto'}`}
          >
            <Icono className="size-4" aria-hidden /> {etiqueta}
          </button>
        ))}
      </div>

      <div id="panel-perfil" role="tabpanel" aria-labelledby={`pestana-${activa}`} key={activa} className="animar-subir mt-10 grid items-center gap-10 lg:grid-cols-2">
        <div>
          <h3 className="text-2xl font-black sm:text-3xl">{perfil.titulo}</h3>
          <ul className="mt-6 space-y-3">
            {perfil.puntos.map((punto, i) => (
              <li key={punto} className="animar-subir flex items-start gap-3" style={{ animationDelay: `${0.08 * (i + 1)}s` }}>
                <CircleCheck className="mt-0.5 size-5 shrink-0 text-acento" aria-hidden />
                <span>{punto}</span>
              </li>
            ))}
          </ul>
        </div>
        <Ilustracion clave={activa} />
      </div>
    </div>
  )
}

/** Pantalla de ejemplo dibujada con bloques (no son datos reales). */
function Ilustracion({ clave }) {
  return (
    <div className="tarjeta animar-crecer overflow-hidden p-0 shadow-xl" aria-hidden>
      <div className="flex items-center gap-1.5 border-b bg-suave px-4 py-2.5">
        <span className="size-2.5 rounded-full bg-acento" />
        <span className="size-2.5 rounded-full bg-borde" />
        <span className="size-2.5 rounded-full bg-borde" />
      </div>
      <div className="p-5">
        {clave === 'estudiantes' && (
          <div className="space-y-4">
            {[['Módulo 1', 100], ['Módulo 2', 70], ['Módulo 3', 35]].map(([m, v]) => (
              <div key={m}>
                <div className="flex justify-between text-sm font-semibold"><span>{m}</span><span className="text-tenue">{v} %</span></div>
                <div className="mt-1.5 h-3 overflow-hidden rounded-full bg-suave">
                  <div className={`h-full rounded-full ${v === 100 ? 'bg-exito' : 'bg-primario'}`} style={{ width: `${v}%` }} />
                </div>
              </div>
            ))}
            <div className="flex items-center gap-3 rounded-xl border-2 border-dashed p-3">
              <span className="grid size-10 place-items-center rounded-lg bg-acento-suave font-black text-acento">QR</span>
              <div className="flex-1 space-y-1.5"><div className="h-2.5 w-2/3 rounded bg-suave" /><div className="h-2.5 w-1/3 rounded bg-suave" /></div>
            </div>
          </div>
        )}

        {clave === 'docentes' && (
          <div className="grid grid-cols-[1fr_repeat(3,3rem)] gap-2 text-sm">
            {['Estudiante', 'P1', 'P2', 'Final'].map((c) => <span key={c} className="font-bold text-tenue">{c}</span>)}
            {[0, 1, 2, 3].map((fila) => (
              <div key={fila} className="contents">
                <span className="h-3 self-center rounded bg-suave" style={{ width: `${85 - fila * 12}%` }} />
                {[0, 1, 2].map((c) => <span key={c} className={`grid h-8 place-items-center rounded-lg font-bold ${c === 2 ? 'bg-primario-suave text-primario' : 'bg-suave'}`}>{c === 2 ? '✓' : ''}</span>)}
              </div>
            ))}
          </div>
        )}

        {clave === 'administracion' && (
          <div>
            <div className="flex h-40 items-end gap-3">
              {[45, 70, 55, 90, 65, 80].map((h, i) => (
                <div key={i} className={`flex-1 rounded-t-lg ${i === 3 ? 'bg-acento' : 'bg-primario'}`} style={{ height: `${h}%` }} />
              ))}
            </div>
            <div className="mt-4 grid grid-cols-3 gap-2">
              {[0, 1, 2].map((i) => <div key={i} className="h-12 rounded-lg bg-suave" />)}
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
