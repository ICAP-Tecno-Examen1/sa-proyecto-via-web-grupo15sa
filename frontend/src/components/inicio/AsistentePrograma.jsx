import { ArrowRight, Check, RotateCcw, X } from 'lucide-react'
import { useMemo, useState } from 'react'
import { legible } from '../../utils/formato'
import Boton from '../ui/Boton'
import { ICONO_CUALQUIERA, ICONO_MODALIDAD, ICONO_TIPO } from './contenido'

const CUALQUIERA = '*'

/** Opciones de una pregunta: solo las que tienen ofertas abiertas, con su cantidad. */
function opcionesDe(ofertas, obtener, iconos) {
  const conteo = new Map()
  ofertas.forEach((o) => conteo.set(obtener(o), (conteo.get(obtener(o)) ?? 0) + 1))

  return [...conteo.entries()]
    .sort((a, b) => b[1] - a[1])
    .map(([valor, cantidad]) => ({ valor, etiqueta: legible(valor), cantidad, Icono: iconos[valor] ?? ICONO_CUALQUIERA }))
}

/**
 * Asistente de la portada: dos preguntas, al estilo de una lección interactiva,
 * que filtran las ofertas abiertas reales y llevan al catálogo ya filtrado.
 */
export default function AsistentePrograma({ ofertas, cargando }) {
  const [paso, setPaso] = useState(0)
  const [tipo, setTipo] = useState(null)
  const [modalidad, setModalidad] = useState(null)

  const lista = useMemo(() => ofertas ?? [], [ofertas])
  const porTipo = useMemo(() => lista.filter((o) => !tipo || tipo === CUALQUIERA || o.programa.tipo === tipo), [lista, tipo])
  const resultado = useMemo(
    () => porTipo.filter((o) => !modalidad || modalidad === CUALQUIERA || o.modalidad === modalidad),
    [porTipo, modalidad],
  )

  const preguntas = [
    {
      texto: '¿Qué tipo de formación buscas?',
      opciones: opcionesDe(lista, (o) => o.programa.tipo, ICONO_TIPO),
      valor: tipo,
      elegir: (v) => { setTipo(v); setModalidad(null) },
    },
    {
      texto: '¿Cómo prefieres estudiar?',
      opciones: opcionesDe(porTipo, (o) => o.modalidad, ICONO_MODALIDAD),
      valor: modalidad,
      elegir: setModalidad,
    },
  ]

  const reiniciar = () => { setPaso(0); setTipo(null); setModalidad(null) }
  const filtro = new URLSearchParams()
  if (tipo && tipo !== CUALQUIERA) filtro.set('tipo', tipo)
  if (modalidad && modalidad !== CUALQUIERA) filtro.set('modalidad', modalidad)
  const consulta = filtro.toString()
  const enlace = `/catalogo${consulta ? `?${consulta}` : ''}`

  return (
    <div className="tarjeta relative w-full overflow-hidden p-5 text-texto shadow-2xl sm:p-6">
      {/* Barra de progreso, como en una lección */}
      <div className="flex items-center gap-3">
        <button type="button" onClick={reiniciar} aria-label="Reiniciar el asistente" className="rounded-full p-1 text-tenue hover:bg-suave">
          {paso === 0 ? <X className="size-5" aria-hidden /> : <RotateCcw className="size-5" aria-hidden />}
        </button>
        <div className="h-3.5 flex-1 overflow-hidden rounded-full bg-suave" role="progressbar" aria-valuemin={0} aria-valuemax={2} aria-valuenow={paso} aria-label="Avance del asistente">
          <div className="h-full rounded-full bg-acento transition-[width] duration-500 ease-out" style={{ width: `${Math.max(8, (paso / 2) * 100)}%` }}>
            <div className="mx-2 mt-1 h-1 rounded-full bg-white/35" />
          </div>
        </div>
      </div>

      {cargando && !ofertas && <Esqueleto />}

      {!cargando && !ofertas && (
        <div className="py-8 text-center">
          <p className="text-lg font-bold">No pudimos cargar las ofertas</p>
          <p className="mt-1 text-sm text-tenue">Puedes revisar el catálogo completo directamente.</p>
          <Boton a="/catalogo" variante="acento" className="mt-5">Ir al catálogo</Boton>
        </div>
      )}

      {ofertas && lista.length === 0 && (
        <div className="py-8 text-center">
          <p className="text-lg font-bold">Pronto abriremos nuevas inscripciones</p>
          <p className="mt-1 text-sm text-tenue">Crea tu cuenta y estarás listo para preinscribirte apenas se publiquen.</p>
          <Boton a="/registro" variante="acento" className="mt-5">Crear mi cuenta</Boton>
        </div>
      )}

      {lista.length > 0 && paso < 2 && (
        <Pregunta key={paso} pregunta={preguntas[paso]} alContinuar={() => setPaso(paso + 1)} />
      )}

      {lista.length > 0 && paso === 2 && (
        <div key="resultado" className="animar-subir pt-6 text-center">
          <span className="animar-rebote mx-auto grid size-16 place-items-center rounded-full bg-exito text-white">
            <Check className="size-9" strokeWidth={3} aria-hidden />
          </span>
          <p className="mt-4 text-xl font-extrabold">
            {resultado.length === 1 ? '¡Encontramos 1 programa para ti!' : `¡Encontramos ${resultado.length} programas para ti!`}
          </p>
          <ul className="mx-auto mt-3 max-w-xs space-y-1 text-sm text-tenue">
            {resultado.slice(0, 3).map((o) => <li key={o.id_oferta} className="truncate">• {o.programa.nombre}</li>)}
          </ul>
          <Boton a={enlace} variante="acento" tamano="lg" className="mt-6 w-full">
            Ver mis programas <ArrowRight className="size-5" aria-hidden />
          </Boton>
        </div>
      )}
    </div>
  )
}

function Pregunta({ pregunta, alContinuar }) {
  const { texto, opciones, valor, elegir } = pregunta

  return (
    <div className="animar-subir">
      <p className="mt-6 text-xl font-extrabold sm:text-2xl">{texto}</p>

      <div role="radiogroup" aria-label={texto} className="mt-5 grid gap-2.5 sm:grid-cols-2">
        {[...opciones, { valor: CUALQUIERA, etiqueta: 'Me da igual', Icono: ICONO_CUALQUIERA }].map(({ valor: v, etiqueta, cantidad, Icono }) => (
          <button key={v} type="button" role="radio" aria-checked={valor === v} onClick={() => elegir(v)} className="opcion-leccion">
            <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-suave text-primario"><Icono className="size-5" aria-hidden /></span>
            <span className="min-w-0 flex-1 truncate">{etiqueta}</span>
            {cantidad != null && <span className="tabular text-xs font-bold text-tenue">{cantidad}</span>}
          </button>
        ))}
      </div>

      <Boton variante="acento" tamano="lg" className="mt-6 w-full" disabled={valor == null} onClick={alContinuar}>
        Continuar
      </Boton>
    </div>
  )
}

function Esqueleto() {
  return (
    <div className="animate-pulse pt-6" aria-hidden>
      <div className="h-7 w-3/4 rounded-lg bg-suave" />
      <div className="mt-5 grid gap-2.5 sm:grid-cols-2">
        {[1, 2, 3, 4].map((i) => <div key={i} className="h-14 rounded-xl bg-suave" />)}
      </div>
      <div className="mt-6 h-12 rounded-xl bg-suave" />
    </div>
  )
}
