<div>
<div class="flex h-screen bg-white text-gray-900" x-data="{
    sidebarOpen: true,
    showDocumentModal: @entangle('showDocumentSelector'),
    fileUploadError: null,
    isUploading: false,
    validateFileBeforeUpload(event) {
        const file = event.target.files[0];
        if (!file) {
            this.fileUploadError = null;
            return true;
        }
        
        this.fileUploadError = null;
        
        // Extensiones permitidas: documentos + imágenes
        const allowedDocExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];
        const allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        const allowedExtensions = [...allowedDocExtensions, ...allowedImageExtensions];
        const fileExtension = file.name.split('.').pop().toLowerCase();
        
        if (!allowedExtensions.includes(fileExtension)) {
            this.fileUploadError = '❌ Formato no permitido. Permitidos: Imágenes (JPG, PNG, GIF, WebP) o Documentos (PDF, Word, Excel, CSV, TXT)';
            setTimeout(() => { this.fileUploadError = null; }, 5000);
            return false;
        }
        
        const maxSize = 15 * 1024 * 1024; // 15MB
        if (file.size > maxSize) {
            this.fileUploadError = '❌ Archivo demasiado grande. Máximo 15MB';
            setTimeout(() => { this.fileUploadError = null; }, 5000);
            return false;
        }
        
        this.fileUploadError = null;
        return true;
    }
}">
    
    <!-- Left Sidebar: Chat History (Colapsable) -->
    <aside
        x-show="sidebarOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="w-80 h-screen bg-gray-100 flex flex-col border-r border-gray-300 lg:relative fixed top-0 left-0 bottom-0 z-40"
    >
        <!-- Sidebar Header -->
        <div class="p-4 border-b border-gray-300 flex items-center justify-between">
            <h1 class="text-xl font-bold text-gray-800">ChatGodai</h1>
            <button
                type="button"
                @click="sidebarOpen = false"
                class="lg:hidden p-2 hover:bg-gray-200 rounded-lg transition-colors"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        {{-- Selector de cuenta --}}
        <div class="px-4 py-3 border-b border-gray-300 bg-white">
            @if(count($availableAccounts) > 0)
                <label for="chat-account-selector" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                    Cuenta
                </label>
                <select
                    id="chat-account-selector"
                    wire:model.live="selectedAccountId"
                    class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-black focus:ring-black"
                    @if(!$isSuperAdmin && count($availableAccounts) === 1) disabled @endif
                >
                    @if($isSuperAdmin)
                        <option value="">-- Seleccionar cuenta --</option>
                    @endif
                    @foreach($availableAccounts as $account)
                        <option value="{{ $account['id'] }}">{{ $account['name'] }}</option>
                    @endforeach
                </select>
            @elseif($isSuperAdmin)
                <p class="text-xs text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-lg px-2 py-2">
                    No hay cuentas activas en el sistema.
                </p>
            @else
                <p class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-2 py-2">
                    No tienes cuentas asignadas.
                </p>
            @endif
        </div>

        <!-- Chat History List (igual que old-chat-component) -->
        <div class="flex-1 overflow-y-auto px-2 mt-6">
            @if(!empty($conversations))
                @foreach($conversations as $conv)
                    @php $isCurrent = $conv['is_current'] ?? false; @endphp
                    <div
                        wire:click="selectConversation({{ json_encode($conv['session_key']) }})"
                        class="mb-1 px-3 py-2 rounded-lg cursor-pointer transition-colors group {{ $isCurrent ? 'bg-black' : 'hover:bg-gray-200' }}"
                    >
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 rounded flex items-center justify-center text-xs font-bold flex-shrink-0
                                @if($conv['agent_key'] === 'openai') bg-gradient-to-br from-blue-500 to-cyan-500 text-white
                                @elseif($conv['agent_key'] === 'claude') bg-gradient-to-br from-orange-500 to-pink-500 text-white
                                @else bg-gradient-to-br from-purple-500 to-indigo-500 text-white
                                @endif">
                                {{ substr($conv['agent_name'], 0, 3) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm {{ $isCurrent ? 'text-white' : 'text-gray-700' }} truncate">
                                        {{ $conv['agent_name'] }}
                                    </span>
                                </div>
                                <p class="text-xs {{ $isCurrent ? 'text-gray-300' : 'text-gray-600' }} truncate mt-1">
                                    {{ $conv['last_message'] ?? $conv['title'] }}
                                </p>
                                <div class="flex items-center justify-between mt-1">
                                    <span class="text-xs {{ $isCurrent ? 'text-gray-400' : 'text-gray-500' }}">{{ $conv['last_activity'] ?? $conv['last_message_at_formatted'] }}</span>
                                    @if(!$isCurrent)
                                        <button
                                            type="button"
                                            wire:click.stop="deleteConversation({{ json_encode($conv['session_key']) }})"
                                            class="text-xs text-red-400 hover:text-red-300 opacity-0 group-hover:opacity-100 transition-opacity"
                                            title="Eliminar conversación"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="mb-1 px-3 py-2 text-center">
                    <p class="text-sm text-gray-500">No hay conversaciones</p>
                </div>
            @endif
        </div>

        <!-- New Chat Button -->
        <div class="p-3 border-t border-gray-300">
            <button
                type="button"
                wire:click="startNewChat"
                class="w-full py-2.5 px-4 bg-black  text-white font-medium rounded-lg transition-colors duration-200 flex items-center justify-center gap-2 text-sm"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Nuevo Chat</span>
            </button>
        </div>

        <!-- Model & Reasoning Selector -->
        <div class="px-3 pb-3 pt-2" x-data="{ open: false }">
            <button
                type="button"
                @click="open = !open"
                class="w-full flex items-center justify-between gap-2 bg-white hover:bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition-colors"
            >
                <div class="flex items-center gap-2 min-w-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span class="truncate">{{ $this->getCurrentModelDisplayName() }}</span>
                    @if($this->currentModelSupportsReasoningEffort() && count($this->getReasoningOptionsForCurrentModel()) > 1)
                        <span class="text-xs text-gray-400 flex-shrink-0">· {{ $this->getCurrentReasoningLabel() }}</span>
                    @endif
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 flex-shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div
                x-show="open"
                x-cloak
                @click.away="open = false"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-1"
                class="mt-2 bg-white border border-gray-200 rounded-xl p-3 shadow-lg"
            >
                <?php
                    $availableProviders = $this->getAvailableProviders();
                    $providerLabels = ['openai' => 'OpenAI', 'claude' => 'Claude', 'gemini' => 'Gemini'];
                ?>
                @if(count($availableProviders) > 1)
                    <p class="text-xs text-gray-500 mb-2">Proveedor</p>
                    <div class="grid grid-cols-3 gap-1.5 mb-3">
                        @foreach($availableProviders as $prov)
                            <button
                                type="button"
                                wire:click="switchProvider('{{ $prov }}')"
                                class="px-2 py-1.5 text-xs rounded-lg font-medium transition-colors {{ $selectedProvider === $prov ? 'bg-black text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700' }}"
                            >{{ $providerLabels[$prov] ?? $prov }}</button>
                        @endforeach
                    </div>
                @endif

                <p class="text-xs text-gray-500 mb-2">Modelo</p>
                <div class="grid grid-cols-1 gap-1.5">
                    @foreach($this->getAvailableModels() as $modelId => $modelInfo)
                        <button
                            type="button"
                            wire:click="switchModel('{{ $modelId }}')"
                            @click="open = false"
                            class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors {{ $selectedModel === $modelId ? 'bg-black text-white' : 'bg-gray-100 hover:bg-gray-800 hover:text-white text-gray-700' }}"
                        >
                            <span class="font-medium">{{ $modelInfo['name'] }}</span>
                            @if($selectedModel === $modelId)
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            @endif
                        </button>
                    @endforeach
                </div>

                @if($this->currentModelSupportsReasoningEffort() && count($this->getReasoningOptionsForCurrentModel()) > 1)
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-xs text-gray-500 mb-2">Razonamiento</p>
                        <div class="grid grid-cols-1 gap-1.5">
                            @foreach($this->getReasoningOptionsForCurrentModel() as $opt)
                                <button
                                    type="button"
                                    wire:click="$set('reasoningEffort', '{{ $opt }}')"
                                    @click="open = false"
                                    class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors {{ $reasoningEffort === $opt ? 'bg-black text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700' }}"
                                >
                                    <span>{{ $this->getReasoningOptionLabel($opt) }}</span>
                                    @if($reasoningEffort === $opt)
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Bottom Actions -->
        <div class="p-3 border-t border-gray-300 flex items-center justify-between">
            <button type="button" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Prompts">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <button type="button" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Settings">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </button>
            <button type="button" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="More">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                </svg>
            </button>
        </div>
    </aside>

    <!-- Overlay for mobile -->
    <div
        x-show="sidebarOpen"
        @click="sidebarOpen = false"
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden"
    ></div>

    <!-- Main Chat Area -->
    <main class="flex-1 flex flex-col bg-white relative overflow-hidden min-w-0">
        <!-- Header -->
        <header class="flex-shrink-0 flex items-center justify-between px-4 lg:px-6 py-3 border-b border-gray-300 bg-gray-50">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="sidebarOpen = !sidebarOpen"
                    class="p-2 hover:bg-gray-200 rounded-lg transition-colors"
                >
                    <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <h2 class="text-base font-semibold text-gray-800">ChatGodai</h2>
            </div>
            
            <div class="flex items-center gap-3">
                <!-- Botón para limpiar chat actual -->
                <button 
                    wire:click="clearChat" 
                    class="p-2 hover:bg-gray-200 rounded-lg transition-colors group"
                    title="Limpiar chat"
                >
                    <svg class="w-5 h-5 text-gray-600 group-hover:text-red-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </div>
        </header>
        
        <!-- Messages Area -->
        <div id="chat-messages" class="flex-1 overflow-y-auto p-4 lg:p-6 space-y-6" style="height: 0;">
            <!-- Empty State (mostrar solo cuando no hay mensajes) -->
            @if(empty($messages))
                <div class="h-full flex items-center justify-center">
                    <div class="text-center">
                        <h1 class="text-4xl lg:text-5xl font-normal text-gray-800 mb-2">¿Por dónde empezamos?</h1>
                        @if(auth()->check() && auth()->user()->name)
                            <p class="text-lg text-gray-500">¡Hola, {{ auth()->user()->name }}!</p>
                        @endif
                    </div>
                </div>
            @endif
            
            <!-- Contenedor de mensajes -->
            <div class="max-w-4xl mx-auto">
                @foreach($messages as $index => $message)
                    @if($message['role'] === 'assistant')
                        <!-- Assistant Message -->
                        <div wire:key="msg-assistant-{{ $index }}-{{ $message['timestamp'] }}-{{ md5($message['content']) }}" class="flex items-start gap-3 mb-6">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="bg-gray-100 px-4 py-3 rounded-2xl rounded-tl-md">
                                    <div 
                                        class="text-gray-800 text-sm leading-relaxed assistant-message" 
                                        data-message-id="msg-{{ $index }}-{{ $message['timestamp'] }}"
                                    >{{ $message['content'] }}</div>
                                </div>
                            </div>
                        </div>
                    @else
                        <!-- User Message -->
                        <div wire:key="msg-user-{{ $index }}-{{ $message['timestamp'] }}-{{ md5($message['content']) }}" class="flex items-start gap-3 mb-6 justify-end">
                            <div class="flex-1 flex justify-end">
                                <div class="flex flex-col items-end gap-2">
                                    <!-- Documentos adjuntos (si existen y no están vacíos) -->
                                    @if(isset($message['attachments']) && is_array($message['attachments']) && !empty($message['attachments']))
                                        @foreach($message['attachments'] as $attachment)
                                            @if(isset($attachment['type']) && isset($attachment['name']))
                                                @if($attachment['type'] === 'genesis')
                                                    <!-- Badge para Documento Genesis -->
                                                    <div class="bg-gradient-to-r from-purple-600 to-purple-700 px-4 py-3 rounded-xl flex items-center gap-3 max-w-sm shadow-md">
                                                        <div class="flex-shrink-0">
                                                            <div class="w-10 h-10 rounded-lg bg-white bg-opacity-20 flex items-center justify-center">
                                                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                                                </svg>
                                                            </div>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-white text-sm font-medium truncate">{{ $attachment['name'] }}</p>
                                                            <p class="text-purple-200 text-xs">📄 Documento Genesis</p>
                                                        </div>
                                                    </div>
                                                @elseif($attachment['type'] === 'image')
                                                    <!-- Preview de Imagen compacto -->
                                                    <div class="bg-gray-700 rounded-xl overflow-hidden shadow-md flex items-center gap-3 pr-3 max-w-xs">
                                                        @if(isset($attachment['preview']))
                                                            <img 
                                                                src="{{ $attachment['preview'] }}" 
                                                                alt="{{ $attachment['name'] }}" 
                                                                class="w-16 h-16 object-cover flex-shrink-0"
                                                            >
                                                        @endif
                                                        <div class="flex-1 min-w-0 py-2">
                                                            <p class="text-white text-sm font-medium truncate">{{ $attachment['name'] }}</p>
                                                            <p class="text-gray-400 text-xs">Imagen</p>
                                                        </div>
                                                    </div>
                                                @elseif($attachment['type'] === 'external_file')
                                                    <!-- Badge para Archivo Externo -->
                                                    <div class="bg-gray-700 px-4 py-3 rounded-xl flex items-center gap-3 max-w-sm shadow-md">
                                                        <div class="flex-shrink-0">
                                                            <div class="w-10 h-10 rounded-lg bg-blue-500 flex items-center justify-center">
                                                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                                </svg>
                                                            </div>
                                                        </div>
                                                        <div class="flex-1 min-w-0">
                                                            <p class="text-white text-sm font-medium truncate">{{ $attachment['name'] }}</p>
                                                            <p class="text-gray-400 text-xs">Archivo externo</p>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endif
                                        @endforeach
                                    @endif
                                    
                                    <!-- Mensaje de texto -->
                                    <div class="bg-gray-800 px-4 py-3 rounded-2xl rounded-br-md max-w-2xl">
                                        <p class="text-white text-sm leading-relaxed whitespace-pre-wrap">{{ $message['content'] }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-gray-600 to-gray-700 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach

                <!-- Typing Indicator -->
                @if($isLoading)
                    <div class="flex items-start gap-3 mb-6">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 flex items-center justify-center animate-pulse">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <div class="bg-gray-100 px-4 py-3 rounded-2xl rounded-tl-md">
                                <div class="flex items-center space-x-2">
                                    <span class="text-sm text-gray-600 mr-2">
                                        @if($selectedProvider === 'openai') OpenAI
                                        @elseif($selectedProvider === 'claude') Claude
                                        @elseif($selectedProvider === 'gemini') Gemini
                                        @else Chat
                                        @endif
                                        está escribiendo
                                    </span>
                                    <div class="flex items-center space-x-1">
                                        <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce"></div>
                                        <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 0.15s;"></div>
                                        <div class="w-2 h-2 bg-gray-500 rounded-full animate-bounce" style="animation-delay: 0.3s;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Input Area (Fixed at bottom) -->
        <div class="flex-shrink-0 border-t border-gray-300 bg-gray-50 p-4">
            <div class="max-w-4xl mx-auto">
                <form wire:submit.prevent="sendMessage">
                    <!-- Toolbar -->
                    <div class="flex items-center gap-2 mb-3 px-2">
                        <!-- Nivel de Razonamiento (solo si el modelo lo soporta y tiene más de una opción) -->
                        @if($this->currentModelSupportsReasoningEffort() && count($this->getReasoningOptionsForCurrentModel()) > 1)
                            <div x-data="{ open: false }" class="relative flex flex-col items-center">
                                <button
                                    type="button"
                                    @click="open = !open"
                                    class="p-2 rounded-lg transition-colors hover:bg-gray-200"
                                    :class="open ? 'bg-gray-200 text-gray-800' : 'text-gray-600'"
                                    title="Nivel de razonamiento"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </button>
                                <span class="text-xs text-gray-500 mt-0.5 max-w-[80px] truncate text-center">{{ $this->getCurrentReasoningLabel() }}</span>
                                
                                <div
                                    x-show="open"
                                    x-cloak
                                    @click.away="open = false"
                                    class="absolute bottom-full left-0 mb-1 bg-white border border-gray-200 rounded-xl p-4 w-[240px] z-20 shadow-lg"
                                    style="display: none;"
                                >
                                    <div class="text-center mb-3 text-gray-600 font-medium">Nivel de Razonamiento</div>
                                    <div class="grid grid-cols-1 gap-2">
                                        @foreach($this->getReasoningOptionsForCurrentModel() as $opt)
                                            <button
                                                type="button"
                                                wire:click="$set('reasoningEffort', '{{ $opt }}')"
                                                @click="open = false"
                                                class="bg-{{ $reasoningEffort === $opt ? 'black text-white' : 'gray-100 hover:bg-gray-200 text-gray-800' }} rounded text-center py-2 text-sm flex justify-between items-center px-3"
                                            >
                                                <span>{{ $this->getReasoningOptionLabel($opt) }}</span>
                                                @if($reasoningEffort === $opt)
                                                    <span class="text-xs">✓</span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                        
                        <!-- Botón para adjuntar archivo -->
                        <button 
                            type="button" 
                            onclick="document.getElementById('file-upload-input').click()"
                            class="p-2 rounded-lg transition-colors {{ $uploadedFileInfo ? 'bg-gray-200 text-gray-800 hover:bg-gray-300' : 'text-gray-600 hover:bg-gray-200' }}" 
                            title="{{ $uploadedFileInfo ? 'Archivo adjunto' : 'Adjuntar archivo' }}"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                            </svg>
                        </button>
                        
                        <!-- Input oculto para subir archivos e imágenes -->
                        <input 
                            type="file" 
                            id="file-upload-input"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.gif,.webp"
                            class="hidden"
                            x-on:change="
                                const file = $event.target.files[0];
                                const input = $event.target;
                                
                                if (!file) {
                                    fileUploadError = null;
                                    return;
                                }
                                
                                const isValid = validateFileBeforeUpload($event);
                                
                                if (isValid) {
                                    isUploading = true;
                                    @this.upload('uploadedFile', file, 
                                        (uploadedFilename) => {
                                            // Success callback
                                            isUploading = false;
                                            input.value = '';
                                        },
                                        () => {
                                            // Error callback
                                            isUploading = false;
                                            fileUploadError = '❌ Error al subir el archivo';
                                            setTimeout(() => { fileUploadError = null; }, 5000);
                                            input.value = '';
                                        },
                                        (event) => {
                                            // Progress callback (opcional)
                                        }
                                    );
                                } else {
                                    input.value = '';
                                }
                            "
                        >
                        
                        <!-- Botón para seleccionar documento Genesis -->
                        <button 
                            type="button" 
                            wire:click="toggleDocumentSelector"
                            class="p-2 hover:bg-gray-200 rounded-lg transition-colors {{ $selectedDocument ? 'bg-black text-white' : 'text-gray-600' }}" 
                            title="{{ $selectedDocument ? 'Documento: ' . ($documentInfo['name'] ?? '') : 'Seleccionar Génesis' }}"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Mensaje de error frontend (Alpine.js) -->
                    <div x-show="fileUploadError" x-cloak class="mb-3 px-2">
                        <div class="bg-black border border-gray-700 text-white px-3 py-2 rounded-lg text-sm flex items-center justify-between">
                            <span x-text="fileUploadError"></span>
                            <button @click="fileUploadError = null" class="ml-2 text-gray-300 hover:text-white transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Loader mientras se sube archivo (Alpine.js) -->
                    <div x-show="isUploading" x-cloak class="mb-3 px-2">
                        <div class="bg-gray-100 border border-gray-300 rounded-lg p-3">
                            <div class="flex items-center justify-center gap-3">
                                <svg class="animate-spin h-5 w-5 text-purple-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-sm text-gray-600">Subiendo archivo...</span>
                            </div>
                        </div>
                    </div>

                    <!-- Información del archivo/imagen subido -->
                    <div x-show="!isUploading">
                    @if($uploadedFileInfo)
                        <div class="mb-3 px-2">
                            <div class="bg-gray-100 border border-gray-300 rounded-lg p-2">
                                @if(isset($uploadedFileInfo['is_image']) && $uploadedFileInfo['is_image'] && (isset($uploadedFileInfo['preview']) || isset($uploadedFileInfo['s3_url'])))
                                    <!-- Preview de imagen compacto -->
                                    <div class="flex items-center gap-3">
                                        <div class="relative flex-shrink-0">
                                            <img 
                                                src="{{ $uploadedFileInfo['preview'] ?? $uploadedFileInfo['s3_url'] }}" 
                                                alt="{{ $uploadedFileInfo['name'] }}" 
                                                class="w-16 h-16 object-cover rounded-lg"
                                            >
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">{{ $uploadedFileInfo['name'] }}</p>
                                            <p class="text-xs text-gray-500">{{ number_format($uploadedFileInfo['size'] / 1024 / 1024, 2) }} MB</p>
                                        </div>
                                        <button 
                                            type="button"
                                            wire:click="clearUploadedFile"
                                            class="flex-shrink-0 text-gray-500 hover:text-gray-700 p-1 rounded transition-colors"
                                            title="Eliminar imagen"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                @else
                                    <!-- Preview de documento -->
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">{{ $uploadedFileInfo['name'] }}</p>
                                                <p class="text-xs text-gray-600">{{ number_format($uploadedFileInfo['size'] / 1024 / 1024, 2) }} MB</p>
                                            </div>
                                        </div>
                                        <button 
                                            type="button"
                                            wire:click="clearUploadedFile"
                                            class="text-gray-600 hover:text-gray-800 p-1 rounded transition-colors"
                                            title="Eliminar archivo"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                    </div>

                    <!-- Mensaje de error de Livewire -->
                    @error('uploadedFile')
                        <div class="mb-3 px-2">
                            <div class="bg-black border border-gray-700 text-white px-3 py-2 rounded-lg text-sm">
                                {{ $message }}
                            </div>
                        </div>
                    @enderror

                    @error('newMessage')
                        <div class="mb-3 px-2">
                            <div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2 rounded-lg text-sm flex items-center gap-2">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                {{ $message }}
                            </div>
                        </div>
                    @enderror

                    <!-- Input Field -->
                    <div class="flex items-end gap-3 bg-white rounded-xl border border-gray-300 p-2">
                        <textarea 
                            wire:model="newMessage" 
                            placeholder="Pregunta lo que quieras" 
                            rows="1"
                            class="flex-1 bg-transparent px-2 py-2 text-sm text-gray-800 placeholder-gray-500 focus:outline-none resize-auto border-none"
                            style="min-height: 40px; max-height: 200px;"
                            @if($isLoading) disabled @endif
                        ></textarea>
                        <button 
                            type="submit" 
                            class="flex-shrink-0 px-4 py-2 bg-gradient-to-br from-blue-600 to-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 text-sm font-medium"
                            @if($isLoading) disabled @endif
                        >
                            <span>Enviar</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Document Selector Modal (Alpine.js - no re-renderiza) -->
    <div 
        x-show="showDocumentModal" 
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
        @keydown.escape.window="showDocumentModal = false"
    >
            <div 
                class="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[80vh] flex flex-col"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                @click.away="showDocumentModal = false"
            >
                <!-- Modal Header -->
                <div class="flex items-center justify-between p-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Seleccionar Documento Génesis</h3>
                    <button 
                        @click="showDocumentModal = false"
                        class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Documents List -->
                <div class="flex-1 overflow-y-auto p-4">
                    @if(!empty($documents))
                        <div class="grid gap-3">
                            @foreach($documents as $document)
                                <div 
                                    wire:click="selectDocument('{{ $document['id'] }}')"
                                    class="p-3 {{ $selectedDocument == $document['id'] ? 'bg-gray-50 border-2 border-black' : 'hover:bg-gray-50 border border-gray-200' }} rounded-lg cursor-pointer transition-colors"
                                >
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-indigo-500 rounded flex items-center justify-center text-xs font-bold text-white">
                                            GN
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between">
                                                <h4 class="text-sm font-medium text-gray-800 truncate">
                                                    {{ $document['name'] }}
                                                </h4>
                                                @if($selectedDocument == $document['id'])
                                                    <svg class="w-4 h-4 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                    </svg>
                                                @endif
                                            </div>
                                            <p class="text-xs text-gray-600 mt-1">
                                                Génesis • {{ $document['account'] }}
                                            </p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                {{ $document['date'] }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="text-gray-500">No hay documentos Génesis disponibles</p>
                        </div>
                    @endif
                </div>

                <!-- Selected Document Info -->
                @if($documentInfo)
                    <div class="p-4 border-t border-gray-200 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-sm font-medium text-gray-800">Documento Seleccionado</h4>
                                <p class="text-xs text-gray-600">{{ $documentInfo['name'] }}</p>
                            </div>
                            <button 
                                wire:click="removeSelectedDocument"
                                class="text-gray-600 hover:text-gray-800 text-sm"
                            >
                                Quitar
                            </button>
                        </div>
                    </div>
                @endif

                <!-- Modal Footer -->
                <div class="p-4 border-t border-gray-200 flex justify-end gap-3">
                    <button 
                        @click="showDocumentModal = false"
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button 
                        wire:click="confirmDocumentSelection"
                        @click="showDocumentModal = false"
                        class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-900 transition-colors"
                    >
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Markdown to HTML Conversion -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
/* Alpine x-cloak */
[x-cloak] { 
    display: none !important; 
}

/* Estilos para markdown */
.assistant-message h1, .assistant-message h2, .assistant-message h3, 
.assistant-message h4, .assistant-message h5, .assistant-message h6 {
    font-weight: bold;
    margin: 0.5em 0;
}

.assistant-message h1 { font-size: 1.2em; }
.assistant-message h2 { font-size: 1.1em; }
.assistant-message h3 { font-size: 1.05em; }

.assistant-message strong {
    font-weight: bold;
}

.assistant-message em {
    font-style: italic;
}

.assistant-message code {
    background-color: rgba(0, 0, 0, 0.1);
    padding: 0.1em 0.3em;
    border-radius: 0.2em;
    font-family: monospace;
    font-size: 0.9em;
}

.assistant-message pre {
    background-color: rgba(0, 0, 0, 0.05);
    padding: 0.5em;
    border-radius: 0.3em;
    overflow-x: auto;
    margin: 0.5em 0;
}

.assistant-message pre code {
    background: none;
    padding: 0;
}

.assistant-message ul, .assistant-message ol {
    margin: 0.5em 0;
    padding-left: 1.5em;
}

.assistant-message ul {
    list-style-type: disc;
}

.assistant-message ol {
    list-style-type: decimal;
}

.assistant-message li {
    margin: 0.2em 0;
}

.assistant-message blockquote {
    border-left: 3px solid #ccc;
    margin: 0.5em 0;
    padding-left: 1em;
    color: #666;
}

.assistant-message a {
    color: #2563eb;
    text-decoration: underline;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    marked.setOptions({
        breaks: true,
        gfm: true,
        headerIds: false,
        mangle: false
    });
    
    function isMarkdown(element) {
        const html = element.innerHTML.trim();
        if (/<(div|span|ul|ol|pre|code|table|h[1-6]|blockquote)[\s>]/i.test(html)) {
            return false;
        }
        
        const text = element.textContent.trim();
        const markdownPatterns = [
            /^#{1,6}\s/m, /\*\*.*\*\*/, /__.*__/, /\[.+\]\(.+\)/, /```/,
            /^\s*[-*+]\s/m, /^\s*\d+\.\s/m, /^\s*>\s/m, /`[^`]+`/, /\*[^*]+\*/, /_[^_]+_/
        ];
        
        return markdownPatterns.some(pattern => pattern.test(text));
    }
    
    function convertMarkdownToHtml() {
        // Solo procesar mensajes que NO han sido procesados (sin atributo data-markdown-processed)
        const assistantMessages = document.querySelectorAll('.assistant-message:not([data-markdown-processed])');
        
        assistantMessages.forEach(function(element) {
            try {
                const content = element.textContent.trim();
                if (!content) return;
                
                if (isMarkdown(element)) {
                    const markdownContent = element.textContent;
                    const htmlContent = marked.parse(markdownContent);
                    element.innerHTML = htmlContent;
                }
                
                // Marcar como procesado para evitar re-procesar
                element.setAttribute('data-markdown-processed', 'true');
            } catch (error) {
                console.error('Error al convertir mensaje:', error);
            }
        });
    }
    
    function scrollToBottom() {
        const messagesArea = document.querySelector('.overflow-y-auto.p-4');
        if (messagesArea) {
            requestAnimationFrame(() => {
                messagesArea.scrollTop = messagesArea.scrollHeight;
            });
        }
    }
    
    // Procesar mensajes iniciales
    convertMarkdownToHtml();
    
    // Hook de Livewire - solo procesa mensajes NUEVOS gracias a data-markdown-processed
    Livewire.hook('morph.updated', ({ el, component }) => {
        // Pequeño delay para que el DOM se actualice
        requestAnimationFrame(() => {
            convertMarkdownToHtml(); // Solo procesará los nuevos (sin data-markdown-processed)
            scrollToBottom();
        });
    });
    
    const observer = new MutationObserver(function(mutations) {
        let hasNewMessages = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1 && (node.classList?.contains('assistant-message') || node.querySelector?.('.assistant-message'))) {
                        hasNewMessages = true;
                    }
                });
            }
        });
        
        if (hasNewMessages) {
            requestAnimationFrame(() => {
                convertMarkdownToHtml();
                scrollToBottom();
            });
        }
    });
    
    const messagesContainer = document.getElementById('chat-messages');
    if (messagesContainer) {
        observer.observe(messagesContainer, {
            childList: true,
            subtree: true
        });
    }
    
    Livewire.on('executeAgentResponse', () => {
        setTimeout(scrollToBottom, 100);
    });
});
    </script>
</div>
