<?php

namespace App\AiAgents\LlmDrivers;

use LarAgent\Drivers\Gemini\GeminiDriver;
use LarAgent\Drivers\Gemini\GeminiMessageFormatter;

/**
 * Driver Gemini que extiende el nativo de LarAgent y corrige el manejo
 * de function_response en historial persistido (formato + name nunca vacío).
 */
class NativeGeminiDriver extends GeminiDriver
{
    protected function createFormatter(): GeminiMessageFormatter
    {
        return new GeminiMessageFormatterFixed;
    }

    public function getFormatter(): GeminiMessageFormatterFixed
    {
        return $this->formatter;
    }
}
