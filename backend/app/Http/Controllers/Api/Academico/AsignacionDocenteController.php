<?php

namespace App\Http\Controllers\Api\Academico;

use App\Http\Controllers\Controller;
use App\Models\AsignacionDocente;
use App\Models\OfertaModulo;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CU2 · Asignación de docentes a los módulos de una oferta, con sus datos
 * de contratación y honorarios.
 */
class AsignacionDocenteController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'id_oferta_modulo' => ['required', 'integer', Rule::exists('oferta_modulo', 'id_oferta_modulo')],
            'id_usuario_docente' => ['required', 'integer', Rule::exists('usuario', 'id_usuario')],
            ...$this->reglas(),
        ]);

        $docente = Usuario::findOrFail($datos['id_usuario_docente']);
        abort_unless($docente->estaActivo() && $docente->tieneRol(Rol::DOCENTE), 422, 'El usuario seleccionado no es un docente activo.');

        $modulo = OfertaModulo::findOrFail($datos['id_oferta_modulo']);
        abort_if($modulo->estaCerrado(), 422, 'El módulo ya fue cerrado; no admite nuevas asignaciones.');

        $duplicada = AsignacionDocente::where('id_oferta_modulo', $modulo->id_oferta_modulo)
            ->where('id_usuario_docente', $docente->id_usuario)->where('estado', AsignacionDocente::ACTIVO)->exists();
        abort_if($duplicada, 422, 'Este docente ya está asignado al módulo.');

        $asignacion = AsignacionDocente::create([...$datos, 'estado' => AsignacionDocente::ACTIVO]);
        Auditoria::describir("Asignó al docente {$docente->nombre_completo} a un módulo");

        return response()->json($asignacion->load('docente:id_usuario,nombres,apellidos,ci'), 201);
    }

    public function update(Request $request, AsignacionDocente $asignacion): JsonResponse
    {
        $datos = $request->validate([
            ...$this->reglas(),
            'estado' => ['required', Rule::in([AsignacionDocente::ACTIVO, AsignacionDocente::INACTIVO])],
        ]);

        $asignacion->update($datos);
        Auditoria::describir('Actualizó los datos de contratación de un docente');

        return response()->json($asignacion->load('docente:id_usuario,nombres,apellidos,ci'));
    }

    /** Se elimina si el módulo no tiene actividad; de lo contrario queda inactiva (historial). */
    public function destroy(AsignacionDocente $asignacion): JsonResponse
    {
        $modulo = $asignacion->ofertaModulo;

        if ($modulo->evaluaciones()->exists() || $modulo->estaCerrado()) {
            $asignacion->update(['estado' => AsignacionDocente::INACTIVO]);

            return response()->json(['message' => 'El módulo ya tiene actividad: la asignación quedó inactiva.']);
        }

        $asignacion->delete();
        Auditoria::describir('Quitó la asignación de un docente');

        return response()->json(['message' => 'Asignación eliminada.']);
    }

    private function reglas(): array
    {
        return [
            'tipo_contrato' => ['required', Rule::in(AsignacionDocente::TIPOS_CONTRATO)],
            'fecha_contrato' => ['required', 'date'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'monto_honorario' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }
}
