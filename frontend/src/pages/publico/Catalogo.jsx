import { Search } from 'lucide-react'
import { useState } from 'react'
import TarjetaOferta from '../../components/catalogo/TarjetaOferta'
import { EncabezadoPagina, Estado, Vacio } from '../../components/ui/Basicos'
import Campo from '../../components/ui/Campo'
import { useConsulta, useRetardo } from '../../hooks/useConsulta'
import { legible } from '../../utils/formato'

/** CU2 · Catálogo público de programas con ofertas abiertas. */
export default function Catalogo() {
  const [texto, setTexto] = useState('')
  const [tipo, setTipo] = useState('')
  const [modalidad, setModalidad] = useState('')
  const catalogo = useConsulta('/publico/catalogo', { q: useRetardo(texto), tipo, modalidad })
  const opciones = (lista = []) => lista.map((v) => ({ valor: v, etiqueta: legible(v) }))

  return (
    <>
      <EncabezadoPagina titulo="Catálogo de programas" descripcion="Programas del ICAP con inscripciones abiertas. Consulta costos, duración y modalidad, y preinscríbete en línea." />

      <div className="tarjeta mb-5 flex flex-wrap items-end gap-3 p-3">
        <div className="relative min-w-56 flex-1">
          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tenue" aria-hidden />
          <input type="search" value={texto} onChange={(e) => setTexto(e.target.value)} placeholder="Buscar un programa…" aria-label="Buscar un programa" className="campo-control pl-9" />
        </div>
        <Campo tipo="select" nombre="tipo" valor={tipo} alCambiar={setTipo} opciones={opciones(catalogo.datos?.tipos)} vacio="Todos los tipos" aria-label="Tipo de programa" className="w-48" />
        <Campo tipo="select" nombre="modalidad" valor={modalidad} alCambiar={setModalidad} opciones={opciones(catalogo.datos?.modalidades)} vacio="Todas las modalidades" aria-label="Modalidad" className="w-52" />
      </div>

      <Estado consulta={catalogo}>
        {({ ofertas }) => ofertas.length === 0
          ? <div className="tarjeta"><Vacio titulo="No se encontraron programas">Pruebe con otros filtros o vuelva más adelante.</Vacio></div>
          : <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">{ofertas.map((o) => <TarjetaOferta key={o.id_oferta} oferta={o} />)}</div>}
      </Estado>
    </>
  )
}
