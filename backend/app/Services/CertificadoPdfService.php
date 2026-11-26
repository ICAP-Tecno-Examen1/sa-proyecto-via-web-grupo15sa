<?php

namespace App\Services;

use App\Models\Certificado;
use App\Models\Documento;
use App\Models\PlantillaCertificado;
use App\Models\Programa;
use App\Models\Usuario;
use App\Support\CodigoQr;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CU6 · Dibujo del certificado / título con el formato oficial del ICAP
 * (A4 vertical, encabezado gótico, QR a la izquierda y foto a la derecha,
 * firmas digitales de la Coordinación General y la Dirección; el sello se
 * estampa a mano en el espacio central). Textos, firmantes e imágenes vienen
 * de la plantilla editable (tabla plantilla_certificado).
 */
class CertificadoPdfService
{
    /** Programas que otorgan «Título» (carreras técnicas); el resto otorga «Certificado». */
    public const OTORGAN_TITULO = [Programa::TECNICO_AUXILIAR, Programa::TECNICO_MEDIO, Programa::TECNICO_SUPERIOR, 'PROGRAMA_TECNICO'];

    public const TITULOS = [
        'TECNICO_AUXILIAR' => 'Técnico Auxiliar',
        'TECNICO_MEDIO' => 'Técnico Medio',
        'TECNICO_SUPERIOR' => 'Técnico Superior',
        'PROGRAMA_TECNICO' => 'Programa Técnico',
        'CURSO' => 'Certificado de Aprobación',
        'TALLER' => 'Certificado de Aprobación',
    ];

    private const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    /** PDF de un certificado emitido. */
    public function certificado(Certificado $certificado, array $datos): string
    {
        $alumno = $certificado->matricula->usuario;

        return $this->dibujar([
            'numero' => str_pad((string) $certificado->id_certificado, 7, '0', STR_PAD_LEFT),
            'codigo' => $certificado->codigo_unico,
            'qr' => CodigoQr::dataUri($certificado->codigo_qr, 260),
            'tipo_programa' => $datos['tipo_programa'],
            'programa' => $datos['programa'],
            'horas' => $datos['horas_totales'],
            'estudiante' => $alumno->nombre_completo,
            'tipo_documento' => $alumno->tipo_documento,
            'documento' => $alumno->ci,
            'fecha_nacimiento' => $alumno->fecha_nacimiento,
            'lugar_nacimiento' => $alumno->lugar_nacimiento,
            'fecha_emision' => $certificado->fecha_emision,
            'foto' => $this->foto($alumno),
        ]);
    }

    /** Vista previa de la plantilla con datos de ejemplo. */
    public function vistaPrevia(?string $tipoPrograma = null): string
    {
        $tipo = array_key_exists((string) $tipoPrograma, self::TITULOS) ? $tipoPrograma : Programa::TECNICO_AUXILIAR;

        return $this->dibujar([
            'numero' => '0000000',
            'codigo' => 'VISTA-PREVIA',
            'qr' => CodigoQr::dataUri('VISTA PREVIA - SIN VALIDEZ', 260),
            'tipo_programa' => $tipo,
            'programa' => in_array($tipo, self::OTORGAN_TITULO, true) ? 'Auxiliar Contable' : 'Excel Avanzado',
            'horas' => 800,
            'estudiante' => 'Nombre Apellido Apellido',
            'tipo_documento' => 'CI',
            'documento' => '1234567',
            'fecha_nacimiento' => Carbon::parse('1995-06-10'),
            'lugar_nacimiento' => 'Santa Cruz - Bolivia',
            'fecha_emision' => now(),
            'foto' => null,
        ], vistaPrevia: true);
    }

    private function dibujar(array $d, bool $vistaPrevia = false): string
    {
        $p = PlantillaCertificado::actual();
        $esTitulo = in_array($d['tipo_programa'], self::OTORGAN_TITULO, true);
        $titulo = self::TITULOS[$d['tipo_programa']] ?? 'Certificado';

        $c = [
            ...$d,
            'vista_previa' => $vistaPrevia,
            'es_titulo' => $esTitulo,
            'titulo' => $titulo,
            'en' => Str::upper($this->sinPrefijo($d['programa'])),
            'otorgamiento' => strtr($p->texto_otorgamiento, ['{documento}' => $esTitulo ? 'Título' : 'Certificado']),
            'cierre' => $esTitulo ? $p->texto_habilitacion : strtr($p->texto_curso, ['{horas}' => (string) $d['horas']]),
            'nacimiento' => $this->nacimiento($d),
            'fecha_lugar' => $p->ciudad.' '.$this->fechaLarga($d['fecha_emision']),
        ];

        return Pdf::loadView('pdf.certificado', [
            'c' => $c,
            'p' => $p,
            'fuente' => str_replace('\\', '/', resource_path('certificado/UnifrakturMaguntia.ttf')),
            'logo_izquierdo' => $this->imagen($p->logo_izquierdo) ?? $this->recurso('logo-icap.png'),
            'logo_derecho' => $this->imagen($p->logo_derecho),
            'firma_1' => $this->imagen($p->firmante_1_firma),
            'firma_2' => $this->imagen($p->firmante_2_firma),
        ])->setPaper('a4', 'portrait')->output();
    }

    /** «Técnico Auxiliar en Marketing Digital» → «Marketing Digital» (el tipo ya es el título grande). */
    private function sinPrefijo(string $programa): string
    {
        $limpio = preg_replace('/^(t[ée]cnico\s+(auxiliar|medio|superior)|programa\s+t[ée]cnico|curso|taller)\s+(en|de)\s+/iu', '', trim($programa));

        return filled($limpio) ? $limpio : $programa;
    }

    private function nacimiento(array $d): string
    {
        $documento = ($d['tipo_documento'] ?? 'CI') === 'CI' ? 'C.I.' : Str::title(str_replace('_', ' ', $d['tipo_documento']));
        $texto = '';

        if ($d['fecha_nacimiento']) {
            $texto = 'Nacido(a) en fecha '.$this->fechaLarga($d['fecha_nacimiento']);
        }
        if ($d['lugar_nacimiento']) {
            $texto .= ($texto ? ' en ' : 'Nacido(a) en ').$d['lugar_nacimiento'];
        }

        return $texto.($texto ? ' con ' : 'Con ').$documento.' N° '.$d['documento'];
    }

    private function fechaLarga(Carbon $fecha): string
    {
        return $fecha->day.' de '.self::MESES[$fecha->month - 1].' de '.$fecha->year;
    }

    /** Fotografía del expediente (la más reciente, vigente y en formato de imagen). */
    private function foto(Usuario $alumno): ?string
    {
        $foto = $alumno->documentos()
            ->where('documento.tipo', 'FOTOGRAFIA')
            ->where('documento.estado', Documento::VIGENTE)
            ->whereNotNull('documento.url_archivo')
            ->orderByDesc('documento.id_documento')
            ->get()
            ->first(fn ($doc) => Str::startsWith((string) $doc->tipo_mime, 'image/'));

        return $foto ? $this->imagen($foto->url_archivo) : null;
    }

    /** Imagen del disco local como data URI (DomPDF no lee archivos privados por URL). */
    public function imagen(?string $ruta): ?string
    {
        if (! $ruta || ! Storage::exists($ruta)) {
            return null;
        }

        return $this->dataUri(Storage::get($ruta));
    }

    private function recurso(string $archivo): ?string
    {
        $ruta = resource_path('certificado/'.$archivo);

        return is_file($ruta) ? $this->dataUri(file_get_contents($ruta)) : null;
    }

    private function dataUri(string $bytes): ?string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/png';

        // DomPDF no siempre dibuja WEBP: se convierte a PNG si GD lo permite.
        if ($mime === 'image/webp') {
            $gd = function_exists('imagecreatefromstring') ? @imagecreatefromstring($bytes) : false;

            if (! $gd) {
                return null;
            }

            ob_start();
            imagepng($gd);
            $bytes = ob_get_clean();
            $mime = 'image/png';
        }

        return in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)
            ? 'data:'.$mime.';base64,'.base64_encode($bytes)
            : null;
    }
}
