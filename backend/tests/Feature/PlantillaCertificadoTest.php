<?php

namespace Tests\Feature;

use App\Models\PlantillaCertificado;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Plantilla del certificado: el Director edita textos y firmantes, sube las firmas
 * (imágenes) y ve una vista previa. Nadie más puede cambiarla.
 */
class PlantillaCertificadoTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function como(string $username)
    {
        $token = Usuario::where('username', $username)->firstOrFail()->createToken('test')->plainTextToken;
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json']);
    }

    public function test_el_director_edita_la_plantilla_y_sube_las_firmas(): void
    {
        Storage::fake('local');

        $plantilla = $this->como('director')->getJson('/api/certificacion/plantilla')->assertOk()->json();
        $this->assertSame('Director ICAP - UAGRM', $plantilla['firmante_2_cargo']);
        $this->assertFalse($plantilla['imagenes']['firmante_1_firma']);

        $this->como('director')->putJson('/api/certificacion/plantilla', [...$plantilla, 'firmante_1_nombre' => 'Lic. Ana Suárez', 'mostrar_foto' => false])
            ->assertOk()->assertJsonPath('firmante_1_nombre', 'Lic. Ana Suárez');
        $this->como('director')->putJson('/api/certificacion/plantilla', [...$plantilla, 'firmante_1_cargo' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('firmante_1_cargo');

        // Firma digital (imagen); el sello no es un campo de la plantilla
        $this->como('director')->post('/api/certificacion/plantilla/imagen/firmante_1_firma', ['archivo' => UploadedFile::fake()->image('firma.png', 300, 100)])
            ->assertOk()->assertJsonPath('imagenes.firmante_1_firma', true);
        Storage::assertExists(PlantillaCertificado::actual()->firmante_1_firma);
        $this->como('director')->post('/api/certificacion/plantilla/imagen/sello', ['archivo' => UploadedFile::fake()->image('sello.png')])->assertNotFound();
        $this->como('director')->get('/api/certificacion/plantilla/imagen/firmante_1_firma')->assertOk();

        $pdf = $this->como('director')->get('/api/certificacion/plantilla/vista-previa?tipo=TECNICO_AUXILIAR')->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $ruta = PlantillaCertificado::actual()->firmante_1_firma;
        $this->como('director')->deleteJson('/api/certificacion/plantilla/imagen/firmante_1_firma')->assertOk()->assertJsonPath('imagenes.firmante_1_firma', false);
        Storage::assertMissing($ruta);

        // Solo el Director (certificados.autorizar)
        $this->como('secretaria')->getJson('/api/certificacion/plantilla')->assertForbidden();
        $this->como('coord.academico')->putJson('/api/certificacion/plantilla', $plantilla)->assertForbidden();
    }
}
