<?php

namespace App\AiAgents\Tools;

use App\Models\Generated;
use LarAgent\Tool;

class DocumentContext extends Tool
{
    protected string $name = 'get_document_context';

    protected string $description = 'Obtener el contenido de un documento seleccionado. Si el documento es muy largo, se proporcionará una parte del contenido y se indicará si está truncado.';

    protected array $properties = [
        'document_id' => [
            'type' => 'string',
            'description' => 'ID del documento seleccionado para obtener su contexto, el usuario no debe saber el ID del documento, solo el nombre del documento y para hacertelo llegar el usuario debe seleccionrlo de la lista de documentos disponibles',
        ]
    ];

    protected array $required = ['document_id'];

    protected array $metaData = ['sent_at' => '2024-01-01'];

    public function execute(array $input): mixed
    {
        \Log::info('📄 DocumentContext ejecutado', [
            'input' => $input,
            'session_document' => session()->get('chat_document'),
            'user_id' => auth()->id()
        ]);
        
        $documentId = $input['document_id'] ?? null;
        
        // Si no se proporciona ID, usar el de la sesión
        if (!$documentId) {
            $documentId = session()->get('chat_document');
            \Log::info('📄 Usando document_id de sesión', ['document_id' => $documentId]);
        }
        
        if (!$documentId) {
            \Log::warning('⚠️ No hay documento seleccionado');
            return "No hay documento seleccionado actualmente. El usuario debe seleccionar un documento primero desde el selector de documentos.";
        }
        
        $document = Generated::find($documentId);
        if (!$document) {
            \Log::error('❌ Documento no encontrado en BD', ['document_id' => $documentId]);
            return "Documento no encontrado (ID: {$documentId}). Es posible que haya sido eliminado.";
        }
        
        \Log::info('✅ Documento encontrado', [
            'document_id' => $document->id,
            'name' => $document->name,
            'type' => $document->key,
            'content_length' => strlen($document->value ?? '')
        ]);
        
        // Verificar permisos del usuario
        $user = auth()->user();
        if (!$user) {
            \Log::error('❌ Usuario no autenticado');
            return "Usuario no autenticado";
        }
        
        $puedeAcceder = $user->haveFullAccess() || 
                       $user->accounts->pluck('id')->contains($document->account_id);
        
        if (!$puedeAcceder) {
            \Log::warning('⚠️ Usuario sin permisos', [
                'user_id' => $user->id,
                'document_id' => $document->id
            ]);
            return "No tienes permisos para acceder a este documento";
        }
        
        // Obtener límite de contenido según el agente
        $maxLength = $this->getMaxLengthForAgent();
        $contentValue = $document->value ?? '';
        $content = substr($contentValue, 0, $maxLength);
        $isTruncated = strlen($contentValue) > $maxLength;
        
        $result = [
            'document_name' => $document->name,
            'document_type' => $this->getDocumentTypeName($document->key),
            'account_name' => $document->account?->name ?? 'Sin cuenta',
            'content' => $content,
            'truncated' => $isTruncated,
            'original_length' => strlen($contentValue),
            'provided_length' => strlen($content),
            'created_at' => $document->created_at->format('d/m/Y H:i')
        ];
        
        \Log::info('✅ Documento leído exitosamente', [
            'document_id' => $document->id,
            'name' => $document->name,
            'content_length' => strlen($content),
            'truncated' => $isTruncated
        ]);
        
        return $result;
    }
    
    /**
     * Obtiene el límite de contenido según el agente
     */
    private function getMaxLengthForAgent(): int
    {
        // Obtener el provider del agente actual
        $provider = $this->agent->provider ?? 'default';
        
        return match($provider) {
            'claude' => 100000,  // Claude puede manejar documentos muy grandes
            'gemini' => 100000,   // Gemini también puede manejar bastante
            'default' => 100000,  // OpenAI también puede manejar más
            default => 50000
        };
    }
    
    /**
     * Obtiene un nombre amigable para el tipo de documento
     */
    private function getDocumentTypeName($key): string
    {
        $tipos = [
            'Brief' => 'Brief',
            'Genesis' => 'Génesis',
            'Investigacion' => 'Investigación',
            'Creatividad' => 'Asistente Creativo',
            'Grafica' => 'Asistente Gráfico',
            'SocialMedia' => 'Social Media',
            'Innovacion' => 'Innovación'
        ];
        
        return $tipos[$key] ?? $key;
    }
}
