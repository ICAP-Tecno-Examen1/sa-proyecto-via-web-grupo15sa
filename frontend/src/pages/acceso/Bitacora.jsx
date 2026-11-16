import { Search } from 'lucide-react'
import { useState } from 'react'
import { Alerta, EncabezadoPagina, Insignia, Tarjeta } from '../../components/ui/Basicos'
import Campo from '../../components/ui/Campo'
import Tabla, { Paginacion } from '../../components/ui/Tabla'
import { useConsulta, useRetardo } from '../../hooks/useConsulta'
import { fechaHora, legible } from '../../utils/formato'

const TIPOS = ['LOGIN_EXITOSO', 'LOGIN_FALLIDO', 'LOGOUT', 'ACCION', 'ACCESO_DENEGADO']

/** Requisito 4 · Bitácora de auditoría: sesiones, intentos fallidos, IP y acciones. */
export default function Bitacora() {
  const [filtros, setFiltros] = useState({ q: '', tipo: '', desde: '', hasta: '' })
  const [pagina, setPagina] = useState(1)
  const q = useRetardo(filtros.q)
  const consulta = useConsulta('/bitacora', { ...filtros, q, page: pagina })
  const cambiar = (campo) => (valor) => (setFiltros((f) => ({ ...f, [campo]: valor })), setPagina(1))

  return (
    <>
      <EncabezadoPagina titulo="Bitácora de auditoría" descripcion="Registro permanente de inicios de sesión exitosos y fallidos, direcciones IP, acciones realizadas y accesos denegados." />

      <Tarjeta relleno={false}>
        <div className="flex flex-wrap items-end gap-3 border-b p-3">
          <div className="relative min-w-56 flex-1">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tenue" aria-hidden />
            <input type="search" value={filtros.q} onChange={(e) => cambiar('q')(e.target.value)} placeholder="Buscar por usuario, IP, recurso o descripción…" aria-label="Buscar en la bitácora" className="campo-control pl-9" />
          </div>
          <Campo tipo="select" nombre="tipo" valor={filtros.tipo} alCambiar={cambiar('tipo')} vacio="Todos los eventos" aria-label="Tipo de evento" className="w-52"
            opciones={TIPOS.map((t) => ({ valor: t, etiqueta: legible(t) }))} />
          <Campo tipo="date" nombre="desde" etiqueta="Desde" valor={filtros.desde} alCambiar={cambiar('desde')} className="w-40" />
          <Campo tipo="date" nombre="hasta" etiqueta="Hasta" valor={filtros.hasta} alCambiar={cambiar('hasta')} className="w-40" />
        </div>

        {consulta.error ? <div className="p-4"><Alerta tipo="peligro">{consulta.error}</Alerta></div> : (
          <Tabla
            id="id_bitacora"
            filas={consulta.datos?.data}
            cargando={consulta.cargando}
            columnas={[
              { clave: 'fecha', titulo: 'Fecha y hora', render: (b) => fechaHora(b.fecha), clase: 'whitespace-nowrap tabular' },
              { clave: 'tipo', titulo: 'Evento', render: (b) => <Insignia estado={b.tipo} /> },
              { clave: 'usuario', titulo: 'Usuario', render: (b) => b.usuario ? `${b.usuario.nombres} ${b.usuario.apellidos}` : (b.identificador ? <span className="text-tenue">«{b.identificador}»</span> : '—') },
              { clave: 'descripcion', titulo: 'Descripción', clase: 'max-w-xs' },
              { clave: 'recurso', titulo: 'Recurso', render: (b) => b.recurso ? <code className="text-xs">{b.metodo} /{b.recurso}</code> : '—' },
              { clave: 'ip', titulo: 'IP', clase: 'tabular' },
            ]}
          />
        )}
        <Paginacion pagina={consulta.datos} alCambiar={setPagina} />
      </Tarjeta>
    </>
  )
}
