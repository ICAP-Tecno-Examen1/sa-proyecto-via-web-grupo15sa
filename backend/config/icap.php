<?php

/*
|--------------------------------------------------------------------------
| Parámetros de negocio del ICAP
|--------------------------------------------------------------------------
| Reglas académicas, financieras y de integración que usa la capa de
| lógica de negocio. Todas pueden ajustarse desde el archivo .env.
*/

return [

    'institucion' => [
        'nombre' => 'Instituto de Capacitación Popular',
        'sigla' => 'ICAP - UAGRM',
        'universidad' => 'Universidad Autónoma Gabriel René Moreno',
        'ciudad' => 'Santa Cruz de la Sierra',
    ],

    // URL pública del cliente React (se usa en el QR de verificación de certificados).
    'frontend' => [
        'url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/'),
        // "browser" => /verificar/ABC   |   "hash" => /#/verificar/ABC
        'router' => env('FRONTEND_ROUTER', 'browser'),
    ],

    'academico' => [
        'nota_minima_aprobacion' => (float) env('ICAP_NOTA_MINIMA', 51),
        'asistencia_minima' => (float) env('ICAP_ASISTENCIA_MINIMA', 80),
        'nota_maxima' => 100,
        // Carreras: cada módulo de la carrera dura este número de meses y sus 1 o 2
        // materias se dictan al mismo tiempo. El calendario se genera al crear la oferta
        // y luego puede ajustarse materia por materia.
        'meses_por_modulo' => max(1, (int) env('ICAP_MESES_POR_MODULO', 1)),
    ],

    'finanzas' => [
        // Días de plazo para la primera cuota (pago inicial) desde la matrícula.
        'dias_plazo_pago_inicial' => (int) env('ICAP_DIAS_PAGO_INICIAL', 7),
        'moneda' => 'Bs.',
    ],

    // Documentos que debe tener el expediente del estudiante.
    'documentos_requeridos' => [
        'CI' => 'Cédula de identidad',
        'TITULO' => 'Título o certificado de estudios',
        'FOTOGRAFIA' => 'Fotografía 4x4',
    ],

    'archivos' => [
        'tamano_maximo_kb' => (int) env('ICAP_ARCHIVO_MAX_KB', 4096),
        'extensiones' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    ],

    // PagoFácil (API MasterQR v2). Las credenciales se administran desde "Métodos de pago"
    // y se guardan en la tabla metodo_pago_electronico (igual que en el proyecto de referencia); estas
    // variables solo son los valores iniciales del seeder.
    'pagofacil' => [
        'url_base' => env('PAGO_FACIL_URL', 'https://masterqr.pagofacil.com.bo/api/services/v2'),
        'tc_token_service' => env('PAGO_FACIL_TCTOKEN_SERVICE'),
        'tc_token_secret' => env('PAGO_FACIL_TCTOKEN_SECRET'),
        'callback_url' => env('PAGO_FACIL_CALLBACK_URL'),
        // Secreto que se anexa como ?token=... a la URL de callback para autenticar el webhook.
        'webhook_secret' => env('PAGO_FACIL_WEBHOOK_SECRET'),
    ],
];
