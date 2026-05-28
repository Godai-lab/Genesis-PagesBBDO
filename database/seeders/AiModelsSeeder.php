<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\ModelPricing;
use App\Models\Provider;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AiModelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * NOTA: Los precios para modelos tipo 'per_token' se configuran como "por millón de tokens".
     * Precios actualizados según pricing oficial de cada proveedor (Enero 2026).
     */
    public function run(): void
    {
        // ============================================
        // CREAR PROVEEDORES
        // ============================================
        $providers = $this->createProviders();
        
        // ============================================
        // CREAR MODELOS Y PRECIOS
        // ============================================
        $this->createOpenAIModels($providers['openai']);
        $this->createAnthropicModels($providers['anthropic']);
        $this->createGoogleModels($providers['google']);
        $this->createPerplexityModels($providers['perplexity']);
        $this->createImageModels($providers);
        $this->createVideoModels($providers);
        $this->createPresentationModels($providers['gamma']);
        
        // ============================================
        // MENSAJES DE CONFIRMACIÓN
        // ============================================
        $this->command->info('✅ Seeder ejecutado exitosamente.');
        $this->command->info('📝 Precios actualizados según pricing oficial (Enero 2026).');
        $this->command->info('💡 Modelos de texto: precios por millón de tokens (1M)');
        $this->command->info('🖼️ Modelos de imagen: precios por generación');
        $this->command->info('🎬 Modelos de video: precios por segundo');
        $this->command->info('🎯 Servicios especiales: precios por crédito');
    }
    
    /**
     * Crear todos los proveedores
     */
    private function createProviders(): array
    {
        return [
            'openai' => Provider::firstOrCreate(['name' => 'OpenAI'], ['status' => 'active']),
            'anthropic' => Provider::firstOrCreate(['name' => 'Anthropic'], ['status' => 'active']),
            'perplexity' => Provider::firstOrCreate(['name' => 'Perplexity'], ['status' => 'active']),
            'google' => Provider::firstOrCreate(['name' => 'Google'], ['status' => 'active']),
            'flux' => Provider::firstOrCreate(['name' => 'Flux'], ['status' => 'active']),
            'bytedance' => Provider::firstOrCreate(['name' => 'Bytedance'], ['status' => 'active']),
            'qwen' => Provider::firstOrCreate(['name' => 'Qwen'], ['status' => 'active']),
            'replicate' => Provider::firstOrCreate(['name' => 'Replicate'], ['status' => 'active']),
            'kwaivgi' => Provider::firstOrCreate(['name' => 'Kwaivgi'], ['status' => 'active']),
            'runway' => Provider::firstOrCreate(['name' => 'Runway'], ['status' => 'active']),
            'luma' => Provider::firstOrCreate(['name' => 'Luma'], ['status' => 'active']),
            'gamma' => Provider::firstOrCreate(['name' => 'Gamma'], ['status' => 'active']),
        ];
    }
    
    /**
     * Crear modelos de OpenAI
     */
    private function createOpenAIModels($provider): void
    {
        // ============================================
        // MODELOS GPT-5 (Text Generation)
        // ============================================
        
        // GPT-5 (base)
        // Usado en: Herramienta2Controller, AsistenteCreativoController, AsistenteSocialMediaController
        $this->createTextModel($provider, [
            'name' => 'gpt-5',
            'slug' => 'gpt-5',
            'input_price' => 1.25,  // USD por 1M tokens
            'output_price' => 10.00, // USD por 1M tokens
        ]);
        
        // GPT-5.1-2025-11-13
        // Usado en: AsistenteCreativoController, AsistenteSocialMediaController
        $this->createTextModel($provider, [
            'name' => 'gpt-5.1-2025-11-13',
            'slug' => 'gpt-5.1-2025-11-13',
            'input_price' => 1.25,  // USD por 1M tokens
            'output_price' => 10.00, // USD por 1M tokens
        ]);
        
        // GPT-5.2-2025-12-11
        // Usado en: Herramienta2Controller (validar concepto)
        $this->createTextModel($provider, [
            'name' => 'gpt-5.2-2025-12-11',
            'slug' => 'gpt-5.2-2025-12-11',
            'input_price' => 1.75,  // USD por 1M tokens
            'output_price' => 14.00, // USD por 1M tokens
        ]);
        
        // GPT-5-Mini-2025-08-07
        // Usado en: Herramienta2Controller (creatividad, estrategia, ideas contenido)
        $this->createTextModel($provider, [
            'name' => 'gpt-5-mini-2025-08-07',
            'slug' => 'gpt-5-mini-2025-08-07',
            'input_price' => 0.25,  // USD por 1M tokens
            'output_price' => 2.00,  // USD por 1M tokens
        ]);
        
        // ============================================
        // MODELOS O-SERIES (Deep Research)
        // ============================================
        
        // O4-Mini Deep Research
        // Usado en: InvestigacionController
        $this->createTextModel($provider, [
            'name' => 'o4-mini-deep-research',
            'slug' => 'o4-mini-deep-research',
            'input_price' => 2.00,  // USD por 1M tokens
            'output_price' => 8.00,  // USD por 1M tokens
        ]);
        
        // O3 Deep Research
        // Usado en: InvestigacionController
        $this->createTextModel($provider, [
            'name' => 'o3-deep-research',
            'slug' => 'o3-deep-research',
            'input_price' => 10.00,  // USD por 1M tokens
            'output_price' => 40.00,  // USD por 1M tokens
        ]);
        
        // ============================================
        // MODELOS DE VIDEO (SORA)
        // ============================================
        
        // Sora 2 - Video Generation
        $this->createVideoModel($provider, [
            'name' => 'sora-2',
            'slug' => 'sora-2',
            'price_per_second' => 0.10,
            'minimum_seconds' => 4,
            'extra' => [
                'portrait_resolution' => '720x1280',
                'landscape_resolution' => '1280x720'
            ]
        ]);
        
        // Sora 2 Pro - Video Generation (High Quality)
        $sora2Pro = AiModel::firstOrCreate(
            ['provider_id' => $provider->id, 'name' => 'sora-2-pro'],
            ['slug' => 'sora-2-pro', 'model_type' => 'video', 'status' => 'active']
        );
        
        $this->updateOrCreatePricing($sora2Pro->id, [
            'pricing_type' => 'per_second',
            'unit_definition' => [
                'price_per_second' => 0.30,
                'minimum_seconds' => 4,
                'unit' => 'second',
                'portrait_resolution' => '720x1280',
                'landscape_resolution' => '1280x720',
                'high_res_portrait' => '1024x1792',
                'high_res_landscape' => '1792x1024',
                'high_res_price_per_second' => 0.50,
                'note' => 'Resolución estándar: $0.30/seg | Alta resolución (1024x1792): $0.50/seg'
            ],
            'effective_to' => null,
            'markup_percentage' => null,
            'status' => 'active'
        ]);
    }
    
    /**
     * Crear modelos de Anthropic
     */
    private function createAnthropicModels($provider): void
    {
        // Claude Sonnet 4 (20250514)
        // Usado en: Herramienta2Controller (Genesis, Construcción Escenario)
        $this->createTextModel($provider, [
            'name' => 'claude-sonnet-4-20250514',
            'slug' => 'claude-sonnet-4-20250514',
            'input_price' => 3.00,  // USD por 1M tokens
            'output_price' => 15.00, // USD por 1M tokens
        ]);
        // Claude Sonnet 4.5 (20250929)
        // Usado en: Herramienta2Controller (Genesis, Construcción Escenario), Chat
        // Disponible hasta: 29 de septiembre de 2026
        $this->createTextModel($provider, [
            'name' => 'claude-sonnet-4-5-20250929',
            'slug' => 'claude-sonnet-4-5-20250929',
            'input_price' => 3.00,  // USD por 1M tokens
            'output_price' => 15.00, // USD por 1M tokens
            'available_until' => '2026-09-29', // No antes del 29 de septiembre de 2026
        ]);

        // Claude Haiku 4.5 (20251001)
        // Usado en: Chat (modelo rápido y económico)
        $this->createTextModel($provider, [
            'name' => 'claude-haiku-4-5-20251001',
            'slug' => 'claude-haiku-4-5-20251001',
            'input_price' => 1.00,  // USD por 1M tokens
            'output_price' => 5.00, // USD por 1M tokens
        ]);

        // Claude Opus 4.1 (20250805)
        // Usado en: Chat (modelo más potente y preciso)
        $this->createTextModel($provider, [
            'name' => 'claude-opus-4-1-20250805',
            'slug' => 'claude-opus-4-1-20250805',
            'input_price' => 15.00,  // USD por 1M tokens
            'output_price' => 75.00, // USD por 1M tokens
        ]);
    }
    
    /**
     * Crear modelos de Google (Gemini)
     */
    private function createGoogleModels($provider): void
    {
        // Gemini 2.0 Flash Experimental
        $this->createTextModel($provider, [
            'name' => 'gemini-2.0-flash-exp',
            'slug' => 'gemini-2.0-flash-exp',
            'input_price' => 0.10,  // USD por 1M tokens
            'output_price' => 0.40,  // USD por 1M tokens
        ]);
        
        // Gemini 2.5 Flash
        // Usado en: Herramienta1Controller (datosextras, GenerarBrief, GenerarBriefGenerateIA), Chat
        $this->createTextModel($provider, [
            'name' => 'gemini-2.5-flash',
            'slug' => 'gemini-2.5-flash',
            'input_price' => 0.30,  // USD por 1M tokens
            'output_price' => 2.50,  // USD por 1M tokens
        ]);

        // Gemini 2.5 Pro
        // Usado en: Chat (modelo avanzado, contexto ≤ 200K tokens)
        $this->createTextModel($provider, [
            'name' => 'gemini-2.5-pro',
            'slug' => 'gemini-2.5-pro',
            'input_price' => 1.25,  // USD por 1M tokens (instrucciones ≤ 200K)
            'output_price' => 10.00, // USD por 1M tokens (incl. thought tokens)
        ]);

        // Gemini 3.1 Pro Preview
        // Usado en: Chat (modelo avanzado, contexto ≤ 200K tokens)
        $this->createTextModel($provider, [
            'name' => 'gemini-3.1-pro-preview',
            'slug' => 'gemini-3.1-pro-preview',
            'input_price' => 2.00,   // USD por 1M tokens (≤200K)
            'output_price' => 12.00, // USD por 1M tokens (≤200K, incl. thought tokens)
        ]);
    }
    
    /**
     * Crear modelos de Perplexity
     */
    private function createPerplexityModels($provider): void
    {
        // Sonar Reasoning Pro
        // Usado en: Herramienta2Controller (GenerarInsight, GenerarInsight2)
        $this->createTextModel($provider, [
            'name' => 'sonar-reasoning-pro',
            'slug' => 'sonar-reasoning-pro',
            'input_price' => 1.00,  // USD por 1M tokens
            'output_price' => 5.00,  // USD por 1M tokens
        ]);
        
        // Sonar Deep Research (con costos adicionales)
        // Usado en: InvestigacionController
        $sonarDeepResearch = AiModel::firstOrCreate(
            ['provider_id' => $provider->id, 'name' => 'sonar-deep-research'],
            ['slug' => 'sonar-deep-research', 'model_type' => 'text', 'status' => 'active']
        );
        
        $this->updateOrCreatePricing($sonarDeepResearch->id, [
            'pricing_type' => 'per_token',
            'unit_definition' => [
                'input_price' => 2.00,           // USD por 1M tokens
                'output_price' => 8.00,          // USD por 1M tokens
                'citation_price' => 2.00,        // USD por 1M tokens
                'reasoning_price' => 3.00,       // USD por 1M tokens
                'search_query_price' => 5.00,    // USD por 1K consultas (no 1M)
                'unit' => 'token'
            ],
            'effective_to' => null,
            'markup_percentage' => null,
            'status' => 'active'
        ]);
    }
    
    /**
     * Crear modelos de generación de imágenes
     */
    private function createImageModels(array $providers): void
    {
        // ============================================
        // OPENAI - GPT Image (cobra por tokens)
        // ============================================
        
        $gptImage1 = AiModel::firstOrCreate(
            ['provider_id' => $providers['openai']->id, 'name' => 'gpt-image-1'],
            ['slug' => 'gpt-image-1', 'model_type' => 'image', 'status' => 'active']
        );
        
        $this->updateOrCreatePricing($gptImage1->id, [
            'pricing_type' => 'per_token',
            'unit_definition' => [
                'input_price' => 10.00,  // USD por 1M tokens
                'output_price' => 40.00, // USD por 1M tokens
                'unit' => 'token'
            ],
            'effective_to' => null,
            'markup_percentage' => null,
            'status' => 'active'
        ]);
        
        // ============================================
        // GOOGLE - Imagen 4.0 (vía Gemini API directa)
        // ============================================
        
        $this->createImageGenerationModel($providers['google'], [
            'name' => 'imagen-4.0-generate-001',
            'slug' => 'imagen-4.0-generate-001',
            'price_per_generation' => 0.06,
        ]);

        // Nano Banana 2 (Gemini 3.1 Flash Image - vía Gemini API directa, cobro por token)
        $gemini31Flash = AiModel::firstOrCreate(
            ['provider_id' => $providers['google']->id, 'name' => 'gemini-3.1-flash-image-preview'],
            ['slug' => 'gemini-3.1-flash-image-preview', 'model_type' => 'image', 'status' => 'active']
        );
        $this->updateOrCreatePricing($gemini31Flash->id, [
            'pricing_type' => 'per_token',
            'unit_definition' => [
                'input_price' => 0.25,   // USD por 1M tokens (texto/imagen entrada)
                'output_price' => 60.00, // USD por 1M tokens (imágenes salida)
                'unit' => 'token'
            ],
            'effective_to' => null,
            'markup_percentage' => null,
            'status' => 'active'
        ]);

        // Nano Banana Pro = gemini-3-pro-image-preview (Google vía Gemini API directa, cobro por token)
        // Precios: entrada USD 2.00/1M tokens (≈0.0011/img), salida USD 120/1M tokens (1K/2K≈0.134/img, 4K≈0.24/img)
        $nanoBananaPro = AiModel::firstOrCreate(
            ['provider_id' => $providers['google']->id, 'name' => 'gemini-3-pro-image-preview'],
            ['slug' => 'gemini-3-pro-image-preview', 'model_type' => 'image', 'status' => 'active']
        );
        $this->updateOrCreatePricing($nanoBananaPro->id, [
            'pricing_type' => 'per_token',
            'unit_definition' => [
                'input_price' => 2.00,    // USD por 1M tokens (texto/imagen; 560 tokens ≈ 0.0011 por imagen entrada)
                'output_price' => 120.00, // USD por 1M tokens (imágenes: 1K/2K=1120 tokens≈0.134, 4K=2000≈0.24)
                'unit' => 'token'
            ],
            'effective_to' => null,
            'markup_percentage' => null,
            'status' => 'active'
        ]);
        
        // ============================================
        // REPLICATE - Modelos de terceros vía Replicate
        // ============================================
        
        // Imagen 4 Ultra (Google vía Replicate)
        $this->createImageGenerationModel($providers['replicate'], [
            'name' => 'imagen-4-ultra',
            'slug' => 'imagen-4-ultra',
            'price_per_generation' => 0.06,
        ]);
        
        // Seedream 4.5 (Bytedance vía Replicate)
        $this->createImageGenerationModel($providers['replicate'], [
            'name' => 'seedream-4.5',
            'slug' => 'seedream-4.5',
            'price_per_generation' => 0.04,
        ]);
        
        // Qwen Image (Qwen vía Replicate)
        $this->createImageGenerationModel($providers['replicate'], [
            'name' => 'qwen-image',
            'slug' => 'qwen-image',
            'price_per_generation' => 0.025,
        ]);
        
        // ============================================
        // FLUX - Modelos de generación de imágenes
        // ============================================
        
        $fluxModels = [
            ['name' => 'flux-kontext-max', 'slug' => 'flux-kontext-max', 'price' => 0.08],
            ['name' => 'flux-kontext-pro', 'slug' => 'flux-kontext-pro', 'price' => 0.04],
            ['name' => 'flux-pro', 'slug' => 'flux-pro-1.1', 'price' => 0.04],
            ['name' => 'flux-ultra', 'slug' => 'flux-pro-1.1-ultra', 'price' => 0.06],
            ['name' => 'flux-2-pro', 'slug' => 'flux-2-pro', 'price' => 0.03],
        ];
        
        foreach ($fluxModels as $model) {
            $this->createImageGenerationModel($providers['flux'], [
                'name' => $model['name'],
                'slug' => $model['slug'],
                'price_per_generation' => $model['price'],
            ]);
        }
    }
    
    /**
     * Crear modelos de generación de videos
     */
    private function createVideoModels(array $providers): void
    {
        // ============================================
        // GOOGLE - VEO Models
        // ============================================
        
        // Veo 3.1 (vía Replicate)
        $this->createVideoModel($providers['replicate'], [
            'name' => 'veo3.1',
            'slug' => 'veo3.1',
            'price_per_second' => 0.40,
            'minimum_seconds' => 4,
            'extra' => ['note' => 'Con audio: $0.40/seg | Sin audio: $0.20/seg | Siempre se usa $0.40']
        ]);
        
        // Veo2 (vía Gemini API directa)
        $this->createVideoModel($providers['google'], [
            'name' => 'veo2',
            'slug' => 'veo-2.0-generate-001',
            'price_per_second' => 0.12,
            'minimum_seconds' => 4,
        ]);
        
        // ============================================
        // KWAIVGI - Kling (vía Replicate)
        // ============================================
        
        $this->createVideoModel($providers['replicate'], [
            'name' => 'kling',
            'slug' => 'kling',
            'price_per_second' => 0.07,
            'minimum_seconds' => 4,
        ]);
        
        // ============================================
        // RUNWAY - Gen Models
        // ============================================
        
        $runwayModels = [
            ['name' => 'gen4_turbo', 'slug' => 'gen4_turbo', 'price' => 0.08, 'min' => 4],
            ['name' => 'gen3a_turbo', 'slug' => 'gen3a_turbo', 'price' => 0.06, 'min' => 4],
            ['name' => 'gen4_aleph', 'slug' => 'gen4_aleph', 'price' => 0.15, 'min' => 1], // Editor
        ];
        
        foreach ($runwayModels as $model) {
            $this->createVideoModel($providers['runway'], [
                'name' => $model['name'],
                'slug' => $model['slug'],
                'price_per_second' => $model['price'],
                'minimum_seconds' => $model['min'],
            ]);
        }
        
        // ============================================
        // LUMA - Ray Models (720p 5s, ver https://lumalabs.ai/api/pricing)
        // ============================================
        
        $lumaModels = [
            ['name' => 'ray2', 'slug' => 'ray2', 'price' => 0.142],       // 720p·5s ≈ $0.71 → $0.142/s
            ['name' => 'ray2-flash', 'slug' => 'ray2-flash', 'price' => 0.048], // 720p·5s ≈ $0.24 → $0.048/s
        ];
        
        foreach ($lumaModels as $model) {
            $this->createVideoModel($providers['luma'], [
                'name' => $model['name'],
                'slug' => $model['slug'],
                'price_per_second' => $model['price'],
                'minimum_seconds' => 4,
            ]);
        }
    }
    
    /**
     * Crear modelos de presentaciones
     */
    private function createPresentationModels($provider): void
    {
        // Gamma App - Generación de presentaciones
        $gammaApp = AiModel::firstOrCreate(
            ['provider_id' => $provider->id, 'name' => 'gamma-app'],
            ['slug' => 'gamma-app', 'model_type' => 'presentation', 'status' => 'active']
        );
        
        $this->updateOrCreatePricing($gammaApp->id, [
            'pricing_type' => 'per_credit',
            'unit_definition' => [
                'price_per_credit' => 0.004, // 1500 créditos = $6.00, entonces 1 crédito = $0.004
                'unit' => 'credit'
            ],
            'effective_to' => null,
            'markup_percentage' => null,
            'status' => 'active'
        ]);
    }
    
    // ============================================
    // MÉTODOS AUXILIARES
    // ============================================
    
    /**
     * Actualiza o crea un precio para un modelo
     * 
     * IMPORTANTE: Siempre actualiza el precio vigente si existe, o lo crea si no existe.
     * No crea registros duplicados. El historial de precios se mantiene mediante el 
     * campo pricing_snapshot en cada registro de uso (UsageRecord).
     * 
     * @param int $modelId ID del modelo
     * @param array $pricingData Datos del pricing
     * @return ModelPricing
     */
    private function updateOrCreatePricing(int $modelId, array $pricingData): ModelPricing
    {
        // Buscar precio vigente (effective_to = null) para este modelo
        $currentPricing = ModelPricing::where('ai_model_id', $modelId)
            ->where('status', 'active')
            ->whereNull('effective_to')
            ->first();
        
        // Si existe un precio vigente, actualizarlo (siempre)
        if ($currentPricing) {
            // Mantener el effective_from original
            unset($pricingData['effective_from']);
            $currentPricing->update($pricingData);
            $this->command->info("  ✓ Precio actualizado para modelo ID: {$modelId}");
            return $currentPricing->fresh();
        }
        
        // Si no existe, crear uno nuevo
        $pricingData['ai_model_id'] = $modelId;
        $pricingData['effective_from'] = $pricingData['effective_from'] ?? Carbon::now()->format('Y-m-d');
        $pricing = ModelPricing::create($pricingData);
        $this->command->info("  ✓ Precio creado para modelo ID: {$modelId}");
        return $pricing;
    }
    
    /**
     * Crear modelo de texto con pricing
     */
    private function createTextModel($provider, array $config): void
    {
        $model = AiModel::firstOrCreate(
            ['provider_id' => $provider->id, 'name' => $config['name']],
            [
                'slug' => $config['slug'], 
                'model_type' => 'text', 
                'status' => 'active',
                'available_until' => $config['available_until'] ?? null
            ]
        );
        
        // Si ya existe el modelo pero tiene una fecha nueva, actualizarla
        if ($model->wasRecentlyCreated === false && isset($config['available_until'])) {
            $model->update(['available_until' => $config['available_until']]);
        }
        
        $this->updateOrCreatePricing($model->id, [
            'pricing_type' => 'per_token',
            'unit_definition' => [
                'input_price' => $config['input_price'],
                'output_price' => $config['output_price'],
                'unit' => 'token'
            ],
            'effective_to' => null,
            'markup_percentage' => null,
            'status' => 'active'
        ]);
    }
    
    /**
     * Crear modelo de imagen con pricing por generación
     */
    private function createImageGenerationModel($provider, array $config): void
    {
        $model = AiModel::firstOrCreate(
            ['provider_id' => $provider->id, 'name' => $config['name']],
            [
                'slug' => $config['slug'], 
                'model_type' => 'image', 
                'status' => 'active',
                'available_until' => $config['available_until'] ?? null
            ]
        );
        
        // Si ya existe el modelo pero tiene una fecha nueva, actualizarla
        if ($model->wasRecentlyCreated === false && isset($config['available_until'])) {
            $model->update(['available_until' => $config['available_until']]);
        }
        
        $this->updateOrCreatePricing($model->id, [
            'pricing_type' => 'per_generation',
            'unit_definition' => [
                'price_per_generation' => $config['price_per_generation'],
                'unit' => 'generation'
            ],
            'effective_to' => null,
            'markup_percentage' => null,
            'status' => 'active'
        ]);
    }
    
    /**
     * Crear modelo de video con pricing por segundo
     */
    private function createVideoModel($provider, array $config): void
    {
        $model = AiModel::firstOrCreate(
            ['provider_id' => $provider->id, 'name' => $config['name']],
            [
                'slug' => $config['slug'], 
                'model_type' => 'video', 
                'status' => 'active',
                'available_until' => $config['available_until'] ?? null
            ]
        );
        
        // Si ya existe el modelo pero tiene una fecha nueva, actualizarla
        if ($model->wasRecentlyCreated === false && isset($config['available_until'])) {
            $model->update(['available_until' => $config['available_until']]);
        }
        
        $unitDefinition = [
            'price_per_second' => $config['price_per_second'],
            'minimum_seconds' => $config['minimum_seconds'],
            'unit' => 'second'
        ];
        
        if (isset($config['extra'])) {
            $unitDefinition = array_merge($unitDefinition, $config['extra']);
        }
        
        $this->updateOrCreatePricing($model->id, [
            'pricing_type' => 'per_second',
            'unit_definition' => $unitDefinition,
            'effective_to' => null,
            'markup_percentage' => null,
            'status' => 'active'
        ]);
    }
}
