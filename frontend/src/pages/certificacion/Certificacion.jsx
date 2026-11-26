import { Download, FileBadge, Search } from 'lucide-react'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api, { abrirArchivo } from '../../api/cliente'
import { Alerta, EncabezadoPagina, Estado, Insignia, Pestanas, Tarjeta } from '../../components/ui/Basicos'
import Boton from '../../components/ui/Boton'
import { Confirmar } from '../../components/ui/Modal'
import Tabla, { Paginacion } from '../../components/ui/Tabla'
import { useAuth } from '../../context/AuthContext'
import { useAvisos } from '../../context/AvisosContext'
import { useConsulta, useRetardo } from '../../hooks/useConsulta'
import { fecha } from '../../utils/formato'

const REQUISITOS = [['ACADEMICO', 'Académico'], ['FINANCIERO', 'Financiero'], ['DOCUMENTAL', 'Documental']]

/** CU6 · Estudiantes que finalizaron, estado de sus requisitos y certificados emitidos. */
export default function Certificacion() {
  const auth = useAuth()
  const avisar = useAvisos()
  const navegar = useNavigate()
  const [pestana, setPestana] = useState('requisitos')
  const [texto, setTexto] = useState('')
  const [pagina, setPagina] = useState(1)
  const finalizados = useConsulta('/certificacion', { q: useRetardo(texto), page: pagina })
  const certificados = useConsulta(pestana === 'emitidos' ? '/certificados' : null)
  const [anulando, setAnulando] = useState(null)

  const anular = async () => {
    try {
      await api.post(`/certificados/${anulando.id_certificado}/anular`)
      avisar.exito('Certificado anulado. La verificación pública lo mostrará como no válido.')
      certificados.recargar()
    } catch (e) {
      avisar.error(e)
    } finally {
      setAnulando(null)
    }
  }

  return (
    <>
      <EncabezadoPagina titulo="Requisitos y certificados" descripcion="Cada estudiante que finaliza necesita tres validaciones (académica, financiera y documental). Con las tres aprobadas, el Director autoriza la emisión del certificado digital.">
        {auth.puede('certificados.autorizar') && <Boton variante="secundario" icono={FileBadge} a="/certificacion/plantilla">Plantilla del certificado</Boton>}
      </EncabezadoPagina>

      <Pestanas activa={pestana} alCambiar={setPestana} pestanas={[{ clave: 'requisitos', etiqueta: 'Validación de requisitos' }, { clave: 'emitidos', etiqueta: 'Certificados emitidos' }]} />

      {pestana === 'requisitos' ? (
        <Tarjeta relleno={false}>
          <div className="border-b p-3">
            <div className="relative max-w-md">
              <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-tenue" aria-hidden />
              <input type="search" value={texto} onChange={(e) => (setTexto(e.target.value), setPagina(1))} placeholder="Buscar estudiante o matrícula…" aria-label="Buscar" className="campo-control pl-9" />
            </div>
          </div>
          {finalizados.error ? <div className="p-4"><Alerta tipo="peligro">{finalizados.error}</Alerta></div> : (
            <Tabla
              id="id_matricula"
              filas={finalizados.datos?.data}
              cargando={finalizados.cargando}
              vacio="Aún no hay estudiantes con el programa finalizado."
              alSeleccionar={(m) => navegar(`/certificacion/${m.id_matricula}`)}
              columnas={[
                { clave: 'estudiante', titulo: 'Estudiante', render: (m) => (<><span className="font-semibold">{m.estudiante.nombre_completo}</span><span className="block text-xs text-tenue">{m.numero_registro}</span></>) },
                { clave: 'programa', titulo: 'Programa', render: (m) => (<>{m.programa.nombre}<span className="block text-xs text-tenue">Finalizó el {fecha(m.fecha_finalizacion)}</span></>) },
                ...REQUISITOS.map(([tipo, nombre]) => ({ clave: tipo, titulo: nombre, render: (m) => <Insignia estado={m.validaciones[tipo] ?? 'PENDIENTE'} /> })),
                { clave: 'certificado', titulo: 'Certificado', render: (m) => (m.certificado ? <Insignia tono="exito">{m.certificado.codigo_unico}</Insignia> : <span className="text-xs text-tenue">No emitido</span>) },
              ]}
            />
          )}
          <Paginacion pagina={finalizados.datos} alCambiar={setPagina} />
        </Tarjeta>
      ) : (
        <Tarjeta relleno={false}>
          <Estado consulta={certificados}>
            {(filas) => (
              <Tabla
                id="id_certificado"
                filas={filas}
                vacio="Aún no se emitieron certificados."
                columnas={[
                  { clave: 'codigo_unico', titulo: 'Código único', clase: 'font-semibold tabular' },
                  { clave: 'fecha_emision', titulo: 'Emisión', render: (c) => fecha(c.fecha_emision) },
                  { clave: 'estudiante', titulo: 'Estudiante', render: (c) => (<>{c.estudiante}<span className="block text-xs text-tenue">{c.numero_registro}</span></>) },
                  { clave: 'programa', titulo: 'Programa', render: (c) => `${c.programa} · ${c.gestion}` },
                  { clave: 'autorizado_por', titulo: 'Autorizó' },
                  { clave: 'estado', titulo: 'Estado', render: (c) => <Insignia estado={c.estado} /> },
                  { clave: 'acciones', titulo: 'Acciones', alinear: 'derecha', render: (c) => (
                    <div className="flex justify-end gap-1">
                      <Boton tamano="sm" variante="secundario" icono={Download} onClick={() => abrirArchivo(`/certificados/${c.id_certificado}/pdf`).catch(avisar.error)}>PDF</Boton>
                      {auth.puede('certificados.autorizar') && c.estado === 'EMITIDO' && <Boton tamano="sm" variante="fantasma" className="text-peligro" onClick={() => setAnulando(c)}>Anular</Boton>}
                    </div>
                  ) },
                ]}
              />
            )}
          </Estado>
        </Tarjeta>
      )}

      <Confirmar abierto={Boolean(anulando)} titulo="Anular certificado" textoConfirmar="Anular certificado" alConfirmar={anular} alCerrar={() => setAnulando(null)}
        mensaje={`El certificado ${anulando?.codigo_unico} de ${anulando?.estudiante} dejará de ser válido. Quien escanee su QR verá que fue anulado.`} />
    </>
  )
}
