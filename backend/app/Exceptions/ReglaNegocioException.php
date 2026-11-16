<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Se lanza cuando una operación incumple una regla de negocio del ICAP.
 * La API responde 422 con un mensaje en español pensado para el usuario.
 */
class ReglaNegocioException extends Exception
{
    public function __construct(string $mensaje, private readonly int $estado = 422)
    {
        parent::__construct($mensaje);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->estado);
    }

    /** No ensucia el log: es un resultado esperado, no una falla del sistema. */
    public function report(): bool
    {
        return true;
    }
}
