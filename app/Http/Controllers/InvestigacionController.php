<?php

namespace App\Http\Controllers;

use App\Http\Traits\ValidatesCreditLimit;
use App\Models\Account;
use App\Models\Generated;
use App\Services\PerplexityService;
use App\Supports\CostCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class InvestigacionController extends Controller
{
    use ValidatesCreditLimit;
    
    protected string $toolName = 'Investigación';
    /**
     * Método auxiliar para registrar el uso de tokens
     * 
     * @param int $accountId ID de la cuenta
     * @param string $model Nombre del modelo usado
     * @param array $usageData Datos de uso del servicio
     * @param string $serviceType Tipo de servicio: 'openai', 'perplexity'
     * @param string $context Contexto de la llamada para request_type
     * @param string|null $externalRequestId ID de la solicitud externa (ej: ID de tarea asíncrona) para evitar duplicados
     * @param int|null $generatedId ID de la generación (Generated) para agrupar procesos
     * @return void
     */
    private function trackUsageIfAvailable($accountId, $model, $usageData, $serviceType, $context = '', $externalRequestId = null, $generatedId = null)
    {
        try {
            $inputTokens = 0;
            $outputTokens = 0;
            $usageMetrics = [];
            
            // Extraer tokens según el tipo de servicio
            if ($serviceType === 'openai' && isset($usageData['input_tokens']) && isset($usageData['output_tokens'])) {
                $inputTokens = $usageData['input_tokens'];
                $outputTokens = $usageData['output_tokens'];
                $usageMetrics = [
                    'tokens' => [
                        'input' => $inputTokens,
                        'output' => $outputTokens
                    ]
                ];
            } elseif ($serviceType === 'openai' && isset($usageData['prompt_tokens']) && isset($usageData['completion_tokens'])) {
                // Formato alternativo de OpenAI
                $inputTokens = $usageData['prompt_tokens'];
                $outputTokens = $usageData['completion_tokens'];
                $usageMetrics = [
                    'tokens' => [
                        'input' => $inputTokens,
                        'output' => $outputTokens
                    ]
                ];
            } elseif ($serviceType === 'perplexity') {
                // Para Perplexity, extraer todos los tipos de tokens disponibles
                $inputTokens = $usageData['prompt_tokens'] ?? 0;
                $outputTokens = $usageData['completion_tokens'] ?? 0;
                
                // Para sonar-deep-research, guardar todos los tipos de tokens
                // Si el modelo es sonar-deep-research, incluir todos los campos adicionales
                if ($model === 'sonar-deep-research') {
                    $usageMetrics = [
                        'tokens' => [
                            'input' => $inputTokens,
                            'output' => $outputTokens,
                            'citation' => $usageData['citation_tokens'] ?? 0,
                            'reasoning' => $usageData['reasoning_tokens'] ?? 0,
                            'search_queries' => $usageData['num_search_queries'] ?? $usageData['search_queries'] ?? 0
                        ]
                    ];
                } else {
                    // Para otros modelos de Perplexity, solo input/output
                    $usageMetrics = [
                        'tokens' => [
                            'input' => $inputTokens,
                            'output' => $outputTokens
                        ]
                    ];
                }
            }
            
            // Solo registrar si hay tokens
            if ($inputTokens > 0 || $outputTokens > 0) {
                // Agregar sufijo "-Investigacion" para identificar que es de InvestigacionController
                $requestType = $context ? $context . '-Investigacion' : 'investigacion';
                
                CostCalculationService::trackUsage(
                    $accountId,
                    auth()->id(),
                    $model,
                    $usageMetrics,
                    Carbon::now(),
                    $requestType,
                    $externalRequestId,
                    $generatedId, // generated_id
                    $context, // step
                    $serviceType // service_type
                );
                
                Log::info("✅ Uso registrado exitosamente (Investigación)", [
                    'context' => $context,
                    'model' => $model,
                    'service_type' => $serviceType,
                    'usage_metrics' => $usageMetrics,
                    'request_type' => $requestType,
                    'external_request_id' => $externalRequestId
                ]);
            }
        } catch (\Exception $e) {
            Log::error("❌ Error al registrar uso en InvestigacionController", [
                'context' => $context,
                'model' => $model,
                'service_type' => $serviceType,
                'external_request_id' => $externalRequestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // No lanzamos la excepción para no interrumpir el flujo
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize('haveaccess','investigacion.index');
        $accounts = Account::fullaccess()->get();
        $data_generated = [];
        $id_generated = $request->query('generated');

        if ($id_generated) {
            $generated = Generated::find($id_generated);
            if ($generated && $generated->key === 'Investigacion') {
                $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];
                $step = isset($metadata['step']) ? $metadata['step'] : null;
                $data_generated = [
                    'id_generated' => $generated->id,
                    'account_id' => $generated->account_id,
                    'step' => $step,
                    'metadata' => $metadata,
                    'status' => $generated->status,
                    'value' => $generated->value,
                ];
            }
        }
        return view('herramienta1.dashboard.investigacion.index',compact('accounts', 'data_generated'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validar las URLs y archivos
            $validator = Validator::make($request->all(), [
                'investigaciongenerada' => 'required|string',
                'account' => 'required|integer',
                'id_generated' => 'required|integer',
                'rating' => 'required|integer',
                'file_name' => 'required|string',
            ]);

            if ($validator->fails()) {
                Log::error('Validación fallida en guardarInvestigacion', [
                    'errors' => $validator->errors()
                ]);
                return response()->json(['error' => $validator->errors()]);
            }

            Log::info('Guardando investigación', [
                'request_data' => $request->all(),
                'account_id' => $request->input('account')
            ]);
    
            $accountId = $request->input('account');
            $id_generated = $request->input('id_generated');
            $investigacionData = $request->input('investigaciongenerada');
            $rating = $request->input('rating');
            $fileName = $request->input('file_name');
            // Validar que el account_id existe
            if (!$id_generated) {
                Log::error('Campo id_generated vacío en la solicitud', [
                    'request_data' => $request->all(),
                    'headers' => $request->headers->all()
                ]);
                throw new \Exception('No se ha proporcionado un ID de generación válido. Por favor, asegúrate de seleccionar una generación en el paso 1.');
            }
    
            // Validar que la generación existe en la base de datos
            $generated = Generated::find($id_generated);
            if (!$generated) {
                throw new \Exception('La generación seleccionada no existe en la base de datos.');
            }

            $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];
            $metadata['generacion_investigacion_content'] = $investigacionData;
            $metadata['generacion_investigacion_status'] = 'completed';
            $metadata['step'] = 4;

            $generated->update([
                'status' => 'completed',
                'name' => $fileName,
                'rating' => $rating,
                'value' => $investigacionData,
                'metadata' => json_encode($metadata)
            ]);
            // $generated->save();
    
            // Crear el registro solo con la investigación (HTML puro)
            // $generated = Generated::create([
            //     'account_id' => $accountId,
            //     'key' => 'Investigacion',
            //     'name' => $request->input('file_name'),
            //     'value' => $investigacionData, // Aquí se guarda solo el HTML
            //     'rating' => $request->input('rating'),
            // ]);
    
            return response()->json([
                'success' => true,
                'message' => 'Investigación guardada con éxito',
                'goto' => 4,
                'function' => 'guardarInvestigacion',
                'id_generated' => $generated->id
            ]);
        } catch (\Exception $e) {
            Log::error('Error al guardar investigación: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'success' => false,
                'error' => 'Error al guardar la investigación: ' . $e->getMessage()
            ], 500);
        }
    }
    
    

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
    public function generarInvestigacion(Request $request)
    {

        // esperar la respuesta por 300 segundos con max execution time
        set_time_limit(300);
        ini_set('max_execution_time', 300);

        $accountId = $request->input('account');        
        $country = $request->input('country');
        $brand = $request->input('brand');
        $instruccion = $request->input('instruccion');
        $modelo = $request->input('modelo', 'sonar-deep-research'); // Valor por defecto

        try {
            // Validar límite de créditos antes de generar
            $this->validateCreditLimit($accountId);
            $metadata = [
                'country' => $country,
                'brand' => $brand,
                'instruccion' => $instruccion,
                'modelo' => $modelo,
                'started_at' => now()->toISOString(),
                'step' => 2,
            ];
            $generated = Generated::create([
                'account_id' => $accountId,
                'key' => 'Investigacion',
                'name' => 'Investigación en proceso - ' . $brand,
                'value' => '<div class="text-center p-4"><div class="spinner-border" role="status"></div><p class="mt-2">Generando investigación...</p></div>',
                'rating' => null,
                'status' => 'processing', // Nuevo campo para el estado
                'metadata' => json_encode($metadata)
            ]);

            // Iniciar la generación en background usando un endpoint separado
            // $this->iniciarGeneracionBackground($generated->id);
            $respuesta = $this->ejecutarGeneracion($accountId, $country, $brand, $instruccion, $modelo, $generated->id);

            // $metadata = json_decode($generated->metadata, true);
            $metadata['queued_at'] = now()->toISOString();
            $metadata['id_deep_research'] = isset($respuesta['data']['id']) ? $respuesta['data']['id'] : null;
            $metadata['sources'] = isset($respuesta['sources']) ? $respuesta['sources'] : null;
            $metadata['generacion_investigacion_status'] = 'processing';
            $metadata['step'] = 3;

            $generated->update([
                'value' => $respuesta['data'],
                'status' => 'processing',
                'metadata' => json_encode($metadata)
            ]);

            return response()->json([
                'success' => true,
                'modelo' => $modelo,
                'data' => isset($respuesta['data']) ? $respuesta['data'] : null,
                'generation_id' => $generated->id,
                'id_deep_research' => isset($respuesta['data']['id']) ? $respuesta['data']['id'] : null,
                'message' => 'Investigación iniciada. Consultando estado...',
                'status' => 'processing',
                'function' => 'generarInvestigacion',
                'goto' => null
            ]);

        }catch (\App\Exceptions\CreditLimitExceededException $e) {
            // Error específico de límite de créditos - mostrar mensaje personalizado
            Log::warning('Límite de créditos excedido en generarInvestigacion', [
                'message' => $e->getMessage(),
                'accountId' => $accountId ?? null
            ]);
    
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al iniciar la investigación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint para ejecutar la generación (llamado por cURL)
     */
    public function ejecutarGeneracion($accountId, $country, $brand, $instruccion, $modelo, $generationId)
    {
        try {
            // Elegir el método según el modelo seleccionado
            switch ($modelo) {
                case 'sonar-deep-research':
                    $respuestaper = $this->callPerplexityAsync($country, $brand, $instruccion, $modelo);
                    break;
                case 'o4-mini-deep-research':
                case 'o3-deep-research':
                    $respuestaper = $this->callOpenAIDeepResearchSync($country, $brand, $instruccion, $modelo);
                    break;
                default:
                    // Por defecto usar Perplexity
                    $respuestaper = $this->callPerplexityAsync($country, $brand, $instruccion, $modelo);
                    break;
            }

            // $fuentes = $respuestaper['fuentes'] ?? [];

            // // Convertimos las fuentes a una lista numerada con enlaces
            // $fuentesFormatted = !empty($fuentes)
            // ? "<p>" . implode("</p><p>", array_map(fn($fuente, $index) => ($index + 1) . ". <a href=\"$fuente\" target=\"_blank\">$fuente</a>", $fuentes, array_keys($fuentes))) . "</p>"
            // : "<p>No se encontraron fuentes.</p>";

            // if (!isset($respuestaper['data'])) {
            //     throw new \Exception('La respuesta no contiene la clave "data".');
            // }

            // // Limpiar el contenido dentro de las etiquetas <think>
            // $investigacionData = preg_replace('/<think>.*?<\/think>/s', '', $respuestaper['data']);
            // $investigacionData = trim($investigacionData);

            // $investContent = $investigacionData . "<p><strong>Fuentes:</strong><br>" . $fuentesFormatted . "</p>";

            // Actualizar el registro con la respuesta completa
            // $generated->update([
            //     'value' => '<div class="text-center p-4"><div class="spinner-border" role="status"></div><p class="mt-2">Generando investigación...</p></div>',
            //     'status' => 'completed',
            //     'metadata' => json_encode([
            //         'country' => $country,
            //         'brand' => $brand,
            //         'instruccion' => $instruccion,
            //         'modelo' => $modelo,
            //         'started_at' => $metadata['started_at'] ?? null,
            //         'completed_at' => now()->toISOString(),
            //         'sources_count' => count($fuentes)
            //     ])
            // ]);
            if(isset($respuestaper['success']) && !$respuestaper['success']){
                return ['success' => false, 'error' => $respuestaper['error']];
            }

            Log::info('Investigación completada exitosamente', [
                'generation_id' => $generationId,
                'modelo' => $modelo,
                'id_deep_research' => isset($respuestaper['data']['id']) ? $respuestaper['data']['id'] : null,
                'data' => $respuestaper['data']
            ]);

            return ['success' => true, 'modelo' => $modelo, 'data' => $respuestaper['data']];

        } catch (\Exception $e) {
            Log::error('Error en generación asíncrona de investigación', [
                'generation_id' => $generationId,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => 'Error al generar la investigación: ' . $e->getMessage()];
        }
    }

    /**
     * Consultar el estado de una generación
     */
    public function consultarEstadoGeneracion($generationId)
    {
        try {
            $generated = Generated::find($generationId);
            
            if (!$generated) {
                return response()->json([
                    'success' => false,
                    'error' => 'Generación no encontrada'
                ], 404);
            }

            $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

            $content = '';
            $sources = [];
            $statusgenerated = 'processing';

            if($metadata['modelo'] === 'sonar-deep-research'){
                
                $response = \App\Services\PerplexityService::ChatCompletionsAsyncGet($metadata['id_deep_research']);

                if(isset($response['success']) && !$response['success']){
                    return response()->json([
                        'success' => false,
                        'error' => $response['error']
                    ], 500);
                }

                $externalRequestId = $metadata['id_deep_research'] ?? null;

                if($response['data']['status'] === 'COMPLETED'){
                    $statusgenerated = 'completed';
                    
                    if (isset($response['data']['response']['choices'][0]['message']['content'])) {
                        $contendata = $response['data']['response']['choices'][0]['message']['content'];
                        // Limpiar el contenido dentro de las etiquetas <think>
                        $investigacionData = preg_replace('/<think>.*?<\/think>/s', '', $contendata);
                        $content = trim($investigacionData);
                    }
                    if(isset($response['data']['response']['search_results'])){
                        foreach($response['data']['response']['search_results'] as $source){
                            $sources[] = $source['url'];
                        }
                    }
                    
                    // Registrar uso de tokens cuando se completa exitosamente (Perplexity Deep Research)
                    // Nota: Perplexity sonar-deep-research tiene múltiples tipos de tokens (input, output, citation, reasoning, search_queries)
                    // Usamos external_request_id para evitar duplicados si ya se registró en el POST
                  
                    if (isset($response['data']['response']['usage'])) {
                        Log::info('Usage completo Perplexity Deep Research', [
                            'usage' => $response['data']['response']['usage'],
                            'external_request_id' => $externalRequestId
                        ]);
                        
                        $this->trackUsageIfAvailable(
                            $generated->account_id,
                            $metadata['modelo'], // sonar-deep-research
                            $response['data']['response']['usage'],
                            'perplexity',
                            'consultarEstadoGeneracion-Investigacion',
                            $externalRequestId,
                            $generationId // generated_id
                        );
                    }
                } elseif($response['data']['status'] === 'FAILED') {
                    // Si falló pero tiene usage, registrar el consumo (consumió antes de fallar)
                    if (isset($response['data']['response']['usage'])) {
                        Log::warning('Status FAILED pero tiene usage - registrando consumo', [
                            'usage' => $response['data']['response']['usage'],
                            'external_request_id' => $externalRequestId,
                            'error_message' => $response['data']['error_message'] ?? null
                        ]);
                        
                        $this->trackUsageIfAvailable(
                            $generated->account_id,
                            $metadata['modelo'], // sonar-deep-research
                            $response['data']['response']['usage'],
                            'perplexity',
                            'consultarEstadoGeneracion-Investigacion-FAILED',
                            $externalRequestId,
                            $generationId // generated_id
                        );
                    }
                }

            }elseif($metadata['modelo'] === 'o4-mini-deep-research' || $metadata['modelo'] === 'o3-deep-research'){
                $response = \App\Services\OpenAiService::getModelResponse($metadata['id_deep_research']);

                if(isset($response['success']) && !$response['success']){
                    return response()->json([
                        'success' => false,
                        'error' => $response['error']
                    ], 500);
                }

                $externalRequestId = $metadata['id_deep_research'] ?? null;

                if($response['data']['status'] === 'completed'){
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
                    
                    // Registrar uso de tokens cuando se completa exitosamente (OpenAI Deep Research)
                    // Usamos external_request_id para evitar duplicados
                    if (isset($response['data']['usage'])) {
                        $this->trackUsageIfAvailable(
                            $generated->account_id,
                            $metadata['modelo'], // o4-mini-deep-research o o3-deep-research
                            $response['data']['usage'],
                            'openai',
                            'consultarEstadoGeneracion-Investigacion',
                            $externalRequestId,
                            $generationId // generated_id
                        );
                    }
                }

            }else{
                return response()->json([
                    'success' => false,
                    'error' => 'Modelo no válido'
                ], 500);
            }

            if($statusgenerated === 'completed'){
                $metadata['completed_at'] = now()->toISOString();
                $metadata['generacion_investigacion_sources'] = $sources;
                $metadata['generacion_investigacion_content'] = $content;
                $metadata['generacion_investigacion_status'] = 'completed';
                $metadata['step'] = 3;
                $generated->update([
                    'value' => $content,
                    'metadata' => json_encode($metadata)
                ]);

                return response()->json([
                    'success' => true,
                    'status' => 'completed',
                    'generation_id' => $generated->id,
                    'data' => $content,
                    'sources' => $sources
                ]);
            }else{
                $metadata['generacion_investigacion_status'] = 'processing';
                $generated->update([
                    'metadata' => json_encode($metadata)
                ]);
                return response()->json([
                    'success' => true,
                    'status' => $generated->status,
                    'generation_id' => $generated->id
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al consultar estado: ' . $e->getMessage()
            ], 500);
        }
    }

   
    public function callPerplexityAsync($country, $brand, $instruccion, $modelo){
        

 $prompt = <<<EOT
Conduct a comprehensive advertising and marketing research report on the brand "$brand," operating in "$country," addressing the following user request: "$instruccion." The report should be professionally structured, detailed, and easy to navigate, containing updated and relevant data. The deliverable should include:

- Executive Summary
- Background and Market Context
- Brand Analysis (current market positioning, advertising strategies, and competitive landscape)
- Key Insights and Recommendations directly responding to the user's specific instruction

Ensure the report meets professional advertising research standards similar to those produced by leading agencies such as Kantar.

Your response must be formatted **strictly in Markdown** using the following rules:

1. Use `#` for main titles.
2. Use `##` for subtitles.
3. Use standard paragraph text for regular content (no extra formatting unless necessary).
4. Use **numbered lists** where needed for structured points.
5. Do **not** include any HTML tags or code formatting.
6. The response must be clear, clean, and easy to parse using Markdown renderers (GitHub, Notion, PDF converters, etc.)
7. All sections must be written in **Spanish** in a professional, research-oriented tone.
EOT;


        
$system_prompt = <<<EOT
You are a specialist researcher in advertising and marketing whose task is to conduct detailed online research and deliver responses in a professional, advertising-research format in Spanish.

Rules:

- Always respond exclusively in Spanish, using a professional and research-driven tone.
- Present the final answer strictly in the form of an advertising research report, avoiding any explanation of how the information was obtained.
- Format the response strictly in Markdown using this structure:
  1. Use `#` for main titles.
  2. Use `##` for subtitles.
  3. Write regular text as simple paragraphs.
  4. For enumerations, use numbered Markdown lists.
  5. Avoid any HTML or code formatting.
  6. The entire response must be well-structured, clean, and professionally presented for easy export to PDF or other formats.
  7. Ensure the result is aligned with the standards of high-end advertising research agencies like Kantar.

Process:

1. Clearly identify the parameters provided by the user: country, brand, and specific request.
2. Conduct thorough online research using those parameters.
3. Curate the most relevant and updated information.
4. Write the report in a structured, professional format.
5. Deliver the response in Markdown, and **nothing else**.
EOT;

try {
    $model = $modelo;
    $temperature = 0.5;
    $response = PerplexityService::ChatCompletionsAsync($prompt, $model, $temperature, $system_prompt);

    Log::info('Respuesta PerplexityService::ChatCompletionsAsync', [
        'response' => $response
    ]);

    if(isset($response['error'])){
        return ['success' => false, 'error' => $response['error']];
    }

    // Verificar si el POST devuelve status COMPLETED con response.usage
    // Esto puede pasar si la tarea se completa inmediatamente
    if (isset($response['data']['status']) && $response['data']['status'] === 'COMPLETED') {
        if (isset($response['data']['response']['usage'])) {
            // Obtener el account_id del generated (se pasa como parámetro o se obtiene del contexto)
            // Por ahora, lo registramos en consultarEstadoGeneracion, pero aquí podríamos hacerlo también
            Log::info('POST devolvió status COMPLETED con usage, se registrará en consultarEstadoGeneracion', [
                'id' => $response['data']['id'] ?? null,
                'usage' => $response['data']['response']['usage']
            ]);
        }
    }

    // Verificar si falló pero tiene usage (consumió antes de fallar)
    if (isset($response['data']['status']) && $response['data']['status'] === 'FAILED') {
        if (isset($response['data']['response']['usage'])) {
            // Obtener el account_id - necesitamos pasarlo como parámetro o obtenerlo del contexto
            // Por ahora, lo registramos en consultarEstadoGeneracion
            Log::warning('POST devolvió status FAILED pero tiene usage (consumió antes de fallar)', [
                'id' => $response['data']['id'] ?? null,
                'usage' => $response['data']['response']['usage'],
                'error_message' => $response['data']['error_message'] ?? null
            ]);
        }
    }

    return ['success' => true, 'data' =>  $response['data']];

} catch (\Exception $e) {
    Log::error('Error en la llamada a PerplexityService::ChatCompletionsAsync', [
        'exception' => $e->getMessage(),
        'prompt' => $prompt
    ]);
    return ['success' => false, 'error' => 'Error al procesar la solicitud de IA.'];
}

}

    /**
     * Descargar la última investigación generada para una cuenta.
     */
    public function download(Generated $generated)
    {
        try {
            
            if (!$generated) {
                return redirect()->route('dashboard')
                    ->with('error', 'No se encontró ninguna investigación para esta cuenta.');
            }
            
            Log::info('Descargando última investigación', [
                'account_id' => $generated->account_id,
                'generated_id' => $generated->id,
                'name' => $generated->name
            ]);

            $fields = [
                'generated' => $generated->value,
            ];

            $pdf = Pdf::setOptions([
                'defaultFont' => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true
            ])->loadView('generated.pdf.template', array_merge($fields));
            
            $now = Carbon::now();
            $timestamp = $now->format('Ymd_His');

            return $pdf->download('Investigacion_' . $generated->name . '_' . $timestamp . '.pdf');
        } catch (\Exception $e) {
            Log::error('Error al descargar investigación', [
                'error' => $e->getMessage(),
                'account_id' => $generated->account_id
            ]);
            
            return redirect()->route('dashboard')
                ->with('error', 'Error al generar el PDF: ' . $e->getMessage());
        }
    }

    /**
     * Función alternativa para investigación profunda sin background
     */
    public function callOpenAIDeepResearchSync($country, $brand, $instruccion, $modelo = 'o4-mini-deep-research')
    {       
        try {
            // Configurar opciones para deep research síncrono
            $options = [
                'model' => $modelo,
                'prompt' => [
                    'id' => 'pmpt_68b07ba5195c81969950997c6f9f2b6a01ab0e16c989050b',
                    'version' => '4',
                    'variables' => [
                        'country' => $country,
                        'brand' => $brand,
                        'instruccion' => $instruccion
                    ]
                ],
                'background' => true, // Ejecutar en background y esperar hasta completar
                // 'max_tool_calls' => 1,
                'tools' => [
                    ['type' => 'web_search_preview'],
                    ['type' => 'code_interpreter', 'container' => ['type' => 'auto']]
                ]
            ];

            // Llamar al nuevo endpoint de deep research
            $response = \App\Services\OpenAiService::createModelResponse($options);

            Log::info('Respuesta OpenAiService::createModelResponse (Deep Research Sync)', [
                'response' => $response
            ]);

            if (isset($response['error'])) {
                Log::error('Error en la llamada a OpenAiService::createModelResponse (Deep Research Sync)', [
                    'error' => $response['error']
                ]);
                return ['success' => false, 'error' => $response['error']];
            }

            return ['success' => true, 'data' => $response['data']];
            
        } catch (\Exception $e) {
            Log::error('Error en la llamada a OpenAiService::createModelResponse (Deep Research Sync)', [   
                'exception' => $e->getMessage()
            ]);
            return ['success' => false, 'error' => 'Error al procesar la solicitud de IA.'];
        }
    }
}
