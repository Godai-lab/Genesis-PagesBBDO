<?php

namespace App\Http\Controllers;

use App\Http\Traits\ValidatesCreditLimit;
use App\Models\Account;
use App\Models\Generated;
use App\Supports\CostCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ValidarConceptoController extends Controller
{
    use ValidatesCreditLimit;
    
    protected string $toolName = 'Validar Concepto';
    /**
     * Método auxiliar para registrar el uso de tokens de OpenAI
     * 
     * @param int $accountId ID de la cuenta
     * @param string $model Nombre del modelo usado (ej: 'gpt-5')
     * @param array $usageData Datos de uso del servicio OpenAI
     * @param string $context Contexto de la llamada para request_type (ej: 'getValidarConceptoForm', 'get_concepto')
     * @return void
     */
    private function trackUsageIfAvailable($accountId, $model, $usageData, $context = '')
    {
        try {
            $inputTokens = 0;
            $outputTokens = 0;
            
            // Extraer tokens de OpenAI (input_tokens y output_tokens)
            if (isset($usageData['input_tokens']) && isset($usageData['output_tokens'])) {
                $inputTokens = $usageData['input_tokens'];
                $outputTokens = $usageData['output_tokens'];
            } elseif (isset($usageData['prompt_tokens']) && isset($usageData['completion_tokens'])) {
                // Formato alternativo de OpenAI
                $inputTokens = $usageData['prompt_tokens'];
                $outputTokens = $usageData['completion_tokens'];
            }
            
            // Solo registrar si hay tokens
            if ($inputTokens > 0 || $outputTokens > 0) {
                // Agregar sufijo "-Concepto" para identificar que es de ValidarConcepto
                $requestType = $context ? $context . '-Concepto' : 'validar-concepto';
                
                CostCalculationService::trackUsage(
                    $accountId,
                    auth()->id(),
                    $model,
                    [
                        'tokens' => [
                            'input' => $inputTokens,
                            'output' => $outputTokens
                        ]
                    ],
                    Carbon::now(),
                    $requestType
                );
                
                Log::info("✅ Uso registrado exitosamente (Validar Concepto)", [
                    'context' => $context,
                    'model' => $model,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'request_type' => $requestType
                ]);
            }
        } catch (\Exception $e) {
            Log::error("❌ Error al registrar uso en ValidarConceptoController", [
                'context' => $context,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // No lanzamos la excepción para no interrumpir el flujo
        }
    }

    public function index(Request $request)
    {
        Log::info('📋 Accediendo a index de ValidarConceptoController', [
            'user_id' => auth()->id(),
            'query_params' => $request->query()
        ]);
        
        Gate::authorize('haveaccess','genesis.index');
        $accounts = Account::fullaccess()->get();
        $data_generated = [];
        $id_generated = $request->query('generated');

        if ($id_generated) {
            Log::info('🔍 Buscando generación existente en index', [
                'id_generated' => $id_generated
            ]);
            
            $generated = Generated::find($id_generated);
            if ($generated && $generated->key === 'Concepto') {
                $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];
                $step = isset($metadata['step']) ? $metadata['step'] : null;
                $data_generated = [
                    'name' => $generated->name,
                    'value' => $generated->value,
                    'rating' => $generated->rating,
                    'status' => $generated->status,
                    'id_generated' => $generated->id,
                    'account_id' => $generated->account_id,
                    'step' => $step,
                    'metadata' => $metadata
                ];
                
                Log::info('✅ Generación encontrada y cargada en index', [
                    'id_generated' => $generated->id,
                    'status' => $generated->status,
                    'step' => $step
                ]);
            } else {
                Log::warning('⚠️ Generación no encontrada o no es de tipo Concepto', [
                    'id_generated' => $id_generated,
                    'found' => $generated ? true : false,
                    'key' => $generated ? $generated->key : null
                ]);
            }
        }
        
        Log::info('✅ Vista index de ValidarConcepto cargada exitosamente', [
            'accounts_count' => $accounts->count(),
            'has_data_generated' => !empty($data_generated)
        ]);
        
        return view('validarConcepto.index', compact('accounts', 'data_generated'));
    }
    public function getValidarConceptoForm(Request $request)
    {
        Log::info('🚀 Iniciando getValidarConceptoForm', [
            'user_id' => auth()->id(),
            'request_data' => $request->except(['_token'])
        ]);
        
        try{
            // Validar las URLs y archivos
            $validator = Validator::make($request->all(), [
                'concepto_pais' => 'required|string',
                'concepto_nombre_marca' => 'required|string',
                'concepto_categoria' => 'required|string',
                'concepto_periodo_campania' => 'required|string',
                'concepto_concepto' => 'required|string',
                'id_account' => 'required|integer',
                'id_generated' => 'nullable|integer', //permitir null
            ]);
            if ($validator->fails()) {
                Log::error('Validación fallida en getValidarConceptoForm', [
                    'errors' => $validator->errors()
                ]);
                throw new \Exception('Validación fallida en getValidarConceptoForm');
                // return response()->json(['success' => false, 'error' => $validator->errors()]);
            }
            $concepto_pais = $request->input('concepto_pais');
            $concepto_nombre_marca = $request->input('concepto_nombre_marca');
            $concepto_categoria = $request->input('concepto_categoria');
            $concepto_periodo_campania = $request->input('concepto_periodo_campania');
            $concepto_concepto = $request->input('concepto_concepto');
            $id_account = $request->input('id_account');
            
            // Validar límite de créditos antes de generar
            $this->validateCreditLimit($id_account);
            
            $id_generated = $request->input('id_generated') ?? null;
            if($id_generated){
                Log::info('🔄 Usando generación existente en getValidarConceptoForm', [
                    'id_generated' => $id_generated
                ]);
                $generated = Generated::find($id_generated);
            }else{
                Log::info('✨ Creando nueva generación en getValidarConceptoForm', [
                    'account_id' => $id_account
                ]);
                $metadata = [
                    'account_id' => $id_account,
                    'concepto_pais' => $concepto_pais,
                    'concepto_nombre_marca' => $concepto_nombre_marca,
                    'concepto_categoria' => $concepto_categoria,
                    'concepto_periodo_campania' => $concepto_periodo_campania,
                    'concepto_concepto' => $concepto_concepto,
                    'step' => 3,
                ];
                $generated = Generated::create([
                    'account_id' => $id_account,
                    'key' => 'Concepto',
                    'name' => 'Validar Concepto en proceso...',
                    'value' => $concepto_concepto,
                    'rating' => null,
                    'status' => 'processing',
                    'metadata' => json_encode($metadata),
                ]);
                
                Log::info('✅ Nueva generación creada exitosamente', [
                    'id_generated' => $generated->id,
                    'account_id' => $id_account
                ]);
            }

            $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];
            
            $options = [
                'prompt' => [
                    'id' => 'pmpt_68a2319d991c8190a4152ca9c8ae51e705034b13c3fd9d8e',
                    'variables' => [
                        "concepto" => $concepto_concepto,
                        "marca" => $concepto_nombre_marca,
                        "categoria" => $concepto_categoria,
                        "pais" => $concepto_pais,   
                        "periodo" => $concepto_periodo_campania
                    ]
                ],
                'background' => true
            ];

            Log::info('🤖 Llamando a OpenAiService::createModelResponse', [
                'id_generated' => $generated->id,
                'prompt_id' => $options['prompt']['id']
            ]);

            $response = \App\Services\OpenAiService::createModelResponse($options);

            if (isset($response['error'])) {
                Log::error('Error en la llamada a OpenAiService::createModelResponse (Validar Concepto)', [
                    'error' => $response['error']
                ]);
                throw new \Exception('Error en la llamada a OpenAiService::createModelResponse (Validar Concepto)');
                // return response()->json(['success' => false, 'error' => $response['error']]);
            }

            $metadata['id_generacion_concepto'] = $response['data']['id'];
            $metadata['generacion_concepto_data'] = $response['data'];
            $metadata['generacion_concepto_status'] = 'processing';
            $metadata['step'] = 4;

            Log::info('💾 Actualizando generación con respuesta de OpenAI', [
                'id_generated' => $generated->id,
                'openai_generation_id' => $response['data']['id'],
                'step' => 4
            ]);

            $generated->update([
                'name' => 'Validar Concepto en proceso...',
                'metadata' => json_encode($metadata)
            ]);

            Log::info('✅ getValidarConceptoForm completado exitosamente', [
                'id_generated' => $generated->id,
                'openai_generation_id' => $response['data']['id']
            ]);

            return response()->json(['success' => true, 'data' => $response['data'], 'function' => 'getValidarConceptoForm', 'id_generated' => $generated->id]);
        } catch (\App\Exceptions\CreditLimitExceededException $e) {
            Log::warning('Límite de créditos excedido en getValidarConceptoForm', [
                'message' => $e->getMessage(),
                'accountId' => $id_account ?? null
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        } catch(\Exception $e){
            Log::error('Error en getValidarConceptoForm', [
                'message' => $e->getMessage(),
                'accountId' => $id_account ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Ha ocurrido un error al validar el concepto. Por favor, intenta nuevamente.',
            ]);
        }
    }

    public function getValidarConceptoGenesis(Request $request)
    {
        Log::info('🚀 Iniciando getValidarConceptoGenesis', [
            'user_id' => auth()->id(),
            'request_data' => $request->except(['_token'])
        ]);
        
        try{
            // Validar las URLs y archivos
            $validator = Validator::make($request->all(), [
                // 'concepto_pais' => 'required|string',
                // 'concepto_nombre_marca' => 'required|string',
                'concepto_categoria' => 'required|string',
                'concepto_periodo_campania' => 'required|string',
                // 'concepto_concepto' => 'required|string',
                'id_account' => 'required|integer',
                'id_generated' => 'nullable|integer',
                'id_genesis' => 'required|integer',
            ]);
            if ($validator->fails()) {
                Log::error('Validación fallida en getValidarConceptoForm', [
                    'errors' => $validator->errors()
                ]);
                throw new \Exception('Validación fallida en getValidarConceptoForm');
                // return response()->json(['success' => false, 'error' => $validator->errors()]);
            }
            // $concepto_pais = $request->input('concepto_pais');
            // $concepto_nombre_marca = $request->input('concepto_nombre_marca');
            $concepto_categoria = $request->input('concepto_categoria');
            $concepto_periodo_campania = $request->input('concepto_periodo_campania');
            // $concepto_concepto = $request->input('concepto_concepto');
            $id_account = $request->input('id_account');
            
            // Validar límite de créditos antes de generar
            $this->validateCreditLimit($id_account);
            
            $id_generated = $request->input('id_generated') ?? null;
            $id_genesis = $request->input('id_genesis');
            
            Log::info('🔍 Buscando generación Genesis', [
                'id_genesis' => $id_genesis
            ]);
            
            $genesis = Generated::find($id_genesis);
            if (!$genesis) {
                Log::error('❌ Genesis no encontrada en getValidarConceptoGenesis', [
                    'id_genesis' => $id_genesis
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Generación no encontrada'
                ]);
            }
            $metadataGenesis = $genesis->metadata ? json_decode($genesis->metadata, true) : [];
            $concepto_concepto = $metadataGenesis['construccionescenario'];
            $id_brief = $metadataGenesis['id_brief'];
            
            Log::info('🔍 Buscando generación Brief', [
                'id_brief' => $id_brief
            ]);
            
            $brief = Generated::find($id_brief);
            if (!$brief) {
                Log::error('❌ Brief no encontrada en getValidarConceptoGenesis', [
                    'id_brief' => $id_brief
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Generación no encontrada'
                ]);
            }
            $metadataBrief = $brief->metadata ? json_decode($brief->metadata, true) : [];
            $concepto_pais = $metadataBrief['country'];
            $concepto_nombre_marca = $metadataBrief['name'];
            
            Log::info('✅ Datos extraídos de Genesis y Brief', [
                'concepto_pais' => $concepto_pais,
                'concepto_nombre_marca' => $concepto_nombre_marca
            ]);

            if($id_generated){
                Log::info('🔄 Usando generación existente en getValidarConceptoGenesis', [
                    'id_generated' => $id_generated
                ]);
                $generated = Generated::find($id_generated);
            }else{
                Log::info('✨ Creando nueva generación en getValidarConceptoGenesis', [
                    'account_id' => $id_account,
                    'id_genesis' => $id_genesis,
                    'id_brief' => $id_brief
                ]);
                $metadata = [
                    'account_id' => $id_account,
                    'concepto_pais' => $concepto_pais,
                    'concepto_nombre_marca' => $concepto_nombre_marca,
                    'concepto_categoria' => $concepto_categoria,
                    'concepto_periodo_campania' => $concepto_periodo_campania,
                    'concepto_concepto' => $concepto_concepto,
                    'id_brief' => $id_brief,
                    'id_genesis' => $id_genesis,
                    'step' => 3,
                ];
                $generated = Generated::create([
                    'account_id' => $id_account,
                    'key' => 'Concepto',
                    'name' => 'Validar Concepto en proceso...',
                    'value' => $concepto_concepto,
                    'rating' => null,
                    'status' => 'processing',
                    'metadata' => json_encode($metadata),
                ]);
                
                Log::info('✅ Nueva generación creada exitosamente en getValidarConceptoGenesis', [
                    'id_generated' => $generated->id,
                    'account_id' => $id_account
                ]);
            }

            $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];
            
            $options = [
                'prompt' => [
                    'id' => 'pmpt_68a2319d991c8190a4152ca9c8ae51e705034b13c3fd9d8e',
                    'variables' => [
                        "concepto" => $concepto_concepto,
                        "marca" => $concepto_nombre_marca,
                        "categoria" => $concepto_categoria,
                        "pais" => $concepto_pais,   
                        "periodo" => $concepto_periodo_campania
                    ]
                ],
                'background' => true
            ];

            Log::info('🤖 Llamando a OpenAiService::createModelResponse (Genesis)', [
                'id_generated' => $generated->id,
                'prompt_id' => $options['prompt']['id']
            ]);

            $response = \App\Services\OpenAiService::createModelResponse($options);

            if (isset($response['error'])) {
                Log::error('Error en la llamada a OpenAiService::createModelResponse (Validar Concepto)', [
                    'error' => $response['error']
                ]);
                throw new \Exception('Error en la llamada a OpenAiService::createModelResponse (Validar Concepto)');
                // return response()->json(['success' => false, 'error' => $response['error']]);
            }

            $metadata['id_generacion_concepto'] = $response['data']['id'];
            $metadata['generacion_concepto_data'] = $response['data'];
            $metadata['generacion_concepto_status'] = 'processing';
            $metadata['step'] = 4;

            Log::info('💾 Actualizando generación con respuesta de OpenAI (Genesis)', [
                'id_generated' => $generated->id,
                'openai_generation_id' => $response['data']['id'],
                'step' => 4
            ]);

            $generated->update([
                'name' => 'Validar Concepto en proceso...',
                'metadata' => json_encode($metadata)
            ]);

            Log::info('✅ getValidarConceptoGenesis completado exitosamente', [
                'id_generated' => $generated->id,
                'openai_generation_id' => $response['data']['id']
            ]);

            return response()->json(['success' => true, 'data' => $response['data'], 'function' => 'getValidarConceptoForm', 'id_generated' => $generated->id]);
        } catch (\App\Exceptions\CreditLimitExceededException $e) {
            Log::warning('Límite de créditos excedido en getValidarConceptoGenesis', [
                'message' => $e->getMessage(),
                'accountId' => $id_account ?? null
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        } catch(\Exception $e){
            Log::error('Error en getValidarConceptoGenesis', [
                'message' => $e->getMessage(),
                'accountId' => $id_account ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Ha ocurrido un error al validar el concepto. Por favor, intenta nuevamente.',
            ]);
        }
    }

    public function get_concepto($generationId){
        Log::info('🔍 Consultando estado de concepto', [
            'generation_id' => $generationId,
            'user_id' => auth()->id()
        ]);
        
        try {
            $generated = Generated::find($generationId);
            
            if (!$generated) {
                Log::warning('⚠️ Generación no encontrada en get_concepto', [
                    'generation_id' => $generationId
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Generación no encontrada'
                ], 404);
            }
            
            Log::info('✅ Generación encontrada en get_concepto', [
                'generation_id' => $generationId,
                'status' => $generated->status,
                'account_id' => $generated->account_id
            ]);
    
            $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];
    
            $content = '';
            $sources = [];
            $statusgenerated = 'processing';
    
            Log::info('🤖 Consultando respuesta de OpenAI', [
                'generation_id' => $generationId,
                'openai_generation_id' => $metadata['id_generacion_concepto'] ?? null
            ]);
    
            $response = \App\Services\OpenAiService::getModelResponse($metadata['id_generacion_concepto']);
    
            if(isset($response['success']) && !$response['success']){
                Log::error('❌ Error en respuesta de OpenAI en get_concepto', [
                    'generation_id' => $generationId,
                    'error' => $response['error'] ?? 'Error desconocido'
                ]);
                return response()->json([
                    'success' => false,
                    'error' => $response['error']
                ], 500);
            }
    
            Log::info('📊 Estado de generación OpenAI', [
                'generation_id' => $generationId,
                'status' => $response['data']['status'] ?? 'unknown'
            ]);
    
            if($response['data']['status'] === 'completed'){
                Log::info('✅ Generación completada, procesando contenido', [
                    'generation_id' => $generationId
                ]);
                $statusgenerated = 'completed';
                if (isset($response['data']['output'])) {
                    foreach ($response['data']['output'] as $output_item) {
                        if ($output_item['type'] === 'message') {
                            if (isset($output_item['content'][0]['text'])) {
                                $content = $output_item['content'][0]['text'];
                            }
                            if (isset($output_item['content'][0]['annotations'])) {
                                foreach($output_item['content'][0]['annotations'] as $annotation){
                                    if($annotation['type'] === 'url_citation'){
                                        $sources[] = $annotation['url'];
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Registrar uso de tokens cuando se completa exitosamente
                if (isset($response['data']['usage'])) {
                    Log::info('📊 Registrando uso de tokens', [
                        'generation_id' => $generationId,
                        'usage' => $response['data']['usage']
                    ]);
                    
                    $this->trackUsageIfAvailable(
                        $generated->account_id,
                        'gpt-5', // Modelo usado por OpenAI (puede variar según configuración)
                        $response['data']['usage'],
                        'get_concepto'
                    );
                }
            }
    
    
            if($statusgenerated === 'completed'){
                Log::info('💾 Guardando contenido completado en generación', [
                    'generation_id' => $generationId,
                    'content_length' => strlen($content),
                    'sources_count' => count($sources)
                ]);
    
                $metadata['generacion_concepto_content'] = $content;
                $metadata['generacion_concepto_sources'] = $sources;
                $metadata['generacion_concepto_status'] = 'completed';
    
                $generated->update([
                    'metadata' => json_encode($metadata)
                ]);
    
                Log::info('✅ get_concepto completado exitosamente', [
                    'generation_id' => $generationId,
                    'status' => 'completed'
                ]);
    
                return response()->json([
                    'success' => true,
                    'status' => 'completed',
                    'generation_id' => $generated->id,
                    'data' => $content,
                    'sources' => $sources
                ]);
            }else{
                Log::info('⏳ Generación aún en proceso', [
                    'generation_id' => $generationId,
                    'status' => $generated->status
                ]);
                
                return response()->json([
                    'success' => true,
                    'status' => $generated->status,
                    'generation_id' => $generated->id
                ]);
            }
    
        } catch (\Exception $e) {
            Log::error('❌ Error en get_concepto', [
                'generation_id' => $generationId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error al consultar estado: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveValidarConcepto(Request $request){
        Log::info('💾 Iniciando saveValidarConcepto', [
            'user_id' => auth()->id(),
            'request_data' => $request->except(['_token', 'validarConcepto'])
        ]);
        
        try{
            // Validar las URLs y archivos
            $validator = Validator::make($request->all(), [
                'validarConcepto' => 'required|string',
                'id_account' => 'required|integer',
                'rating' => 'required|integer',
                'file_name' => 'required|string',
                'id_generated' => 'required|integer',
            ]);
        
            if ($validator->fails()) {
                Log::error('❌ Validación fallida en saveValidarConcepto', [
                    'errors' => $validator->errors()
                ]);
                return response()->json(['error' => $validator->errors()]);
            }
        
            $validarConcepto = $request->input('validarConcepto');
            $id_account = $request->input('id_account');
            $id_generated = $request->input('id_generated');
            $rating = $request->input('rating');
            $file_name = $request->input('file_name');
        
            Log::info('🔍 Buscando generación para guardar', [
                'id_generated' => $id_generated,
                'rating' => $rating,
                'file_name' => $file_name
            ]);
        
            $generated = Generated::find($id_generated);
        
            if (!$generated) {
                Log::error('❌ Generación no encontrada en saveValidarConcepto', [
                    'id_generated' => $id_generated
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Generación no encontrada'
                ]);
            }
        
            $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];
        
            $metadata['validar_concepto'] = $validarConcepto;
            $metadata['step'] = 5;
        
            Log::info('💾 Actualizando generación con concepto validado', [
                'id_generated' => $id_generated,
                'rating' => $rating,
                'step' => 5
            ]);
        
            $generated->update([
                'name' => $file_name,
                'value' => $validarConcepto,
                'rating' => $rating,
                'status' => 'completed',
                'metadata' => json_encode($metadata)
            ]);
        
            Log::info('✅ saveValidarConcepto completado exitosamente', [
                'id_generated' => $generated->id,
                'rating' => $rating,
                'status' => 'completed'
            ]);
        
            return response()->json(['success' => true, 'data' => $validarConcepto, 'function' => 'saveValidarConcepto', 'id_generated' => $generated->id]);
        }catch(\Exception $e){
            Log::error('❌ Error en saveValidarConcepto', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id_generated' => $request->input('id_generated') ?? null
            ]);
            
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function mejorarConcepto(Request $request){
        Log::info('🚀 Iniciando mejorarConcepto', [
            'user_id' => auth()->id(),
            'request_data' => $request->except(['_token', 'validarConcepto'])
        ]);
        
        try{
            $validator = Validator::make($request->all(), [
                'validarConcepto' => 'required|string',
                'id_account' => 'required|integer',
                'id_generated' => 'required|integer',
            ]);
        
            if ($validator->fails()) {
                Log::error('❌ Validación fallida en mejorarConcepto', [
                    'errors' => $validator->errors()
                ]);
                return response()->json(['error' => $validator->errors()]);
            }
        
            $id_account = $request->input('id_account');
            
            // Validar límite de créditos antes de mejorar
            $this->validateCreditLimit($id_account);
            
            $id_generated = $request->input('id_generated');
            $validarConcepto = $request->input('validarConcepto');
        
            Log::info('🔍 Buscando generación y datos relacionados', [
                'id_generated' => $id_generated,
                'id_account' => $id_account
            ]);
        
            $generated = Generated::find($id_generated);
            if (!$generated) {
                Log::error('❌ Generación no encontrada en mejorarConcepto', [
                    'id_generated' => $id_generated
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Generación no encontrada'
                ]);
            }
        
            $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

            $id_genesis = $metadata['id_genesis'];
            
            Log::info('🔍 Buscando Genesis', [
                'id_genesis' => $id_genesis
            ]);
            
            $genesis = Generated::find($id_genesis);
            if (!$genesis) {
                Log::error('❌ Genesis no encontrada en mejorarConcepto', [
                    'id_genesis' => $id_genesis
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Genesis no encontrada'
                ]);
            }
            $metadataGenesis = $genesis->metadata ? json_decode($genesis->metadata, true) : [];
            $id_brief = $metadata['id_brief'];
            
            Log::info('🔍 Buscando Brief', [
                'id_brief' => $id_brief
            ]);
            
            $brief = Generated::find($id_brief);
            if (!$brief) {
                Log::error('❌ Brief no encontrada en mejorarConcepto', [
                    'id_brief' => $id_brief
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Brief no encontrada'
                ]);
            }
            $metadataBrief = $brief->metadata ? json_decode($brief->metadata, true) : [];
            $creatividad = $metadataGenesis['construccionescenario'];
            
            Log::info('✅ Datos relacionados encontrados', [
                'id_genesis' => $id_genesis,
                'id_brief' => $id_brief
            ]);
        
            $options = [
                'prompt' => [
                    'id' => 'pmpt_68cc1dd185588193a98bf0d237f72c0206d213a923b14fc6',
                    'variables' => [
                        "creatividad" => $creatividad,
                        "concepto_validado" => $validarConcepto,
                    ]
                ],
                'background' => true
            ];
        
            Log::info('🤖 Llamando a OpenAiService::createModelResponse (Mejorar Concepto)', [
                'id_generated' => $id_generated,
                'prompt_id' => $options['prompt']['id']
            ]);
        
            // Llamar al nuevo endpoint de deep research
            $response = \App\Services\OpenAiService::createModelResponse($options);
        
            if (isset($response['error'])) {
                Log::error('Error en la llamada a OpenAiService::createModelResponse (Mejorar Concepto)', [
                    'error' => $response['error']
                ]);
                return response()->json(['success' => false, 'error' => $response['error']]);
            }

            $metadataGenesis['id_generacion_mejorar_concepto'] = $response['data']['id'];
            $metadataGenesis['generacion_mejorar_concepto_data'] = $response['data'];
            $metadataGenesis['generacion_mejorar_concepto_status'] = 'pending';
        
            $metadataGenesis['step'] = 9;

            Log::info('✨ Creando nueva generación Genesis para concepto mejorado', [
                'account_id' => $id_account,
                'openai_generation_id' => $response['data']['id'],
                'step' => 9
            ]);

            $newGenesisGenerated = Generated::create([
                'account_id' => $id_account,
                'key' => 'Genesis',
                'name' => 'Mejorando concepto en proceso...',
                'value' => '<div class="text-center p-4"><div class="spinner-border" role="status"></div><p class="mt-2">Generando mejorar concepto...</p></div>',
                'rating' => null,
                'status' => 'processing', // Nuevo campo para el estado
                'metadata' => json_encode($metadataGenesis)
            ]);
        
            Log::info('✅ mejorarConcepto completado exitosamente', [
                'id_generated' => $newGenesisGenerated->id,
                'openai_generation_id' => $response['data']['id']
            ]);
        
            return response()->json(['success' => true, 'data' => $response['data'], 'function' => 'mejorarConcepto', 'id_generated' => $newGenesisGenerated->id]);
        } catch (\App\Exceptions\CreditLimitExceededException $e) {
            Log::warning('Límite de créditos excedido en mejorarConcepto', [
                'message' => $e->getMessage(),
                'accountId' => $id_account ?? null
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        } catch(\Exception $e){
            Log::error('Error en mejorarConcepto', [
                'message' => $e->getMessage(),
                'accountId' => $id_account ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Ha ocurrido un error al mejorar el concepto. Por favor, intenta nuevamente.',
            ]);
        }
    }
}
