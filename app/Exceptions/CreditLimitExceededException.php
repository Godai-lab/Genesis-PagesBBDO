<?php

namespace App\Exceptions;

use Exception;

/**
 * Excepción lanzada cuando una cuenta excede su límite de créditos
 */
class CreditLimitExceededException extends Exception
{
    /**
     * Información adicional sobre el límite
     */
    protected array $limitInfo;

    /**
     * Constructor
     * 
     * @param string $message Mensaje de error
     * @param array $limitInfo Información sobre el límite
     */
    public function __construct(string $message, array $limitInfo = [])
    {
        parent::__construct($message, 403);
        $this->limitInfo = $limitInfo;
    }

    /**
     * Obtener información del límite
     */
    public function getLimitInfo(): array
    {
        return $this->limitInfo;
    }

    /**
     * Renderizar la excepción como respuesta HTTP
     */
    public function render($request)
    {
        // Para peticiones AJAX o API
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'error' => $this->getMessage(),
                'limit_info' => $this->limitInfo,
            ], 403);
        }

        // Para componentes Livewire
        if ($request->header('X-Livewire')) {
            // Livewire manejará esto como un error
            return response()->json([
                'message' => $this->getMessage(),
            ], 403);
        }

        // Para peticiones normales, redirigir con mensaje
        return redirect()->back()
            ->with('error', $this->getMessage())
            ->with('limit_info', $this->limitInfo);
    }

    /**
     * Reportar la excepción (logging)
     */
    public function report(): bool
    {
        // No reportar al log de errores (ya se loggea en el trait)
        return false;
    }
}

