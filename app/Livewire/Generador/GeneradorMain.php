<?php

namespace App\Livewire\Generador;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Account;

/**
 * Componente contenedor principal para herramientas de IA.
 *
 * - Mantiene el estado de la herramienta activa
 * - Registra un historial unificado de resultados
 * - Expone eventos para coordinarse con herramientas hijas
 * - Gestiona la selección de cuenta para todas las herramientas
 */
class GeneradorMain extends Component
{
    /**
     * Herramienta activa actual.
     * Valores: prompt-generator | image-generator | image-editor | video-generator | video-editor | image-editor-expand | image-editor-fill
     */
    public string $activeTool = 'prompt-generator';

    /**
     * Historial cronológico de resultados generados o editados.
     * Formato de cada item: ['type' => string, 'url' => string, 'date' => string ISO8601]
     */
    public array $history = [];

    /**
     * Contador para forzar re-render del historial.
     * Se incrementa cada vez que se agrega un item para forzar que Livewire
     * detecte el cambio y re-renderice el componente.
     */
    public int $historyVersion = 0;

    /**
     * Lista de errores recientes para mostrar al usuario.
     * Formato: ['message' => string, 'type' => string, 'date' => string, 'tool' => string]
     */
    public array $errors = [];

    /**
     * Cuenta seleccionada actualmente para las generaciones.
     * Null significa que no hay cuenta seleccionada (super admin sin selección)
     */
    public ?int $selectedAccountId = null;

    /**
     * Cuentas disponibles para el usuario actual.
     * Para super admins: todas las cuentas del sistema
     * Para usuarios normales: solo sus cuentas asignadas
     */
    public array $availableAccounts = [];

    /**
     * Indica si el usuario actual es super admin
     */
    public bool $isSuperAdmin = false;

    /**
     * Catálogo de herramientas disponibles y sus etiquetas.
     */
    public array $tools = [
        'image-generator' => [
            'label' => 'Imagen', 
            'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'
        ],
        'image-editor' => [
    'label' => 'Editor de imagen', 
    'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16
               m-2-2l1.586-1.586a2 2 0 012.828 0L20 14
               m-6-6h.01
               M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z
               M21 2v6
               M18 5h6'
        ],

        'video-generator' => [
            'label' => 'Video', 
            'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'
        ],
        'video-editor' => [
            'label' => 'Editor de video',
            'icon'  => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z M19 2v4M17 4h4'
        ],
        'image-editor-expand' => [
            'label' => 'Editor de imágenes · Expand', 
            'icon' => 'M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4'
        ],
        'image-editor-fill' => [
            'label' => 'Editor de imágenes · Fill', 
            'icon' => 'M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 16H9v-2.828zM5 20h14a2 2 0 002-2V7a2 2 0 00-2-2h-7l-2-2H5a2 2 0 00-2 2v11a2 2 0 002 2z'
        ],
        'prompt-generator' => [
            'label' => 'Prompt', 
            'icon' => 'M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-5M18.5 2.5a2.121 2.121 0 113 3L12 15l-4 1 1-4 9.5-9.5z'
        ],
        
    ];

    /**
     * Permite iniciar con una herramienta específica vía parámetro.
     */
    public function mount(?string $tool = null): void
    {
        $storedActive = session('generador.activeTool');
        if ($storedActive && isset($this->tools[$storedActive])) {
            $this->activeTool = $storedActive;
        }

        if ($tool && isset($this->tools[$tool])) {
            $this->activeTool = $tool;
        }
        $this->dispatch('scrollToLatest');

        $this->history = session('generador.history', []);
        $this->historyVersion = session('generador.historyVersion', 0);
        $this->errors = session('generador.errors', []);

        // ✅ Inicializar selección de cuenta
        $this->initializeAccountSelection();
    }

    /**
     * Inicializa la selección de cuenta según el tipo de usuario
     */
    private function initializeAccountSelection(): void
    {
        $user = Auth::user();
        
        if (!$user) {
            return;
        }
        
        // Verificar si es super admin
        $this->isSuperAdmin = $user->haveFullAccess();
        
        if ($this->isSuperAdmin) {
            // Super admin: obtener TODAS las cuentas del sistema
            $this->availableAccounts = Account::select('id', 'name')
                ->where('status', 1) // Solo cuentas activas
                ->orderBy('name')
                ->get()
                ->map(function($account) {
                    return [
                        'id' => $account->id,
                        'name' => $account->name
                    ];
                })
                ->toArray();
                
            // Para super admin, intentar obtener de sesión pero no auto-seleccionar
            $sessionAccountId = session('generador.selectedAccountId');
            
            // Validar que la cuenta de sesión exista en las cuentas disponibles
            if ($sessionAccountId && collect($this->availableAccounts)->pluck('id')->contains($sessionAccountId)) {
                $this->selectedAccountId = $sessionAccountId;
            }
            // Si no hay sesión, selectedAccountId queda en null (obligando a seleccionar)
        } else {
            // Usuario normal: solo sus cuentas asignadas
            $this->availableAccounts = $user->accounts()
                ->select('id', 'name')
                ->where('status', 1)
                ->orderBy('name')
                ->get()
                ->map(function($account) {
                    return [
                        'id' => $account->id,
                        'name' => $account->name
                    ];
                })
                ->toArray();
            
            // Auto-seleccionar la primera cuenta si existe
            if (!empty($this->availableAccounts)) {
                // Intentar obtener de sesión primero
                $sessionAccountId = session('generador.selectedAccountId');
                
                if ($sessionAccountId && collect($this->availableAccounts)->pluck('id')->contains($sessionAccountId)) {
                    $this->selectedAccountId = $sessionAccountId;
                } else {
                    // Seleccionar la primera cuenta disponible
                    $this->selectedAccountId = $this->availableAccounts[0]['id'];
                }
            }
        }
        
        // Guardar en sesión
        if ($this->selectedAccountId) {
            session(['generador.selectedAccountId' => $this->selectedAccountId]);
        }
        
        Log::info('🏢 Cuentas inicializadas para generador', [
            'user_id' => $user->id,
            'is_super_admin' => $this->isSuperAdmin,
            'available_accounts' => count($this->availableAccounts),
            'selected_account_id' => $this->selectedAccountId
        ]);
    }

    /**
     * Hook de Livewire que se ejecuta cuando selectedAccountId cambia
     * Este se dispara automáticamente por wire:model.live
     */
    public function updatedSelectedAccountId($value): void
    {
        // Validar que el usuario tenga acceso a esta cuenta
        if ($value) {
            $hasAccess = $this->isSuperAdmin || 
                         collect($this->availableAccounts)->pluck('id')->contains((int)$value);
            
            if (!$hasAccess) {
                $this->dispatch('addErrorToList', 
                    message: 'No tienes acceso a esta cuenta', 
                    type: 'validation'
                );
                // Revertir al valor anterior
                $this->selectedAccountId = session('generador.selectedAccountId');
                return;
            }
        }
        
        // Guardar en sesión
        session(['generador.selectedAccountId' => $value]);
        
        Log::info('🔄 Cuenta seleccionada cambiada', [
            'user_id' => Auth::id(),
            'account_id' => $value,
            'via' => 'updatedSelectedAccountId'
        ]);
        
        // Notificar a las herramientas del cambio
        $this->dispatch('accountChanged', accountId: $value);
    }

    /**
     * Cambiar la cuenta seleccionada (método alternativo para llamadas manuales)
     */
    public function selectAccount(?int $accountId): void
    {
        $this->selectedAccountId = $accountId;
        // El hook updatedSelectedAccountId se encargará del resto
    }

    /**
     * Listener: Cambiar herramienta activa desde otros componentes.
     */
    #[On('toolChanged')]
    public function setActiveTool(string $tool): void
    {
        if (! isset($this->tools[$tool])) {
            return;
        }

        $this->activeTool = $tool;
        session(['generador.activeTool' => $tool]);
    }

    /**
     * Listener: Agregar un item al historial unificado.
     *
     * Puede ser llamado desde componentes hijos:
     *   $this->dispatch('addToHistory', type: 'image', url: $signedUrl)
     *   $this->dispatch('addToHistory', type: 'image/generate', images: $images, generationId: $id, ...)
     */
    #[On('addToHistory')]
    public function addToHistory(
        string $type, 
        ?string $url = null, 
        ?array $images = null,
        ?string $generationId = null,
        ?string $prompt = null,
        ?string $model = null,
        ?string $ratio = null,
        ?int $count = null,
        ?string $date = null,
        ?string $generatedPrompt = null,
        ?string $documento = null
    ): void
    {
        $entry = [
            'type' => $type,
            'date' => $date ?: now()->toIso8601String(),
        ];

        // Si es una generación múltiple de imágenes
        if ($images && $generationId) {
            $entry['images'] = $images;
            $entry['generationId'] = $generationId;
            $entry['prompt'] = $prompt;
            $entry['model'] = $model;
            $entry['ratio'] = $ratio;
            $entry['count'] = $count;
        } 
        // Si es una generación de prompt
        elseif ($type === 'prompt/generate') {
            $entry['prompt'] = $prompt;
            $entry['generatedPrompt'] = $generatedPrompt;
            $entry['model'] = $model;
            $entry['documento'] = $documento;
        }
        // Si es una imagen individual (compatibilidad hacia atrás)
        elseif ($url) {
            $entry['url'] = $url;
        }

        // Agregar al historial
        $this->history[] = $entry;
        
        // ✅ SOLUCIÓN: Incrementar versión para forzar re-render
        // Esto hace que Livewire detecte un cambio de estado y re-renderice
        $this->historyVersion++;
        
        // Guardar en sesión
        session(['generador.history' => $this->history]);
        session(['generador.historyVersion' => $this->historyVersion]);
        
        // Log para debug
        \Illuminate\Support\Facades\Log::info('📝 Historial actualizado', [
            'totalItems' => count($this->history),
            'version' => $this->historyVersion,
            'lastEntry' => $entry['type'] ?? 'unknown'
        ]);
        
        // ✅ NUEVO: Disparar evento de historial actualizado
        $this->dispatch('historyUpdated', version: $this->historyVersion);
        
        // Disparar evento para scroll automático
        $this->dispatch('scrollToLatest');
    }

    /** Vacía el historial guardado. */
    public function clearHistory(): void
    {
        $this->history = [];
        $this->historyVersion = 0;
        session()->forget('generador.history');
        session()->forget('generador.historyVersion');
    }

    /**
     * Listener: Agregar un error al listado de errores.
     * 
     * Puede ser llamado desde componentes hijos:
     *   $this->dispatch('addErrorToList', message: 'Error generando imagen', type: 'generation', tool: 'image-generator')
     */
    #[On('addErrorToList')]
    public function addErrorToList(string $message, string $type = 'general', ?string $tool = null): void
    {
        $error = [
            'message' => $message,
            'type' => $type,
            'tool' => $tool ?: $this->activeTool,
            'date' => now()->toIso8601String(),
            'id' => uniqid('error_')
        ];

        // Agregar al inicio para mostrar errores más recientes primero
        array_unshift($this->errors, $error);
        
        // Limitar a los últimos 10 errores para no sobrecargar la UI
        $this->errors = array_slice($this->errors, 0, 10);
        
        session(['generador.errors' => $this->errors]);
    }

    /** Eliminar un error específico por ID */
    public function dismissError(string $errorId): void
    {
        $this->errors = array_filter($this->errors, function($error) use ($errorId) {
            return $error['id'] !== $errorId;
        });
        
        // Re-indexar el array
        $this->errors = array_values($this->errors);
        session(['generador.errors' => $this->errors]);
    }

    /** Vacía todos los errores */
    public function clearErrors(): void
    {
        $this->errors = [];
        session()->forget('generador.errors');
    }


    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.generador.generador-main');
    }
}


