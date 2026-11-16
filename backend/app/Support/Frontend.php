<?php

namespace App\Support;

/**
 * Construye enlaces hacia el cliente React (capa de presentación).
 * Respeta el tipo de enrutador configurado: /ruta  o  /#/ruta.
 */
class Frontend
{
    public static function url(string $ruta): string
    {
        $base = config('icap.frontend.url');
        $ruta = '/'.ltrim($ruta, '/');

        return config('icap.frontend.router') === 'hash' ? $base.'/#'.$ruta : $base.$ruta;
    }
}
