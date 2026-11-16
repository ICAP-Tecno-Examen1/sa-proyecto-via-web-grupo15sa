<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Documento;
use App\Models\ExpedienteDocumento;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CU7 · Repositorio documental histórico y expediente único.
 *
 * Los archivos se guardan en el disco privado (storage/app/private) y solo
 * se entregan a través de la API, después de verificar el acceso por rol:
 *
 *   documentos.personal / .academico / .financiero / .institucional
 *       -> permiten ver TODOS los documentos de esa categoría.
 *   Sin permiso de categoría (Docente, Estudiante)
 *       -> solo los documentos propios: los que registró o los de su expediente.
 */
class DocumentoService
{
    private const PERMISOS_CATEGORIA = [
        'PERSONAL' => 'documentos.personal',
        'ACADEMICO' => 'documentos.academico',
        'FINANCIERO' => 'documentos.financiero',
        'INSTITUCIONAL' => 'documentos.institucional',
    ];

    /** Categorías que el rol activo del usuario puede consultar completas. */
    public function categoriasPermitidas(Usuario $usuario): array
    {
        return array_keys(array_filter(
            self::PERMISOS_CATEGORIA,
            fn (string $permiso) => $usuario->tienePermiso($permiso),
        ));
    }

    /** Categorías en las que el usuario puede registrar documentos nuevos. */
    public function categoriasParaRegistrar(Usuario $usuario): array
    {
        return $this->categoriasPermitidas($usuario) ?: ['ACADEMICO'];
    }

    /** Consulta base con los documentos que el usuario tiene derecho a ver. */
    public function visibles(Usuario $usuario): Builder
    {
        $categorias = $this->categoriasPermitidas($usuario);

        return Documento::query()->where(function (Builder $q) use ($usuario, $categorias) {
            $q->whereIn('categoria', $categorias)
                ->orWhere('id_usuario_registro', $usuario->id_usuario)
                ->orWhereHas('titulares', fn (Builder $t) => $t->where('usuario.id_usuario', $usuario->id_usuario));
        });
    }

    public function puedeVer(Usuario $usuario, Documento $documento): bool
    {
        return $this->visibles($usuario)->whereKey($documento->getKey())->exists();
    }

    /**
     * Registra un documento. Si se indican titulares, queda asociado a su
     * expediente único (expediente_documento).
     */
    public function registrar(array $datos, ?UploadedFile $archivo, ?Usuario $registrador, array $idsTitulares = []): Documento
    {
        $documento = new Documento([
            'nombre' => $datos['nombre'],
            'descripcion' => $datos['descripcion'] ?? null,
            'tipo' => $datos['tipo'],
            'categoria' => $datos['categoria'] ?? 'PERSONAL',
            'fecha_documento' => $datos['fecha_documento'] ?? now()->toDateString(),
            'gestion' => $datos['gestion'] ?? now()->year,
            'estado' => Documento::VIGENTE,
            'id_usuario_registro' => $registrador?->id_usuario,
            'fecha_registro' => now(),
        ]);

        if ($archivo) {
            $this->adjuntar($documento, $archivo);
        } elseif (! empty($datos['url_archivo'])) {
            // Archivo ya existente en el disco privado (comprobante o certificado generado).
            $documento->url_archivo = $datos['url_archivo'];
            $documento->nombre_archivo = $datos['nombre_archivo'] ?? basename($datos['url_archivo']);
            $documento->tipo_mime = $datos['tipo_mime'] ?? null;
            $documento->tamano_bytes = Storage::exists($datos['url_archivo']) ? Storage::size($datos['url_archivo']) : null;
        }

        $documento->save();

        foreach (array_unique($idsTitulares) as $idUsuario) {
            ExpedienteDocumento::create([
                'id_usuario' => $idUsuario,
                'id_documento' => $documento->id_documento,
                'fecha_asociacion' => now(),
            ]);
        }

        return $documento;
    }

    public function reemplazarArchivo(Documento $documento, UploadedFile $archivo): void
    {
        $anterior = $documento->url_archivo;
        $this->adjuntar($documento, $archivo);
        $documento->save();

        if ($anterior && $anterior !== $documento->url_archivo && $this->esArchivoPropio($anterior)) {
            Storage::delete($anterior);
        }
    }

    public function eliminar(Documento $documento): void
    {
        $ruta = $documento->url_archivo;
        $documento->delete();

        if ($ruta && $this->esArchivoPropio($ruta)) {
            Storage::delete($ruta);
        }
    }

    public function descargar(Documento $documento, bool $enLinea = false): StreamedResponse
    {
        return $this->entregar($documento->url_archivo, $documento->nombre_archivo ?: $documento->nombre, $enLinea);
    }

    /** Entrega un archivo del disco privado (lo usan documentos, comprobantes y certificados). */
    public function entregar(?string $ruta, string $nombre, bool $enLinea = false): StreamedResponse
    {
        if (! $ruta || ! Storage::exists($ruta)) {
            throw new ReglaNegocioException('El archivo no está disponible en el repositorio.', 404);
        }

        $extension = pathinfo($ruta, PATHINFO_EXTENSION);
        $nombre = Str::of($nombre)->ascii()->replaceMatches('/[^A-Za-z0-9._ -]/', '')->trim()->value() ?: 'archivo';

        if ($extension && ! Str::endsWith(Str::lower($nombre), '.'.Str::lower($extension))) {
            $nombre .= '.'.$extension;
        }

        return $enLinea
            ? Storage::response($ruta, $nombre)
            : Storage::download($ruta, $nombre);
    }

    /** Tipos de documento requeridos que le faltan al expediente de un usuario. */
    public function requisitosFaltantes(Usuario $usuario): array
    {
        $presentes = $usuario->documentos()
            ->where('documento.estado', Documento::VIGENTE)
            ->whereNotNull('documento.url_archivo')
            ->pluck('documento.tipo')
            ->unique();

        return collect(config('icap.documentos_requeridos'))
            ->reject(fn ($etiqueta, $tipo) => $presentes->contains($tipo))
            ->all();
    }

    private function adjuntar(Documento $documento, UploadedFile $archivo): void
    {
        $documento->url_archivo = $archivo->store('documentos/'.now()->year);
        $documento->nombre_archivo = Str::limit($archivo->getClientOriginalName(), 250, '');
        $documento->tipo_mime = $archivo->getMimeType();
        $documento->tamano_bytes = $archivo->getSize();
    }

    /** Los comprobantes y certificados pertenecen a otros módulos: no se borran desde aquí. */
    private function esArchivoPropio(string $ruta): bool
    {
        return Str::startsWith($ruta, 'documentos/');
    }
}
