<?php

namespace App\Http\Controllers\Api\Certificacion;

use App\Http\Controllers\Controller;
use App\Models\PlantillaCertificado;
use App\Services\Auditoria;
use App\Services\CertificadoPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * CU6 · Plantilla del certificado / título: textos, firmantes, firmas digitales
 * (imágenes) y escudos. El sello no se digitaliza: se estampa a mano.
 * La edita el Director (certificados.autorizar).
 */
class PlantillaCertificadoController extends Controller
{
    private const TEXTOS = [
        'encabezado_1' => ['required', 'string', 'max:200'],
        'encabezado_2' => ['required', 'string', 'max:200'],
        'texto_otorgamiento' => ['required', 'string', 'max:2000'],
        'texto_habilitacion' => ['required', 'string', 'max:2000'],
        'texto_curso' => ['required', 'string', 'max:2000'],
        'texto_pie' => ['required', 'string', 'max:2000'],
        'ciudad' => ['required', 'string', 'max:100'],
        'lugar_pie' => ['required', 'string', 'max:100'],
        'firmante_1_nombre' => ['nullable', 'string', 'max:150'],
        'firmante_1_cargo' => ['required', 'string', 'max:150'],
        'firmante_2_nombre' => ['nullable', 'string', 'max:150'],
        'firmante_2_cargo' => ['required', 'string', 'max:150'],
        'mostrar_foto' => ['required', 'boolean'],
    ];

    public function show(): JsonResponse
    {
        return response()->json($this->datos(PlantillaCertificado::actual()));
    }

    public function update(Request $request): JsonResponse
    {
        $datos = $request->validate(self::TEXTOS);
        $plantilla = PlantillaCertificado::actual();
        $plantilla->update($datos);

        Auditoria::describir('Actualizó los textos y firmantes de la plantilla del certificado');

        return response()->json($this->datos($plantilla));
    }

    /** Sube la firma de un firmante o un escudo (PNG/JPG; la firma idealmente con fondo transparente). */
    public function subirImagen(Request $request, string $campo): JsonResponse
    {
        $this->validarCampo($campo);
        $request->validate(['archivo' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:2048']], [], ['archivo' => 'imagen']);

        $plantilla = PlantillaCertificado::actual();
        $anterior = $plantilla->{$campo};
        $plantilla->update([$campo => $request->file('archivo')->store('plantilla-certificado')]);

        if ($anterior && $anterior !== $plantilla->{$campo}) {
            Storage::delete($anterior);
        }

        Auditoria::describir('Cambió la imagen «'.self::etiqueta($campo).'» de la plantilla del certificado');

        return response()->json($this->datos($plantilla));
    }

    public function quitarImagen(string $campo): JsonResponse
    {
        $this->validarCampo($campo);
        $plantilla = PlantillaCertificado::actual();

        if ($plantilla->{$campo}) {
            Storage::delete($plantilla->{$campo});
            $plantilla->update([$campo => null]);
        }

        Auditoria::describir('Quitó la imagen «'.self::etiqueta($campo).'» de la plantilla del certificado');

        return response()->json($this->datos($plantilla));
    }

    public function imagen(string $campo): Response
    {
        $this->validarCampo($campo);
        $ruta = PlantillaCertificado::actual()->{$campo};
        abort_unless($ruta && Storage::exists($ruta), 404, 'No hay imagen cargada.');

        return Storage::response($ruta);
    }

    /** PDF de muestra con datos ficticios y la marca «VISTA PREVIA». */
    public function vistaPrevia(Request $request, CertificadoPdfService $pdf): Response
    {
        $request->validate(['tipo' => ['nullable', Rule::in(array_keys(CertificadoPdfService::TITULOS))]]);

        return response($pdf->vistaPrevia($request->query('tipo')), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="vista-previa-certificado.pdf"',
        ]);
    }

    // ------------------------------------------------------------------

    private function validarCampo(string $campo): void
    {
        abort_unless(in_array($campo, PlantillaCertificado::IMAGENES, true), 404, 'Imagen de plantilla no válida.');
    }

    private static function etiqueta(string $campo): string
    {
        return [
            'firmante_1_firma' => 'firma del firmante izquierdo',
            'firmante_2_firma' => 'firma del firmante derecho',
            'logo_izquierdo' => 'escudo izquierdo',
            'logo_derecho' => 'escudo derecho',
        ][$campo];
    }

    private function datos(PlantillaCertificado $plantilla): array
    {
        $datos = $plantilla->only([...array_keys(self::TEXTOS), 'id_plantilla']);

        foreach (PlantillaCertificado::IMAGENES as $campo) {
            $datos['imagenes'][$campo] = filled($plantilla->{$campo});
        }

        return $datos;
    }
}
