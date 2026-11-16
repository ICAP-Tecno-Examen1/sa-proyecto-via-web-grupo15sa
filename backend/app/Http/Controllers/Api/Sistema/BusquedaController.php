<?php

namespace App\Http\Controllers\Api\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Certificado;
use App\Models\Matricula;
use App\Models\OfertaAcademica;
use App\Models\Programa;
use App\Models\Usuario;
use App\Services\DocumentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Requisito 9 · Búsqueda global del encabezado (predictiva).
 *
 * Sin sesión busca en el catálogo público. Con sesión agrega los recursos
 * que el rol activo tiene permiso de ver. Cada resultado trae la ruta del
 * cliente a la que debe navegar.
 */
class BusquedaController extends Controller
{
    private const LIMITE = 5;

    public function __invoke(Request $request, DocumentoService $documentos): JsonResponse
    {
        $datos = $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']]);
        $patron = '%'.mb_strtolower(trim($datos['q'])).'%';
        $usuario = Auth::guard('sanctum')->user();
        $grupos = [];

        // --- Público: ofertas abiertas del catálogo
        $ofertas = OfertaAcademica::where('estado', OfertaAcademica::ABIERTA)->with('malla.programa')
            ->where(fn ($q) => $q->whereRaw('LOWER(codigo) LIKE ?', [$patron])
                ->orWhereHas('malla.programa', fn ($p) => $p->where(fn ($w) => $w
                    ->whereRaw('LOWER(nombre) LIKE ?', [$patron])->orWhereRaw("LOWER(COALESCE(descripcion, '')) LIKE ?", [$patron]))))
            ->limit(self::LIMITE)->get()
            ->map(fn ($o) => $this->item($o->malla->programa->nombre, "Oferta {$o->codigo} · ".ucfirst(strtolower($o->modalidad))." · Gestión {$o->gestion}", "/catalogo/{$o->id_oferta}"));
        $this->agregar($grupos, 'Catálogo de programas', $ofertas);

        // --- Público: verificación de certificados por código
        $certificado = Certificado::whereRaw('LOWER(codigo_unico) = ?', [mb_strtolower(trim($datos['q']))])->first();
        if ($certificado) {
            $this->agregar($grupos, 'Verificación de certificados', collect([
                $this->item($certificado->codigo_unico, 'Verificar la autenticidad del certificado', "/verificar/{$certificado->codigo_unico}"),
            ]));
        }

        if ($usuario?->estaActivo()) {
            if ($usuario->tienePermiso('programas.ver')) {
                $this->agregar($grupos, 'Programas', Programa::where(fn ($q) => $q
                    ->whereRaw('LOWER(nombre) LIKE ?', [$patron])->orWhereRaw('LOWER(codigo) LIKE ?', [$patron]))
                    ->limit(self::LIMITE)->get()
                    ->map(fn ($p) => $this->item($p->nombre, "Programa {$p->codigo} · ".ucfirst(strtolower($p->estado)), '/programas?q='.urlencode($p->codigo))));
            }

            if ($usuario->tieneAlgunPermiso(['usuarios.ver', 'estudiantes.gestionar'])) {
                $soloEstudiantes = ! $usuario->tienePermiso('usuarios.ver');

                $this->agregar($grupos, $soloEstudiantes ? 'Estudiantes' : 'Usuarios', Usuario::where(fn ($q) => $q
                    ->whereRaw("LOWER(nombres || ' ' || apellidos) LIKE ?", [$patron])
                    ->orWhereRaw('LOWER(ci) LIKE ?', [$patron])->orWhereRaw('LOWER(correo) LIKE ?', [$patron]))
                    ->when($soloEstudiantes, fn ($q) => $q->whereHas('rolesActivos', fn ($r) => $r->where('rol.nombre', 'Estudiante')))
                    ->limit(self::LIMITE)->get()
                    ->map(fn ($u) => $this->item($u->nombre_completo, "Doc. {$u->ci} · {$u->correo}", ($soloEstudiantes ? '/estudiantes' : '/usuarios').'?q='.urlencode($u->ci))));
            }

            if ($usuario->tienePermiso('matriculas.ver')) {
                $this->agregar($grupos, 'Matrículas', Matricula::with(['usuario', 'oferta.malla.programa'])
                    ->where(fn ($q) => $q->whereRaw('LOWER(numero_registro) LIKE ?', [$patron])
                        ->orWhereHas('usuario', fn ($u) => $u->where(fn ($w) => $w
                            ->whereRaw("LOWER(nombres || ' ' || apellidos) LIKE ?", [$patron])->orWhereRaw('LOWER(ci) LIKE ?', [$patron]))))
                    ->orderByDesc('id_matricula')->limit(self::LIMITE)->get()
                    ->map(fn ($m) => $this->item("{$m->numero_registro} · {$m->usuario->nombre_completo}", $m->oferta->malla->programa->nombre.' · '.ucfirst(strtolower(str_replace('_', ' ', $m->estado))), "/matriculas/{$m->id_matricula}")));
            }

            if ($usuario->tienePermiso('documentos.ver')) {
                $this->agregar($grupos, 'Repositorio documental', $documentos->visibles($usuario)
                    ->whereRaw('LOWER(nombre) LIKE ?', [$patron])->orderByDesc('id_documento')->limit(self::LIMITE)->get()
                    ->map(fn ($d) => $this->item($d->nombre, ucfirst(strtolower($d->categoria))." · Gestión {$d->gestion}", '/documentos?q='.urlencode($d->nombre))));
            }

            // Opciones del menú del propio usuario (navegación rápida).
            $menu = collect(app(\App\Services\MenuService::class)->paraUsuario($usuario))
                ->flatMap(fn ($s) => $s['hijos'])
                ->filter(fn ($h) => str_contains(mb_strtolower($h['etiqueta']), mb_strtolower(trim($datos['q']))))
                ->take(self::LIMITE)
                ->map(fn ($h) => $this->item($h['etiqueta'], 'Ir a la sección', $h['ruta']));
            $this->agregar($grupos, 'Secciones del sistema', $menu);
        }

        return response()->json(['q' => $datos['q'], 'grupos' => $grupos, 'total' => collect($grupos)->sum(fn ($g) => count($g['resultados']))]);
    }

    private function item(string $titulo, string $detalle, string $ruta): array
    {
        return ['titulo' => $titulo, 'detalle' => $detalle, 'ruta' => $ruta];
    }

    private function agregar(array &$grupos, string $nombre, $resultados): void
    {
        if ($resultados->isNotEmpty()) {
            $grupos[] = ['grupo' => $nombre, 'resultados' => $resultados->values()];
        }
    }
}
