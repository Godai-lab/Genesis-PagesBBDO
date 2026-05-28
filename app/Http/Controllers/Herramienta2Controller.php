<?php

namespace App\Http\Controllers;

use App\Http\Traits\ValidatesCreditLimit;
use App\Models\Account;
use App\Models\Brand;
use App\Models\Field;
use App\Models\Generated;
use App\Services\AnthropicService;
use App\Services\GeminiService;
use App\Services\OpenAiService;
use App\Services\PerplexityService;
use App\Services\ProcessFileContentService;
use App\Supports\CostCalculationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class Herramienta2Controller extends Controller
{
    use ValidatesCreditLimit;
    
    protected string $toolName = 'Herramienta 2 - Genesis';
    
    // Modelos utilizados por cada funcionalidad (para registro de uso)
    // Estos modelos NO se envían al ChatPrompt, solo se usan para trackUsageIfAvailable
    private const MODEL_GENESIS = 'claude-sonnet-4-5-20250929';
    private const MODEL_CONSTRUCCION_ESCENARIO = 'claude-sonnet-4-5-20250929';
    private const MODEL_VALIDAR_CONCEPTO = 'gpt-5.2-2025-12-11';
    private const MODEL_MEJORAR_CONCEPTO = 'gpt-5.2-2025-12-11';
    private const MODEL_CREATIVIDAD = 'gpt-5-mini-2025-08-07';
    private const MODEL_ESTRATEGIA = 'gpt-5-mini-2025-08-07';
    private const MODEL_IDEAS_CONTENIDO = 'gpt-5-mini-2025-08-07';
    private const MODEL_INSIGHT = 'sonar-reasoning-pro';
    private const MODEL_INSIGHT2 = 'sonar-reasoning-pro';
    
    /**
     * Método auxiliar para registrar el uso de tokens de manera consistente
     * 
     * @param int $accountId ID de la cuenta
     * @param string $model Nombre del modelo usado (ej: 'gpt-5', 'claude-sonnet-4-20250514', 'sonar-reasoning')
     * @param array $usageData Datos de uso del servicio
     * @param string $serviceType Tipo de servicio: 'anthropic', 'openai', 'perplexity'
     * @param string $context Contexto de la llamada para logs (ej: 'generarGenesis', 'GenerarInsight')
     * @param int|null $generatedId ID de la generación (Generated) para agrupar procesos
     * @return void
     */
    private function trackUsageIfAvailable($accountId, $model, $usageData, $serviceType, $context = '', $generatedId = null)
    {
        try {
            Log::info("🔍 trackUsageIfAvailable llamado", [
                'accountId' => $accountId,
                'model' => $model,
                'serviceType' => $serviceType,
                'context' => $context,
                'generatedId' => $generatedId,
                'usageData_keys' => is_array($usageData) ? array_keys($usageData) : 'no es array',
                'usageData' => $usageData
            ]);
            
            $inputTokens = 0;
            $outputTokens = 0;
            
            // Extraer tokens según el tipo de servicio
            if ($serviceType === 'anthropic' && isset($usageData['input_tokens']) && isset($usageData['output_tokens'])) {
                $inputTokens = $usageData['input_tokens'];
                $outputTokens = $usageData['output_tokens'];
            } elseif ($serviceType === 'openai') {
                // OpenAI puede devolver input_tokens/output_tokens (nuevo formato) o prompt_tokens/completion_tokens (formato antiguo)
                // reasoning_tokens está incluido en output_tokens, no se cobra por separado
                if (isset($usageData['input_tokens']) && isset($usageData['output_tokens'])) {
                    $inputTokens = $usageData['input_tokens'];
                    $outputTokens = $usageData['output_tokens'];
                } elseif (isset($usageData['prompt_tokens']) && isset($usageData['completion_tokens'])) {
                    $inputTokens = $usageData['prompt_tokens'];
                    $outputTokens = $usageData['completion_tokens'];
                }
                
                Log::info("📊 Tokens extraídos de OpenAI", [
                    'inputTokens' => $inputTokens,
                    'outputTokens' => $outputTokens,
                    'total_tokens' => $usageData['total_tokens'] ?? 'no disponible',
                    'has_reasoning_tokens' => isset($usageData['output_tokens_details']['reasoning_tokens'])
                ]);
                
                if ($inputTokens === 0 && $outputTokens === 0) {
                    Log::warning("⚠️ No se encontraron tokens en usageData de OpenAI", [
                        'usageData_keys' => is_array($usageData) ? array_keys($usageData) : 'no es array',
                        'usageData' => $usageData
                    ]);
                }
            } elseif ($serviceType === 'perplexity') {
                // Perplexity devuelve prompt_tokens y completion_tokens
                $inputTokens = $usageData['prompt_tokens'] ?? 0;
                $outputTokens = $usageData['completion_tokens'] ?? 0;
                
                Log::info("📊 Tokens extraídos de Perplexity", [
                    'inputTokens' => $inputTokens,
                    'outputTokens' => $outputTokens,
                    'total_tokens' => $usageData['total_tokens'] ?? 'no disponible'
                ]);
                
                if ($inputTokens === 0 && $outputTokens === 0) {
                    Log::warning("⚠️ No se encontraron tokens en usageData de Perplexity", [
                        'usageData_keys' => is_array($usageData) ? array_keys($usageData) : 'no es array',
                        'usageData' => $usageData
                    ]);
                }
            } else {
                Log::warning("⚠️ Tipo de servicio no reconocido o no se encontraron tokens", [
                    'serviceType' => $serviceType,
                    'usageData_keys' => is_array($usageData) ? array_keys($usageData) : 'no es array',
                    'usageData' => $usageData
                ]);
            }
            
            // Solo registrar si hay tokens
            if ($inputTokens > 0 || $outputTokens > 0) {
                // Agregar sufijo "-Genesis" para identificar que es de Herramienta2
                $requestType = $context ? $context . '-Genesis' : 'Genesis';
                
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
                    $requestType,
                    null, // external_request_id
                    $generatedId, // generated_id
                    $context, // step
                    $serviceType // service_type
                );
                
                Log::info("✅ Uso registrado exitosamente", [
                    'context' => $context,
                    'model' => $model,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'generated_id' => $generatedId
                ]);
            }
        } catch (\Exception $e) {
            Log::error("⚠️ Error al registrar uso en {$context}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // No lanzamos la excepción para no interrumpir el flujo
        }
    }

    public function index(Request $request)
    {
        Gate::authorize('haveaccess','genesis.index');
        $accounts = Account::fullaccess()->get();
        $data_generated = [];
        $id_generated = $request->query('generated');

        if ($id_generated) {
            $generated = Generated::find($id_generated);
            if ($generated && $generated->key === 'Genesis') {
                $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];
                $step = isset($metadata['step']) ? $metadata['step'] : null;
                $data_generated = [
                    'id_generated' => $generated->id,
                    'account_id' => $generated->account_id,
                    'step' => $step,
                    'metadata' => $metadata
                ];
            }
        }

        return view('herramienta2.index', compact('accounts', 'data_generated'));

    }
   public function generarGenesis(Request $request){
    Log::info('Iniciando generarGenesis', [
        'request_data' => $request->all()
    ]);
    
    // Validar las URLs y archivos
    $validator = Validator::make($request->all(), [
        '360_objective' => 'required|string',
        'account' => 'required|integer',
        'brief' => 'required|integer',
        'investigation' => 'nullable|integer',
    ]);

    if ($validator->fails()) {
        Log::error('Validación fallida en generarGenesis', [
            'errors' => $validator->errors()
        ]);
        return response()->json(['error' => $validator->errors()]);
    }
    
    ini_set('max_execution_time', 800);
    $objective = $request->input('360_objective');
    $accountId = $request->input('account');
    $idBrief = $request->input('brief');
    $idInvestigation = $request->input('investigation');

    Log::info('Datos extraídos de la request', [
        'objective' => $objective,
        'accountId' => $accountId,
        'idBrief' => $idBrief,
        'idInvestigation' => $idInvestigation
    ]);
    
    try {
        $brief = Generated::find($idBrief)->value;
        Log::info('Brief encontrado', [
            'brief_length' => strlen($brief),
            'brief_preview' => substr($brief, 0, 100) . '...'
        ]);
    } catch (\Exception $e) {
        Log::error('Error al obtener el brief', [
            'idBrief' => $idBrief,
            'error' => $e->getMessage()
        ]);
        return response()->json(['success' => false, 'error' => 'Error al obtener el brief']);
    }

    $investigation = '';
    if ($idInvestigation && $idInvestigation !== null) {
        try {
            $investigation = Generated::find($idInvestigation)->value;
            Log::info('Investigation encontrado', [
                'investigation_length' => strlen($investigation),
                'investigation_preview' => substr($investigation, 0, 100) . '...'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener la investigation', [
                'idInvestigation' => $idInvestigation,
                'error' => $e->getMessage()
            ]);
            return response()->json(['success' => false, 'error' => 'Error al obtener la investigation']);
        }
    }

    $metadata = [
        'objective' => $objective,
        'id_brief' => $idBrief,
        'brief' => $brief,
        'id_investigation' => $idInvestigation,
        'investigation' => $investigation,
        'started_at' => now()->toISOString(),
        'step' => 2,
    ];

    // Crear un registro temporal con estado "procesando"
    $generated = Generated::create([
        'account_id' => $accountId,
        'key' => 'Genesis',
        'name' => 'Estrategia en proceso...',
        'value' => 'Generando estrategia...',
        'rating' => null,
        'status' => 'processing', // Nuevo campo para el estado
        'metadata' => json_encode($metadata)
    ]);

    if ($metadata && $metadata['brief'] && $metadata['objective']) {
        
        Log::info('Campos guardados en la base de datos');
        
        try {
            // Validar límite de créditos antes de generar
             $this->validateCreditLimit($accountId);
            Log::info('Iniciando generación de insight');
            $insightgenerado = $this->GenerarInsight($brief,$objective,$accountId, $generated->id); 
            Log::info('Insight generado exitosamente', [
                'insight_data_length' => strlen($insightgenerado['data']),
                'insight_fuentes_count' => is_array($insightgenerado['fuentes']) ? count($insightgenerado['fuentes']) : 'no es array',
                'insight_fuentes_type' => gettype($insightgenerado['fuentes'])
            ]);
            
            $insightdata = $insightgenerado['data'];
            $insightfuentes = $insightgenerado['fuentes'];

            // Verificar si las fuentes existen y convertirlas en enlaces clickeables con comillas dobles
            if (is_array($insightfuentes) && !empty($insightfuentes)) {
                $fuentesHTML = '<p><strong>Fuentes:</strong></p><ul><li>' . implode(
                    '</li><li>',
                    array_map(function ($fuente) {
                        return filter_var($fuente, FILTER_VALIDATE_URL)
                            ? sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', htmlspecialchars($fuente, ENT_QUOTES, 'UTF-8'), htmlspecialchars($fuente, ENT_QUOTES, 'UTF-8'))
                            : htmlspecialchars($fuente, ENT_QUOTES, 'UTF-8'); // Escapar si no es URL
                    }, $insightfuentes)
                ) . '</li></ul>';
            } else {
                $fuentesHTML = '<p><strong>Fuentes:</strong> No se encontraron fuentes.</p>';
            }

            Log::info('Fuentes HTML generadas', [
                'fuentes_html_length' => strlen($fuentesHTML)
            ]);

            // Preparar las fuentes para la respuesta
            $fuentesResponse = is_string($insightfuentes) ? explode("\n", trim($insightfuentes)) : $insightfuentes;

            $metadata['genesis_insight_data'] = $insightdata;
            $metadata['genesis_insight_fuentes'] = $fuentesResponse;
            $metadata['genesis_insight_fuentes_html'] = $fuentesHTML;

            $generated->update([
                'metadata' => json_encode($metadata)
            ]);

            if ($investigation) {
                $prompt = <<<EOT
En base a la información proporcionada en el brief y el objetivo dado, crea un GÉNESIS completo. 

BRIEF: 
$brief

OBJETIVO: 
$objective

INSIGHTS:
$insightdata

INVESTIGACIÓN:
$investigation

Analiza cuidadosamente la información disponible, además de los Insights poderosos y la investigación que te servirán para nutrir el resultado, identifica los puntos clave relevantes para cada componente del GÉNESIS, y desarrolla una estrategia coherente y efectiva. Responde en español y presenta solo el resultado final del GÉNESIS sin notas o textos adicionales. Aquí tienes la información para lograrlo:

EOT;
            } else {
                $prompt = <<<EOT
En base a la información proporcionada en el brief y el objetivo dado, crea un GÉNESIS completo. 

BRIEF: 
$brief

OBJETIVO: 
$objective

INSIGHTS:
$insightdata

Analiza cuidadosamente la información disponible, además de los Insights poderosos que te servirán para nutrir el resultado, identifica los puntos clave relevantes para cada componente del GÉNESIS, y desarrolla una estrategia coherente y efectiva. Responde en español y presenta solo el resultado final del GÉNESIS sin notas o textos adicionales. Aquí tienes la información para lograrlo:

EOT;
            }

            $system_prompt = <<<EOT
Eres un experto en planificación estratégica publicitaria especializado en la metodología GÉNESIS (Generación Estratégica Neuroinspirada de Efectividad Sincronizada con Inteligencia Sintética) de god-ai (Objetivo cuantificado, Obstáculo identificado, Insight Aumentado, Desafío creativo). Esta metodología se utiliza para crear estrategias creativas publicitarios efectivas y consiste en los siguientes componentes:
1. Objetivo Cuantificado: Define el propósito de negocio medible y específico.
2. Obstáculo Identificado: Identifica el principal obstáculo para lograr el objetivo. ¿Qué se interpone en el camino para alcanzar ese objetivo? ¿Qué papel juega la comunicación en el cambio de esa conducta? A veces, puedes ver más de un obstáculo, pero trata de identificar la única cosa que, si se supera, hará que todo lo demás caiga en su lugar.
3. Insight Aumentado: Revela una verdad sobre el consumidor que ayuda a superar el obstaculo Identificado. ¿Cuál es la verdad que desbloquea este problema? ¿Qué sabemos sobre el consumidor que nos ayude a crear comunicaciones que superen el problema? Esta información debe provenir del consumidor, no del conocimiento del producto. Un gran insight sorprende a la gente con lo obvio.
4. Desafío Creativo: Plantea un desafío ambicioso y transformador para la comunicación. ¿Cuál es el reto para nuestras comunicaciones? Este debe ser grande, ambicioso y valiente. Puede ser provocador, pero al menos, emocionante. Un gran reto te ayudará a ver claramente y a romper las barreras. Transforma la marca. Nunca es una tarea. Es simple. No está basado en un negocio, un producto o una tarea.
Para crear un GÉNESIS efectivo:
- Mantén cada sección concisa y enfocada.
- Asegúrate de que todas las partes cuenten una historia coherente.
- Enfócate en las personas, no en el producto.
- Busca insights culturales profundos según la info del país.
- El desafío creativo debe ser transformador y no incluir el nombre de la marca.
-Analiza:, ¿Dice una historia simple?
-Menos palabras tienen un mayor impacto; trata de reducir el conteo de palabras a la mitad, casi siempre esto genera una mejora
-Trata de encontrar la línea conductora para que el brief realmente se lea como una historia
-Sé brutalmente honesto con el problema. Incluso si duele
-¿Tu insight se centra en las personas? Los insights más geniales son insights culturales
-Es tentador escribir un insight sobre el producto, pero al final debería regresar a la gente real en el mundo real
-¿Cómo es esto propio? ¿Cómo logro que esta marca se diferencie de la competencia?
- ¡Rompe las reglas!


Puedes usar estos ejemplos como inspiración y guía de formato respetando los saltos de líneas, no debes copiar:
<examples>
<example>
1.- Coca Cola: Sencillo pero resuelve\n\n
**Objetivo Cuantificado:**\n
Aumentar las ventas de Coca-Cola en formato individual en un 10% en los próximos 3 meses.\n\n
**Obstáculo Identificado:**\n
En un mercado saturado, los consumidores tienen múltiples opciones y están optando por alternativas percibidas como más saludables.\n\n
**Insight Aumentado:**\n
Los consumidores buscan pequeños momentos de placer y recompensa en su día a día.\n\n
**Desafío Creativo:**\n
Posicionar la Coca-Cola individual como el compañero ideal para los pequeños momentos de disfrute diario.
</example>

<example>
2.- Ameriprise: Transformador y mantiene a Ameriprise a la vanguardia\n\n
**Objetivo Cuantificado:**\n
Ayudar a crecer la adquisición de nuevos clientes aumentando los niveles de Amor y Respeto por Ameriprise.\n\n
**Obstáculo Identificado:**\n
Ameriprise compite en una categoría intensamente saturada donde todos luchan por lo mismo: la falta de confianza y la inercia. Para combatir esto, más y más competidores están intentando llegar a los consumidores con "plática franca" (Schwab, Smith Barney). Otros están siguiendo los pasos de Ameriprise, alentando a la gente a alcanzar sus sueños de retiro (Allianz, Wachovia, Lincoln). Ahora Ameriprise necesita adoptar una nueva táctica para diferenciarse del grupo una vez más.\n\n
**Insight Aumentado:**\n
Hemos dado en el clavo con la campaña actual. Inspira a la gente a verse como optimista y aventurera. Y esto hace que Ameriprise se vea más progresiva que la competencia. Ahora, a medida que ampliamos el público objetivo para incluir a los Baby Boomers y a la Generación X, necesitamos ir más allá del retiro, para alentar todo tipo de sueños: sueños grandes y pequeños, sueños de hoy y sueños de mañana, sueños egoístas y sueños altruistas, sueños para ti y sueños para tus hijos. Para romper con el cinismo de los asesores financieros, se necesita una representación de los asesores de Ameriprise como una raza aparte: accesibles, atentos y serviciales.\n\n
**Desafío Creativo:**\n
Hacer que Ameriprise marque la diferencia entre los soñadores y los hacedores.
</example>

<example>
3.- Cascade con Bleach Hydro-Clean:\n\n
**Objetivo Cuantificado:**\n
Impulsar el crecimiento de la marca Cascade mediante la migración de los consumidores de Cascade a Cascade Complete.\n\n
**Obstáculo Identificado:**\n
Desde su punto de vista, ella no tiene ninguna razón para pagar más por Cascade Complete, ya que no ve en qué se diferencia de su Cascade regular u otras marcas que usa.\n\n
**Insight Aumentado:**\n
Ella es la mujer que es un poco más germofóbica y, como resultado, es más proactiva en cuanto a que sus platos estén completamente limpios. Para ella, la limpieza necesita ir más allá de la superficie, especialmente cuando se trata de niños pequeños o al trabajar con ciertos alimentos crudos. Por eso, ha recurrido a ciertos comportamientos como lavar en el lavavajillas con agua extra caliente o incluso evitar cortar pollo en la tabla de cortar por completo, solo para asegurarse de que no haya "cosas pegajosas" que queden atrás y que sus platos estén seguros para comer.\n\n
**Desafío Creativo:**\n
Limpiar incluso lo que no se ve.
</example>
</examples>

EOT;

            // Usar la constante definida al inicio del controlador
            $model = self::MODEL_GENESIS;
            
            $temperature = 0.8;
            
            Log::info('Preparando llamada a AnthropicService', [
                'model' => $model,
                'temperature' => $temperature,
                'system_prompt_length' => strlen($system_prompt)
            ]);
            
            Log::info('Iniciando llamada a AnthropicService::TextGeneration');
            $response = AnthropicService::TextGeneration($prompt, $model, $temperature, $system_prompt);
            
            // Log de tokens Claude
            if(isset($response['usage'])) {
                Log::info('Usage tokens Claude (generarGenesis)', [
                    'id_generated' => $generated->id,
                    'usage' => $response['usage']
                ]);
            }
            
            Log::info('Respuesta de AnthropicService recibida', [
                'response_type' => gettype($response),
                'response_keys' => is_array($response) ? array_keys($response) : 'no es array',
                'response_data_length' => is_array($response) && isset($response['data']) ? strlen($response['data']) : 'no disponible'
            ]);
            // Verificar que la respuesta tenga los datos esperados
            if (!is_array($response) || !isset($response['data'])) {
                Log::error('Respuesta inesperada de AnthropicService', [
                    'response' => $response,
                    'response_type' => gettype($response)
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Respuesta inesperada del servicio de IA, La respuesta no contiene los datos esperados',
                ]);
            }
            
            // Registrar uso de tokens si está disponible (solo si el proceso fue exitoso)
            if (isset($response['usage'])) {
                $this->trackUsageIfAvailable($accountId, $model, $response['usage'], 'anthropic', 'generarGenesis', $generated->id);
            }
            
            Log::info('Preparando respuesta final');

            $metadata['genesis'] = $response['data'];
            $metadata['step'] = 3;

            $generated->update([
                'metadata' => json_encode($metadata)
            ]);

            $finalResponse = [
                'success' => true, 
                'data' => array_merge($response, ['fuentes' => $fuentesResponse]),
                'function' => 'generarGenesis',
                'id_generated' => $generated->id
            ];
            
            Log::info('Respuesta final preparada', [
                'response_keys' => array_keys($finalResponse)
            ]);

            return response()->json($finalResponse);

        } catch (\App\Exceptions\CreditLimitExceededException $e) {
            // Error específico de límite de créditos - mostrar mensaje personalizado
            Log::warning('Límite de créditos excedido en generarGenesis', [
                'message' => $e->getMessage(),
                'accountId' => $accountId
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            // Otros errores - mostrar mensaje genérico
            Log::error('Error al generar Genesis', [
                'message' => $e->getMessage(),
                'accountId' => $accountId,
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Ha ocurrido un error al generar el Genesis. Por favor, intenta nuevamente.',
            ]);
        }

    } else {
        Log::error('No se encontraron datos de cuenta', [
            'accountId' => $accountId
        ]);
        return response()->json(['success' => false, 'error' => 'faltan datos']);
        // return response()->json(['error' => 'faltan datos']);
    }
}


public function regenerateGenesis(Request $request){
    // Validar las URLs y archivos
    $validator = Validator::make($request->all(), [
        'genesisgenerado' => 'required|string',
        'account' => 'required|integer',
        'id_generated' => 'required|integer',
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()]);
    }
    ini_set('max_execution_time', 300);

    $genesisgenerado = $request->input('genesisgenerado');
    $accountId = $request->input('account');
    $id_generated = $request->input('id_generated');

    $generated = Generated::find($id_generated);
            
    if (!$generated) {
        return response()->json([
            'success' => false,
            'error' => 'Generación no encontrada'
        ]);
    }

    $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];


    // $accountData = Field::where('account_id', $accountId)->pluck('value', 'key');

    // Aquí ya no obtendré los datos desde field sino desde el generated
    // Verifico si en generated tengo el brief y el objetivo

    if ($metadata && $metadata['brief'] && $metadata['objective']) {
        $brief = $metadata['brief'];
        $objective = $metadata['objective'];

        $prompt = <<<EOT

Ahora, basándote en la información proporcionada en el brief y el objetivo dado, crea un GÉNESIS completamente diferente con otro enfoque emocional a:

$genesisgenerado

Usando el brief: 
$brief

Y el objetivo: 
$objective

Analiza cuidadosamente la información disponible, identifica los puntos clave relevantes para cada componente del GÉNESIS, y desarrolla una estrategia coherente y efectiva. Responde en español y presenta solo el resultado final del GÉNESIS sin notas o textos adicionales.

EOT;

    $system_prompt = <<<EOT
Eres un experto en planificación estratégica publicitaria especializado en la metodología GÉNESIS (Generación Estratégica Neuroinspirada de Efectividad Sincronizada con Inteligencia Sintética) de god-ai (Objetivo cuantificado, Obstáculo identificado, Insight Aumentado, Desafío creativo). Esta metodología se utiliza para crear estrategias creativas publicitarios efectivas y consiste en los siguientes componentes:
1. Objetivo Cuantificado: Define el propósito de negocio medible y específico.
2. Obstáculo Identificado: Identifica el principal obstáculo para lograr el objetivo. ¿Qué se interpone en el camino para alcanzar ese objetivo? ¿Qué papel juega la comunicación en el cambio de esa conducta? A veces, puedes ver más de un obstáculo, pero trata de identificar la única cosa que, si se supera, hará que todo lo demás caiga en su lugar.
3. Insight Aumentado: Revela una verdad sobre el consumidor que ayuda a superar el obstaculo Identificado. ¿Cuál es la verdad que desbloquea este problema? ¿Qué sabemos sobre el consumidor que nos ayude a crear comunicaciones que superen el problema? Esta información debe provenir del consumidor, no del conocimiento del producto. Un gran insight sorprende a la gente con lo obvio.
4. Desafío Creativo: Plantea un desafío ambicioso y transformador para la comunicación. ¿Cuál es el reto para nuestras comunicaciones? Este debe ser grande, ambicioso y valiente. Puede ser provocador, pero al menos, emocionante. Un gran reto te ayudará a ver claramente y a romper las barreras. Transforma la marca. Nunca es una tarea. Es simple. No está basado en un negocio, un producto o una tarea.
Para crear un GÉNESIS efectivo:
- Mantén cada sección concisa y enfocada.
- Asegúrate de que todas las partes cuenten una historia coherente.
- Enfócate en las personas, no en el producto.
- Busca insights culturales profundos según la info del país.
- El desafío creativo debe ser transformador y no incluir el nombre de la marca.
-Analiza:, ¿Dice una historia simple?
-Menos palabras tienen un mayor impacto; trata de reducir el conteo de palabras a la mitad, casi siempre esto genera una mejora
-Trata de encontrar la línea conductora para que el brief realmente se lea como una historia
-Sé brutalmente honesto con el problema. Incluso si duele
-¿Tu insight se centra en las personas? Los insights más geniales son insights culturales
-Es tentador escribir un insight sobre el producto, pero al final debería regresar a la gente real en el mundo real
-¿Cómo es esto propio? ¿Cómo logro que esta marca se diferencie de la competencia?
- ¡Rompe las reglas!


Puedes usar estos ejemplos como inspiración y guía de formato respetando los saltos de líneas, no debes copiar:
<examples>
<example>
1.- Coca Cola: Sencillo pero resuelve\n\n
**Objetivo Cuantificado:**\n
Aumentar las ventas de Coca-Cola en formato individual en un 10% en los próximos 3 meses.\n\n
**Obstáculo Identificado:**\n
En un mercado saturado, los consumidores tienen múltiples opciones y están optando por alternativas percibidas como más saludables.\n\n
**Insight Aumentado:**\n
Los consumidores buscan pequeños momentos de placer y recompensa en su día a día.\n\n
**Desafío Creativo:**\n
Posicionar la Coca-Cola individual como el compañero ideal para los pequeños momentos de disfrute diario.
</example>

<example>
2.- Ameriprise: Transformador y mantiene a Ameriprise a la vanguardia\n\n
**Objetivo Cuantificado:**\n
Ayudar a crecer la adquisición de nuevos clientes aumentando los niveles de Amor y Respeto por Ameriprise.\n\n
**Obstáculo Identificado:**\n
Ameriprise compite en una categoría intensamente saturada donde todos luchan por lo mismo: la falta de confianza y la inercia. Para combatir esto, más y más competidores están intentando llegar a los consumidores con "plática franca" (Schwab, Smith Barney). Otros están siguiendo los pasos de Ameriprise, alentando a la gente a alcanzar sus sueños de retiro (Allianz, Wachovia, Lincoln). Ahora Ameriprise necesita adoptar una nueva táctica para diferenciarse del grupo una vez más.\n\n
**Insight Aumentado:**\n
Hemos dado en el clavo con la campaña actual. Inspira a la gente a verse como optimista y aventurera. Y esto hace que Ameriprise se vea más progresiva que la competencia. Ahora, a medida que ampliamos el público objetivo para incluir a los Baby Boomers y a la Generación X, necesitamos ir más allá del retiro, para alentar todo tipo de sueños: sueños grandes y pequeños, sueños de hoy y sueños de mañana, sueños egoístas y sueños altruistas, sueños para ti y sueños para tus hijos. Para romper con el cinismo de los asesores financieros, se necesita una representación de los asesores de Ameriprise como una raza aparte: accesibles, atentos y serviciales.\n\n
**Desafío Creativo:**\n
Hacer que Ameriprise marque la diferencia entre los soñadores y los hacedores.
</example>

<example>
3.- Cascade con Bleach Hydro-Clean:\n\n
**Objetivo Cuantificado:**\n
Impulsar el crecimiento de la marca Cascade mediante la migración de los consumidores de Cascade a Cascade Complete.\n\n
**Obstáculo Identificado:**\n
Desde su punto de vista, ella no tiene ninguna razón para pagar más por Cascade Complete, ya que no ve en qué se diferencia de su Cascade regular u otras marcas que usa.\n\n
**Insight Aumentado:**\n
Ella es la mujer que es un poco más germofóbica y, como resultado, es más proactiva en cuanto a que sus platos estén completamente limpios. Para ella, la limpieza necesita ir más allá de la superficie, especialmente cuando se trata de niños pequeños o al trabajar con ciertos alimentos crudos. Por eso, ha recurrido a ciertos comportamientos como lavar en el lavavajillas con agua extra caliente o incluso evitar cortar pollo en la tabla de cortar por completo, solo para asegurarse de que no haya "cosas pegajosas" que queden atrás y que sus platos estén seguros para comer.\n\n
**Desafío Creativo:**\n
Limpiar incluso lo que no se ve.
</example>
</examples>
EOT;

        try {
            // Validar límite de créditos antes de regenerar
            $this->validateCreditLimit($accountId);
            
            $model = self::MODEL_GENESIS;
            $temperature = 0.8;
            $response = AnthropicService::TextGeneration($prompt, $model, $temperature, $system_prompt);
            
            // Log de tokens Claude
            if(isset($response['usage'])) {
                Log::info('Usage tokens Claude (regenerateGenesis)', [
                    'id_generated' => $id_generated,
                    'usage' => $response['usage']
                ]);
            }
            
            // Verificar que la respuesta sea exitosa antes de registrar
            if (isset($response['data']) && !isset($response['error'])) {
                // Registrar uso de tokens si está disponible
                // Usar un step único para distinguir regeneraciones (se agregará como proceso separado en processes_detail)
                if (isset($response['usage'])) {
                    $this->trackUsageIfAvailable($accountId, $model, $response['usage'], 'anthropic', 'regenerateGenesis', $id_generated);
                }
            }

            $metadata['newgenesis'] = $response['data'];
            $metadata['oldgenesis'] = $genesisgenerado;

            $generated->update([
                'metadata' => json_encode($metadata)
            ]);

            return response()->json(['success' => true, 'data' => ['newgenesis' => $response['data'], 'oldgenesis' => $genesisgenerado], 'function' => 'regenerateGenesis', 'id_generated' => $generated->id]);
        } catch (\App\Exceptions\CreditLimitExceededException $e) {
            // Error específico de límite de créditos - mostrar mensaje personalizado
            Log::warning('Límite de créditos excedido en regenerateGenesis', [
                'message' => $e->getMessage(),
                'accountId' => $accountId
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            // Otros errores - mostrar mensaje genérico
            Log::error('Error al regenerar Genesis', [
                'message' => $e->getMessage(),
                'accountId' => $accountId,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Ha ocurrido un error al regenerar el Genesis. Por favor, intenta nuevamente.',
            ]);
        }

    }else{
        return response()->json(['success' => false, 'error' => 'faltan datos']);
        // return response()->json(['error' => 'faltan datos']);
    }
}

public function construccionescenario(Request $request){
    // Validar las URLs y archivos
    $validator = Validator::make($request->all(), [
        'genesisgenerado' => 'required|string',
        'account' => 'required|integer',
        'id_generated' => 'required|integer',
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()]);
    }
    ini_set('max_execution_time', 300);

    $genesisgenerado = $request->input('genesisgenerado');
    $accountId = $request->input('account');
    // $accountData = Field::where('account_id', $accountId)->pluck('value', 'key');
    $id_generated = $request->input('id_generated');

    $generated = Generated::find($id_generated);
            
    if (!$generated) {
        return response()->json([
            'success' => false,
            'error' => 'Generación no encontrada'
        ]);
    }

    $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

    if ($metadata && $metadata['brief'] && $metadata['objective']) {
        // Buscar el registro existente
        $brief = $metadata['brief'];
        $objective = $metadata['objective'];

        try {
            // Guarda los datos del génesis
            // Field::updateOrCreate(
            //     ['account_id' => $accountId, 'key' => '360_genesis'],
            //     ['value' => $genesisgenerado]
            // );

            // Validar límite de créditos antes de generar
            $this->validateCreditLimit($accountId);

            $metadata['genesis'] = $genesisgenerado;

            $generated->update([
                'metadata' => json_encode($metadata)
            ]);
            // Genera insight usando el génesis
            $insightgenerado = $this->GenerarInsight2($brief,$objective,$genesisgenerado,$accountId, $id_generated);
            $insightdata = $insightgenerado['data'];
            $insightfuentes = $insightgenerado['fuentes'];

            // Verificar si las fuentes existen y convertirlas en enlaces clickeables con comillas dobles
            if (is_array($insightfuentes) && !empty($insightfuentes)) {
                $fuentesHTML = '<p><strong>Fuentes:</strong></p><ul><li>' . implode(
                    '</li><li>',
                    array_map(function ($fuente) {
                        return filter_var($fuente, FILTER_VALIDATE_URL)
                            ? sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', htmlspecialchars($fuente, ENT_QUOTES, 'UTF-8'), htmlspecialchars($fuente, ENT_QUOTES, 'UTF-8'))
                            : htmlspecialchars($fuente, ENT_QUOTES, 'UTF-8'); // Escapar si no es URL
                    }, $insightfuentes)
                ) . '</li></ul>';
            } else {
                $fuentesHTML = '<p><strong>Fuentes:</strong> No se encontraron fuentes.</p>';
            }

            // Field::updateOrCreate(
            //     [
            //         'account_id' => $accountId,
            //         'key' => 'fuentesEscenario'
            //     ],
            //     [
            //         'value' => $fuentesHTML
            //     ]
            // );
            
            // Preparar las fuentes para la respuesta
            $fuentesResponse = is_string($insightfuentes) ? explode("\n", trim($insightfuentes)) : $insightfuentes;

            $metadata['escenario_insight_data'] = $insightdata;
            $metadata['escenario_insight_fuentes'] = $fuentesResponse;
            $metadata['escenario_insight_fuentes_html'] = $fuentesHTML;

            $generated->update([
                'metadata' => json_encode($metadata)
            ]);

            // Genera el escenario
            $prompt = <<<EOT
            Analiza cuidadosamente la información disponible, además de los Insights sociales poderosos que te servirán para nutrir el resultado, identifica los puntos clave relevantes para cada componente creativo. Responde en español y presenta solo el resultado final sin notas o textos adicionales. Aquí tienes la información para lograrlo:

            Génesis:
            $genesisgenerado

            Brief:
            $brief

            Objetivo:
            $objective


            Insights Sociales:
            $insightdata

            EOT;

            $system_prompt = <<<EOT
            Ahora explota tu potencial creativo y debes construir un escenario estratégico creativo con el Objetivo Cuantificado planteado, con el Obstáculo Identificado, para crear el escenario usando el Insight Aumentado y el Desafío Creativo.
            El concepto debe ser replanteado internamente por parte tuya y de 3 opciones que desarrolles, elige mostrar la que sea más original y creativa. Reflexiona internamente para mejorar el resultado y que sea de talla mundial.
            Entrega siempre esta formato sin información adicional y respetando los saltos de líneas:
            **Contexto**\n
            Un relato que amplifique y evidencie el Objetivo Cuantificado (tendencias, hechos, observaciones, etc)
            **Problema**\n
            Dar, mediante storytelling y ojalá desde el punto de vista del target, esa visión que representa esa lucha o frustración. 
            **Solución**\n
            Tomando el Desafío Creativo, se establece la manera en la cual se resolverá
            **Concepto**\n
            Condensación del Desafío Creativo en la frase publicitaria
            REGLAS
            - Mantén cada sección concisa y enfocada.
            - Asegúrate de que todas las partes cuenten una historia coherente.
            - Enfócate en las personas, no en el producto.
            - Busca aspectos culturales profundos según la info del país. Usa los insights sociales para alimentar la creatividad
            - El concepto debe ser transformador y puede no incluir el nombre de la marca si lo ves necesario.
            -Trata de encontrar la línea conductora para que se lea como una historia
            -Sé brutalmente honesto con el problema. Incluso si duele
            -¿Tu solución se centra en las personas? Los insights más geniales son insights culturales, no siempre te bases en tecnología, centraté en solucionar el problema y cumplir con el objetivo
            - ¿Cómo logro que esta marca se diferencie de la competencia? 
            - Para los relatos usa nombres al azar (evitando María, José o Luis) de diferentes personas y asigna el género según la coherencia de los datos del brief, el país y el storytelling. Puedes usar modismos regionales/país.
            - ¡Rompe las reglas!

            EOT;

            // Usar la constante definida al inicio del controlador
            $model = self::MODEL_CONSTRUCCION_ESCENARIO;
            $temperature = 0.8;
            $response = AnthropicService::TextGeneration($prompt, $model, $temperature, $system_prompt);
            
            // Log de tokens Claude
            if(isset($response['usage'])) {
                Log::info('Usage tokens Claude (construccionescenario)', [
                    'id_generated' => $id_generated,
                    'usage' => $response['usage']
                ]);
            }
            
            // Verificar que la respuesta sea exitosa antes de registrar
            if (isset($response['data']) && !isset($response['error'])) {
                // Registrar uso de tokens si está disponible
                if (isset($response['usage'])) {
                    $this->trackUsageIfAvailable($accountId, $model, $response['usage'], 'anthropic', 'construccionescenario', $id_generated);
                }
            }

            // Field::updateOrCreate(
            //     ['account_id' => $accountId, 'key' => '360_construccionescenario'],
            //     ['value' => $response['data']]
            // );

            $metadata['construccionescenario'] = $response['data'];
            $metadata['step'] = 4;

            $generated->update([
                'name' => 'Creatividad en proceso...',
                'metadata' => json_encode($metadata)
            ]);

            return response()->json(
            ['success' => true, 
            'data' =>   array_merge($response, ['fuentes' => $fuentesResponse]), 
            'function' => 'construccionescenario',
            'id_generated' => $generated->id
                ]);

        } catch (\App\Exceptions\CreditLimitExceededException $e) {
            // Error específico de límite de créditos - mostrar mensaje personalizado
            Log::warning('Límite de créditos excedido en construccionescenario', [
                'message' => $e->getMessage(),
                'accountId' => $accountId
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al generar construcción de escenario', [
                'message' => $e->getMessage(),
                'accountId' => $accountId,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Ha ocurrido un error al generar el escenario. Por favor, intenta nuevamente.'
            ]);
        }
    } else {
        return response()->json(['success' => false, 'error' => 'faltan datos']);
        // return response()->json(['error' => 'faltan datos']);
    }
}

public function regenerarConstruccionEscenario(Request $request){
    // Validar las URLs y archivos
    $validator = Validator::make($request->all(), [
        'construccionescenario' => 'required|string',
        // 'genesisgenerado' => 'required|string',
        'account' => 'required|integer',
        'id_generated' => 'required|integer',
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()]);
    }
    ini_set('max_execution_time', 300);

    $construccionescenario = $request->input('construccionescenario');
    // $genesisgenerado = $request->input('genesisgenerado');
    $accountId = $request->input('account');
    $id_generated = $request->input('id_generated');

    // $accountData = Field::where('account_id', $accountId)->pluck('value', 'key');

    $generated = Generated::find($id_generated);

    if (!$generated) {
        return response()->json([
            'success' => false,
            'error' => 'Generación no encontrada'
        ]);
    }   

    $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

    if ($metadata && $metadata['brief'] && $metadata['objective'] && $metadata['genesis']) {
        $brief = $metadata['brief'];
        $objective = $metadata['objective'];
        $genesisgenerado = $metadata['genesis'];

        try {
            // Validar límite de créditos antes de regenerar
            $this->validateCreditLimit($accountId);
            
            $prompt = <<<EOT
            Analiza cuidadosamente la información disponible, incluyendo el Escenario ya generado previamente. Tu tarea es construir un nuevo escenario creativo que sea distinto al anterior, pero manteniendo coherencia con el objetivo y los insights sociales. No debe ser una repetición, sino una nueva interpretación creativa con un enfoque diferente.

            Aquí tienes la información relevante para lograrlo:

            ESCENARIO ANTERIOR:
            $construccionescenario

            Génesis:
            $genesisgenerado

            Brief:
            $brief

            Objetivo:
            $objective

            Genera un nuevo escenario siguiendo la estructura establecida, pero asegurándote de que tenga un enfoque fresco, innovador y diferenciado del escenario anterior. Responde en español y presenta solo el resultado final del escenario nuevo sin notas o textos adicionales.
            EOT;

            $system_prompt = <<<EOT
            Ahora explota tu potencial creativo y debes construir un escenario estratégico creativo con el Objetivo Cuantificado planteado, con el Obstáculo Identificado, para crear el escenario usando el Insight Aumentado y el Desafío Creativo.
            El concepto debe ser replanteado internamente por parte tuya y de 3 opciones que desarrolles, elige mostrar la que sea más original y creativa. Reflexiona internamente para mejorar el resultado y que sea de talla mundial.
            Entrega siempre esta formato sin información adicional y respetando los saltos de líneas:
            **Contexto**\n
            Un relato que amplifique y evidencie el Objetivo Cuantificado (tendencias, hechos, observaciones, etc)
            **Problema**\n
            Dar, mediante storytelling y ojalá desde el punto de vista del target, esa visión que representa esa lucha o frustración. 
            **Solución**\n
            Tomando el Desafío Creativo, se establece la manera en la cual se resolverá
            **Concepto**\n
            Condensación del Desafío Creativo en la frase publicitaria
            REGLAS
            - Mantén cada sección concisa y enfocada.
            - Asegúrate de que todas las partes cuenten una historia coherente.
            - Enfócate en las personas, no en el producto.
            - Busca aspectos culturales profundos según la info del país. Usa los insights sociales para alimentar la creatividad
            - El concepto debe ser transformador y puede no incluir el nombre de la marca si lo ves necesario.
            -Trata de encontrar la línea conductora para que se lea como una historia
            -Sé brutalmente honesto con el problema. Incluso si duele
            -¿Tu solución se centra en las personas? Los insights más geniales son insights culturales, no siempre te bases en tecnología, centraté en solucionar el problema y cumplir con el objetivo
            - ¿Cómo logro que esta marca se diferencie de la competencia? 
            - Para los relatos usa nombres al azar (evitando María, José o Luis) de diferentes personas y asigna el género según la coherencia de los datos del brief, el país y el storytelling. Puedes usar modismos regionales/país.
            - ¡Rompe las reglas!

            EOT;

            // Usar la constante definida al inicio del controlador
            $model = self::MODEL_CONSTRUCCION_ESCENARIO;
            $temperature = 0.8;
            $response = AnthropicService::TextGeneration($prompt, $model, $temperature, $system_prompt);
            
            // Log de tokens Claude
            if(isset($response['usage'])) {
                Log::info('Usage tokens Claude (regenerarConstruccionEscenario)', [
                    'id_generated' => $id_generated,
                    'usage' => $response['usage']
                ]);
            }
            
            // IMPORTANTE: Cuando se regenera el escenario, NO se llama a GenerarInsight2 nuevamente
            // porque se usan los insights anteriores del metadata. Esto significa que no se registra
            // el uso de Perplexity en regeneraciones, solo el uso de Claude para regenerar el escenario.
            // Si en el futuro se decide regenerar también los insights, se debe llamar a GenerarInsight2 aquí.
            
            // Verificar que la respuesta sea exitosa antes de registrar
            if (isset($response['data']) && !isset($response['error'])) {
                // Registrar uso de tokens si está disponible
                // Usar un step único para distinguir regeneraciones (se agregará como proceso separado en processes_detail)
                if (isset($response['usage'])) {
                    $this->trackUsageIfAvailable($accountId, $model, $response['usage'], 'anthropic', 'regenerarConstruccionEscenario', $id_generated);
                }
            }

            $metadata['newescenario'] = $response['data'];
            $metadata['oldescenario'] = $construccionescenario;

            $generated->update([
                'metadata' => json_encode($metadata)
            ]);

            return response()->json(['success' => true, 'data' => ['newescenario' => $response['data'], 'oldescenario' => $construccionescenario], 'function' => 'regenerarConstruccionEscenario', 'id_generated' => $generated->id]);
        } catch (\App\Exceptions\CreditLimitExceededException $e) {
            // Error específico de límite de créditos - mostrar mensaje personalizado
            Log::warning('Límite de créditos excedido en regenerarConstruccionEscenario', [
                'message' => $e->getMessage(),
                'accountId' => $accountId
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            // Otros errores - mostrar mensaje genérico
            Log::error('Error al regenerar construcción de escenario', [
                'message' => $e->getMessage(),
                'accountId' => $accountId,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Ha ocurrido un error al regenerar el escenario. Por favor, intenta nuevamente.',
            ]);
        }
    } else {
        return response()->json(['success' => false, 'error' => 'faltan datos']);
        // return response()->json(['error' => 'faltan datos']);
    }
}
public function saveconstruccionescenario(Request $request){
    // Validar las URLs y archivos
    $validator = Validator::make($request->all(), [
        'construccionescenario' => 'required|string',
        'account' => 'required|integer',
        'id_generated' => 'required|integer',
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()]);
    }
    $construccionescenario = $request->input('construccionescenario');
   
    $accountId = $request->input('account');
    // $accountData = Field::where('account_id', $accountId)->pluck('value', 'key');
    // $objective = Field::where('key', '360_objective')->where('account_id', $accountId)->first();
    // $problema = Field::where('key', '360_problema')->where('account_id', $accountId)->first();
    // $insight = Field::where('key', '360_insight')->where('account_id', $accountId)->first();

    $id_generated = $request->input('id_generated');

    $generated = Generated::find($id_generated);

    if (!$generated) {
        return response()->json([
            'success' => false,
            'error' => 'Generación no encontrada'
        ]);
    }

    $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];
    if ($metadata && $metadata['brief'] && $metadata['objective'] && $metadata['genesis']) {
        // Buscar el registro existente
        $brief = $metadata['brief'];
        $objective = $metadata['objective'];
        $genesis = $metadata['genesis'];    

        // $prompt = "Tu función es generar un CONTEXTO, PROBLEMA, SOLUCIÓN y CONCEPTO usando estos datos. \nObjetivo: $objective\nProblema: $problema\nInsight: $insight\nReto: $reto\nConstrucción de escenario:";
        // $prompt .= "El formato que debes enviar la información es HTML";

        // $response = AnthropicService::TextGeneration($prompt);

        $metadata['construccionescenario'] = $construccionescenario;
        $metadata['step'] = 5;

        $generated->update([
            'name' => 'Elige campaña en proceso...',
            'metadata' => json_encode($metadata)
        ]);

        return response()->json(['success' => true, 'data' => $genesis, 'function' => 'saveconstruccionescenario', 'id_generated' => $generated->id]);
    }else{
        return response()->json(['success' => false, 'error' => 'faltan datos']);
    }
}

public function saveconstruccionescenarioFinalizar(Request $request){
    // Validar las URLs y archivos
    $validator = Validator::make($request->all(), [
        'construccionescenario' => 'required|string',
        'account' => 'required|integer',
        'id_generated' => 'required|integer',
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()]);
    }
    $construccionescenario = $request->input('construccionescenario');
   
    $accountId = $request->input('account');

    $id_generated = $request->input('id_generated');

    $generated = Generated::find($id_generated);

    if (!$generated) {
        return response()->json([
            'success' => false,
            'error' => 'Generación no encontrada'
        ]);
    }

    $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];
    if ($metadata && $metadata['brief'] && $metadata['objective'] && $metadata['genesis']) {
        // Buscar el registro existente
        $brief = $metadata['brief'];
        $objective = $metadata['objective'];
        $genesis = $metadata['genesis'];
        $fuentesGenesis= $metadata['genesis_insight_fuentes_html'];
        $fuentesEscenario= $metadata['escenario_insight_fuentes_html'];

        $metadata['construccionescenario'] = $construccionescenario;
        $metadata['step'] = 4.1;
        $metadata['save_fields'] = ['genesis', 'fuentes_genesis', 'escenario', 'fuentes_escenario'];
        $metadata['final_step'] = 4.2;
        $metadata['final_function'] = 'construccionGenesisGuardado4_2';

        $generated->update([
            'name' => 'Generando resultado final...',
            'metadata' => json_encode($metadata)
        ]);

        $content = ['genesis' =>  $genesis,'fuentesGenesis'=>$fuentesGenesis,'escenario'=>$construccionescenario ,'fuentesEscenario'=>$fuentesEscenario];

        return response()->json(['success' => true, 'data' => $content, 'function' => 'saveconstruccionescenarioFinalizar', 'id_generated' => $generated->id]);
    }else{
        return response()->json(['success' => false, 'error' => 'faltan datos']);
    }
}

public function validarconcepto(Request $request){
    try {
        // Validar las URLs y archivos
        $validator = Validator::make($request->all(), [
            // 'concepto_pais' => 'required|string',
            // 'concepto_nombre_marca' => 'required|string',
            'concepto_categoria' => 'required|string',
            'concepto_periodo_campania' => 'required|string',
            'account' => 'required|integer',
            'id_generated' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        Log::info("Iniciando Validar Concepto", $request->all());

        // $concepto_pais = $request->input('concepto_pais');
        // $concepto_nombre_marca = $request->input('concepto_nombre_marca');
        $concepto_categoria = $request->input('concepto_categoria');
        $concepto_periodo_campania = $request->input('concepto_periodo_campania');
        $accountId = $request->input('account');
        $id_generated = $request->input('id_generated');

        $generated = Generated::find($id_generated);

        if (!$generated) {
            return response()->json([
                'success' => false,
                'error' => 'Generación no encontrada'
            ]);
        }

        $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

        $generatedBrief = Generated::find($metadata['id_brief']);
        if (!$generatedBrief) {
            return response()->json([
                'success' => false,
                'error' => 'Generación no encontrada'
            ]);
        }
        $metadataBrief = $generatedBrief->metadata ? json_decode($generatedBrief->metadata, true) : [];

        if ($metadata && $metadata['construccionescenario'] && $metadataBrief) {
            $construccionescenario = $metadata['construccionescenario'];
            $metadata['concepto_pais'] = $metadataBrief['country'];
            $metadata['concepto_nombre_marca'] = $metadataBrief['name'];
            $metadata['concepto_categoria'] = $concepto_categoria;
            $metadata['concepto_periodo_campania'] = $concepto_periodo_campania;

            $concepto = $construccionescenario;

            $generated->update([
                'metadata' => json_encode($metadata)
            ]);

            // Validar límite de créditos antes de generar
            $this->validateCreditLimit($accountId);

            $options = [
                'prompt' => [
                    'id' => 'pmpt_68a2319d991c8190a4152ca9c8ae51e705034b13c3fd9d8e',
                    'variables' => [
                        "concepto" => $concepto,
                        "marca" => $metadataBrief['name'],
                        "categoria" => $concepto_categoria,
                        "pais" => $metadataBrief['country'],   
                        "periodo" => $concepto_periodo_campania
                    ]
                ],
                'background' => true
            ];

            // Llamar al nuevo endpoint de deep research
            $response = OpenAiService::createModelResponse($options);

            // $response = ['data' => ['id' => 'resp_68b9a8201b3481958d94f473015b20080df8ba61e105eceb']];

            Log::info('Respuesta OpenAiService::createModelResponse (Validar Concepto)', [
                'response' => $response
            ]);

            if (isset($response['error'])) {
                Log::error('Error en la llamada a OpenAiService::createModelResponse (Validar Concepto)', [
                    'error' => $response['error']
                ]);
                return response()->json(['success' => false, 'error' => $response['error']]);
            }
            
            // Registrar uso de tokens si está disponible (solo si el proceso fue exitoso)
            if (isset($response['data']['usage'])) {
                $this->trackUsageIfAvailable($accountId, self::MODEL_VALIDAR_CONCEPTO, $response['data']['usage'], 'openai', 'validarconcepto', $generated->id);
            }

            $metadata['id_generacion_concepto'] = $response['data']['id'];
            $metadata['generacion_concepto_data'] = $response['data'];
            $metadata['generacion_concepto_status'] = 'pending';
            $metadata['step'] = 10;

            $generated->update([
                'name' => 'Validando concepto en proceso...',
                'metadata' => json_encode($metadata)
            ]);

            return response()->json(['success' => true, 'data' => $response['data'], 'function' => 'validarconcepto', 'id_generated' => $generated->id]);
            
        }else{
            return response()->json(['success' => false, 'error' => 'faltan datos']);
        }
    } catch (\App\Exceptions\CreditLimitExceededException $e) {
        // Error específico de límite de créditos - mostrar mensaje personalizado
        Log::warning('Límite de créditos excedido en validarconcepto', [
            'message' => $e->getMessage(),
            'accountId' => $accountId ?? null
        ]);

        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
    } catch (\Exception $e) {
        Log::error('Error al validar concepto', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['success' => false, 'error' => 'Ha ocurrido un error al validar el concepto. Por favor, intenta nuevamente.']);
    }
}

public function get_concepto($generationId){
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

        $response = OpenAiService::getModelResponse($metadata['id_generacion_concepto']);

        if(isset($response['success']) && !$response['success']){
            return response()->json([
                'success' => false,
                'error' => $response['error']
            ], 500);
        }

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
            
            // Registrar uso de tokens cuando se completa exitosamente
            if (isset($response['data']['usage'])) {
                $this->trackUsageIfAvailable($generated->account_id, self::MODEL_VALIDAR_CONCEPTO, $response['data']['usage'], 'openai', 'get_concepto', $generated->id);
            }
        }


        if($statusgenerated === 'completed'){

            $metadata['generacion_concepto_content'] = $content;
            $metadata['generacion_concepto_sources'] = $sources;
            $metadata['generacion_concepto_status'] = 'completed';

            $generated->update([
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

public function saveValidarConcepto(Request $request){
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
            return response()->json(['error' => $validator->errors()]);
        }

        $validarConcepto = $request->input('validarConcepto');
        $accountId = $request->input('id_account');
        $id_generated = $request->input('id_generated');
        $rating = $request->input('rating');
        $file_name = $request->input('file_name');

        $generated = Generated::find($id_generated);

        if (!$generated) {
            return response()->json([
                'success' => false,
                'error' => 'Generación no encontrada'
            ]);
        }

        $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

        $metadata['validar_concepto'] = $validarConcepto;
        $metadata['step'] = 6;

        $generated->update([
            'name' => 'eleccion de campaña en proceso...',
            'metadata' => json_encode($metadata)
        ]);

        $generatedValidarConcepto = Generated::create([
            'account_id' => $accountId,
            'key' => 'Concepto',
            'name' => $file_name,
            'value' => $validarConcepto,
            'rating' => $rating,
            'status' => 'completed',
            'metadata' => null
        ]);

        return response()->json(['success' => true, 'data' => $validarConcepto, 'function' => 'saveValidarConcepto', 'id_generated' => $generated->id]);
    }catch(\Exception $e){
        return response()->json(['success' => false, 'error' => $e->getMessage()]);
    }
}

public function mejorarConcepto(Request $request){
    try{
        $validator = Validator::make($request->all(), [
            'validarConcepto' => 'required|string',
            'id_account' => 'required|integer',
            'id_generated' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        $accountId = $request->input('id_account');
        $id_generated = $request->input('id_generated');
        $validarConcepto = $request->input('validarConcepto');

        $generated = Generated::find($id_generated);
        if (!$generated) {
            return response()->json([
                'success' => false,
                'error' => 'Generación no encontrada'
            ]);
        }

        $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

        $creatividad = $metadata['construccionescenario'];

        // Validar límite de créditos antes de generar
        $this->validateCreditLimit($accountId);

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

        // Llamar al nuevo endpoint de deep research
        $response = OpenAiService::createModelResponse($options);

        if (isset($response['error'])) {
            Log::error('Error en la llamada a OpenAiService::createModelResponse (Mejorar Concepto)', [
                'error' => $response['error']
            ]);
            return response()->json(['success' => false, 'error' => $response['error']]);
        }
        
        // Registrar uso de tokens si está disponible (solo si el proceso fue exitoso)
        if (isset($response['data']['usage'])) {
            $this->trackUsageIfAvailable($accountId, self::MODEL_MEJORAR_CONCEPTO, $response['data']['usage'], 'openai', 'mejorarConcepto', $generated->id);
        }

        $metadata['id_generacion_mejorar_concepto'] = $response['data']['id'];
        $metadata['generacion_mejorar_concepto_data'] = $response['data'];
        $metadata['generacion_mejorar_concepto_status'] = 'pending';

        $metadata['step'] = 9;

        $generated->update([
            'name' => 'Mejorando concepto en proceso...',
            'metadata' => json_encode($metadata)
        ]);

        return response()->json(['success' => true, 'data' => $response['data'], 'function' => 'mejorarConcepto', 'id_generated' => $generated->id]);
    } catch (\App\Exceptions\CreditLimitExceededException $e) {
        // Error específico de límite de créditos - mostrar mensaje personalizado
        Log::warning('Límite de créditos excedido en mejorarConcepto', [
            'message' => $e->getMessage(),
            'accountId' => $accountId
        ]);

        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
    } catch(\Exception $e){
        Log::error('Error al mejorar concepto', [
            'message' => $e->getMessage(),
            'accountId' => $accountId ?? null,
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'error' => 'Ha ocurrido un error al mejorar el concepto. Por favor, intenta nuevamente.'
        ]);
    }
}

public function get_concepto_mejorado($generationId){
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

        $response = \App\Services\OpenAiService::getModelResponse($metadata['id_generacion_mejorar_concepto']);

        if(isset($response['success']) && !$response['success']){
            return response()->json([
                'success' => false,
                'error' => $response['error']
            ], 500);
        }

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
            
            // Registrar uso de tokens cuando se completa exitosamente
            if (isset($response['data']['usage'])) {
                $this->trackUsageIfAvailable($generated->account_id, self::MODEL_MEJORAR_CONCEPTO, $response['data']['usage'], 'openai', 'get_concepto_mejorado', $generated->id);
            }
        }

        if($statusgenerated === 'completed'){

            $metadata['generacion_mejorar_concepto_content'] = $content;
            $metadata['generacion_mejorar_concepto_sources'] = $sources;
            $metadata['generacion_mejorar_concepto_status'] = 'completed';

            $generated->update([
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

public function saveconstruccionescenariomejorado(Request $request){
    try{
        // Validar las URLs y archivos
        $validator = Validator::make($request->all(), [
            'construccionescenariomejorado' => 'required|string',
            'id_account' => 'required|integer',
            'id_generated' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }
        $construccionescenariomejorado = $request->input('construccionescenariomejorado');
        $accountId = $request->input('id_account');
        $id_generated = $request->input('id_generated');

        $generated = Generated::find($id_generated);

        if (!$generated) {
            return response()->json([
                'success' => false,
                'error' => 'Generación no encontrada'
            ]);
        }

        $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];
        if ($metadata) {

            $metadata['construccionescenario'] = $construccionescenariomejorado;
            $metadata['step'] = 6;

            $generated->update([
                'name' => 'Elige campaña en proceso...',
                'metadata' => json_encode($metadata)
            ]);

            return response()->json(['success' => true, 'data' => $construccionescenariomejorado, 'function' => 'saveconstruccionescenariomejorado', 'id_generated' => $generated->id]);
        }else{
            return response()->json(['success' => false, 'error' => 'faltan datos']);
        }
    }catch(\Exception $e){
        return response()->json(['success' => false, 'error' => $e->getMessage()]);
    }
}

public function setGenerarCreatividad(Request $request){
    try{
        // Validar las URLs y archivos
        
        $validator = Validator::make($request->all(), [
            '360_Tipo_de_campaña' => 'required|string',
            'id_account' => 'required|integer',
            'id_generated' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        ini_set('max_execution_time', 300);

        $accountId = $request->input('id_account');
        $account = Account::find($accountId);
        $id_generated = $request->input('id_generated');

        $generated = Generated::find($id_generated);
        if (!$generated) {
            return response()->json([
                'success' => false,
                'error' => 'Generación no encontrada'
            ]);
        }
        
        $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

        if ($metadata) {
            $generatedBrief = Generated::find($metadata['id_brief']);
            if (!$generatedBrief) {
                return response()->json([
                    'success' => false,
                    'error' => 'Generación no encontrada'
                ]);
            }
            $metadataBrief = $generatedBrief->metadata ? json_decode($generatedBrief->metadata, true) : [];
        
            $category = $account->category;
            Log::info("Categoria encontrada", [
                'Categoría' => $category,
            ]);
            $brief = $metadata['brief'];
            $objective = $metadata['objective'];
            $genesis = $metadata['genesis'];
            $fuentesGenesis= $metadata['genesis_insight_fuentes_html'];
            $fuentesEscenario= $metadata['escenario_insight_fuentes_html'];
            $construccionescenario = $metadata['construccionescenario'];
            $genesiscompleto = $genesis . $construccionescenario;
            $Tipodecampaña = $request->input('360_Tipo_de_campaña');

            $metadata['tipo_de_campaña'] = $Tipodecampaña;
            
            $country = $metadataBrief['country'];

            Log::info('Iniciando generación de creatividad', [
                'id_generated' => $id_generated,
                'tipo_campania' => $Tipodecampaña,
                'account_id' => $accountId,
                'category' => $category
            ]);

            // Validar límite de créditos antes de generar
            $this->validateCreditLimit($accountId);

            $creatividad = $this->generarCreatividad($Tipodecampaña, $objective, $genesiscompleto, $brief, $category);
            
            if(!$creatividad['success']){
                Log::error('Error al generar creatividad', [
                    'id_generated' => $id_generated,
                    'error' => $creatividad['error']
                ]);
                return response()->json(['success' => false, 'error' => $creatividad['error']]);
            }
            
            $id_generacion_creatividad = $creatividad['data']['id'] ?? null;
            
            // Log del ID de respuesta
            Log::info('ID de respuesta generarCreatividad', [
                'id_generated' => $id_generated,
                'id_generacion_creatividad' => $id_generacion_creatividad
            ]);
            
            // Log del objeto usage (si existe en la respuesta inicial)
            if(isset($creatividad['data']['usage'])) {
                Log::info('Usage tokens generarCreatividad', [
                    'id_generated' => $id_generated,
                    'id_generacion_creatividad' => $id_generacion_creatividad,
                    'usage' => $creatividad['data']['usage']
                ]);
            }
            
            Log::info('Generación de creatividad iniciada exitosamente', [
                'id_generated' => $id_generated,
                'id_generacion_creatividad' => $id_generacion_creatividad,
                'status' => $creatividad['data']['status'] ?? 'unknown'
            ]);

            $metadata['generacion_creatividad_data'] = $creatividad['data'];
            $metadata['generacion_creatividad_status'] = 'processing';
            
            $metadata['step'] = 7;
            $generated->update([
                'name' => 'Generando creatividad...',
                'metadata' => json_encode($metadata)
            ]);

            return response()->json(['success' => true, 'data' => $creatividad['data'], 'function' => 'setGenerarCreatividad', 'id_generated' => $generated->id]);
        }else{
            return response()->json(['success' => false, 'error' => 'faltan datos']);
        }
    } catch (\App\Exceptions\CreditLimitExceededException $e) {
        // Error específico de límite de créditos - mostrar mensaje personalizado
        Log::warning('Límite de créditos excedido en setGenerarCreatividad', [
            'message' => $e->getMessage(),
            'accountId' => $accountId ?? null
        ]);

        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
    } catch(\Exception $e){
        Log::error('Error al generar creatividad', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'error' => 'Ha ocurrido un error al generar la creatividad. Por favor, intenta nuevamente.'
        ]);
    }
}

public function get_construccion_creatividad($generationId){
    try {
        Log::info("Inicio de get_construccion_creatividad");
        $generated = Generated::find($generationId);
        
        if (!$generated) {
            return response()->json([
                'success' => false,
                'error' => 'Generación no encontrada'
            ], 404);
        }

        $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

        $contentCreatividad = $metadata['generacion_creatividad_content'] ?? '';
        $sourcesCreatividad = $metadata['generacion_creatividad_sources'] ?? [];
        $statusgenerated = 'processing';

        // $genesis = $metadata['genesis'];
        // $fuentesGenesis= $metadata['genesis_insight_fuentes_html'];
        // $fuentesEscenario= $metadata['escenario_insight_fuentes_html'];
        // $construccionescenario = $metadata['construccionescenario'];
        // $brief = $metadata['brief'];
        // $objective = $metadata['objective'];
        // $genesiscompleto = $genesis . $construccionescenario;
        // $Tipodecampaña = $metadata['tipo_de_campaña'];

        $id_generacion_creatividad = $metadata['generacion_creatividad_data']['id'] ?? null;
        // $id_generacion_estrategia = $metadata['generacion_estrategia_data']['id'] ?? null;
        // $id_generacion_ideas_contenido = $metadata['generacion_ideas_contenido_data']['id'] ?? null;

        $statusgeneratedCreatividad = $metadata['generacion_creatividad_status'] ?? 'processing';
        // $statusgeneratedEstrategia = $metadata['generacion_estrategia_status'] ?? 'processing';
        // $statusgeneratedIdeasContenido = $metadata['generacion_ideas_contenido_status'] ?? 'processing';

        if($statusgeneratedCreatividad != 'completed'){
            Log::info("Consultando estado de creatividad", [
                'id_generated' => $generationId,
                'id_generacion_creatividad' => $id_generacion_creatividad
            ]);
            $responseCreatividad = OpenAiService::getModelResponse($id_generacion_creatividad);
            
            // Log del ID de respuesta
            $responseId = $responseCreatividad['data']['id'] ?? null;
            Log::info('ID de respuesta OpenAI (Creatividad)', [
                'id_generated' => $generationId,
                'id_generacion_creatividad' => $id_generacion_creatividad,
                'response_id' => $responseId
            ]);
            
            // Log del objeto usage
            if(isset($responseCreatividad['data']['usage'])) {
                Log::info('Usage tokens OpenAI (Creatividad)', [
                    'id_generated' => $generationId,
                    'id_generacion_creatividad' => $id_generacion_creatividad,
                    'usage' => $responseCreatividad['data']['usage']
                ]);
            }
            
            if(isset($responseCreatividad['success']) && !$responseCreatividad['success']){
                Log::error('Error en respuesta de OpenAI', [
                    'error' => $responseCreatividad['error']
                ]);
            }else{
                if($responseCreatividad['data']['status'] === 'completed'){
                    $statusgeneratedCreatividad = 'completed';
                    $statusgenerated = 'completed';
                    if (isset($responseCreatividad['data']['output'])) {
                        foreach ($responseCreatividad['data']['output'] as $output_item) {
                            if ($output_item['type'] === 'message') {
                                if (isset($output_item['content'][0]['text'])) {
                                    $contentCreatividad = $output_item['content'][0]['text'];
                                }
                                if (isset($output_item['content'][0]['annotations'])) {
                                    foreach($output_item['content'][0]['annotations'] as $annotation){
                                        if($annotation['type'] === 'url_citation'){
                                            $sourcesCreatividad[] = $annotation['url'];
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // Registrar uso de tokens cuando se completa exitosamente
                    if (isset($responseCreatividad['data']['usage'])) {
                        $this->trackUsageIfAvailable($generated->account_id, self::MODEL_CREATIVIDAD, $responseCreatividad['data']['usage'], 'openai', 'get_construccion_creatividad', $generationId);
                    }
                    
                    Log::info('Generación de creatividad completada', [
                        'id_generated' => $generationId,
                        'id_generacion_creatividad' => $id_generacion_creatividad,
                        'content_length' => strlen($contentCreatividad),
                        'sources_count' => count($sourcesCreatividad)
                    ]);
                    $metadata['generacion_creatividad_content'] = $contentCreatividad;
                    $metadata['generacion_creatividad_sources'] = $sourcesCreatividad;
                    $metadata['generacion_creatividad_status'] = 'completed';
                    $generated->update([
                        'metadata' => json_encode($metadata)
                    ]);
                }
            }
        }

        // if($statusgeneratedCreatividad === 'completed' && $statusgeneratedEstrategia === 'completed'){
        //     Log::info("Generación de creatividad y estrategia completadas");
        //     if($id_generacion_ideas_contenido == null){
        //         Log::info("Creando generación de ideas de contenido");
        //         $ideasContenido = $this->generarIdeasContenido($Tipodecampaña, $objective, $genesiscompleto, $brief, $contentCreatividad, $contentEstrategia );
        //         if(!$ideasContenido['success']){
        //             return response()->json(['success' => false, 'error' => $ideasContenido['error']]);
        //         }
        //         $metadata['generacion_ideas_contenido_data'] = $ideasContenido['data'];
        //         $metadata['generacion_ideas_contenido_status'] = 'processing';
        //         $generated->update([
        //             'metadata' => json_encode($metadata)
        //         ]);
        //         $id_generacion_ideas_contenido = $ideasContenido['data']['id'];
        //     }

        //     if($statusgeneratedIdeasContenido != 'completed'){
        //         Log::info("Consultando estado de ideas de contenido");
        //         $responseIdeasContenido = OpenAiService::getModelResponse($id_generacion_ideas_contenido);
        //         if(isset($responseIdeasContenido['success']) && !$responseIdeasContenido['success']){
        //             Log::error('Error en respuesta de OpenAI', [
        //                 'error' => $responseIdeasContenido['error']
        //             ]);
        //         }else{
        //             if($responseIdeasContenido['data']['status'] === 'completed'){
        //                 $statusgeneratedIdeasContenido = 'completed';
        //                 $statusgenerated = 'completed';
        //                 if (isset($responseIdeasContenido['data']['output'])) {
        //                     foreach ($responseIdeasContenido['data']['output'] as $output_item) {
        //                         if ($output_item['type'] === 'message') {
        //                             if (isset($output_item['content'][0]['text'])) {
        //                                 $contentIdeasContenido = $output_item['content'][0]['text'];
        //                             }
        //                             if (isset($output_item['content'][0]['annotations'])) {
        //                                 foreach($output_item['content'][0]['annotations'] as $annotation){
        //                                     if($annotation['type'] === 'url_citation'){
        //                                         $sourcesIdeasContenido[] = $annotation['url'];
        //                                     }
        //                                 }
        //                             }
        //                         }
        //                     }
                            
        //                 }
        //                 $metadata['generacion_ideas_contenido_content'] = $contentIdeasContenido;
        //                 $metadata['generacion_ideas_contenido_sources'] = $sourcesIdeasContenido;
        //                 $metadata['generacion_ideas_contenido_status'] = 'completed';
        //                 $generated->update([
        //                     'metadata' => json_encode($metadata)
        //                 ]);
        //             }
        //         }
        //     }
        // }

        if($statusgenerated === 'completed'){
            Log::info("Generación de creatividad, estrategia e ideas de contenido completadas");

            $metadata['generacion_creatividad_content'] = $contentCreatividad;
            $metadata['generacion_creatividad_sources'] = $sourcesCreatividad;
            $metadata['generacion_creatividad_status'] = 'completed';

            $generated->update([
                'metadata' => json_encode($metadata)
            ]);

            return response()->json([
                'success' => true,
                'status' => 'completed',
                'generation_id' => $generated->id,
                'data' => $contentCreatividad,
                'sources' => $sourcesCreatividad
            ]);
        }else{
            return response()->json([
                'success' => true,
                'status' => $generated->status,
                'generation_id' => $generated->id
            ]);
        }

    } catch (\Exception $e) {
        Log::error('Error en generarIdeasContenido', [
            'error' => $e->getMessage()
        ]);
        return response()->json([
            'success' => false,
            'error' => 'Error al consultar estado: ' . $e->getMessage()
        ], 500);
    }
}

public function setGenerarEstrategia(Request $request){
    try{
        // Validar las URLs y archivos
        $validator = Validator::make($request->all(), [
            '360_Tipo_de_campaña' => 'required|string',
            'id_account' => 'required|integer',
            'id_generated' => 'required|integer',
            'construccioncreatividad' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        ini_set('max_execution_time', 300);

        $accountId = $request->input('id_account');
        $account = Account::find($accountId);
        $id_generated = $request->input('id_generated');
        $construccioncreatividad = $request->input('construccioncreatividad');

        $generated = Generated::find($id_generated);
        if (!$generated) {
            return response()->json([
                'success' => false,
                'error' => 'Generación no encontrada'
            ]);
        }
        
        $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

        if ($metadata) {
            $generatedBrief = Generated::find($metadata['id_brief']);
            if (!$generatedBrief) {
                return response()->json([
                    'success' => false,
                    'error' => 'Generación no encontrada'
                ]);
            }
            $metadataBrief = $generatedBrief->metadata ? json_decode($generatedBrief->metadata, true) : [];
        
            $category = $account->category;
            Log::info("Categoria encontrada", [
                'Categoría' => $category,
            ]);
            $brief = $metadata['brief'];
            $objective = $metadata['objective'];
            $genesis = $metadata['genesis'];
            $fuentesGenesis= $metadata['genesis_insight_fuentes_html'];
            $fuentesEscenario= $metadata['escenario_insight_fuentes_html'];
            $construccionescenario = $metadata['construccionescenario'];
            $genesiscompleto = $genesis . $construccionescenario;
            $Tipodecampaña = $request->input('360_Tipo_de_campaña');

            $metadata['tipo_de_campaña'] = $Tipodecampaña;
            
            $country = $metadataBrief['country'];

            Log::info('Iniciando generación de estrategia', [
                'id_generated' => $id_generated,
                'tipo_campania' => $Tipodecampaña,
                'account_id' => $accountId,
                'country' => $country
            ]);

            // Validar límite de créditos antes de generar
            $this->validateCreditLimit($accountId);

            $estrategia = $this->generarEstrategia($Tipodecampaña, $objective, $genesiscompleto, $country, $brief);
            
            if(!$estrategia['success']){
                Log::error('Error al generar estrategia', [
                    'id_generated' => $id_generated,
                    'error' => $estrategia['error']
                ]);
                return response()->json(['success' => false, 'error' => $estrategia['error']]);
            }
            
            $id_generacion_estrategia = $estrategia['data']['id'] ?? null;
            
            // Log del ID de respuesta
            Log::info('ID de respuesta generarEstrategia', [
                'id_generated' => $id_generated,
                'id_generacion_estrategia' => $id_generacion_estrategia
            ]);
            
            // Log del objeto usage (si existe en la respuesta inicial)
            if(isset($estrategia['data']['usage'])) {
                Log::info('Usage tokens generarEstrategia', [
                    'id_generated' => $id_generated,
                    'id_generacion_estrategia' => $id_generacion_estrategia,
                    'usage' => $estrategia['data']['usage']
                ]);
            }
            
            Log::info('Generación de estrategia iniciada exitosamente', [
                'id_generated' => $id_generated,
                'id_generacion_estrategia' => $id_generacion_estrategia,
                'status' => $estrategia['data']['status'] ?? 'unknown'
            ]);

            $metadata['generacion_estrategia_data'] = $estrategia['data'];
            $metadata['generacion_estrategia_status'] = 'processing';

            $metadata['generacion_creatividad_content'] = $construccioncreatividad;
            
            $metadata['step'] = 7.1;
            $generated->update([
                'name' => 'Generando estrategia...',
                'metadata' => json_encode($metadata)
            ]);

            return response()->json(['success' => true, 'data' => $estrategia['data'], 'function' => 'setGenerarEstrategia', 'id_generated' => $generated->id]);
        }else{
            return response()->json(['success' => false, 'error' => 'faltan datos']);
        }
    } catch (\App\Exceptions\CreditLimitExceededException $e) {
        // Error específico de límite de créditos - mostrar mensaje personalizado
        Log::warning('Límite de créditos excedido en setGenerarEstrategia', [
            'message' => $e->getMessage(),
            'accountId' => $accountId ?? null
        ]);

        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
    } catch(\Exception $e){
        Log::error('Error al generar estrategia', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'error' => 'Ha ocurrido un error al generar la estrategia. Por favor, intenta nuevamente.'
        ]);
    }
}

public function saveconstruccioncreatividadFinalizar(Request $request){
    try{
        // Validar las URLs y archivos
        $validator = Validator::make($request->all(), [
            '360_Tipo_de_campaña' => 'required|string',
            'id_account' => 'required|integer',
            'id_generated' => 'required|integer',
            'construccioncreatividad' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        ini_set('max_execution_time', 300);

        $accountId = $request->input('id_account');
        $account = Account::find($accountId);
        $id_generated = $request->input('id_generated');
        $construccioncreatividad = $request->input('construccioncreatividad');

        $generated = Generated::find($id_generated);
        if (!$generated) {
            return response()->json([
                'success' => false,
                'error' => 'Generación no encontrada'
            ]);
        }
        
        $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

        if ($metadata) {
            $generatedBrief = Generated::find($metadata['id_brief']);
            if (!$generatedBrief) {
                return response()->json([
                    'success' => false,
                    'error' => 'Generación no encontrada'
                ]);
            }
            $metadataBrief = $generatedBrief->metadata ? json_decode($generatedBrief->metadata, true) : [];
        
            $category = $account->category;
            Log::info("Categoria encontrada", [
                'Categoría' => $category,
            ]);
            $brief = $metadata['brief'];
            $objective = $metadata['objective'];
            $genesis = $metadata['genesis'];
            $fuentesGenesis= $metadata['genesis_insight_fuentes_html'];
            $fuentesEscenario= $metadata['escenario_insight_fuentes_html'];
            $construccionescenario = $metadata['construccionescenario'];
            $genesiscompleto = $genesis . $construccionescenario;
            $Tipodecampaña = $request->input('360_Tipo_de_campaña');

            $metadata['tipo_de_campaña'] = $Tipodecampaña;
            
            $country = $metadataBrief['country'];

            Log::info('Iniciando generación de estrategia', [
                'id_generated' => $id_generated,
                'tipo_campania' => $Tipodecampaña,
                'account_id' => $accountId,
                'country' => $country
            ]);

            $estrategia = $this->generarEstrategia($Tipodecampaña, $objective, $genesiscompleto, $country, $brief);
            
            if(!$estrategia['success']){
                Log::error('Error al generar estrategia', [
                    'id_generated' => $id_generated,
                    'error' => $estrategia['error']
                ]);
                return response()->json(['success' => false, 'error' => $estrategia['error']]);
            }
            
            $id_generacion_estrategia = $estrategia['data']['id'] ?? null;
            
            // Log del ID de respuesta
            Log::info('ID de respuesta generarEstrategia', [
                'id_generated' => $id_generated,
                'id_generacion_estrategia' => $id_generacion_estrategia
            ]);
            
            // Log del objeto usage (si existe en la respuesta inicial)
            if(isset($estrategia['data']['usage'])) {
                Log::info('Usage tokens generarEstrategia', [
                    'id_generated' => $id_generated,
                    'id_generacion_estrategia' => $id_generacion_estrategia,
                    'usage' => $estrategia['data']['usage']
                ]);
            }
            
            Log::info('Generación de estrategia iniciada exitosamente', [
                'id_generated' => $id_generated,
                'id_generacion_estrategia' => $id_generacion_estrategia,
                'status' => $estrategia['data']['status'] ?? 'unknown'
            ]);

            $metadata['generacion_estrategia_data'] = $estrategia['data'];
            $metadata['generacion_estrategia_status'] = 'processing';

            $metadata['generacion_creatividad_content'] = $construccioncreatividad;
            
            $metadata['step'] = 7.3;
            $metadata['save_fields'] = ['genesis', 'fuentes_genesis', 'escenario', 'fuentes_escenario', 'creatividad'];
            $metadata['final_step'] = 7.4;
            $metadata['final_function'] = 'construccionCreatividadGuardado7_4';

            $generated->update([
                'name' => 'Generando resultado final...',
                'metadata' => json_encode($metadata)
            ]);

            $content = ['genesis' =>  $genesis,'fuentesGenesis'=>$fuentesGenesis,'escenario'=>$construccionescenario ,'fuentesEscenario'=>$fuentesEscenario, 'creatividad' => $construccioncreatividad];

            return response()->json(['success' => true, 'data' => $content, 'function' => 'saveconstruccioncreatividadFinalizar', 'id_generated' => $generated->id]);
        }else{
            return response()->json(['success' => false, 'error' => 'faltan datos']);
        }
    }catch(\Exception $e){
        return response()->json(['success' => false, 'error' => $e->getMessage()]);
    }
}

public function get_construccion_estrategia($generationId){
    try {
        Log::info("Inicio de get_construccion_estrategia");
        $generated = Generated::find($generationId);
        
        if (!$generated) {
            return response()->json([
                'success' => false,
                'error' => 'Generación no encontrada'
            ], 404);
        }

        $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

        $contentEstrategia = $metadata['generacion_estrategia_content'] ?? '';
        $sourcesEstrategia = $metadata['generacion_estrategia_sources'] ?? [];

        $statusgenerated = 'processing';

        $id_generacion_estrategia = $metadata['generacion_estrategia_data']['id'] ?? null;
        $statusgeneratedEstrategia = $metadata['generacion_estrategia_status'] ?? 'processing';

        if($statusgeneratedEstrategia != 'completed'){
            Log::info("Consultando estado de estrategia", [
                'id_generated' => $generationId,
                'id_generacion_estrategia' => $id_generacion_estrategia
            ]);
            $responseEstrategia = OpenAiService::getModelResponse($id_generacion_estrategia);
            
            // Log del ID de respuesta
            $responseId = $responseEstrategia['data']['id'] ?? null;
            Log::info('ID de respuesta OpenAI (Estrategia)', [
                'id_generated' => $generationId,
                'id_generacion_estrategia' => $id_generacion_estrategia,
                'response_id' => $responseId
            ]);
            
            // Log del objeto usage
            if(isset($responseEstrategia['data']['usage'])) {
                Log::info('Usage tokens OpenAI (Estrategia)', [
                    'id_generated' => $generationId,
                    'id_generacion_estrategia' => $id_generacion_estrategia,
                    'usage' => $responseEstrategia['data']['usage']
                ]);
            }
            
            if(isset($responseEstrategia['success']) && !$responseEstrategia['success']){
                Log::error('Error en respuesta de OpenAI', [
                    'error' => $responseEstrategia['error']
                ]);
            }else{
                if($responseEstrategia['data']['status'] === 'completed'){
                    $statusgeneratedEstrategia = 'completed';
                    $statusgenerated = 'completed';
                    if (isset($responseEstrategia['data']['output'])) {
                        foreach ($responseEstrategia['data']['output'] as $output_item) {
                            if ($output_item['type'] === 'message') {
                                if (isset($output_item['content'][0]['text'])) {
                                    $contentEstrategia = $output_item['content'][0]['text'];
                                }
                                if (isset($output_item['content'][0]['annotations'])) {
                                    foreach($output_item['content'][0]['annotations'] as $annotation){
                                        if($annotation['type'] === 'url_citation'){
                                            $sourcesEstrategia[] = $annotation['url'];
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // Registrar uso de tokens cuando se completa exitosamente
                    if (isset($responseEstrategia['data']['usage'])) {
                        $this->trackUsageIfAvailable($generated->account_id, self::MODEL_ESTRATEGIA, $responseEstrategia['data']['usage'], 'openai', 'get_construccion_estrategia', $generationId);
                    }
                    
                    Log::info('Generación de estrategia completada', [
                        'id_generated' => $generationId,
                        'id_generacion_estrategia' => $id_generacion_estrategia,
                        'content_length' => strlen($contentEstrategia),
                        'sources_count' => count($sourcesEstrategia)
                    ]);
                    $metadata['generacion_estrategia_content'] = $contentEstrategia;
                    $metadata['generacion_estrategia_sources'] = $sourcesEstrategia;
                    $metadata['generacion_estrategia_status'] = 'completed';
                    $generated->update([
                        'metadata' => json_encode($metadata)
                    ]);
                }
            }
        }

        if($statusgenerated === 'completed'){
            Log::info("Generación de estrategia completada");

            $metadata['generacion_estrategia_content'] = $contentEstrategia;
            $metadata['generacion_estrategia_sources'] = $sourcesEstrategia;
            $metadata['generacion_estrategia_status'] = 'completed';

            $generated->update([
                'metadata' => json_encode($metadata)
            ]);

            return response()->json([
                'success' => true,
                'status' => 'completed',
                'generation_id' => $generated->id,
                'data' => $contentEstrategia,
                'sources' => $sourcesEstrategia
            ]);
        }else{
            return response()->json([
                'success' => true,
                'status' => $generated->status,
                'generation_id' => $generated->id
            ]);
        }

    } catch (\Exception $e) {
        Log::error('Error en get_construccion_estrategia', [
            'error' => $e->getMessage()
        ]);
        return response()->json([
            'success' => false,
            'error' => 'Error al consultar estado: ' . $e->getMessage()
        ], 500);
    }
}

public function setGenerarIdeasContenido(Request $request){
    try{
        // Validar las URLs y archivos
        $validator = Validator::make($request->all(), [
            '360_Tipo_de_campaña' => 'required|string',
            'id_account' => 'required|integer',
            'id_generated' => 'required|integer',
            'construccioncreatividad' => 'required|string',
            'construccionestrategia' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        ini_set('max_execution_time', 300);

        $accountId = $request->input('id_account');
        $account = Account::find($accountId);
        $id_generated = $request->input('id_generated');
        $construccioncreatividad = $request->input('construccioncreatividad');
        $construccionestrategia = $request->input('construccionestrategia');

        $generated = Generated::find($id_generated);
        if (!$generated) {
            return response()->json([
                'success' => false,
                'error' => 'Generación no encontrada'
            ]);
        }
        
        $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

        if ($metadata) {
            $generatedBrief = Generated::find($metadata['id_brief']);
            if (!$generatedBrief) {
                return response()->json([
                    'success' => false,
                    'error' => 'Generación no encontrada'
                ]);
            }
            $metadataBrief = $generatedBrief->metadata ? json_decode($generatedBrief->metadata, true) : [];
        
            $category = $account->category;
            Log::info("Categoria encontrada", [
                'Categoría' => $category,
            ]);
            $brief = $metadata['brief'];
            $objective = $metadata['objective'];
            $genesis = $metadata['genesis'];
            $fuentesGenesis= $metadata['genesis_insight_fuentes_html'];
            $fuentesEscenario= $metadata['escenario_insight_fuentes_html'];
            $construccionescenario = $metadata['construccionescenario'];
            $genesiscompleto = $genesis . $construccionescenario;
            $Tipodecampaña = $request->input('360_Tipo_de_campaña');

            $metadata['tipo_de_campaña'] = $Tipodecampaña;
            
            $country = $metadataBrief['country'];

            Log::info('Iniciando generación de ideas de contenido', [
                'id_generated' => $id_generated,
                'tipo_campania' => $Tipodecampaña,
                'account_id' => $accountId
            ]);

            // Validar límite de créditos antes de generar
            $this->validateCreditLimit($accountId);

            $ideasContenido = $this->generarIdeasContenido($Tipodecampaña, $objective, $genesiscompleto, $brief, $construccioncreatividad, $construccionestrategia );
            
            if(!$ideasContenido['success']){
                Log::error('Error al generar ideas de contenido', [
                    'id_generated' => $id_generated,
                    'error' => $ideasContenido['error']
                ]);
                return response()->json(['success' => false, 'error' => $ideasContenido['error']]);
            }
            
            $id_generacion_ideas_contenido = $ideasContenido['data']['id'] ?? null;
            
            // Log del ID de respuesta
            Log::info('ID de respuesta generarIdeasContenido', [
                'id_generated' => $id_generated,
                'id_generacion_ideas_contenido' => $id_generacion_ideas_contenido
            ]);
            
            // Log del objeto usage (si existe en la respuesta inicial)
            if(isset($ideasContenido['data']['usage'])) {
                Log::info('Usage tokens generarIdeasContenido', [
                    'id_generated' => $id_generated,
                    'id_generacion_ideas_contenido' => $id_generacion_ideas_contenido,
                    'usage' => $ideasContenido['data']['usage']
                ]);
            }
            
            Log::info('Generación de ideas de contenido iniciada exitosamente', [
                'id_generated' => $id_generated,
                'id_generacion_ideas_contenido' => $id_generacion_ideas_contenido,
                'status' => $ideasContenido['data']['status'] ?? 'unknown'
            ]);

            $metadata['generacion_ideas_contenido_data'] = $ideasContenido['data'];
            $metadata['generacion_ideas_contenido_status'] = 'processing';

            $metadata['generacion_creatividad_content'] = $construccioncreatividad;
            $metadata['generacion_estrategia_content'] = $construccionestrategia;
            
            $metadata['step'] = 7.2;
            $generated->update([
                'name' => 'Generando ideas de contenido...',
                'metadata' => json_encode($metadata)
            ]);

            return response()->json(['success' => true, 'data' => $ideasContenido['data'], 'function' => 'setGenerarIdeasContenido', 'id_generated' => $generated->id]);

        }else{
            return response()->json(['success' => false, 'error' => 'faltan datos']);
        }
    } catch (\App\Exceptions\CreditLimitExceededException $e) {
        // Error específico de límite de créditos - mostrar mensaje personalizado
        Log::warning('Límite de créditos excedido en setGenerarIdeasContenido', [
            'message' => $e->getMessage(),
            'accountId' => $accountId ?? null
        ]);

        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
    } catch(\Exception $e){
        Log::error('Error al generar ideas de contenido', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'error' => 'Ha ocurrido un error al generar ideas de contenido. Por favor, intenta nuevamente.'
        ]);
    }
}

public function saveconstruccionestrategiaFinalizar(Request $request){
    try{
        // Validar las URLs y archivos
        $validator = Validator::make($request->all(), [
            '360_Tipo_de_campaña' => 'required|string',
            'id_account' => 'required|integer',
            'id_generated' => 'required|integer',
            'construccioncreatividad' => 'required|string',
            'construccionestrategia' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        ini_set('max_execution_time', 300);

        $accountId = $request->input('id_account');
        $account = Account::find($accountId);
        $id_generated = $request->input('id_generated');
        $construccioncreatividad = $request->input('construccioncreatividad');
        $construccionestrategia = $request->input('construccionestrategia');

        $generated = Generated::find($id_generated);
        if (!$generated) {
            return response()->json([
                'success' => false,
                'error' => 'Generación no encontrada'
            ]);
        }
        
        $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

        if ($metadata) {
            $generatedBrief = Generated::find($metadata['id_brief']);
            if (!$generatedBrief) {
                return response()->json([
                    'success' => false,
                    'error' => 'Generación no encontrada'
                ]);
            }
            $metadataBrief = $generatedBrief->metadata ? json_decode($generatedBrief->metadata, true) : [];
        
            $category = $account->category;
            Log::info("Categoria encontrada", [
                'Categoría' => $category,
            ]);
            $brief = $metadata['brief'];
            $objective = $metadata['objective'];
            $genesis = $metadata['genesis'];
            $fuentesGenesis= $metadata['genesis_insight_fuentes_html'];
            $fuentesEscenario= $metadata['escenario_insight_fuentes_html'];
            $construccionescenario = $metadata['construccionescenario'];
            $genesiscompleto = $genesis . $construccionescenario;
            $Tipodecampaña = $request->input('360_Tipo_de_campaña');

            $metadata['tipo_de_campaña'] = $Tipodecampaña;
            
            $country = $metadataBrief['country'];

            Log::info('Iniciando generación de ideas de contenido', [
                'id_generated' => $id_generated,
                'tipo_campania' => $Tipodecampaña,
                'account_id' => $accountId
            ]);

            $ideasContenido = $this->generarIdeasContenido($Tipodecampaña, $objective, $genesiscompleto, $brief, $construccioncreatividad, $construccionestrategia );
            
            if(!$ideasContenido['success']){
                Log::error('Error al generar ideas de contenido', [
                    'id_generated' => $id_generated,
                    'error' => $ideasContenido['error']
                ]);
                return response()->json(['success' => false, 'error' => $ideasContenido['error']]);
            }
            
            $id_generacion_ideas_contenido = $ideasContenido['data']['id'] ?? null;
            
            // Log del ID de respuesta
            Log::info('ID de respuesta generarIdeasContenido', [
                'id_generated' => $id_generated,
                'id_generacion_ideas_contenido' => $id_generacion_ideas_contenido
            ]);
            
            // Log del objeto usage (si existe en la respuesta inicial)
            if(isset($ideasContenido['data']['usage'])) {
                Log::info('Usage tokens generarIdeasContenido', [
                    'id_generated' => $id_generated,
                    'id_generacion_ideas_contenido' => $id_generacion_ideas_contenido,
                    'usage' => $ideasContenido['data']['usage']
                ]);
            }
            
            Log::info('Generación de ideas de contenido iniciada exitosamente', [
                'id_generated' => $id_generated,
                'id_generacion_ideas_contenido' => $id_generacion_ideas_contenido,
                'status' => $ideasContenido['data']['status'] ?? 'unknown'
            ]);

            $metadata['generacion_ideas_contenido_data'] = $ideasContenido['data'];
            $metadata['generacion_ideas_contenido_status'] = 'processing';

            $metadata['generacion_creatividad_content'] = $construccioncreatividad;
            $metadata['generacion_estrategia_content'] = $construccionestrategia;
            
            $metadata['step'] = 7.5;
            $metadata['save_fields'] = ['genesis', 'fuentes_genesis', 'escenario', 'fuentes_escenario', 'creatividad', 'estrategia'];
            $metadata['final_step'] = 7.6;
            $metadata['final_function'] = 'construccionEstrategiaGuardado7_6';

            $generated->update([
                'name' => 'Generando ideas de contenido...',
                'metadata' => json_encode($metadata)
            ]);

            $content = ['genesis' =>  $genesis,'fuentesGenesis'=>$fuentesGenesis,'escenario'=>$construccionescenario ,'fuentesEscenario'=>$fuentesEscenario, 'creatividad' => $construccioncreatividad, 'estrategia' => $construccionestrategia];

            return response()->json(['success' => true, 'data' => $content, 'function' => 'saveconstruccionestrategiaFinalizar', 'id_generated' => $generated->id]);

        }else{
            return response()->json(['success' => false, 'error' => 'faltan datos']);
        }
    }catch(\Exception $e){
        return response()->json(['success' => false, 'error' => $e->getMessage()]);
    }
}

public function get_construccion_ideasContenido($generationId){
    try {
        Log::info("Inicio de get_construccion_ideasContenido");
        $generated = Generated::find($generationId);
        
        if (!$generated) {
            return response()->json([
                'success' => false,
                'error' => 'Generación no encontrada'
            ], 404);
        }

        $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

        $contentIdeasContenido = $metadata['generacion_ideas_contenido_content'] ?? '';
        $sourcesIdeasContenido = $metadata['generacion_ideas_contenido_sources'] ?? [];
        $statusgenerated = 'processing';

        $id_generacion_ideas_contenido = $metadata['generacion_ideas_contenido_data']['id'] ?? null;

        $statusgeneratedIdeasContenido = $metadata['generacion_ideas_contenido_status'] ?? 'processing';

        if($statusgeneratedIdeasContenido != 'completed'){
            Log::info("Consultando estado de ideas de contenido", [
                'id_generated' => $generationId,
                'id_generacion_ideas_contenido' => $id_generacion_ideas_contenido
            ]);
            $responseIdeasContenido = OpenAiService::getModelResponse($id_generacion_ideas_contenido);
            
            // Log del ID de respuesta
            $responseId = $responseIdeasContenido['data']['id'] ?? null;
            Log::info('ID de respuesta OpenAI (Ideas Contenido)', [
                'id_generated' => $generationId,
                'id_generacion_ideas_contenido' => $id_generacion_ideas_contenido,
                'response_id' => $responseId
            ]);
            
            // Log del objeto usage
            if(isset($responseIdeasContenido['data']['usage'])) {
                Log::info('Usage tokens OpenAI (Ideas Contenido)', [
                    'id_generated' => $generationId,
                    'id_generacion_ideas_contenido' => $id_generacion_ideas_contenido,
                    'usage' => $responseIdeasContenido['data']['usage']
                ]);
            }
            
            if(isset($responseIdeasContenido['success']) && !$responseIdeasContenido['success']){
                Log::error('Error en respuesta de OpenAI', [
                    'error' => $responseIdeasContenido['error']
                ]);
            }else{
                if($responseIdeasContenido['data']['status'] === 'completed'){
                    $statusgeneratedIdeasContenido = 'completed';
                    $statusgenerated = 'completed';
                    if (isset($responseIdeasContenido['data']['output'])) {
                        foreach ($responseIdeasContenido['data']['output'] as $output_item) {
                            if ($output_item['type'] === 'message') {
                                if (isset($output_item['content'][0]['text'])) {
                                    $contentIdeasContenido = $output_item['content'][0]['text'];
                                }
                                if (isset($output_item['content'][0]['annotations'])) {
                                    foreach($output_item['content'][0]['annotations'] as $annotation){
                                        if($annotation['type'] === 'url_citation'){
                                            $sourcesIdeasContenido[] = $annotation['url'];
                                        }
                                    }
                                }
                            }
                        }
                        
                    }
                    
                    // Registrar uso de tokens cuando se completa exitosamente
                    if (isset($responseIdeasContenido['data']['usage'])) {
                        $this->trackUsageIfAvailable($generated->account_id, self::MODEL_IDEAS_CONTENIDO, $responseIdeasContenido['data']['usage'], 'openai', 'get_construccion_ideasContenido', $generationId);
                    }
                    
                    Log::info('Generación de ideas de contenido completada', [
                        'id_generated' => $generationId,
                        'id_generacion_ideas_contenido' => $id_generacion_ideas_contenido,
                        'content_length' => strlen($contentIdeasContenido),
                        'sources_count' => count($sourcesIdeasContenido)
                    ]);
                    $metadata['generacion_ideas_contenido_content'] = $contentIdeasContenido;
                    $metadata['generacion_ideas_contenido_sources'] = $sourcesIdeasContenido;
                    $metadata['generacion_ideas_contenido_status'] = 'completed';
                    $generated->update([
                        'metadata' => json_encode($metadata)
                    ]);
                }
            }
        }
        
        if($statusgenerated === 'completed'){
            Log::info("Generación de creatividad, estrategia e ideas de contenido completadas");

            $metadata['generacion_ideas_contenido_content'] = $contentIdeasContenido;
            $metadata['generacion_ideas_contenido_sources'] = $sourcesIdeasContenido;
            $metadata['generacion_ideas_contenido_status'] = 'completed';

            $generated->update([
                'metadata' => json_encode($metadata)
            ]);

            return response()->json([
                'success' => true,
                'status' => 'completed',
                'generation_id' => $generated->id,
                'data' => $contentIdeasContenido,
                'sources' => $sourcesIdeasContenido
            ]);
        }else{
            return response()->json([
                'success' => true,
                'status' => $generated->status,
                'generation_id' => $generated->id
            ]);
        }

    } catch (\Exception $e) {
        Log::error('Error en generarIdeasContenido', [
            'error' => $e->getMessage()
        ]);
        return response()->json([
            'success' => false,
            'error' => 'Error al consultar estado: ' . $e->getMessage()
        ], 500);
    }
}

public function saveGenerarIdeasContenido(Request $request){
    try{
        // Validar las URLs y archivos
        $validator = Validator::make($request->all(), [
            '360_Tipo_de_campaña' => 'required|string',
            'id_account' => 'required|integer',
            'id_generated' => 'required|integer',
            'construccioncreatividad' => 'required|string',
            'construccionestrategia' => 'required|string',
            'construccionideascontenido' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        ini_set('max_execution_time', 300);

        $accountId = $request->input('id_account');
        $account = Account::find($accountId);
        $id_generated = $request->input('id_generated');
        $construccioncreatividad = $request->input('construccioncreatividad');
        $construccionestrategia = $request->input('construccionestrategia');
        $construccionideascontenido = $request->input('construccionideascontenido');

        $generated = Generated::find($id_generated);
        if (!$generated) {
            return response()->json([
                'success' => false,
                'error' => 'Generación no encontrada'
            ]);
        }
        
        $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

        if ($metadata) {
            $generatedBrief = Generated::find($metadata['id_brief']);
            if (!$generatedBrief) {
                return response()->json([
                    'success' => false,
                    'error' => 'Generación no encontrada'
                ]);
            }
            $metadataBrief = $generatedBrief->metadata ? json_decode($generatedBrief->metadata, true) : [];
        
            $category = $account->category;
            Log::info("Categoria encontrada", [
                'Categoría' => $category,
            ]);
            $brief = $metadata['brief'];
            $objective = $metadata['objective'];
            $genesis = $metadata['genesis'];
            $fuentesGenesis= $metadata['genesis_insight_fuentes_html'];
            $fuentesEscenario= $metadata['escenario_insight_fuentes_html'];
            $construccionescenario = $metadata['construccionescenario'];
            $genesiscompleto = $genesis . $construccionescenario;
            $Tipodecampaña = $request->input('360_Tipo_de_campaña');

            $metadata['tipo_de_campaña'] = $Tipodecampaña;

            $metadata['generacion_creatividad_content'] = $construccioncreatividad;
            $metadata['generacion_estrategia_content'] = $construccionestrategia;
            $metadata['generacion_ideas_contenido_content'] = $construccionideascontenido;
            
            $metadata['step'] = 8;
            $generated->update([
                'name' => 'Generando resultado final...',
                'metadata' => json_encode($metadata)
            ]);

            $content = ['genesis' =>  $genesis,'fuentesGenesis'=>$fuentesGenesis,'escenario'=>$construccionescenario ,'fuentesEscenario'=>$fuentesEscenario, 'estrategia' => $construccionestrategia, 'creatividad' => $construccioncreatividad, 'contenido' => $construccionideascontenido];

            return response()->json(['success' => true, 'data' => $content, 'function' => 'mostrarResultadoFinal', 'id_generated' => $generated->id]);

        }else{
            return response()->json(['success' => false, 'error' => 'faltan datos']);
        }
    }catch(\Exception $e){
        return response()->json(['success' => false, 'error' => $e->getMessage()]);
    }
}

public function generateNewCreatividadEstrategiaInnovacion(Request $request){
    // Validar las URLs y archivos
    $validator = Validator::make($request->all(), [
        'account' => 'required|integer',
        'type' => 'required|string',
        'creatividad' => 'required|string',
        'estrategia' => 'required|string',
        'id_generated' => 'required|integer',
    ]); 

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()]);
    }

    ini_set('max_execution_time', 600);

    $accountId = $request->input('account');
    
    try {
        // Validar límite de créditos antes de generar
        $this->validateCreditLimit($accountId);
        
        $account = Account::find($accountId);
        $category = $account->category;

        $type = $request->input('type');
        $creatividad = $request->input('creatividad');
        $estrategia = $request->input('estrategia');
        $accountData = Field::where('account_id', $accountId)->pluck('value', 'key');
        $id_generated = $request->input('id_generated');
        $generated = Generated::find($id_generated);
        if (!$generated) {
            return response()->json([
                'success' => false,
                'error' => 'Generación no encontrada'
            ]);
        }
        $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

        if ($accountData) {
            // Buscar el registro existente
            $brief = $metadata['brief'];
            $objective = $metadata['objective'];
            $genesis = $metadata['genesis'];
            $construccionescenario = $metadata['construccionescenario'];
            $Tipodecampaña = $metadata['tipo_de_campaña'];
            $country = $accountData->get('country');

            switch ($type) {
                case 'Creatividad':
                    $response = $this->generarCreatividad($Tipodecampaña, $objective, $construccionescenario, $brief, $category);
                    break;
                case 'Estrategia':
                    $response = $this->generarEstrategia($Tipodecampaña, $objective, $construccionescenario, $country, $brief);
                    break;
                case 'Contenido':
                    $response = $this->generarIdeasContenido($Tipodecampaña, $objective, $construccionescenario, $brief, $creatividad, $estrategia );
                    break;
                // case 'Innovacion':
                //     $response = $this->generarInnovacion($Tipodecampaña, $objective, $construccionescenario, $brief);
                //     break;
                default:
                $response = null;
                    break;
                }

            return response()->json($response);
        }else{
            return response()->json(['error' => 'faltan datos']);
        }
    } catch (\App\Exceptions\CreditLimitExceededException $e) {
        // Error específico de límite de créditos - mostrar mensaje personalizado
        Log::warning('Límite de créditos excedido en generateNewCreatividadEstrategiaInnovacion', [
            'message' => $e->getMessage(),
            'accountId' => $accountId
        ]);

        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ]);
    } catch (\Exception $e) {
        // Otros errores - mostrar mensaje genérico
        Log::error('Error en generateNewCreatividadEstrategiaInnovacion', [
            'message' => $e->getMessage(),
            'accountId' => $accountId,
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'error' => 'Ha ocurrido un error al generar. Por favor, intenta nuevamente.',
        ]);
    }

}
public function generarCreatividad($Tipodecampaña, $objective, $genesiscompleto, $brief, $category)
{
    try {
        ini_set('max_execution_time', 600);
        Log::info("Inicio de generarCreatividad", [
            'Tipo de campaña' => $Tipodecampaña,
            'Objetivo' => $objective,
            'Categoría recibida' => $category
        ]);

        // Mapeo categoría -> vector_store_ids
        // $categoryVectorStores = [
        //     'Alimentación y Bebidas'                   => 'vs_67e2ae4650588191b31d8b8224d0ac47',
        //     'Moda y Belleza'                           => 'vs_67e2c08d6dbc81918e82f768e2e40ca9',
        //     'Salud y Bienestar'                        => 'vs_67e2c1889d80819189d3e9f982470163',
        //     'Tecnología y Electrónica'                 => 'vs_67e2c3211aec8191845de6144c927a39',
        //     'Educación y Formación'                    => 'vs_67e2c39720e48191b46f2d0a02138ba1',
        //     'Turismo y Entretenimiento'                => 'vs_67e2c5798354819186b610df3b1488d1',
        //     'Automotriz y Transporte'                  => 'vs_67e2c62a81bc8191b666df23826ce005',
        //     'Bienes Raíces y Construcción'             => 'vs_67e2c6e31c708191b345afc4c53aa916',
        //     'Servicios Profesionales'                  => 'vs_67e2c7fc2c6c819188bef5fd84dad404',
        //     'Deportes y Fitness'                       => 'vs_67e2c9854cd08191b5afe3b76fe4a36c',
        //     'Salud y Medicina'                         => 'vs_67e2ca0cb83881918d49eed54fdbfdc2',
        //     'E-commerce y Tiendas Online'              => 'vs_67e2cb4b90d08191a7b757db58fcbd0b',
        //     'Bienestar y Estilo de Vida'               => 'vs_67e2cc0850ec8191abea747444f70980',
        //     'Hogar y Decoración'                       => 'vs_67e2cdc951e08191a8f4f5a74789a235',
        //     'Servicios Financieros'                    => 'vs_67e2cf37e2088191ba8654ad414bce1a',
        //     'Energía y Sostenibilidad'                 => 'vs_67e2d462492c8191b316476dd2aec789',
        //     'Agronegocios y Agroindustria'             => 'vs_67e2d55450d881919f61b5ee38eb1075',
        //     'Medios, Comunicación y Contenido Digital' => 'vs_67e2d75ad19c81918ad687bf856b68b0',
        //     'Logística y Cadena de Suministro'         => 'vs_WIikAxBR2wfrELhu6On7ALVt',
        //     'Emprendimiento e Innovación'              => 'vs_67e2d8fa265c8191a14d802f759ae7e0',
        //     'Arte, Cultura y Creatividad'              => 'vs_67e2da50d71c8191a17c2df1d768296c',
        //     'Negocios B2B y Servicios Industriales'    => 'vs_67e2dcfc62ac8191b5048aa381ec4336',
        //     'Gaming y eSports'                         => 'vs_67e2ddf1a2cc81919b720e353d43c2dd',
        //     'Otra'                                  => 'vs_WIikAxBR2wfrELhu6On7ALVt',
        // ];
        $categoryVectorStores = [
            'Alimentación y Bebidas'                   => 'vs_69cb3d60175c8191b45280336e0c747b',
            'Moda y Belleza'                           => 'vs_69cb3d1816ac81918bc3b6c4919619b9',
            'Salud y Bienestar'                        => 'vs_69cc54c846588191b89b6b541ea4e627',
            'Tecnología y Electrónica'                 => 'vs_69cb36e321c881919088b2fd363d4a6e',
            'Educación y Formación'                    => 'vs_69cc54aebf948191a97577cb962e7e77',
            'Turismo y Entretenimiento'                => 'vs_69cb35e6801081919b7b2adb8c365115',
            'Automotriz y Transporte'                  => 'vs_69cb3cf6d3948191939d681fd0c409b6',
            'Bienes Raíces y Construcción'             => 'vs_69cb366da6648191a4042387d2861b8f',
            'Servicios Profesionales'                  => 'vs_69cc537015048191ad2fa5c31dd0f43c',
            'Deportes y Fitness'                       => 'vs_69cc534c3a548191932b0596301397a9',
            'Salud y Medicina'                         => 'vs_69cb43b91ad48191b9fdfcb09ee14453',
            'E-commerce y Tiendas Online'              => 'vs_69cc548f7d3c8191aad6f7cdf183b023',
            'Bienestar y Estilo de Vida'               => 'vs_69cb396f24208191baba0ba86f0a9b58',
            'Hogar y Decoración'                       => 'vs_69cc51a7c2d881918eb2a23cf165cff7',
            'Servicios Financieros'                    => 'vs_69cb3b3ea3d8819199d25e3fbe4d4cb4',
            'Energía y Sostenibilidad'                 => 'vs_69cda81c3e508191866430d9475f6152',
            'Agronegocios y Agroindustria'             => 'vs_69cda839458c8191bc4e76b69927c377',
            'Medios, Comunicación y Contenido Digital' => 'vs_69cb3eaa88808191824afabbd0e859da',
            'Logística y Cadena de Suministro'         => 'vs_69cda84e931c8191b1d14d97762326d0',
            'Emprendimiento e Innovación'              => 'vs_69cda878349c81919a10f21551ba2976',
            'Arte, Cultura y Creatividad'              => 'vs_69cda8bff8e08191ae766c8e1a17f2b9',
            'Negocios B2B y Servicios Industriales'    => 'vs_69cb3b0c98d0819185677cb353ad4c06',
            'Gaming y eSports'                         => 'vs_69cc532a65088191ac6b57431e55025f',
            'Otra'                                     => 'vs_69cb42cda0b08191b54417701027fcd6',
        ];

        // ID especial para "Otra" o null
        $vectorIdOtra = 'vs_69cb42cda0b08191b54417701027fcd6';

        // Resolver el vector store según categoría
        if ($category === null || strtolower($category) === 'otra') {
            $vectorIds = [$vectorIdOtra];
        } else {
            $vectorEntry = $categoryVectorStores[$category] ?? $categoryVectorStores['default'];
            $vectorIds = is_array($vectorEntry) ? $vectorEntry : [$vectorEntry];
        }

        // Opciones para el chat-prompt (el modelo se define en el ChatPrompt del dashboard de OpenAI)
        $options = [
            'prompt' => [
                'id' => 'pmpt_68c9cde4f2ac8196b3a33b12c74e47790435d1a45d459271',
                
                'variables' => [
                    'tipo_campania' => $Tipodecampaña,
                    'objetivo' => $objective,
                    'genesis' => $genesiscompleto,
                    'brief' => $brief
                ]
            ],
            'tools' => [
                [
                    'type' => 'file_search',
                    'vector_store_ids' => $vectorIds
                ]
            ],
            'background' => true,
        ];

        Log::info('Llamando OpenAiService::createModelResponse (Creatividad)', [
            'category' => $category,
            'vector_store_ids' => json_encode($vectorIds)
        ]);

        $response = OpenAiService::createModelResponse($options);

        if (isset($response['error'])) {
            Log::error('Error en generarCreatividad', [
                'error' => $response['error']
            ]);
            return ['success' => false, 'error' => $response['error']];
        }

        // Extraer la respuesta final del asistente
        // $textoFinal = '';
        // if (isset($response['data']['output']) && is_array($response['data']['output'])) {
        //     foreach ($response['data']['output'] as $block) {
        //         if (($block['type'] === 'message' || $block['type'] === 'assistant')
        //             && isset($block['content'][0]['text'])) {
        //             $textoFinal = $block['content'][0]['text'];
        //             break;
        //         }
        //     }
        // }

        Log::info('Respuesta OpenAiService::createModelResponse (Creatividad)');

        return ['success' => true, 'data' => $response['data']];

    } catch (\Exception $e) {
        Log::error('Excepción en generarCreatividad', [
            'exception' => $e->getMessage()
        ]);
        return ['success' => false, 'error' => 'Error al procesar la solicitud de IA.'];
    }
}
public function generarCreatividadold($Tipodecampaña, $objective, $genesiscompleto, $brief, $category)
{
    Log::info("Inicio de generarCreatividad", [
        'Tipo de campaña' => $Tipodecampaña,
        'Objetivo' => $objective,
        'Categoría recibida' => $category
    ]);
    
    $assistantMap = [
        'Alimentación y Bebidas'                   => 'asst_qQGOx0GuidFzGfaSeT9ZnrmC',
        'Moda y Belleza'                           => 'asst_pF5G6eNmnScR0pnZsLl3g9XA',
        'Salud y Bienestar'                        => 'asst_Nax5p5MhFKfIzNAy3HOFgH6f',
        'Tecnología y Electrónica'                 => 'asst_LLzFPTxDBz4WrVVE9O1qIY7V',
        'Educación y Formación'                    => 'asst_5jg3JiYcB9oaOcq1FsXAHOSv',
        'Turismo y Entretenimiento'                => 'asst_2PvGR0EmUTvdR6d8mKB0o1m4',
        'Automotriz y Transporte'                  => 'asst_BaWjivMuhDDM9H5n7ajc9UW3',
        'Bienes Raíces y Construcción'             => 'asst_3NVMRs0oWhCy65l8sKALsevx',
        'Servicios Profesionales'                   => 'asst_sY5pTeWYm6He7tOueGLS4z6o',
        'Deportes y Fitness'                       => 'asst_yOtkV1l3aT9733f1U78Ykj3U',
        'Salud y Medicina'                         => 'asst_d8Qec5aXcbQAOvhiggZSKmJd',
        'E-commerce y Tiendas Online'              => 'asst_uaemeiyxaFYdI1p7WwLnOTpX',
        'Bienestar y Estilo de Vida'               => 'asst_amBE1b5YZSFYCbjAPDXnPBhi',
        'Hogar y Decoración'                       => 'asst_J1YuscRVgZvRuFFg4rIBty3d',
        'Servicios Financieros'                    => 'asst_xy3YrJIuGT8GOh8dGkTZUhYx',
        'Energía y Sostenibilidad'                 => 'asst_vc7ljx4ap3e1cQBmjc9ROti9',
        'Agronegocios y Agroindustria'             => 'asst_VISu1lJA4L7gHCOphX0VXxLa',
        'Medios, Comunicación y Contenido Digital' => 'asst_kqdYi89wvkTKjAk21mEjrFmJ',
        'Logística y Cadena de Suministro'         => 'asst_TOQ8ggRyiODmmZpfSy9L0WvD',
        'Emprendimiento e Innovación'              => 'asst_pJOnxGt9oCMEHZVynWwrqHFG',
        'Arte, Cultura y Creatividad'              => 'asst_FW8JGyhxYi7H2ICAZZejJ3EX',
        'Negocios B2B y Servicios Industriales'    => 'asst_RqZhkeC0xGoCCYSKGJd4kHZr',
        'Gaming y eSports'                         => 'asst_KkeCs6HRWNYyiqWMa4JgSSmo',
    ];
    

    
    if (array_key_exists($category, $assistantMap)) {
        $assistant_idCreatividad = $assistantMap[$category];
        Log::info("Asistente encontrado para la categoría", [
            'Categoría' => $category,
            'Asistente ID' => $assistant_idCreatividad
        ]);
    } else {
         
        // throw new \Exception("No se encontró un asistente para la categoría: $category");
        
        $assistant_idCreatividad = "asst_dBtNQk8BArQyo9GNndD19lEQ";
        Log::warning("Categoría no encontrada en el mapa, asignando asistente por defecto", [
            'Categoría' => $category,
            'Asistente ID' => $assistant_idCreatividad
        ]);
    }

    // prompt

    $promptCreatividad = <<<EOT
Genera las mejores propuestas creativas para una campaña de: $Tipodecampaña. Las propuestas deben considerar este objetivo cuantificable: $objective, el concepto creativo a considerar es: $genesiscompleto. Los datos de marca a considerar para hacer las mejores propuestas son: $brief
EOT;
   Log::info("Asistente enviado id:", [

            'Asistente ID' => $assistant_idCreatividad
        ]);
    return OpenAiService::CompletionsAssistants($promptCreatividad, $assistant_idCreatividad);
}

public function generarEstrategia($Tipodecampaña, $objective, $genesiscompleto, $country, $brief)
{
    try {
        ini_set('max_execution_time', 600);
        // Mapeo de países a vector_store_ids
        // $vectorStores = [
        //     'Bolivia' => 'vs_67d056778cb08191a576ad35aa08e40f',
        //     'Argentina' => 'vs_67d05805f67c8191b72413bcc4ed007f',
        //     'Chile' => 'vs_67d058836eec8191ae9e68ccbdfb97b9',
        //     'Colombia' => 'vs_67d075c66ea88191bdefe97058130ffc',
        //     'Costa Rica' => 'vs_67d077079f388191a5c1b50154ac0b8b',
        //     'Ecuador' => 'vs_67d078d06b5c81919076005ba58cc6b6',
        //     'Guatemala' => 'vs_67d07d4e6b008191965bcd5b67f17223',
        //     'Honduras' => 'vs_67d07f90406c8191826a9d2b09ed2e73',
        //     'México' => 'vs_67d082c70f3c8191a1cd533b271331ed',
        //     'Nicaragua' => 'vs_67d084b3b2b08191a771895ffee3ed04',
        //     'Panamá' => 'vs_67d087007d7c81918476b7a20116bae4',
        //     'Paraguay' => 'vs_67d088e01b6c8191bf51e013a9994464',
        //     'Perú' => 'vs_67d08bf93ac88191b92a65da3128f9bf',
        //     'Puerto Rico' => 'vs_67d08def19a4819191d8eb6e66a5dfe3',
        //     'Uruguay' => 'vs_67d08f82493081919f0f18ec30e0aed7',
        //     'El Salvador' => 'vs_67d09143d81881919811ffe0e936dabd',
        //     'Brasil' => 'vs_67d093034f3c8191a22bd3436dd75ed0',
        //     'República Dominicana' => 'vs_67d095d76cc8819190c0288b47ee0d6f',
        // ];
        $vectorStores = [
            'Bolivia'              => 'vs_69cc6a6eb9b881919141a8672316f1d2',
            'Argentina'            => 'vs_69cc6a7e1de48191976a0bcd57a1974a',
            'Chile'                => 'vs_69cc6a8d0f8c8191b0c75bf72110cb47',
            'Colombia'             => 'vs_69cc6a9b1c6c8191a7eaa699e7bf908a',
            'Costa Rica'           => 'vs_69cc6aa960b081918b4400b470bd9cb9',
            'Ecuador'              => 'vs_69cc6ab7f8c08191825ff1f6b2883292',
            'Guatemala'            => 'vs_69cc6ac611688191b38a5f0dae1626e0',
            'Honduras'             => 'vs_69cc6ad428548191a6f26032c3827f83',
            'México'               => 'vs_69cc6ae247488191b052c2f19428e4f5',
            'Nicaragua'            => 'vs_69cc6af039ec81918e5b050f7582146e',
            'Panamá'               => 'vs_69cc6afdf4ac819191dbc172c3b6ceaa',
            'Paraguay'             => 'vs_69cc6b0c43788191be1039816a0dee51',
            'Perú'                 => 'vs_69cc6b1a4fe081918e4d733bfd766ad3',
            'Puerto Rico'          => 'vs_69cc6b28582881918e9779a0656ddd4a',
            'Uruguay'              => 'vs_69cc6b394644819180ab1191dce50378',
            'El Salvador'          => 'vs_69cc6b67c8c081919c908dc94af10c5e',
            'Brasil'               => 'vs_69cc6b75b9cc819181c06dc59de31ac2',
            'República Dominicana' => 'vs_69cc6b83d224819188071e7d36edeac2',
        ];

        // Buscar el vector store para el país
        $vectorId = $vectorStores[$country] ?? null;

        if (!$vectorId) {
            return ['success' => false, 'error' => "No se encontró vector_store para el país: $country"];
        }

        // Opciones para la API (el modelo se define en el ChatPrompt del dashboard de OpenAI)
        $options = [
            'prompt' => [
                'id' => 'pmpt_68c3468147e48193ab09564bc856756905b09529b9ba957c',
                'variables' => [
                    'tipo_campania' => $Tipodecampaña,
                    'pais' => $country,
                    'objetivo' => $objective,
                    'brief' => $brief,
                    'genesis' => $genesiscompleto
                ]
            ],
            'tools' => [
                [
                    'type' => 'file_search',
                    'vector_store_ids' => [$vectorId]
                ]
            ],
            'background' => true,
        ];

        $response = OpenAiService::createModelResponse($options);

        if (isset($response['error'])) {
            Log::error('Error en generarEstrategia', [
                'error' => $response['error']
            ]);
            return ['success' => false, 'error' => $response['error']];
        }

        // Extraer la respuesta final del asistente
        // $textoFinal = '';
        // if (isset($response['data']['output']) && is_array($response['data']['output'])) {
        //     foreach ($response['data']['output'] as $block) {
        //         if ($block['type'] === 'message' && isset($block['content'][0]['text'])) {
        //             $textoFinal = $block['content'][0]['text'];
        //             break; // Tomamos solo el primer mensaje
        //         }
        //     }
        // }

        // Log::info('Respuesta OpenAiService::createModelResponse (Estrategia Digital)', [
        //     'response' => $textoFinal
        // ]);
        Log::info('Data enviada', [
            'response' => $vectorId
        ]);

        return ['success' => true, 'data' => $response['data']];

    } catch (\Exception $e) {
        Log::error('Excepción en generarEstrategia', [
            'exception' => $e->getMessage()
        ]);
        return ['success' => false, 'error' => 'Error al procesar la solicitud de IA.'];
    }
}


public function generarEstrategiaold($Tipodecampaña, $objective, $genesiscompleto, $country, $brief){
    switch ($country) {
        case 'Argentina':
            $assistant_id = 'asst_BvDMPlQNMPiWKGSHi8YgTiyS';
            break;
    
        case 'Bolivia':
            $assistant_id = 'asst_uADcK3IQ89fH2VuaJNzQ1CXD';
            break;
    
        case 'Brasil':
            $assistant_id = 'asst_kt9evA75QLZVeNIrxfdGhN2A';
            break;
    
        case 'Chile':
            $assistant_id = 'asst_DHgTmpCk3d9xfMWDSub6xcSl';
            break;
    
        case 'Colombia':
            $assistant_id = 'asst_tBowMNjPYoLyr9xDvNeu7Saf';
            break;
    
        case 'Costa Rica':
            $assistant_id = 'asst_3lCVysGe7Lc7GeYq1RKIItaU';
            break;
    
        case 'Ecuador':
            $assistant_id = 'asst_iTuGNv1NhNFKYDFdDQmTyqwc';
            break;
    
        case 'El Salvador':
            $assistant_id = 'asst_pO8CsVUw34ru3jrcWF7AdFhy';
            break;
    
        case 'Guatemala':
            $assistant_id = 'asst_qB9F4cwQgBLgQoyCaffeRc1R';
            break;
    
        case 'Honduras':
            $assistant_id = 'asst_VbS87oW41ZCkGTtxv1u8aqkV';
            break;
    
        case 'México':
            $assistant_id = 'asst_1jk0I6nOdAJfHeULJbmWDi4Z';
            break;
    
        case 'Nicaragua':
            $assistant_id = 'asst_qJu94YdEZPFHj3SJnMQj3Ku3';
            break;
    
        case 'Panamá':
            $assistant_id = 'asst_aTXuxujeSDZaE2T5R8hsvlbK';
            break;
    
        case 'Paraguay':
            $assistant_id = 'asst_VQZdR1KDvrW2gGmYCdvXPyqa';
            break;
    
        case 'Perú':
            $assistant_id = 'asst_X9MGseWiwRCkqusXNWen5Fot';
            break;
    
        case 'Puerto Rico':
            $assistant_id = 'asst_HY45bfqa1ulBCYcqLr2eLPUb';
            break;
    
        case 'República Dominicana':
            $assistant_id = 'asst_4g3FitApu6LaJfFYf7bRENjA';
            break;
    
        case 'Uruguay':
            $assistant_id = 'asst_vM1lPc4l7ywEHiB5P7RmnqfA';
            break;
    
        default:
            $assistant_id = 'asst_tBowMNjPYoLyr9xDvNeu7Saf'; // Por defecto el de Colombia
            break;
    }
    

    $prompt = <<<EOT
Genera la mejor estrategia digital para una campaña de: 
$Tipodecampaña 
Para el país: 
$country 
La estrategia debe considerar este objetivo cuantificable: 
$objective, 
Los datos del brief son: 
$brief
El concepto creativo a considerar es: 
$genesiscompleto

EOT;
   Log::info("Asistente encontrado para la estrategia", [
            'País del asistente' => $country,
            'Asistente ID' => $assistant_id
        ]);

    return OpenAiService::CompletionsAssistants($prompt, $assistant_id);
}
public function generarIdeasContenido($Tipodecampaña, $objective, $genesiscompleto, $brief, $creatividad, $estrategia)
{
    try {
        ini_set('max_execution_time', 600);
        Log::info('Iniciando contenido ideas con');
        // Opciones para el chat-prompt (el modelo se define en el ChatPrompt del dashboard de OpenAI)
        $options = [
            'prompt' => [
                'id' => 'pmpt_68cad29e51848196846bfe853574f0590b7a0c62264f6ee6',
               
                'variables' => [
                    'brief' => $brief,
                    'objetivo' => $objective,
                    'genesis' => $genesiscompleto,
                    'tipo_campania' => $Tipodecampaña,
                    'estrategia' => $estrategia,
                    'creatividad' => $creatividad
                ]
            ],
            'tools' => [
                [
                    'type' => 'file_search',
                    'vector_store_ids' => ['vs_69cb42cda0b08191b54417701027fcd6']
                ]
            ],
            'background' => true,
        ];

        $response = OpenAiService::createModelResponse($options);

        if (isset($response['error'])) {
            Log::error('Error en generarIdeasContenido', [
                'error' => $response['error']
            ]);
            return ['success' => false, 'error' => $response['error']];
        }

        // Extraer el texto de salida
        // $textoFinal = '';
        // if (isset($response['data']['output']) && is_array($response['data']['output'])) {
        //     foreach ($response['data']['output'] as $block) {
        //         if (
        //             ($block['type'] === 'message' || $block['type'] === 'assistant')
        //             && isset($block['content'][0]['text'])
        //         ) {
        //             $textoFinal = $block['content'][0]['text'];
        //             break;
        //         }
        //     }
        // }

        // Log::info('Respuesta OpenAiService::createModelResponse (IdeasContenido)', [
        //     'response_preview' => $response['data']
        // ]);

        return ['success' => true, 'data' => $response['data']];

    } catch (\Exception $e) {
        Log::error('Excepción en generarIdeasContenido', [
            'exception' => $e->getMessage()
        ]);
        return ['success' => false, 'error' => 'Error al procesar la solicitud de IA.'];
    }
}
public function generarIdeasContenidoold($Tipodecampaña, $objective, $genesiscompleto, $brief, $creatividad, $estrategia ){

    $promptIdeasContenido = <<<EOT
    Genera las mejores 50 Ideas de contenido respetando el brief, la estrategia digital, las plataformas seleccionadas y el concepto creativo.
    
    Brief: 
    $brief
    
    Las propuestas deben considerar este objetivo cuantificable: 
    $objective
    
    Bajo este concepto creativo y lineamientos: 
    $genesiscompleto 
    
    Para una campaña de: 
    $Tipodecampaña
    
    La bajada de estrategia es:
    $estrategia
    
    La bajada de creatividad es:
    $creatividad
    
    
    EOT;
    

    $assistant_idIdeasContenido = "asst_tQcA7RfVfjt1wnL1uCpjBz1C";

    return OpenAiService::CompletionsAssistants($promptIdeasContenido, $assistant_idIdeasContenido);
}

public function generarInnovacion($Tipodecampaña, $objective, $construccionescenario, $brief){
    $promptInnovacion = <<<EOT
Genera las mejores propuestas innovadoras tecnológicas para una campaña de: $Tipodecampaña. Las propuestas deben considerar este objetivo cuantificable: $objective, el concepto creativo a considerar es: $construccionescenario. Los datos de marca a considerar para hacer las mejores propuestas son: $brief

EOT;

    $assistant_idInnovacion = "asst_uBOwtwTwTL5fLE6smY36FwAP";

    return OpenAiService::CompletionsAssistants($promptInnovacion, $assistant_idInnovacion);
}

public function saveEstrategiaCreatividadInnovacion(Request $request){
    try{
        // Validar las URLs y archivos
        $validator = Validator::make($request->all(), [
            'id_account' => 'required|integer',
            'file_name' => 'required|string',
            'rating' => 'required|integer',
            'id_generated' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        // ini_set('max_execution_time', 300);

        $accountId = $request->input('id_account');
        // $accountData = Field::where('account_id', $accountId)->pluck('value', 'key');

        $id_generated = $request->input('id_generated');
        $generated = Generated::find($id_generated);
        if (!$generated) {
            return response()->json([
                'success' => false,
                'error' => 'Generación no encontrada'
            ]);
        }
        $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];

        if ($metadata) {
            // Obtener array de campos a guardar (opcional)
            // Si no se envía o es null, guarda todo (comportamiento por defecto)
            $saveFields = $metadata['save_fields'] ?? null;
            
            // Mapeo de nombres amigables a campos del metadata
            $fieldMapping = [
                'genesis' => 'genesis',
                'fuentes_genesis' => 'genesis_insight_fuentes_html',
                'escenario' => 'construccionescenario',
                'fuentes_escenario' => 'escenario_insight_fuentes_html',
                'creatividad' => 'generacion_creatividad_content',
                'estrategia' => 'generacion_estrategia_content',
                'contenido' => 'generacion_ideas_contenido_content',
            ];
            
            // Orden de los campos (para mantener consistencia)
            $fieldOrder = ['genesis', 'fuentes_genesis', 'escenario', 'fuentes_escenario', 'creatividad', 'estrategia', 'contenido'];
            
            // Construir el resultado con los campos especificados
            $parts = [];
            
            // Si save_fields no viene o es null, usar todos los campos disponibles
            $fieldsToSave = ($saveFields === null || empty($saveFields)) 
                ? $fieldOrder 
                : (is_array($saveFields) ? $saveFields : [$saveFields]);
            
            // Iterar sobre los campos en el orden especificado
            foreach ($fieldOrder as $fieldName) {
                // Solo incluir si está en la lista de campos a guardar
                if (in_array($fieldName, $fieldsToSave)) {
                    $metadataKey = $fieldMapping[$fieldName];
                    
                    // Verificar que el campo existe en metadata y no está vacío
                    if (isset($metadata[$metadataKey]) && !empty($metadata[$metadataKey])) {
                        $parts[] = $metadata[$metadataKey];
                    }
                }
            }
            
            // Unir todas las partes disponibles con separadores
            $resultGenesis = implode('<br><br>', $parts);

            $metadata['step'] = $metadata['final_step'] ?? 8.1;

            $finalFunction = $metadata['final_function'] ?? 'construccionGenesisGuardado';

            $generated->update([
                'key' => 'Genesis',
                'name' => $request->input('file_name'),
                'value' => $resultGenesis,
                'rating' => $request->input('rating'),
                'status' => 'completed',
                'metadata' => json_encode($metadata)
            ]);

            return response()->json(['success' => true, 'data' => 'Datos guardados correctamente', 'function' => $finalFunction, 'id_generated' => $generated->id]);

        }
    }catch(\Exception $e){
        return response()->json(['success' => false, 'error' => $e->getMessage()]);
    }
}

public function download(Request $request)
{
    $validator = Validator::make($request->all(), [
        'account' => 'required|integer',
        'id_generated' => 'required|integer',
    ]);

    if ($validator->fails()) {
        return response()->json(['error' => $validator->errors()]);
    }

    $accountId = $request->input('account');
    $id_generated = $request->input('id_generated');
    $generated = Generated::find($id_generated);
    if (!$generated) {
        return response()->json([
            'success' => false,
            'error' => 'Generación no encontrada'
        ]);
    }
    $metadata = $generated->metadata ? json_decode($generated->metadata, true) : [];
    // $accountData = Field::where('account_id', $accountId)->pluck('value', 'key');

    if ($metadata) {
        // Buscar el registro existentes
        $construccionescenario = $metadata['construccionescenario'];
        $genesis = $metadata['genesis'];
        $fuentesGenesis = $metadata['genesis_insight_fuentes_html'];
        $fuentesEscenario = $metadata['escenario_insight_fuentes_html'];
        $estrategia = $metadata['generacion_estrategia_data'];
        $creatividad = $metadata['generacion_creatividad_data'];
        $contenido = $metadata['generacion_ideas_contenido_data'];
        //$innovacion = $accountData->get('construccionInnovacion');

        $fields = [
            'construccionescenario' => $construccionescenario,
            'genesis'=> $genesis,
            'fuentesGenesis'=>$fuentesGenesis,
            'fuentesEscenario'=> $fuentesEscenario,
            'creatividad' => $creatividad,
            'estrategia' => $estrategia,
            'contenido' => $contenido,
            //'innovacion' => $innovacion,
        ];

    }

    // Cargar la vista Blade que contiene la plantilla PDF
    $pdf = Pdf::setOptions([
        'defaultFont' => 'sans-serif',
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled' => true
    ])->loadView('herramienta2.pdf.template', array_merge($fields));
    
    // Obtén la fecha y hora actual
    $now = Carbon::now();

    // Formatea la fecha y hora como una cadena en el formato deseado (por ejemplo, "YYYYMMDD_HHMMSS")
    $timestamp = $now->format('Ymd_His');

    // Descargar el PDF
    return $pdf->download('Genesis_' . $timestamp . '.pdf');

}

public function GenerarInsight($brief, $objective, $accountId = null, $generatedId = null){
    try {
        // Validar límite de créditos antes de generar
        if ($accountId) {
            $this->validateCreditLimit($accountId);
        }
        
        Log::info('🔍 [GenerarInsight] Iniciando método', [
            'accountId' => $accountId,
            'generatedId' => $generatedId,
            'brief_length' => strlen($brief),
            'objective_length' => strlen($objective)
        ]);
        
        $prompt = <<<EOT
En base a la siguiente información del brief y objetivo de campaña, encuentras poderosos insights culturales del país asignado en el brief, también si encuentras competidores o campañas similares que se asemejen al objetivo, mencionalos para tomar en cuenta
BRIEF: 
$brief
OBJETIVO: 
$objective

EOT;

        $model = self::MODEL_INSIGHT;
        $temperature = 0.7;
    
        $response = PerplexityService::ChatCompletions($prompt, $model, $temperature);

        // Log detallado de la respuesta
        Log::info('Respuesta completa Perplexity (GenerarInsight)', [
            'has_data' => isset($response['data']),
            'has_error' => isset($response['error']),
            'has_usage' => isset($response['usage']),
            'accountId' => $accountId,
            'generatedId' => $generatedId,
            'response_keys' => array_keys($response),
            'usage_structure' => isset($response['usage']) ? $response['usage'] : 'no existe'
        ]);
        
        // Log de tokens Perplexity
        if(isset($response['usage'])) {
            Log::info('Usage tokens Perplexity (GenerarInsight)', [
                'usage' => $response['usage']
            ]);
        }
        
        // Log de citations Perplexity
        if(isset($response['citations']) && !empty($response['citations'])) {
            Log::info('Citations Perplexity (GenerarInsight)', [
                'citations_count' => count($response['citations']),
                'citations' => $response['citations']
            ]);
        }
        
        // Registrar uso de tokens si está disponible y el proceso fue exitoso
        // IMPORTANTE: Registrar ANTES de verificar errores, para capturar el uso incluso si hay error
        if (isset($response['data']) && !isset($response['error']) && $accountId && isset($response['usage'])) {
            Log::info('✅ Condiciones cumplidas, registrando uso (GenerarInsight)');
            $this->trackUsageIfAvailable($accountId, $model, $response['usage'], 'perplexity', 'GenerarInsight', $generatedId);
        } else {
            Log::warning('⚠️ No se registró el uso (GenerarInsight)', [
                'has_data' => isset($response['data']),
                'has_error' => isset($response['error']),
                'accountId' => $accountId,
                'has_usage' => isset($response['usage'])
            ]);
        }

        // Verificar si hay error ANTES de intentar acceder a 'data'
        if (isset($response['error'])) {
            Log::error('❌ [GenerarInsight] Error detectado en respuesta de Perplexity', [
                'error' => $response['error'],
                'response_completa' => $response
            ]);
            return array('error' => $response['error'], 'fuentes' => $response['citations'] ?? []);
        }

        if (!isset($response['data'])) {
            Log::error('❌ [GenerarInsight] La respuesta no contiene la clave "data"', [
                'response' => $response,
                'response_keys' => is_array($response) ? array_keys($response) : 'no es array',
                'account_id' => $accountId,
                'generated_id' => $generatedId
            ]);
            return array('error' => 'Error al obtener datos de la IA. La respuesta no contiene datos.', 'fuentes' => $response['citations'] ?? []);
        }

        return array('data' => $response['data'], 'fuentes'=>$response['citations'] ?? []);
    } catch (\Exception $e) {
        Log::error('Error al generar Insight', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        throw $e; // Re-lanzamos la excepción para que sea capturada en el método principal
    }
}

public function GenerarInsight2($brief, $objective, $genesisgenerado, $accountId = null, $generatedId = null){
    try {
        // Validar límite de créditos antes de generar
        if ($accountId) {
            $this->validateCreditLimit($accountId);
        }
        
        Log::info('🔍 [GenerarInsight2] Iniciando método', [
            'accountId' => $accountId,
            'generatedId' => $generatedId,
            'brief_length' => strlen($brief),
            'objective_length' => strlen($objective),
            'genesis_length' => strlen($genesisgenerado)
        ]);
        
        $prompt = <<<EOT
Usando la siguiente información, busca conversaciones reales, temas, insights sociales sobre los temas relacionados para potenciar cualquier concepto creativo. Hazlo con un enfoque social.
GENESIS:
$genesisgenerado
BRIEF: 
$brief
OBJETIVO: 
$objective

EOT;

        $model = self::MODEL_INSIGHT2;
        $temperature = 0.7;
    
        $response = PerplexityService::ChatCompletions($prompt, $model, $temperature);

        // Log detallado de la respuesta
        Log::info('Respuesta completa Perplexity (GenerarInsight2)', [
            'has_data' => isset($response['data']),
            'has_error' => isset($response['error']),
            'has_usage' => isset($response['usage']),
            'accountId' => $accountId,
            'generatedId' => $generatedId,
            'response_keys' => array_keys($response),
            'usage_structure' => isset($response['usage']) ? $response['usage'] : 'no existe'
        ]);
        
        // Log de tokens Perplexity
        if(isset($response['usage'])) {
            Log::info('Usage tokens Perplexity (GenerarInsight2)', [
                'usage' => $response['usage']
            ]);
        }
        
        // Log de citations Perplexity
        if(isset($response['citations']) && !empty($response['citations'])) {
            Log::info('Citations Perplexity (GenerarInsight2)', [
                'citations_count' => count($response['citations']),
                'citations' => $response['citations']
            ]);
        }
        
        // Registrar uso de tokens si está disponible y el proceso fue exitoso
        // IMPORTANTE: Registrar ANTES de verificar errores, para capturar el uso incluso si hay error
        if (isset($response['data']) && !isset($response['error']) && $accountId && isset($response['usage'])) {
            Log::info('✅ Condiciones cumplidas, registrando uso (GenerarInsight2)');
            $this->trackUsageIfAvailable($accountId, $model, $response['usage'], 'perplexity', 'GenerarInsight2', $generatedId);
        } else {
            Log::warning('⚠️ No se registró el uso (GenerarInsight2)', [
                'has_data' => isset($response['data']),
                'has_error' => isset($response['error']),
                'accountId' => $accountId,
                'has_usage' => isset($response['usage'])
            ]);
        }

        // Verificar si hay error ANTES de intentar acceder a 'data'
        if (isset($response['error'])) {
            Log::error('❌ [GenerarInsight2] Error detectado en respuesta de Perplexity', [
                'error' => $response['error'],
                'response_completa' => $response
            ]);
            return array('error' => $response['error'], 'fuentes' => $response['citations'] ?? []);
        }

        if (!isset($response['data'])) {
            Log::error('❌ [GenerarInsight2] La respuesta no contiene la clave "data"', [
                'response' => $response,
                'response_keys' => is_array($response) ? array_keys($response) : 'no es array',
                'account_id' => $accountId,
                'generated_id' => $generatedId
            ]);
            return array('error' => 'Error al obtener datos de la IA. La respuesta no contiene datos.', 'fuentes' => $response['citations'] ?? []);
        }

        return array('data' => $response['data'], 'fuentes'=>$response['citations'] ?? []);
    } catch (\Exception $e) {
        Log::error('Error al generar Insight', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        throw $e; // Re-lanzamos la excepción para que sea capturada en el método principal
    }
}
}
