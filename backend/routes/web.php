<?php

use Illuminate\Support\Facades\Route;

// Este proyecto es solo la API (capa 2). La interfaz vive en ../frontend (React).
Route::get('/', fn () => response()->json([
    'sistema' => 'ICAP - UAGRM · API RESTful',
    'api' => url('/api'),
    'estado' => 'en línea',
]));
