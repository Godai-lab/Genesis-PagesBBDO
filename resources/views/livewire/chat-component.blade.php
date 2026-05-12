<div class="flex h-screen bg-white text-gray-900 border-2" x-data="{
    sidebarOpen: true,
    fileUploadError: null,
    validateFileBeforeUpload(event) {
        const file = event.target.files[0];
        if (!file) {
            this.fileUploadError = null;
            return true; // Si no hay archivo, permitir que Livewire limpie el estado
        }
        
        // Limpiar error previo
        this.fileUploadError = null;
        
        // Extensiones permitidas
        const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];
        const fileExtension = file.name.split('.').pop().toLowerCase();
        
        // Validar extensión
        if (!allowedExtensions.includes(fileExtension)) {
            this.fileUploadError = '❌ Formato no permitido. Solo se aceptan: PDF, Word (.doc, .docx), Excel (.xls, .xlsx), CSV, TXT';
            // Ocultar el error después de 5 segundos
            setTimeout(() => { this.fileUploadError = null; }, 5000);
            return false;
        }
        
        // Validar tamaño (15MB máximo)
        const maxSize = 15 * 1024 * 1024; // 15MB
        if (file.size > maxSize) {
            this.fileUploadError = '❌ El archivo es demasiado grande. Tamaño máximo permitido: 15MB';
            // Ocultar el error después de 5 segundos
            setTimeout(() => { this.fileUploadError = null; }, 5000);
            return false;
        }
        
        // Si pasa todas las validaciones, limpiar error y permitir que Livewire procese
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
                @click="sidebarOpen = false"
                class="lg:hidden p-2 hover:bg-gray-200 rounded-lg transition-colors"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Search Bar -->
        <!-- <div class="p-3">
            <div class="relative">
                <input 
                    type="text" 
                    class="w-full pl-9 pr-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent" 
                    placeholder="Search"
                />
                <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
        </div> -->

        <!-- Featured App Card -->
        <!-- <div class="mx-3 mb-3 p-3 bg-gradient-to-br from-blue-600 to-purple-600 rounded-lg">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-white font-semibold text-sm">ChatWise</h3>
                    <p class="text-white/80 text-xs">AI Chat App</p>
                </div>
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                </svg>
            </div>
        </div> -->

        <!-- Time Filter -->
        <!-- <div class="px-3 mb-2">
            <select class="w-full px-3 py-1.5 bg-white border border-gray-300 rounded-lg text-xs text-gray-700 focus:outline-none focus:ring-1 focus:ring-purple-500">
                <option>Últimos 7 días</option>
                <option>Últimos 30 días</option>
                <option>Últimos 3 meses</option>
            </select>
        </div> -->
        
        <!-- Chat History List -->
        <div class="flex-1 overflow-y-auto px-2 mt-6">
            @if(!empty($chatSessions))
                @foreach($chatSessions as $session)
                    <div 
                        wire:click="loadSession('{{ $session['session_id'] }}')"
                        class="mb-1 px-3 py-2 {{ $session['is_current'] ? 'bg-gray-200' : 'hover:bg-gray-200' }} rounded-lg cursor-pointer transition-colors group"
                    >
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 bg-gradient-to-br from-{{ $session['agent_key'] === 'openai' ? 'blue' : ($session['agent_key'] === 'claude' ? 'orange' : 'purple') }}-500 to-{{ $session['agent_key'] === 'openai' ? 'cyan' : ($session['agent_key'] === 'claude' ? 'pink' : 'indigo') }}-500 rounded flex items-center justify-center text-xs font-bold">
                                {{ substr($session['agent_name'], 0, 3) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm {{ $session['is_current'] ? 'text-gray-800' : 'text-gray-700' }} truncate">
                                        {{ $session['agent_name'] }}
                                    </span>
                                    
                                </div>
                                <p class="text-xs text-gray-600 truncate mt-1">
                                    {{ $session['last_message'] }}
                                </p>
                                <div class="flex items-center justify-between mt-1">
                                    <span class="text-xs text-gray-500">{{ $session['last_activity'] }}</span>
                                    @if(!$session['is_current'])
                                        <button 
                                            wire:click.stop="deleteSession('{{ $session['session_id'] }}')"
                                            class="text-xs text-red-400 hover:text-red-300 opacity-0 group-hover:opacity-100 transition-opacity"
                                            title="Eliminar sesión"
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
                wire:click="createNewSession" 
                class="w-full py-2.5 px-4 bg-black  text-white font-medium rounded-lg transition-colors duration-200 flex items-center justify-center gap-2 text-sm"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>Nuevo Chat</span>
            </button>
        </div>

        <!-- Bottom Actions -->
        <div class="p-3 border-t border-gray-300 flex items-center justify-between">
            <button class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Prompts">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <button class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Settings">
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </button>
            <button class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="More">
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
    <main class="flex-1 flex flex-col bg-white relative overflow-hidden">
        <!-- Header -->
        <header class="flex-shrink-0 flex items-center justify-between px-4 lg:px-6 py-3 border-b border-gray-300 bg-gray-50">
            <div class="flex items-center gap-3">
                <button 
                    @click="sidebarOpen = !sidebarOpen"
                    class="p-2 hover:bg-gray-200 rounded-lg transition-colors"
                >
                    <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <h2 class="text-base font-semibold text-gray-800">{{ $availableAgents[$selectedAgent]['name'] }}</h2>
            </div>
            
            <div class="flex items-center gap-3">
                <!-- Select de Proveedor (Agente) -->
                <div class="relative">
                    <select 
                        wire:model.live="selectedAgent" 
                        wire:change="switchAgent($event.target.value)"
                        class="pl-3 pr-8 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500 appearance-none cursor-pointer min-w-[120px]"
                    >
                        @foreach($availableAgents as $key => $agent)
                            <option value="{{ $key }}">{{ $agent['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Select de Modelo -->
                <div class="relative">
                    <select 
                        wire:model.live="selectedModel" 
                        wire:change="switchModel($event.target.value)"
                        class="pl-3 pr-8 py-2 text-sm bg-white border border-gray-300 rounded-lg text-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500 appearance-none cursor-pointer min-w-[180px]"
                    >
                        @foreach($this->getModelsForCurrentAgent() as $modelId => $modelInfo)
                            <option value="{{ $modelId }}">{{ $modelInfo['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Botón para limpiar chat actual -->
                <button 
                    wire:click="clearChat" 
                    class="p-2 hover:bg-gray-200 rounded-lg transition-colors group"
                    title="Limpiar chat actual"
                >
                    <svg class="w-5 h-5 text-gray-600 group-hover:text-red-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
                
                <!-- Botón de configuración (deshabilitado por ahora) -->
                <!-- <button class="p-2 hover:bg-gray-200 rounded-lg transition-colors">
                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </button> -->
            </div>
        </header>
        
        <!-- Messages Area -->
        <div id="chat-messages" class="flex-1 overflow-y-auto p-4 lg:p-6 space-y-6" style="height: 0;">
            <div class="max-w-4xl mx-auto">
                @foreach($messages as $index => $message)
                    @if($message['role'] === 'assistant')
                        <!-- Assistant Message -->
                        <div wire:key="message-{{ $index }}-{{ $message['timestamp'] }}" class="flex items-start gap-3 mb-6">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-600 to-purple-600 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1">
                                <!-- Texto del mensaje del asistente -->
                                <div class="bg-gray-100 px-4 py-3 rounded-2xl rounded-tl-md">
                                    <div 
                                        class="text-gray-800 text-sm leading-relaxed assistant-message" 
                                        data-message-id="msg-{{ $index }}-{{ $message['timestamp'] }}"
                                    >{{ $message['content'] }}</div>
                                </div>
                                
                                <!-- Imágenes generadas por el asistente (FUERA del párrafo) -->
                                @if(isset($message['images']) && !empty($message['images']))
                                    <div class="flex gap-2 mt-3 flex-wrap">
                                        @foreach($message['images'] as $imgIndex => $imageUrl)
                                            <div class="relative group flex-shrink-0">
                                                <div class="max-w-xs rounded-lg overflow-hidden border-2 border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                                                    <img 
                                                        src="{{ $imageUrl }}" 
                                                        alt="Imagen generada {{ $imgIndex + 1 }}" 
                                                        class="w-full h-auto object-contain cursor-pointer hover:opacity-90 transition-opacity"
                                                        style="max-height: 300px;"
                                                        @click="$dispatch('open-lightbox', { imgSrc: '{{ $imageUrl }}', type: 'image' })"
                                                    >
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <!-- User Message -->
                        <div wire:key="message-{{ $index }}-{{ $message['timestamp'] }}" class="flex items-start gap-3 mb-6 justify-end">
                            <div class="flex-1 flex justify-end">
                                <div class="px-4 py-3 rounded-2xl rounded-br-md max-w-2xl justify-end">
                                    <!-- Imágenes del usuario (si las hay) -->
                                    @if(isset($message['images']) && !empty($message['images']))
                                    <div class="flex gap-2 mb-3 flex-wrap justify-end">
                                            @foreach($message['images'] as $imgIndex => $imageUrl)
                                                <div class="relative group flex-shrink-0">
                                                    <div class="w-32 h-32 rounded-lg overflow-hidden border-2 border-white/20">
                                                        <img 
                                                            src="{{ $imageUrl }}" 
                                                            alt="Imagen {{ $imgIndex + 1 }}" 
                                                            class="w-full h-full object-cover cursor-pointer hover:opacity-90 transition-opacity"
                                                            @click="$dispatch('open-lightbox', { imgSrc: '{{ $imageUrl }}', type: 'image' })"
                                                        >
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="bg-gray-800 px-4 py-3 rounded-2xl rounded-br-md max-w-2xl justify-end">
                                        <!-- Texto del mensaje -->
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

                <!-- Typing Indicator (inline con mensajes) -->
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
                                    <span class="text-sm text-gray-600 mr-2">{{ $availableAgents[$selectedAgent]['name'] }} está escribiendo</span>
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
                        <!-- <button type="button" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Voice Input">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                            </svg>
                        </button> -->
                        
                        <!-- Botón para adjuntar (Documentos e Imágenes) -->
                        <div x-data="{ open: false }" class="relative">
                            <button 
                                type="button" 
                                @click="open = !open"
                                class="p-2 rounded-lg transition-colors {{ (!empty($uploadedImages) || $uploadedFileInfo) ? 'bg-gray-200 text-gray-800 hover:bg-gray-300' : 'text-gray-600 hover:bg-gray-200' }}" 
                                title="{{ (!empty($uploadedImages) || $uploadedFileInfo) ? 'Archivos adjuntos' : 'Adjuntar archivo' }}"
                            >
                                <!-- Icono de clip (paperclip) -->
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                            </button>
                            
                            <!-- Menú desplegable -->
                            <div 
                                x-show="open" 
                                x-cloak
                                @click.away="open = false"
                                class="absolute bottom-full left-0 mb-2 bg-white border border-gray-200 rounded-lg shadow-lg z-50 min-w-[180px]"
                            >
                                <!-- Opción: Documentos -->
                                <button 
                                    type="button"
                                    @click="open = false; document.getElementById('file-upload-input').click();"
                                    class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors first:rounded-t-lg"
                                >
                                    <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900">Documentos</p>
                                        <p class="text-xs text-gray-500">PDF, Word, Excel, CSV, TXT</p>
                                    </div>
                                </button>
                                
                                <!-- Separador -->
                                <div class="border-t border-gray-200"></div>
                                
                                <!-- Opción: Imágenes -->
                                <button 
                                    type="button"
                                    @click="open = false; document.getElementById('image-upload-input').click();"
                                    class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-gray-50 transition-colors last:rounded-b-lg"
                                >
                                    <svg class="w-5 h-5 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900">Imágenes</p>
                                        <p class="text-xs text-gray-500">JPG, PNG, GIF, WEBP</p>
                                    </div>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Input oculto para subir archivos (Documentos) -->
                        <input 
                            type="file" 
                            id="file-upload-input"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt"
                            class="hidden"
                            x-on:change="
                                const file = $event.target.files[0];
                                const input = $event.target;
                                
                                if (!file) {
                                    fileUploadError = null;
                                    return;
                                }
                                
                                // Validar ANTES de subir
                                const isValid = validateFileBeforeUpload($event);
                                
                                if (isValid) {
                                    // Solo si es válido, subir a Livewire
                                    @this.upload('uploadedFile', file);
                                } else {
                                    // Si no es válido, limpiar el input (el error ya se mostró en validateFileBeforeUpload)
                                    input.value = '';
                                }
                            "
                        >
                        
                        <!-- Input oculto para subir imágenes (acepta múltiples) -->
                        <input 
                            type="file" 
                            id="image-upload-input"
                            wire:model="uploadedImages"
                            multiple
                            accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                            class="hidden"
                        >
                        
                        <button 
                            type="button" 
                            wire:click="toggleDocumentSelector"
                            class="p-2 hover:bg-gray-200 rounded-lg transition-colors {{ $selectedDocument ? 'bg-black text-white' : 'text-gray-600' }}" 
                            title="{{ $selectedDocument ? 'Documento seleccionado: ' . ($documentInfo['name'] ?? '') : 'Seleccionar Documento' }}"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </button>
                        <!-- <button type="button" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="History">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </button> -->
                        <!-- <button type="button" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Edit">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button> -->
                        <!-- <button type="button" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="Share">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path>
                            </svg>
                        </button> -->
                        <!-- <button type="button" class="p-2 hover:bg-gray-200 rounded-lg transition-colors" title="More Options">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"></path>
                            </svg>
                        </button> -->
                    </div>

                    <!-- Loading indicator para subida de imágenes -->
                    @if($isUploadingImages)
                        <div class="mb-3 px-2">
                            <div class="flex items-center gap-2 px-3 py-2 bg-gray-100 rounded-lg border border-gray-300">
                                <svg class="animate-spin h-4 w-4 text-gray-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-sm text-gray-800 font-medium">Procesando imágenes...</span>
                            </div>
                        </div>
                    @endif

                    <!-- Preview de imágenes antes de enviar -->
                    @if(!empty($imagePreviewUrls) && !$isUploadingImages)
                        <div class="mb-3 px-2">
                            <div class="flex gap-2 flex-wrap">
                                @foreach($imagePreviewUrls as $index => $url)
                                    <div class="relative group">
                                        <div class="w-20 h-20 rounded-lg border-2 border-gray-300 shadow-sm overflow-hidden bg-white relative">
                                            <img 
                                                src="{{ $url }}" 
                                                alt="Preview {{ $index + 1 }}" 
                                                class="w-full h-full object-cover relative z-10"
                                                loading="lazy"
                                                onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-gray-400 text-xs bg-gray-100\'>Error</div>'"
                                            >
                                        </div>
                                        <button 
                                            type="button"
                                            wire:click="removeImage({{ $index }})"
                                            class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 shadow-lg transition-all hover:scale-110 z-20"
                                            title="Eliminar imagen"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Mensaje de error de validación frontend (Alpine.js) -->
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

                    <!-- Mensajes de error de validación de imágenes (Livewire) -->
                    @error('uploadedImages')
                        <div class="mb-3 px-2">
                            <div class="bg-black border border-gray-700 text-white px-3 py-2 rounded-lg text-sm">
                                {{ $message }}
                            </div>
                        </div>
                    @enderror

                    <!-- Información del archivo subido -->
                    @if($uploadedFileInfo)
                        <div class="mb-3 px-2">
                            <div class="bg-gray-100 border border-gray-300 rounded-lg p-3">
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
                            </div>
                        </div>
                    @endif

                    <!-- Mensaje de error de validación de archivos (Livewire) - Solo se muestra si hay error del backend -->
                    @error('uploadedFile')
                        <div class="mb-3 px-2">
                            <div class="bg-black border border-gray-700 text-white px-3 py-2 rounded-lg text-sm">
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
                            style="min-height: 40px; max-height: 2000px;"
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

    <!-- Document Selector Modal -->
    @if($showDocumentSelector)
        <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[80vh] flex flex-col">
                <!-- Modal Header -->
                <div class="flex items-center justify-between p-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">Seleccionar Documento</h3>
                    <button 
                        wire:click="toggleDocumentSelector"
                        class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Modal Content -->
                <div class="flex-1 overflow-hidden flex flex-col">
                    <!-- Filters -->
                    <div class="p-4 border-b border-gray-200">
                        <div class="flex gap-3">
                            <select 
                                wire:model.live="selectedDocumentType" 
                                wire:change="filterByType($event.target.value)"
                                class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-500"
                            >
                                @foreach($documentTypes as $key => $name)
                                    <option value="{{ $key }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
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
                                                {{ substr($document['type_name'], 0, 2) }}
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center justify-between">
                                                    <h4 class="text-sm font-medium {{ $selectedDocument == $document['id'] ? 'text-gray-800' : 'text-gray-800' }} truncate">
                                                        {{ $document['name'] }}
                                                    </h4>
                                                    @if($selectedDocument == $document['id'])
                                                        <svg class="w-4 h-4 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                        </svg>
                                                    @endif
                                                </div>
                                                <p class="text-xs text-gray-600 mt-1">
                                                    {{ $document['type_name'] }} • {{ $document['account'] }}
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
                                <p class="text-gray-500">No hay documentos disponibles</p>
                            </div>
                        @endif
                    </div>

                    <!-- Selected Document Info -->
                    @if($documentInfo)
                        <div class="p-4 border-t border-gray-200 bg-gray-50">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-medium text-gray-800">Documento Seleccionado</h4>
                                    <p class="text-xs text-gray-600">{{ $documentInfo['name'] }} • {{ $documentInfo['type'] }}</p>
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
                            wire:click="toggleDocumentSelector"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button 
                            wire:click="confirmDocumentSelection"
                            class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-900 transition-colors"
                        >
                            Confirmar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif


<!-- Markdown to HTML Conversion -->
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<style>
/* Estilos para el contenido markdown renderizado */
.assistant-message h1, .assistant-message h2, .assistant-message h3, 
.assistant-message h4, .assistant-message h5, .assistant-message h6 {
    font-weight: bold;
    margin: 0.5em 0;
    color: inherit;
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
    list-style-position: outside;
}

.assistant-message ul {
    list-style-type: disc;
}

.assistant-message ol {
    list-style-type: decimal;
}

.assistant-message li {
    margin: 0.2em 0;
    line-height: 1.4;
}

.assistant-message ul ul, .assistant-message ol ol, .assistant-message ul ol, .assistant-message ol ul {
    margin: 0.2em 0;
    padding-left: 1.2em;
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

.assistant-message a:hover {
    color: #1d4ed8;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configurar marked para mejor renderizado
    marked.setOptions({
        breaks: true,        // Saltos de línea → <br>
        gfm: true,          // GitHub Flavored Markdown
        headerIds: false,    // Sin IDs en headers (más limpio)
        mangle: false       // No encodear emails
    });
    
    /**
     * Detecta si el contenido ya está en HTML o es markdown/texto plano
     * @param {HTMLElement} element - Elemento a verificar
     * @returns {boolean} - true si es markdown, false si ya es HTML
     */
    function isMarkdown(element) {
        const html = element.innerHTML.trim();
        
        // Si tiene tags HTML complejos (div, span, ul, ol, pre, code, table)
        // entonces ya fue procesado a HTML
        if (/<(div|span|ul|ol|pre|code|table|h[1-6]|blockquote)[\s>]/i.test(html)) {
            return false; // Ya es HTML
        }
        
        // Si solo tiene <br> o <p> simples, puede ser texto que Livewire agregó
        // Verificar el texto plano para detectar markdown
        const text = element.textContent.trim();
        
        // Patrones comunes de markdown
        const markdownPatterns = [
            /^#{1,6}\s/m,           // Headers: # ## ###
            /\*\*.*\*\*/,           // Bold: **text**
            /__.*__/,               // Bold alt: __text__
            /\[.+\]\(.+\)/,         // Links: [text](url)
            /```/,                  // Code blocks: ```
            /^\s*[-*+]\s/m,         // Listas: - * +
            /^\s*\d+\.\s/m,         // Listas numeradas: 1. 2.
            /^\s*>\s/m,             // Blockquotes: >
            /`[^`]+`/,              // Inline code: `code`
            /\*[^*]+\*/,            // Italic: *text*
            /_[^_]+_/,              // Italic alt: _text_
        ];
        
        // Si encuentra algún patrón de markdown, es markdown
        return markdownPatterns.some(pattern => pattern.test(text));
    }
    
    /**
     * ✅ Función ROBUSTA para convertir markdown a HTML
     * Se ejecuta SIEMPRE en cada render, pero solo procesa si es necesario
     */
    function convertMarkdownToHtml() {
        const assistantMessages = document.querySelectorAll('.assistant-message');
        let processedCount = 0;
        let skippedCount = 0;
        
        assistantMessages.forEach(function(element, index) {
            try {
                // Verificar si el elemento tiene contenido
                const content = element.textContent.trim();
                if (!content) {
                    return; // Saltar elementos vacíos
                }
                
                // ✅ CLAVE: Detectar si ya es HTML o si es markdown
                if (isMarkdown(element)) {
                    // Es markdown → convertir a HTML
                    const markdownContent = element.textContent;
                    const htmlContent = marked.parse(markdownContent);
                    element.innerHTML = htmlContent;
                    processedCount++;
                } else {
                    // Ya es HTML → no hacer nada
                    skippedCount++;
                }
            } catch (error) {
                console.error('❌ Error al convertir mensaje', index, ':', error);
            }
        });
        
        if (processedCount > 0 || skippedCount > 0) {
            /*console.log('✨ Markdown procesado:', {
                convertidos: processedCount,
                saltados: skippedCount,
                total: assistantMessages.length
            });*/
        }
    }
    
    // Función para hacer scroll al final del contenedor de mensajes
    function scrollToBottom() {
        const messagesArea = document.querySelector('.overflow-y-auto.p-4');
        if (messagesArea) {
            requestAnimationFrame(() => {
                messagesArea.scrollTop = messagesArea.scrollHeight;
            });
        }
    }
    
    // ========== EJECUCIÓN INICIAL ==========
    console.log('🎬 Inicializando procesador de markdown...');
    convertMarkdownToHtml();
    
    // ========== LIVEWIRE V3 HOOKS (Principal) ==========
    // Hook que se ejecuta DESPUÉS de que Livewire actualiza el DOM
    Livewire.hook('morph.updated', ({ el, component }) => {
        console.log('🔄 Livewire actualizó el DOM');
        // Pequeño delay para asegurar que el DOM esté completamente renderizado
        setTimeout(() => {
            convertMarkdownToHtml();
            scrollToBottom();
        }, 50);
    });
    
    // Hook adicional para cuando se procesa un mensaje de Livewire
    Livewire.hook('message.processed', (message, component) => {
        console.log('📨 Livewire procesó un mensaje');
        setTimeout(() => {
            convertMarkdownToHtml();
        }, 50);
    });
    
    // ========== MUTATION OBSERVER (Respaldo robusto) ==========
    // Observer para detectar cambios en el DOM que Livewire podría hacer
    const observer = new MutationObserver(function(mutations) {
        let hasNewMessages = false;
        
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        // Verificar si es un mensaje del asistente o contiene uno
                        if (node.classList?.contains('assistant-message') || 
                            node.querySelector?.('.assistant-message')) {
                            hasNewMessages = true;
                        }
                    }
                });
            }
        });
        
        if (hasNewMessages) {
            console.log('👁️ Observer detectó nuevos mensajes');
            requestAnimationFrame(() => {
                convertMarkdownToHtml();
                scrollToBottom();
            });
        }
    });
    
    // Observar el contenedor principal de mensajes
    const messagesContainer = document.getElementById('chat-messages');
    if (messagesContainer) {
        observer.observe(messagesContainer, {
            childList: true,
            subtree: true
        });
        console.log('👁️ Observer activado en #chat-messages');
    }
    
    // ========== EVENTOS PERSONALIZADOS ==========
    // Scroll automático cuando el agente comienza a responder
    Livewire.on('executeAgentResponse', () => {
        console.log('🤖 Agente procesando respuesta...');
        setTimeout(scrollToBottom, 100);
    });
    
    // Limpiar error de validación cuando Livewire procesa el archivo exitosamente
    Livewire.on('file-upload-success', () => {
        console.log('✅ Archivo procesado exitosamente');
        // Limpiar error de Alpine.js si existe
        const alpineComponent = Alpine.$data(document.querySelector('[x-data]'));
        if (alpineComponent && alpineComponent.fileUploadError) {
            alpineComponent.fileUploadError = null;
        }
    });
    
    // Mostrar error cuando Livewire detecta un error
    Livewire.on('file-upload-error', (event) => {
        console.log('❌ Error al procesar archivo:', event.message);
        // El error ya se muestra por Livewire, pero podemos limpiar el error de Alpine si existe
        const alpineComponent = Alpine.$data(document.querySelector('[x-data]'));
        if (alpineComponent && alpineComponent.fileUploadError) {
            // Mantener el error de Alpine si es más descriptivo
        }
    });
    
    // ========== RECUPERACIÓN DE ESTADO ==========
    // Si el loading se queda activo más de 60 segundos, resetear el estado
    let loadingTimeout = null;
    
    Livewire.hook('morph.updated', () => {
        // Limpiar timeout anterior
        if (loadingTimeout) {
            clearTimeout(loadingTimeout);
        }
        
        // Si isLoading está activo, establecer timeout de recuperación
        if (Livewire.find(document.querySelector('[wire\\:id]'))?.$wire?.isLoading) {
            loadingTimeout = setTimeout(() => {
                console.warn('⚠️ Estado de carga bloqueado detectado, forzando reseteo...');
                const component = Livewire.find(document.querySelector('[wire\\:id]'));
                if (component && component.$wire) {
                    component.$wire.set('isLoading', false);
                    console.log('✅ Estado de carga reseteado');
                }
            }, 60000); // 60 segundos
        }
    });
    
    // Limpiar timeout al hacer scroll para evitar falsos positivos
    let scrollTimeout;
    window.addEventListener('scroll', () => {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
            // Si hubo scroll, el usuario está interactuando, limpiar timeout de loading
            if (loadingTimeout) {
                clearTimeout(loadingTimeout);
            }
        }, 1000);
    });
});

// Función para descargar archivos (imágenes o videos) desde S3 usando fetch
async function downloadFile(fileUrl, fileType) {
    try {
        console.log(`🔽 Iniciando descarga de ${fileType}:`, fileUrl);
        
        // Mostrar indicador de descarga
        const button = event?.target;
        if (button) {
            const originalText = button.textContent;
            button.textContent = 'Descargando...';
            button.disabled = true;
        }
        
        // Fetch del archivo desde S3
        const response = await fetch(fileUrl);
        if (!response.ok) {
            throw new Error(`Error al descargar el ${fileType}`);
        }
        
        // Convertir a blob
        const blob = await response.blob();
        
        // Crear URL temporal
        const blobUrl = window.URL.createObjectURL(blob);
        
        // Crear elemento <a> temporal para descarga
        const link = document.createElement('a');
        link.href = blobUrl;
        
        // Generar nombre de archivo basado en timestamp y tipo
        const timestamp = new Date().toISOString().slice(0, 19).replace(/[:-]/g, '');
        let extension = 'mp4'; // Por defecto para videos
        let prefix = 'video';
        
        if (fileType === 'image') {
            extension = fileUrl.includes('.png') ? 'png' : 'jpg';
            prefix = 'imagen';
        }
        
        link.download = `${prefix}_chat_${timestamp}.${extension}`;
        
        // Agregar al DOM temporalmente y hacer clic
        document.body.appendChild(link);
        link.click();
        
        // Limpiar
        document.body.removeChild(link);
        window.URL.revokeObjectURL(blobUrl);
        
        console.log(`✅ ${fileType} descargado exitosamente`);
        
        // Restaurar botón
        if (button) {
            button.textContent = originalText;
            button.disabled = false;
        }
        
    } catch (error) {
        console.error(`❌ Error descargando ${fileType}:`, error);
        
        // Restaurar botón en caso de error
        const button = event?.target;
        if (button) {
            button.textContent = 'Error al descargar';
            button.disabled = false;
            
            // Restaurar texto después de 2 segundos
            setTimeout(() => {
                button.textContent = 'Descargar';
            }, 2000);
        }
        
        // Fallback: intentar descarga directa
        const link = document.createElement('a');
        link.href = fileUrl;
        link.download = `${fileType}_${Date.now()}.${fileType === 'image' ? 'jpg' : 'mp4'}`;
        link.target = '_blank';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}
</script>

<!-- Lightbox para Imágenes -->
<div 
    id="mediaLightbox"
    x-data="{ open: false, currentMedia: '', mediaType: 'image' }"
    x-show="open"
    x-cloak
    @keydown.escape.window="open = false"
    @open-lightbox.window="
        if ($event.detail.type === 'video') {
            currentMedia = $event.detail.videoSrc;
            mediaType = 'video';
        } else {
            currentMedia = $event.detail.imgSrc;
            mediaType = 'image';
        }
        open = true;
    "
    class="fixed inset-0 z-50 bg-black bg-opacity-90 flex items-center justify-center"
    style="display: none;"
>
    <div class="relative max-w-4xl mx-auto p-4">
        <!-- Botón de cerrar -->
        <button 
            @click="open = false"
            class="absolute -top-4 -right-4 bg-black rounded-full p-2 text-white hover:bg-gray-800 z-10"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
        
        <!-- Contenido multimedia -->
        <div class="max-h-[80vh] w-auto mx-auto">
            <template x-if="mediaType === 'video'">
                <video 
                    :src="currentMedia" 
                    class="max-h-[80vh] w-auto mx-auto object-contain rounded-lg"
                    controls
                    autoplay
                    muted
                >
                    Tu navegador no soporta el elemento video.
                </video>
            </template>
            
            <template x-if="mediaType === 'image'">
                <img 
                    :src="currentMedia" 
                    class="max-h-[80vh] w-auto mx-auto object-contain rounded-lg"
                    alt="Imagen ampliada"
                >
            </template>
        </div>
        
        <!-- Botón de descarga -->
        <button 
            @click="downloadFile(currentMedia, mediaType)"
            class="mt-4 block w-full text-center bg-black text-white py-2 px-4 rounded-lg hover:bg-gray-800 transition-colors"
        >
            Descargar <span x-text="mediaType === 'video' ? 'Video' : 'Imagen'"></span>
        </button>
    </div>
</div>
</div>