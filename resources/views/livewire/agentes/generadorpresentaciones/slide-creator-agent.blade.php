<div>
    <x-slot name="title">Génesis - Agente Presentaciones</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-black dark:text-gray-200 leading-tight">
            {{ __('Agente Presentaciones') }}
        </h2>
    </x-slot>

    <!-- Componente de estado de generación -->
    <livewire:generador.components.generating-status
        :show="$isGenerating"
        message="Generando presentación..."
        subtitle="Esto puede tardar unos momentos. Por favor espera."
    />

    <!-- Main Container -->
    <div class="flex h-[calc(100vh-64px)] bg-white text-gray-900 border border-gray-300" x-data="{ sidebarOpen: @entangle('sidebarOpen') }">
        
        <!-- Left Sidebar: Presentations History -->
        <aside 
            x-show="sidebarOpen" 
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="w-80  bg-gray-100 flex flex-col border-r border-gray-300 lg:relative fixed top-0 left-0 bottom-0 z-40"
        >
            <!-- Sidebar Header -->
            <div class="p-4 border-b border-gray-300 flex items-center justify-between">
                <h1 class="text-xl font-bold text-gray-800">Presentaciones</h1>
                <!-- Botón para cerrar sidebar -->
                <button 
                    @click="sidebarOpen = false"
                    class="p-2 hover:bg-gray-200 rounded-lg transition-colors text-gray-600"
                    title="Cerrar menú"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            {{-- Selector de cuenta (créditos / uso) --}}
            <div class="px-4 py-3 border-b border-gray-300 bg-white">
                @if(count($availableAccounts) > 0)
                    <label for="presentaciones-account-selector" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                        Cuenta
                    </label>
                    <select
                        id="presentaciones-account-selector"
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
                    @if($isSuperAdmin && !$selectedAccountId)
                        <p class="mt-1 text-xs text-amber-600">Selecciona una cuenta para registrar el uso y validar créditos.</p>
                    @endif
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

            <!-- Conversations List -->
            <div class="flex-1 overflow-y-auto px-2 mt-4 space-y-2">
                @if(!empty($conversations))
                    @foreach($conversations as $index => $conversation)
                        <div 
                            x-data="{ 
                                editing: false, 
                                title: '{{ addslashes($conversation['name']) }}',
                                save() {
                                    if (this.title.trim()) {
                                        $wire.updateConversationTitle('{{ $conversation['id'] }}', this.title);
                                    }
                                    this.editing = false;
                                }
                            }"
                            class="group flex items-center justify-between px-3 py-3 cursor-pointer rounded-lg transition-colors {{ $currentConversationId == $conversation['id'] ? 'bg-black text-white' : 'hover:bg-gray-200 text-gray-700' }}"
                        >
                            <div 
                                @click="if (!editing) $wire.selectConversation('{{ $conversation['id'] }}')"
                                class="flex items-center gap-3 overflow-hidden flex-1"
                            >
                                <svg class="w-5 h-5 flex-shrink-0 {{ $currentConversationId == $conversation['id'] ? 'text-gray-400' : 'text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                                <div class="flex-1 min-w-0">
                                    {{-- Modo edición --}}
                                    <template x-if="editing">
                                        <input 
                                            type="text" 
                                            x-model="title"
                                            @click.stop
                                            @keydown.enter="save()"
                                            @keydown.escape="editing = false"
                                            @blur="save()"
                                            x-init="$nextTick(() => $el.focus())"
                                            class="w-full text-sm font-medium bg-white text-gray-900 border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            maxlength="100"
                                        />
                                    </template>
                                    {{-- Modo visualización --}}
                                    <template x-if="!editing">
                                        <div>
                                            <h3 class="text-sm font-medium truncate">
                                                {{ $conversation['name'] }}
                                            </h3>
                                            <p class="text-xs {{ $currentConversationId == $conversation['id'] ? 'text-gray-400' : 'text-gray-500' }} truncate">
                                                {{ count($conversation['presentations']) }} {{ count($conversation['presentations']) === 1 ? 'presentación' : 'presentaciones' }}
                                            </p>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            {{-- Botones de acciones (visible en hover) --}}
                            <div x-show="!editing" class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                {{-- Botón editar --}}
                                <button 
                                    @click.stop="editing = true"
                                    class="p-1.5 rounded hover:bg-opacity-20 hover:bg-gray-500 transition-colors {{ $currentConversationId == $conversation['id'] ? 'text-gray-300 hover:text-white' : 'text-gray-400 hover:text-gray-600' }}"
                                    title="Editar nombre"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                {{-- Botón eliminar --}}
                                <button 
                                    @click.stop="if(confirm('¿Estás seguro de eliminar esta presentación y todo su contenido?')) $wire.deleteConversation('{{ $conversation['id'] }}')"
                                    class="p-1.5 rounded hover:bg-red-100 transition-colors {{ $currentConversationId == $conversation['id'] ? 'text-gray-300 hover:text-red-400' : 'text-gray-400 hover:text-red-500' }}"
                                    title="Eliminar"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-8 px-4">
                        <svg class="w-16 h-16 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                        </svg>
                        <p class="text-sm text-gray-600 mb-1">No hay presentaciones</p>
                        <p class="text-xs text-gray-400">Crea tu primera presentación</p>
                    </div>
                @endif
            </div>

            <!-- New Presentation Button -->
            <div class="p-3 border-t border-gray-300">
                <button 
                    wire:click="newPresentation" 
                    class="w-full py-3 px-4 bg-black border border-gray-300 hover:bg-gray-700 text-white font-medium rounded-lg transition-colors duration-200 flex items-center justify-center gap-2 text-sm"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Nueva Presentación
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

        <!-- Main Content -->
        <main class="flex-1 flex flex-col h-full relative bg-white">
            
            <!-- Botón flotante para abrir sidebar (visible cuando está cerrado) -->
            <button 
                x-show="!sidebarOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-x-4"
                x-transition:enter-end="opacity-100 translate-x-0"
                @click="sidebarOpen = true"
                class="hidden lg:flex absolute top-4 left-4 z-20 p-2 bg-white border border-gray-300 rounded-lg shadow-md hover:bg-gray-100 transition-colors items-center gap-2 text-gray-700"
                title="Abrir menú"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
                <span class="text-sm font-medium">Menú</span>
            </button>

            <!-- Mobile Header -->
            <header class="lg:hidden h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 flex-shrink-0 z-30">
                <div class="flex items-center gap-3">
                    <button 
                        @click="sidebarOpen = true"
                        class="p-2 -ml-2 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <h2 class="text-lg font-semibold text-gray-800">Generador</h2>
                </div>
            </header>

    <style>
        /* Barra de errores fija arriba del contenido (como generador-main) */
        .presentaciones-errors-bar {
            position: sticky;
            top: 0;
            z-index: 30;
            flex-shrink: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .error-section {
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-item {
            transition: all 0.2s ease;
        }

        .error-item:hover {
            transform: translateX(2px);
        }
    </style>

    {{-- Errores fuera del scroll (visible sin subir al inicio del chat) --}}
    @if(!empty($errors))
    <div class="presentaciones-errors-bar px-4 py-3">
        <section aria-label="Errores recientes" class="error-section bg-gray-50 dark:bg-gray-800/20 backdrop-blur rounded-xl p-4 max-w-7xl mx-auto border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Errores Recientes</h3>
                </div>
                <button wire:click="clearErrors" type="button" class="text-xs px-3 py-1 rounded-md bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 transition-colors">
                    Limpiar Todo
                </button>
            </div>

            <div class="space-y-2 max-h-40 overflow-y-auto">
                @foreach($errors as $error)
                    <div class="error-item bg-white dark:bg-gray-900/30 rounded-lg border border-red-200 dark:border-red-800/40 p-3 relative">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-800 dark:text-gray-200 break-words font-medium">
                                    {{ $error['message'] }}
                                </p>
                                <div class="flex items-center gap-2 mt-1 text-xs text-gray-600 dark:text-gray-400">
                                    <span class="capitalize">{{ $error['tool'] ?? 'Sistema' }}</span>
                                    <span>•</span>
                                    <span>{{ \Carbon\Carbon::parse($error['date'])->format('H:i:s') }}</span>
                                    @if(isset($error['type']) && $error['type'] !== 'general')
                                        <span>•</span>
                                        <span class="capitalize text-red-600">{{ $error['type'] }}</span>
                                    @endif
                                </div>
                            </div>
                            <button
                                wire:click="dismissError('{{ $error['id'] }}')"
                                type="button"
                                class="flex-shrink-0 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 transition-colors"
                                title="Descartar error"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
    @endif

    <!-- Content Area (Chat Style) -->
    <div class="flex-1 overflow-y-auto p-4 md:p-8 scroll-smooth" id="chat-container">

        <div id="presentation-container" class="w-full max-w-7xl mx-auto space-y-12 pb-24 px-4 md:px-6 lg:px-8">
                    @if($activeConversation && !empty($activeConversation['presentations']))
                        
                        @foreach($activeConversation['presentations'] as $presentation)
                            <!-- Presentation Card -->
                            <div data-presentation-id="{{ $presentation['id'] }}" class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl p-6 md:p-8 border border-gray-200 shadow-sm presentation-card">
                                <div class="flex items-start gap-4 mb-6">
                                    <div class="w-12 h-12 md:w-16 md:h-16 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center flex-shrink-0 shadow-md">
                                        <svg class="w-6 h-6 md:w-8 md:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="text-xl md:text-2xl font-bold text-gray-900 mb-2">{{ $presentation['title'] ?? 'Presentación' }}</h3>
                                        <div class="flex flex-wrap gap-3 text-sm text-gray-600">
                                            @if(isset($presentation['created_at']))
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    {{ \Carbon\Carbon::parse($presentation['created_at'])->format('d/m/Y H:i') }}
                                                </span>
                                            @endif
                                            @if(isset($presentation['genesis_doc_name']) && $presentation['genesis_doc_name'])
                                                <span class="text-gray-400">•</span>
                                                <span class="flex items-center gap-1 text-purple-600 font-medium">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                    </svg>
                                                    {{ $presentation['genesis_doc_name'] }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Prompt usado -->
                                @if(isset($presentation['prompt']) && $presentation['prompt'])
                                    <!-- <div class="bg-white rounded-xl p-4 mb-4 border border-gray-200">
                                        <h4 class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Descripción original</h4>
                                        <p class="text-sm text-gray-800 leading-relaxed">{{ $presentation['prompt'] }}</p>
                                    </div> -->
                                @endif

                                <!-- Status & Actions -->
                                <div class="bg-white rounded-xl p-4 border border-gray-200 mb-4">
                                    <div class="flex items-center justify-between flex-wrap gap-3">
                                        <div class="flex items-center gap-2">
                                            @if(isset($presentation['status']) && $presentation['status'] === 'pending')
                                                <!-- PRESENTACIÓN PENDIENTE -->
                                                <div class="flex items-center gap-2">
                                                    <div class="w-2 h-2 bg-gray-400 rounded-full animate-pulse"></div>
                                                    <span class="text-sm font-medium text-gray-600">Generando presentación...</span>
                                                    <span class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded-full font-semibold border border-gray-200">Pendiente</span>
                                                </div>
                                            @else
                                                <!-- PRESENTACIÓN COMPLETADA -->
                                                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                                <span class="text-sm font-medium text-gray-800">Presentación generada</span>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @if(isset($presentation['status']) && $presentation['status'] === 'pending')
                                                <!-- Botón Verificar Estado -->
                                                <button 
                                                    wire:click="retryPendingGeneration({{ $presentation['id'] }})"
                                                    class="px-4 py-2 bg-black hover:bg-gray-800 text-white rounded-lg transition-colors text-sm font-medium flex items-center gap-2"
                                                    title="Verificar si la presentación ya está lista"
                                                >
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                    </svg>
                                                    <span>Verificar estado</span>
                                                </button>
                                            @else
                                            
                                            
                                            @if(isset($presentation['export_url']) && $presentation['export_url'])
                                                <a 
                                                    href="{{ $presentation['export_url'] }}" 
                                                    download
                                                    class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors text-sm font-medium flex items-center gap-2"
                                                >
                                                    <span>Descargar PPTX</span>
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                                    </svg>
                                                </a>
                                            @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- PowerPoint Online Viewer (Comentado - Usando Gamma Embed) --}}
                                {{-- @if(isset($presentation['export_url']) && $presentation['export_url'])
                                    <div class="bg-white rounded-xl p-2 border border-gray-200 shadow-sm">
                                        <div class="flex items-center justify-between px-4 py-2 border-b border-gray-200 mb-2">
                                            <h4 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                Previsualización PPTX
                                            </h4>
                                            <span class="text-xs text-gray-500">PowerPoint Online</span>
                                        </div>
                                        <div class="relative rounded-lg overflow-hidden bg-gray-100 h-96 md:h-[600px]">
                                            @php
                                                $pptxUrl = urlencode($presentation['export_url']);
                                            @endphp
                                            <iframe 
                                                src="https://view.officeapps.live.com/op/embed.aspx?src={{ $pptxUrl }}"
                                                class="w-full h-full border-none"
                                                allowfullscreen
                                                loading="lazy"
                                            ></iframe>
                                        </div>
                                    </div>
                                @endif --}}

                                <!-- Presentation Embed Viewer -->
                                @if(isset($presentation['gamma_url']) && $presentation['gamma_url'])
                                    <div class="bg-white rounded-xl p-2 border border-gray-200 shadow-sm mt-4">
                                        <div class="flex items-center justify-between px-4 py-2 border-b border-gray-200 mb-2">
                                            <h4 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                Previsualización
                                            </h4>
                                        </div>
                                        <div class="relative rounded-lg overflow-hidden bg-gray-100">
                                            @php
                                                // Construir URL de embed
                                                if (isset($presentation['gamma_embed_url']) && $presentation['gamma_embed_url']) {
                                                    $embedUrl = $presentation['gamma_embed_url'];
                                                } else {
                                                    $parsedUrl = parse_url($presentation['gamma_url']);
                                                    $path = $parsedUrl['path'] ?? '';
                                                    $slug = str_replace('/docs/', '', $path);
                                                    $slug = trim($slug, '/');
                                                    $embedUrl = 'https://gamma.app/embed/' . $slug;
                                                }
                                            @endphp
                                            <iframe 
                                                src="{{ $embedUrl }}" 
                                                style="width: 100%; max-width: 100%; height: 550px" 
                                                allow="fullscreen" 
                                                title="{{ $presentation['title'] ?? 'Presentación' }}"
                                            ></iframe>
                                            
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                    @else
                        <!-- Welcome State -->
                        <div class="text-center py-16">
                            <div class="w-24 h-24 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-2xl flex items-center justify-center  mx-auto mb-6">
                                <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                </svg>
                            </div>
                            <h2 class="text-3xl font-bold text-gray-900 mb-4">Generador de Presentaciones</h2>
                            <p class="text-lg text-gray-600 max-w-2xl mx-auto leading-relaxed">
                                Crea presentaciones profesionales con inteligencia artificial.
                                <br>
                                Describe tu tema o selecciona un documento Genesis como base.
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-4xl mx-auto mt-12">
                                @if($showTemplateOption)
                                    <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                                        <div class="w-12 h-12 bg-black rounded-lg flex items-center justify-center mx-auto mb-4">
                                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                                            </svg>
                                        </div>
                                        <h3 class="font-semibold text-gray-900 mb-2">Elige tu modo</h3>
                                        <p class="text-sm text-gray-600">Con plantilla o desde cero con más opciones</p>
                                    </div>
                                @endif
                                
                                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                                    <div class="w-12 h-12 bg-black rounded-lg flex items-center justify-center mx-auto mb-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="font-semibold text-gray-900 mb-2">Usa contexto</h3>
                                    <p class="text-sm text-gray-600">Selecciona un documento Genesis como base</p>
                                </div>    
                                
                                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                                    <div class="w-12 h-12 bg-black rounded-lg flex items-center justify-center mx-auto mb-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="font-semibold text-gray-900 mb-2">Personaliza</h3>
                                    <p class="text-sm text-gray-600">Elige modelos, estilos y cantidad de diapositivas</p>
                                </div>
                               
                                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                                    <div class="w-12 h-12 bg-black rounded-lg flex items-center justify-center mx-auto mb-4">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="font-semibold text-gray-900 mb-2">Generación IA</h3>
                                    <p class="text-sm text-gray-600">Deja que la IA genere tu presentación</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Input Area (Fixed at bottom) -->
            <div class="flex-shrink-0 border-t border-gray-200 bg-gray-50 p-4">
                <div class="max-w-4xl mx-auto">
                    <form wire:submit.prevent="generatePresentation">
                        

                        <!-- Options Row -->
                        <div class="flex flex-wrap items-center gap-2 mb-3 px-1">
                            <!-- Toolbar -->
                        <div class="flex items-center gap-2">
                            <!-- Botón para seleccionar documento Genesis -->
                            <button 
                                type="button"
                                wire:click="toggleGenesisSelector"
                                class="p-2 hover:bg-gray-200 rounded-lg transition-colors {{ $genesisDocInfo ? 'bg-black text-white' : 'text-gray-600' }}" 
                                title="{{ $genesisDocInfo ? 'Documento seleccionado: ' . $genesisDocInfo['name'] : 'Seleccionar Documento Genesis' }}"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Generation Mode Selector (solo si hay plantillas disponibles) -->
                        @if($showTemplateOption)
                            <div x-data="{ open: false }" class="relative">
                                <button 
                                    type="button"
                                    @click="open = !open"
                                    class="flex items-center space-x-1 bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1 text-sm shadow-sm text-gray-700 transition-colors"
                                    @if($isGenerating) disabled @endif
                                >
                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                                    </svg>
                                    <span>{{ $generationModes[$generationMode] ?? 'Modo' }}</span>
                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>

                                <div 
                                    x-show="open" 
                                    x-cloak
                                    @click.away="open = false"
                                    class="absolute bottom-full left-0 mb-2 bg-white border border-gray-200 rounded-xl p-2 w-48 z-50 shadow-xl"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="transform opacity-0 scale-95"
                                    x-transition:enter-end="transform opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="transform opacity-100 scale-100"
                                    x-transition:leave-end="transform opacity-0 scale-95"
                                >
                                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">Modo de Generación</div>
                                    <div class="space-y-1">
                                        @foreach($generationModes as $modeId => $modeName)
                                            <button 
                                                type="button"
                                                wire:click="$set('generationMode', '{{ $modeId }}')"
                                                @click="open = false"
                                                class="w-full text-left px-3 py-2 rounded-lg text-sm flex items-center justify-between group {{ $generationMode === $modeId ? 'bg-black text-white' : 'hover:bg-gray-100 text-gray-700' }}"
                                            >
                                                <span>{{ $modeName }}</span>
                                                @if($generationMode === $modeId)
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Genesis Doc Info Badge -->
                        @if($genesisDocInfo)
                            <div class="mb-3 px-2">
                                <div class="bg-black border border-gray-700 text-white px-3 py-2 rounded-lg text-sm flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <span class="font-medium">{{ $genesisDocInfo['name'] }}</span>
                                    </div>
                                    <button 
                                        type="button"
                                        wire:click="removeGenesisDocument"
                                        class="ml-2 text-gray-300 hover:text-white transition-colors"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @endif

                            
                            <!-- Template Selector (solo si modo = template) -->
                            @if($generationMode === 'template')
                            <div x-data="{ open: false }" class="relative">
                                <button 
                                    type="button"
                                    @click="open = !open"
                                    class="flex items-center space-x-1 bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1 text-sm shadow-sm text-gray-700 transition-colors"
                                    @if($isGenerating) disabled @endif
                                >
                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                    <span>{{ $templates[$selectedTemplateId] ?? 'Plantilla' }}</span>
                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>

                                <div 
                                    x-show="open" 
                                    x-cloak
                                    @click.away="open = false"
                                    class="absolute bottom-full left-0 mb-2 bg-white border border-gray-200 rounded-xl p-2 w-56 z-50 shadow-xl"
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="transform opacity-0 scale-95"
                                    x-transition:enter-end="transform opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="transform opacity-100 scale-100"
                                    x-transition:leave-end="transform opacity-0 scale-95"
                                >
                                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">Plantilla</div>
                                    <div class="space-y-1">
                                        @foreach($templates as $id => $name)
                                            <button 
                                                type="button"
                                                wire:click="$set('selectedTemplateId', '{{ $id }}')"
                                                @click="open = false"
                                                class="w-full text-left px-3 py-2 rounded-lg text-sm flex items-center justify-between group {{ $selectedTemplateId === $id ? 'bg-black text-white' : 'hover:bg-gray-100 text-gray-700' }}"
                                            >
                                                <span>{{ $name }}</span>
                                                @if($selectedTemplateId === $id)
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            @endif

                            <!-- Opciones para modo "Sin Plantilla" -->
                            @if($generationMode === 'scratch')
                                <!-- Image Model Selector -->
                                <div x-data="{ open: false }" class="relative">
                                    <button 
                                        type="button"
                                        @click="open = !open"
                                        class="flex items-center space-x-1 bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1 text-sm shadow-sm text-gray-700 transition-colors"
                                        @if($isGenerating) disabled @endif
                                    >
                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <span>{{ $imageModels[$selectedImageModel] ?? 'Modelo' }}</span>
                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>

                                    <div 
                                        x-show="open" 
                                        x-cloak
                                        @click.away="open = false"
                                        class="absolute bottom-full left-0 mb-2 bg-white border border-gray-200 rounded-xl p-2 w-48 z-50 shadow-xl"
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="transform opacity-0 scale-95"
                                        x-transition:enter-end="transform opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="transform opacity-100 scale-100"
                                        x-transition:leave-end="transform opacity-0 scale-95"
                                    >
                                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">Modelo de Imagen</div>
                                        <div class="space-y-1">
                                            @foreach($imageModels as $modelId => $modelName)
                                                <button 
                                                    type="button"
                                                    wire:click="$set('selectedImageModel', '{{ $modelId }}')"
                                                    @click="open = false"
                                                    class="w-full text-left px-3 py-2 rounded-lg text-sm flex items-center justify-between group {{ $selectedImageModel === $modelId ? 'bg-black text-white' : 'hover:bg-gray-100 text-gray-700' }}"
                                                >
                                                    <span>{{ $modelName }}</span>
                                                    @if($selectedImageModel === $modelId)
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <!-- Image Style Selector -->
                                <div x-data="{ open: false }" class="relative">
                                    <button 
                                        type="button"
                                        @click="open = !open"
                                        class="flex items-center space-x-1 bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1 text-sm shadow-sm text-gray-700 transition-colors"
                                        @if($isGenerating) disabled @endif
                                    >
                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                        </svg>
                                        <span>{{ $imageStyles[$selectedImageStyle] ?? 'Estilo' }}</span>
                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>

                                    <div 
                                        x-show="open" 
                                        x-cloak
                                        @click.away="open = false"
                                        class="absolute bottom-full left-0 mb-2 bg-white border border-gray-200 rounded-xl p-2 w-44 z-50 shadow-xl"
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="transform opacity-0 scale-95"
                                        x-transition:enter-end="transform opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="transform opacity-100 scale-100"
                                        x-transition:leave-end="transform opacity-0 scale-95"
                                    >
                                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">Estilo de Imagen</div>
                                        <div class="space-y-1">
                                            @foreach($imageStyles as $styleId => $styleName)
                                                <button 
                                                    type="button"
                                                    wire:click="$set('selectedImageStyle', '{{ $styleId }}')"
                                                    @click="open = false"
                                                    class="w-full text-left px-3 py-2 rounded-lg text-sm flex items-center justify-between group {{ $selectedImageStyle === $styleId ? 'bg-black text-white' : 'hover:bg-gray-100 text-gray-700' }}"
                                                >
                                                    <span>{{ $styleName }}</span>
                                                    @if($selectedImageStyle === $styleId)
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <!-- Text Mode Selector -->
                                <div x-data="{ open: false }" class="relative">
                                    <button 
                                        type="button"
                                        @click="open = !open"
                                        class="flex items-center space-x-1 bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1 text-sm shadow-sm text-gray-700 transition-colors"
                                        @if($isGenerating) disabled @endif
                                    >
                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <span>{{ $textModes[$textMode] ?? 'Modo texto' }}</span>
                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>

                                    <div 
                                        x-show="open" 
                                        x-cloak
                                        @click.away="open = false"
                                        class="absolute bottom-full left-0 mb-2 bg-white border border-gray-200 rounded-xl p-2 w-48 z-50 shadow-xl"
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="transform opacity-0 scale-95"
                                        x-transition:enter-end="transform opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="transform opacity-100 scale-100"
                                        x-transition:leave-end="transform opacity-0 scale-95"
                                    >
                                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">Modo de Texto</div>
                                        <div class="space-y-1">
                                            @foreach($textModes as $modeId => $modeName)
                                                <button 
                                                    type="button"
                                                    wire:click="$set('textMode', '{{ $modeId }}')"
                                                    @click="open = false"
                                                    class="w-full text-left px-3 py-2 rounded-lg text-sm flex items-center justify-between group {{ $textMode === $modeId ? 'bg-black text-white' : 'hover:bg-gray-100 text-gray-700' }}"
                                                >
                                                    <span>{{ $modeName }}</span>
                                                    @if($textMode === $modeId)
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <!-- Number of Cards Selector -->
                                <div x-data="{ open: false }" class="relative">
                                    <button 
                                        type="button"
                                        @click="open = !open"
                                        class="flex items-center space-x-1 bg-gray-100 hover:bg-gray-200 rounded-full px-3 py-1 text-sm shadow-sm text-gray-700 transition-colors"
                                        @if($isGenerating) disabled @endif
                                    >
                                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>
                                        </svg>
                                        <span>{{ $numCards }} diapositivas</span>
                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>

                                    <div 
                                        x-show="open" 
                                        x-cloak
                                        @click.away="open = false"
                                        class="absolute bottom-full left-0 mb-2 bg-white border border-gray-200 rounded-xl p-2 w-44 z-50 shadow-xl"
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="transform opacity-0 scale-95"
                                        x-transition:enter-end="transform opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="transform opacity-100 scale-100"
                                        x-transition:leave-end="transform opacity-0 scale-95"
                                    >
                                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2 px-2">Nº de Diapositivas</div>
                                        <div class="space-y-1">
                                            @foreach([5, 8, 10, 12, 15, 20] as $num)
                                                <button 
                                                    type="button"
                                                    wire:click="$set('numCards', {{ $num }})"
                                                    @click="open = false"
                                                    class="w-full text-left px-3 py-2 rounded-lg text-sm flex items-center justify-between group {{ $numCards === $num ? 'bg-black text-white' : 'hover:bg-gray-100 text-gray-700' }}"
                                                >
                                                    <span>{{ $num }} diapositivas</span>
                                                    @if($numCards === $num)
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    @endif
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif

                        </div>

                        <!-- Input Field -->
                        <div class="flex items-end gap-3 bg-white rounded-xl border border-gray-300 p-2 {{ $genesisDocInfo ? 'bg-gray-50' : '' }}" wire:key="input-field-{{ $genesisDocInfo ? 'genesis' : 'prompt' }}">
                            @if($genesisDocInfo)
                                {{-- Cuando hay Genesis adjunto, mostrar mensaje y deshabilitar textarea --}}
                                <div class="flex-1 px-2 py-2 text-sm text-gray-500 italic" wire:key="genesis-message">
                                    Se generará la presentación usando el documento Genesis seleccionado
                                </div>
                            @else
                                <textarea 
                                    wire:model="prompt" 
                                    wire:key="prompt-textarea"
                                    placeholder="Describe tu presentación... Ej: 'Estrategias de marketing digital para pequeñas empresas'" 
                                    rows="1"
                                    class="flex-1 bg-transparent px-2 py-2 text-sm text-gray-800 placeholder-gray-500 focus:outline-none resize-auto border-none"
                                    style="min-height: 40px; max-height: 200px;"
                                    @if($isGenerating) disabled @endif
                                ></textarea>
                            @endif
                            <button 
                                type="submit" 
                                class="flex-shrink-0 px-4 py-2 bg-gradient-to-br from-blue-600 to-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 text-sm font-medium"
                                @if($isGenerating) disabled @endif
                            >
                                @if($isGenerating)
                                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                @else
                                    <span>Generar</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                @endif
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Genesis Document Selector Modal -->
    @if($showGenesisSelector)
        <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[80vh] flex flex-col">
                <!-- Modal Header -->
                <div class="flex items-center justify-between p-6 border-b border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900">Seleccionar Documento Genesis</h3>
                    <button 
                        wire:click="toggleGenesisSelector"
                        class="p-2 hover:bg-gray-100 rounded-lg transition-colors"
                    >
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Documents List -->
                <div class="flex-1 overflow-y-auto p-6">
                    @if(!empty($genesisDocuments))
                        <div class="grid gap-3">
                            @foreach($genesisDocuments as $document)
                                <div 
                                    wire:click="selectGenesisDocument('{{ $document['id'] }}')"
                                    class="p-4 {{ $selectedGenesisDoc == $document['id'] ? 'bg-black text-white border-2 border-black' : 'hover:bg-gray-50 border-2 border-gray-200' }} rounded-xl cursor-pointer transition-all"
                                >
                                    <div class="flex items-start gap-4">
                                        <div class="w-12 h-12 {{ $selectedGenesisDoc == $document['id'] ? 'bg-white/20' : 'bg-gradient-to-br from-purple-500 to-indigo-500' }} rounded-lg flex items-center justify-center flex-shrink-0">
                                            <svg class="w-6 h-6 {{ $selectedGenesisDoc == $document['id'] ? 'text-white' : 'text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between gap-3">
                                                <h4 class="text-sm font-semibold {{ $selectedGenesisDoc == $document['id'] ? 'text-white' : 'text-gray-900' }}">
                                                    {{ $document['name'] }}
                                                </h4>
                                                @if($selectedGenesisDoc == $document['id'])
                                                    <svg class="w-5 h-5 text-white flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                    </svg>
                                                @endif
                                            </div>
                                            <p class="text-xs {{ $selectedGenesisDoc == $document['id'] ? 'text-white/70' : 'text-gray-600' }} mt-1">
                                                {{ $document['account'] }}
                                            </p>
                                            <p class="text-xs {{ $selectedGenesisDoc == $document['id'] ? 'text-white/50' : 'text-gray-500' }} mt-1">
                                                {{ $document['date'] }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="text-gray-600 font-medium">No hay documentos Genesis disponibles</p>
                            <p class="text-sm text-gray-500 mt-1">Crea un documento Genesis primero</p>
                        </div>
                    @endif
                </div>

                <!-- Modal Footer -->
                <div class="p-6 border-t border-gray-200 flex justify-end gap-3">
                    <button 
                        wire:click="toggleGenesisSelector"
                        class="px-6 py-2.5 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition-colors font-medium"
                    >
                        Cancelar
                    </button>
                    <button 
                        wire:click="confirmGenesisSelection"
                        class="px-6 py-2.5 bg-black hover:bg-gray-800 text-white text-sm rounded-lg transition-colors font-semibold"
                        @if(!$selectedGenesisDoc) disabled @endif
                    >
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Script para auto-resize del textarea -->
    <script>
        function setupTextareaAutoResize() {
            const textarea = document.querySelector('textarea[wire\\:model="prompt"]');
            if (textarea && !textarea.hasAttribute('data-resize-setup')) {
                textarea.setAttribute('data-resize-setup', 'true');
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 200) + 'px';
                });
            }
        }

        // Ejecutar al cargar la página
        document.addEventListener('DOMContentLoaded', setupTextareaAutoResize);
        
        // Ejecutar cuando Livewire actualiza el DOM
        document.addEventListener('livewire:update', setupTextareaAutoResize);
        
        // Para Livewire v3
        Livewire.hook('commit', ({component, commit, respond, succeed, fail}) => {
            succeed(({snapshot, effect}) => {
                setupTextareaAutoResize();
            });
        });
    </script>

    <!-- Script de polling para Gamma -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let pollingTimer = null;
            let currentGenerationData = null;

            // Función para hacer scroll a la última presentación (más reciente)
            function scrollToLastPresentation() {
                // Usar nextTick para asegurar que el DOM esté listo
                setTimeout(() => {
                    const container = document.getElementById('presentation-container');
                    const allCards = document.querySelectorAll('.presentation-card');
                    const lastCard = allCards[allCards.length - 1]; // Última tarjeta = más reciente
                    
                    if (lastCard && container) {
                        // Scroll al último elemento (más reciente)
                        lastCard.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start',
                            inline: 'nearest'
                        });
                        console.log('✅ Scroll realizado a la última presentación (más reciente)');
                    } else {
                        console.log('⚠️ No se encontró presentación o contenedor');
                    }
                }, 100);
            }

            // Hacer scroll inicial al cargar la página
            scrollToLastPresentation();

            // Escuchar cuando inicia una generación de Gamma
            Livewire.on('gammaTaskStarted', (event) => {
                console.log('🚀 Gamma generación iniciada:', event);
                
                currentGenerationData = {
                    generationId: event.generationId,
                    prompt: event.prompt,
                    genesisDocId: event.genesisDocId,
                    genesisDocName: event.genesisDocName
                };

                // Iniciar polling después de 10 segundos (no molestar servidores de Gamma)
                console.log('⏰ Iniciando polling Gamma en 10 segundos...');
                setTimeout(() => {
                    verificarEstado();
                }, 10000);
            });

            // Escuchar cuando Gamma aún está pendiente
            Livewire.on('gammaStillPending', (event) => {
                console.log('⏳ Gamma aún pendiente, reintentando en 10 segundos...');
                
                currentGenerationData = {
                    generationId: event.generationId,
                    prompt: event.prompt,
                    genesisDocId: event.genesisDocId,
                    genesisDocName: event.genesisDocName
                };

                // Continuar polling después de 10 segundos (igual que ImageGenerator)
                setTimeout(() => {
                    verificarEstado();
                }, 10000);
            });

            // Escuchar cuando Gamma completa
            Livewire.on('gammaCompleted', (event) => {
                console.log('✅ Gamma completado:', event);
                
                // Limpiar datos de generación
                currentGenerationData = null;
                
                // Limpiar timer si existe
                if (pollingTimer) {
                    clearTimeout(pollingTimer);
                    pollingTimer = null;
                }
                
                // Asegurar que el textarea se muestre correctamente
                setTimeout(() => {
                    setupTextareaAutoResize();
                }, 100);
            });

            // Escuchar evento de scroll automático
            Livewire.on('scrollToNewest', () => {
                console.log('📜 Scroll a última presentación (más reciente - nuevo item)');
                scrollToLastPresentation();
            });

            // Función para verificar estado
            function verificarEstado() {
                if (!currentGenerationData) {
                    console.log('⚠️ No hay generación activa');
                    return;
                }

                console.log('🔍 Verificando estado de Gamma:', currentGenerationData.generationId);

                // Llamar al método Livewire
                @this.call('verificarEstadoGamma', 
                    currentGenerationData.generationId,
                    currentGenerationData.prompt,
                    currentGenerationData.genesisDocId,
                    currentGenerationData.genesisDocName
                );
            }
        });
    </script>
    
</div>