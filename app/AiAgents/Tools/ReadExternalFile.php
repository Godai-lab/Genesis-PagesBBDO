<?php

namespace App\AiAgents\Tools;

use App\Services\ProcessFileContentService;
use LarAgent\Tool;
use Illuminate\Support\Facades\Log;

class ReadExternalFile extends Tool
{
    protected string $name = 'read_external_file';

    protected string $description = 'Lee y extrae el contenido de archivos externos subidos por el usuario (PDF, Word, Excel, CSV, TXT). Úsalo cuando el usuario menciona que ha subido un archivo o cuando necesites analizar un documento que el usuario ha proporcionado.';

    protected array $properties = [
        'file_path' => [
            'type' => 'string',
            'description' => 'Ruta temporal del archivo subido (proporcionado por el sistema cuando el usuario sube un archivo)',
        ],
        'file_type' => [
            'type' => 'string',
            'description' => 'Tipo MIME del archivo (application/pdf, application/vnd.openxmlformats-officedocument.wordprocessingml.document, etc.)',
        ]
    ];

    protected array $required = ['file_path', 'file_type'];

    protected array $metaData = ['sent_at' => '2024-01-01'];

    public function execute(array $input): mixed
    {
        $filePath = $input['file_path'] ?? null;
        $fileType = $input['file_type'] ?? null;

        if (!$filePath) {
            return "Error: No se proporcionó la ruta del archivo.";
        }

        if (!file_exists($filePath)) {
            return "Error: El archivo no existe o ya fue eliminado. Por favor, vuelve a subirlo.";
        }

        try {
            Log::info('📄 Procesando archivo externo:', [
                'path' => $filePath,
                'type' => $fileType
            ]);

            // Procesar según el tipo de archivo
            $content = null;
            
            if ($fileType === 'application/pdf') {
                $content = ProcessFileContentService::processPdf($filePath);
            } elseif (in_array($fileType, [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/msword'
            ])) {
                $content = ProcessFileContentService::processWord($filePath);
            } elseif (in_array($fileType, [
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ])) {
                $content = ProcessFileContentService::processExcel($filePath);
            } elseif ($fileType === 'text/csv') {
                $content = ProcessFileContentService::processCSV($filePath);
            } elseif ($fileType === 'text/plain') {
                $content = ProcessFileContentService::processTxt($filePath);
            } else {
                return "Error: Tipo de archivo no soportado. Tipos soportados: PDF, Word (.docx, .doc), Excel (.xlsx, .xls), CSV, TXT";
            }

            if ($content === null || empty(trim($content))) {
                return "Error: No se pudo extraer el contenido del archivo. Puede estar corrupto, vacío o protegido con contraseña.";
            }

            // Limitar el tamaño del contenido para evitar exceder el contexto del modelo
            $maxLength = $this->getMaxLengthForAgent();
            $originalLength = strlen($content);
            $isTruncated = $originalLength > $maxLength;
            
            if ($isTruncated) {
                $content = substr($content, 0, $maxLength);
            }

            $result = [
                'success' => true,
                'content' => $content,
                'truncated' => $isTruncated,
                'original_length' => $originalLength,
                'provided_length' => strlen($content),
                'file_type' => $fileType,
            ];

            if ($isTruncated) {
                $result['message'] = "⚠️ El archivo es muy grande ({$originalLength} caracteres). Se proporcionó una parte del contenido ({$maxLength} caracteres).";
            }

            Log::info('✅ Archivo procesado exitosamente:', [
                'original_length' => $originalLength,
                'provided_length' => strlen($content),
                'truncated' => $isTruncated
            ]);

            // Retornar el contenido directamente para que el agente lo pueda leer
            return $isTruncated 
                ? $content . "\n\n⚠️ Nota: Este archivo fue truncado. Contenido original: {$originalLength} caracteres, mostrado: {$maxLength} caracteres."
                : $content;

        } catch (\Exception $e) {
            Log::error('❌ Error procesando archivo externo:', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return "Error al procesar el archivo: " . $e->getMessage();
        }
    }

    /**
     * Obtiene el límite de contenido según el agente
     */
    private function getMaxLengthForAgent(): int
    {
        $provider = $this->agent->provider ?? 'default';
        
        return match($provider) {
            'claude' => 100000,  // Claude puede manejar documentos muy grandes
            'gemini' => 100000,   // Gemini también puede manejar bastante
            'default' => 100000,  // OpenAI también puede manejar más
            default => 50000
        };
    }
}

