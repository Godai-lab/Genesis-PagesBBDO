<?php

namespace App\Supports;

use App\Models\ModelPricing;
use App\Models\UsageRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CostCalculationService
{
    /**
     * Registra un consumo de IA y calcula los costos
     * 
     * IMPORTANTE: Los precios de modelos tipo 'per_token' se configuran como "por millón de tokens".
     * El cálculo divide automáticamente los tokens por 1,000,000 antes de multiplicar por el precio.
     * 
     * @param int $accountId ID de la cuenta que consume
     * @param int $userId ID del usuario que hace la llamada
     * @param string $modelName Nombre del modelo (ej: 'gpt-5', 'claude-sonnet-4-5')
     * @param array $usageMetrics Métricas de uso según el tipo de pricing:
     *                            - per_token: ['tokens' => ['input' => 150, 'output' => 300]]
     *                              Los precios en model_pricing están configurados como "por millón de tokens"
     *                              Ejemplo: Si el precio es $1.25 por millón y se usan 150 tokens:
     *                              Costo = (150 / 1,000,000) * 1.25 = 0.0001875 USD
     *                            - per_generation: ['generations' => 1, 'resolution' => '1024x1024']
     *                            - per_second: ['seconds' => 45]
     *                            - per_credit: ['credits' => 150] (para servicios como Gamma)
     * @param Carbon|null $usageDate Fecha del uso (por defecto: ahora)
     * @param string|null $requestType Tipo de request (ej: 'Genesis', 'Brief', 'Asistente Creativo', 'Asistente Social Media', 'Image Generator', 'Video Generator')
     * @param string|null $externalRequestId ID de la solicitud externa (ej: ID de tarea asíncrona de Perplexity, OpenAI) para evitar duplicados
     * @param int|null $generatedId ID de la generación (Generated) a la que pertenece este proceso. Si se proporciona, se agrupan todos los procesos en un solo registro
     * @param string|null $step Nombre del paso/proceso dentro de la generación (ej: 'GenerarInsight', 'generarGenesis', 'construccionescenario')
     * @param string|null $serviceType Tipo de servicio usado (ej: 'anthropic', 'openai', 'perplexity', 'gemini')
     * @return UsageRecord|null Retorna el registro existente si ya existe uno con el mismo external_request_id o generated_id, o el nuevo registro creado
     */
    public static function trackUsage(
        ?int $accountId,
        int $userId,
        string $modelName,
        array $usageMetrics,
        ?Carbon $usageDate = null,
        ?string $requestType = null,
        ?string $externalRequestId = null,
        ?int $generatedId = null,
        ?string $step = null,
        ?string $serviceType = null
    ): ?UsageRecord {
        try {
            $usageDate = $usageDate ?? Carbon::now();
            
            // Si se proporciona un external_request_id, verificar si ya existe un registro
            // Esto evita duplicados cuando el mismo request se procesa múltiples veces (POST y GET)
            if ($externalRequestId !== null) {
                $query = UsageRecord::where('external_request_id', $externalRequestId);
                if ($accountId !== null) {
                    $query->where('account_id', $accountId);
                } else {
                    $query->whereNull('account_id');
                }
                $existingRecord = $query->first();
                
                if ($existingRecord) {
                    Log::info("Registro de uso ya existe para external_request_id: {$externalRequestId}", [
                        'existing_record_id' => $existingRecord->id,
                        'account_id' => $accountId,
                        'model_name' => $modelName,
                    ]);
                    return $existingRecord;
                }
            }
            
            // Si se proporciona un generated_id, buscar o crear registro agrupado
            if ($generatedId !== null) {
                return self::trackUsageWithGeneratedId(
                    $accountId,
                    $userId,
                    $modelName,
                    $usageMetrics,
                    $usageDate,
                    $requestType,
                    $externalRequestId,
                    $generatedId,
                    $step,
                    $serviceType
                );
            }
            
            // Buscar el modelo y su pricing vigente
            $pricing = self::findCurrentPricing($modelName, $usageDate);
            
            if (!$pricing) {
                Log::warning("No se encontró pricing vigente para el modelo: {$modelName}", [
                    'account_id' => $accountId,
                    'user_id' => $userId,
                    'model_name' => $modelName,
                    'usage_metrics' => $usageMetrics,
                ]);
                return null;
            }

            // Calcular costos según el tipo de pricing
            $costs = self::calculateCosts($pricing, $usageMetrics);
            
            if ($costs === null) {
                Log::warning("Error al calcular costos para el modelo: {$modelName}", [
                    'pricing_type' => $pricing->pricing_type,
                    'usage_metrics' => $usageMetrics,
                ]);
                return null;
            }

            // Aplicar margen de ganancia
            $markupPercentage = $pricing->markup_percentage ?? 0;
            $costFinal = $costs['total'] * (1 + ($markupPercentage / 100));

            // Crear snapshot del pricing (inmutable)
            $pricingSnapshot = $pricing->unit_definition;

            // Crear registro simple (sin generated_id)
            return UsageRecord::create([
                'account_id' => $accountId,
                'user_id' => $userId,
                'request_type' => $requestType,
                'external_request_id' => $externalRequestId,
                'generated_id' => null,
                'model_pricing_id' => $pricing->id,
                'usage_metrics' => $usageMetrics,
                'pricing_snapshot' => $pricingSnapshot,
                'processes_detail' => null,
                'cost_input_usd' => $costs['input'],
                'cost_output_usd' => $costs['output'],
                'cost_total_usd' => $costs['total'],
                'markup_percentage_applied' => $markupPercentage,
                'cost_final_user_usd' => $costFinal,
            ]);

        } catch (\Exception $e) {
            Log::error("Error al registrar uso de IA", [
                'account_id' => $accountId,
                'user_id' => $userId,
                'model_name' => $modelName,
                'external_request_id' => $externalRequestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Busca el pricing vigente para un modelo en una fecha específica
     * 
     * Busca primero por 'slug' (nombre real usado en la API) y luego por 'name' como fallback
     * 
     * @param string $modelName Nombre del modelo o slug (ej: 'sonar-reasoning-pro', 'gpt-5')
     * @param Carbon $date Fecha del uso
     * @return ModelPricing|null
     */
    protected static function findCurrentPricing(string $modelName, Carbon $date): ?ModelPricing
    {
        return ModelPricing::with('model')
            ->whereHas('model', function ($query) use ($modelName) {
                $query->where(function ($q) use ($modelName) {
                    $q->where('slug', $modelName)
                      ->orWhere('name', $modelName);
                })
                ->where('status', 'active');
            })
            ->forDate($date)
            ->active()
            ->orderBy('effective_from', 'desc')
            ->first();
    }

    /**
     * Calcula los costos según el tipo de pricing
     * 
     * @param ModelPricing $pricing
     * @param array $usageMetrics
     * @return array|null ['input' => float, 'output' => float, 'total' => float]
     */
    protected static function calculateCosts(ModelPricing $pricing, array $usageMetrics): ?array
    {
        $unitDef = $pricing->unit_definition;

        switch ($pricing->pricing_type) {
            case 'per_token':
                return self::calculateTokenCosts($unitDef, $usageMetrics);
            
            case 'per_generation':
                return self::calculateGenerationCosts($unitDef, $usageMetrics);
            
            case 'per_second':
                return self::calculateSecondCosts($unitDef, $usageMetrics);
            
            case 'per_credit':
                return self::calculateCreditCosts($unitDef, $usageMetrics);
            
            default:
                Log::error("Tipo de pricing no soportado: {$pricing->pricing_type}");
                return null;
        }
    }

    /**
     * Calcula costos para pricing por token
     * NOTA: Los precios en unitDef están configurados como "por millón de tokens"
     * 
     * Para modelos especiales como sonar-deep-research, se calculan costos adicionales:
     * - citation_tokens: $2/1M
     * - reasoning_tokens: $3/1M
     * - search_queries: $5/1K (por mil, no por millón)
     * 
     * @param array $unitDef ['input_price' => float, 'output_price' => float, 'unit' => 'token']
     *                        Los precios son por millón de tokens (ej: 1.25 = $1.25 por millón)
     *                        Para sonar-deep-research puede incluir: citation_price, reasoning_price, search_query_price
     * @param array $usageMetrics ['tokens' => ['input' => int, 'output' => int, 'citation' => int (opcional), 'reasoning' => int (opcional), 'search_queries' => int (opcional)]]
     * @return array|null
     */
    protected static function calculateTokenCosts(array $unitDef, array $usageMetrics): ?array
    {
        if (!isset($usageMetrics['tokens'])) {
            return null;
        }

        $inputTokens = $usageMetrics['tokens']['input'] ?? 0;
        $outputTokens = $usageMetrics['tokens']['output'] ?? 0;

        // Los precios están configurados como "por millón de tokens"
        $inputPricePerMillion = $unitDef['input_price'] ?? 0;
        $outputPricePerMillion = $unitDef['output_price'] ?? 0;

        // Dividir tokens por 1,000,000 antes de multiplicar por el precio
        $costInput = ($inputTokens / 1000000) * $inputPricePerMillion;
        $costOutput = ($outputTokens / 1000000) * $outputPricePerMillion;
        $costTotal = $costInput + $costOutput;

        // Calcular costos adicionales para modelos especiales (ej: sonar-deep-research)
        // Estos campos son opcionales, si no existen se tratan como 0
        $citationTokens = $usageMetrics['tokens']['citation'] ?? 0;
        $reasoningTokens = $usageMetrics['tokens']['reasoning'] ?? 0;
        $searchQueries = $usageMetrics['tokens']['search_queries'] ?? 0;

        // Si hay tokens adicionales, calcular sus costos
        if ($citationTokens > 0 || $reasoningTokens > 0 || $searchQueries > 0) {
            // Precios por defecto para sonar-deep-research (pueden estar en unitDef o usar defaults)
            $citationPricePerMillion = $unitDef['citation_price'] ?? 2.0; // $2/1M
            $reasoningPricePerMillion = $unitDef['reasoning_price'] ?? 3.0; // $3/1M
            $searchQueryPricePerThousand = $unitDef['search_query_price'] ?? 5.0; // $5/1K

            // Calcular costos adicionales
            $costCitation = ($citationTokens / 1000000) * $citationPricePerMillion;
            $costReasoning = ($reasoningTokens / 1000000) * $reasoningPricePerMillion;
            // Search queries se calculan por mil, no por millón
            $costSearchQueries = ($searchQueries / 1000) * $searchQueryPricePerThousand;

            // Sumar al costo total
            $costTotal += $costCitation + $costReasoning + $costSearchQueries;

            Log::info("Costos adicionales calculados para modelo especial", [
                'citation_tokens' => $citationTokens,
                'citation_cost' => $costCitation,
                'reasoning_tokens' => $reasoningTokens,
                'reasoning_cost' => $costReasoning,
                'search_queries' => $searchQueries,
                'search_queries_cost' => $costSearchQueries,
                'total_additional' => $costCitation + $costReasoning + $costSearchQueries
            ]);
        }

        return [
            'input' => $costInput,
            'output' => $costOutput,
            'total' => $costTotal,
        ];
    }

    /**
     * Calcula costos para pricing por generación
     * 
     * @param array $unitDef ['price_per_generation' => float, 'unit' => 'generation']
     * @param array $usageMetrics ['generations' => int, 'resolution' => string (opcional)]
     * @return array|null
     */
    protected static function calculateGenerationCosts(array $unitDef, array $usageMetrics): ?array
    {
        if (!isset($usageMetrics['generations'])) {
            return null;
        }

        $generations = (int) $usageMetrics['generations'];
        $pricePerGeneration = $unitDef['price_per_generation'] ?? 0;

        $costTotal = $generations * $pricePerGeneration;

        return [
            'input' => 0, // No hay costo de entrada para generaciones
            'output' => $costTotal,
            'total' => $costTotal,
        ];
    }

    /**
     * Calcula costos para pricing por segundo
     * 
     * @param array $unitDef ['price_per_second' => float, 'minimum_seconds' => int, 'unit' => 'second']
     * @param array $usageMetrics ['seconds' => float]
     * @return array|null
     */
    protected static function calculateSecondCosts(array $unitDef, array $usageMetrics): ?array
    {
        if (!isset($usageMetrics['seconds'])) {
            return null;
        }

        $seconds = (float) $usageMetrics['seconds'];
        $minimumSeconds = $unitDef['minimum_seconds'] ?? 1;
        $pricePerSecond = $unitDef['price_per_second'] ?? 0;

        // Aplicar mínimo de segundos
        $billedSeconds = max($seconds, $minimumSeconds);
        $costTotal = $billedSeconds * $pricePerSecond;

        return [
            'input' => 0, // No hay costo de entrada para segundos
            'output' => $costTotal,
            'total' => $costTotal,
        ];
    }

    /**
     * Calcula costos para pricing por créditos
     * 
     * @param array $unitDef ['price_per_credit' => float, 'unit' => 'credit']
     * @param array $usageMetrics ['credits' => int]
     * @return array|null
     */
    protected static function calculateCreditCosts(array $unitDef, array $usageMetrics): ?array
    {
        if (!isset($usageMetrics['credits'])) {
            return null;
        }

        $credits = (int) $usageMetrics['credits'];
        $pricePerCredit = $unitDef['price_per_credit'] ?? 0;

        $costTotal = $credits * $pricePerCredit;

        return [
            'input' => 0, // No hay costo de entrada para créditos
            'output' => $costTotal,
            'total' => $costTotal,
        ];
    }

    /**
     * Obtiene el pricing vigente de un modelo por su nombre
     * Útil para obtener el pricing antes de hacer una llamada
     * 
     * @param string $modelName
     * @return ModelPricing|null
     */
    public static function getCurrentPricing(string $modelName): ?ModelPricing
    {
        return self::findCurrentPricing($modelName, Carbon::now());
    }

    /**
     * Maneja el tracking de uso cuando hay un generated_id (agrupa múltiples procesos)
     * 
     * @param int $accountId
     * @param int $userId
     * @param string $modelName
     * @param array $usageMetrics
     * @param Carbon $usageDate
     * @param string|null $requestType
     * @param string|null $externalRequestId
     * @param int $generatedId
     * @param string|null $step
     * @param string|null $serviceType
     * @return UsageRecord|null
     */
    protected static function trackUsageWithGeneratedId(
        ?int $accountId,
        int $userId,
        string $modelName,
        array $usageMetrics,
        Carbon $usageDate,
        ?string $requestType,
        ?string $externalRequestId,
        int $generatedId,
        ?string $step,
        ?string $serviceType
    ): ?UsageRecord {
        // Buscar el modelo y su pricing vigente
        $pricing = self::findCurrentPricing($modelName, $usageDate);
        
        if (!$pricing) {
            Log::warning("No se encontró pricing vigente para el modelo: {$modelName}", [
                'account_id' => $accountId,
                'user_id' => $userId,
                'model_name' => $modelName,
                'generated_id' => $generatedId,
                'usage_metrics' => $usageMetrics,
            ]);
            return null;
        }

        // Calcular costos según el tipo de pricing
        $costs = self::calculateCosts($pricing, $usageMetrics);
        
        if ($costs === null) {
            Log::warning("Error al calcular costos para el modelo: {$modelName}", [
                'pricing_type' => $pricing->pricing_type,
                'usage_metrics' => $usageMetrics,
                'generated_id' => $generatedId,
            ]);
            return null;
        }

        // Aplicar margen de ganancia
        $markupPercentage = $pricing->markup_percentage ?? 0;
        $costFinal = $costs['total'] * (1 + ($markupPercentage / 100));

        // Crear snapshot del pricing (inmutable)
        $pricingSnapshot = $pricing->unit_definition;

        // Crear objeto del proceso actual
        $currentProcess = [
            'step' => $step ?? 'unknown',
            'model' => $modelName,
            'service_type' => $serviceType,
            'model_pricing_id' => $pricing->id,
            'external_request_id' => $externalRequestId,
            'timestamp' => $usageDate->toIso8601String(),
            'usage_metrics' => $usageMetrics,
            'pricing_snapshot' => $pricingSnapshot,
            'cost_input_usd' => $costs['input'],
            'cost_output_usd' => $costs['output'],
            'cost_total_usd' => $costs['total'],
            'markup_percentage_applied' => $markupPercentage,
            'cost_final_user_usd' => $costFinal,
        ];

        // Buscar registro existente con este generated_id
        $query = UsageRecord::where('generated_id', $generatedId);
        if ($accountId !== null) {
            $query->where('account_id', $accountId);
        } else {
            $query->whereNull('account_id');
        }
        $existingRecord = $query->first();

        if ($existingRecord) {
            // Actualizar registro existente: agregar nuevo proceso y actualizar totales
            $processesDetail = $existingRecord->processes_detail ?? ['processes' => [], 'summary' => []];
            $processes = $processesDetail['processes'] ?? [];
            
            // Agregar el nuevo proceso
            $processes[] = $currentProcess;
            
            // Recalcular summary
            $summary = self::calculateProcessesSummary($processes);
            
            // Actualizar el registro
            $existingRecord->update([
                'processes_detail' => [
                    'processes' => $processes,
                    'summary' => $summary,
                ],
                'cost_input_usd' => $summary['total_input_cost_usd'],
                'cost_output_usd' => $summary['total_output_cost_usd'],
                'cost_total_usd' => $summary['total_cost_usd'],
                'cost_final_user_usd' => $summary['total_final_user_cost_usd'],
            ]);

            Log::info("Proceso agregado a registro existente", [
                'usage_record_id' => $existingRecord->id,
                'generated_id' => $generatedId,
                'step' => $step,
                'model' => $modelName,
            ]);

            return $existingRecord;
        } else {
            // Crear nuevo registro con el primer proceso
            $processesDetail = [
                'processes' => [$currentProcess],
                'summary' => self::calculateProcessesSummary([$currentProcess]),
            ];

            // Simplificar request_type (solo el nombre de la herramienta, sin sufijos)
            $simplifiedRequestType = self::simplifyRequestType($requestType);

            return UsageRecord::create([
                'account_id' => $accountId,
                'user_id' => $userId,
                'request_type' => $simplifiedRequestType,
                'external_request_id' => null, // No usar external_request_id cuando hay generated_id
                'generated_id' => $generatedId,
                'model_pricing_id' => null, // null porque hay múltiples modelos
                'usage_metrics' => null, // null porque se usa processes_detail
                'pricing_snapshot' => null, // null porque hay múltiples modelos
                'processes_detail' => $processesDetail,
                'cost_input_usd' => $processesDetail['summary']['total_input_cost_usd'],
                'cost_output_usd' => $processesDetail['summary']['total_output_cost_usd'],
                'cost_total_usd' => $processesDetail['summary']['total_cost_usd'],
                'markup_percentage_applied' => $markupPercentage, // Promedio o del último proceso
                'cost_final_user_usd' => $processesDetail['summary']['total_final_user_cost_usd'],
            ]);
        }
    }

    /**
     * Calcula el resumen de todos los procesos
     * 
     * @param array $processes
     * @return array
     */
    protected static function calculateProcessesSummary(array $processes): array
    {
        $totalInputCost = 0;
        $totalOutputCost = 0;
        $totalCost = 0;
        $totalFinalUserCost = 0;
        $modelsUsed = [];
        $serviceTypesUsed = [];

        foreach ($processes as $process) {
            $totalInputCost += $process['cost_input_usd'] ?? 0;
            $totalOutputCost += $process['cost_output_usd'] ?? 0;
            $totalCost += $process['cost_total_usd'] ?? 0;
            $totalFinalUserCost += $process['cost_final_user_usd'] ?? 0;
            
            if (!in_array($process['model'] ?? '', $modelsUsed)) {
                $modelsUsed[] = $process['model'] ?? '';
            }
            
            if (!empty($process['service_type']) && !in_array($process['service_type'], $serviceTypesUsed)) {
                $serviceTypesUsed[] = $process['service_type'];
            }
        }

        return [
            'total_processes' => count($processes),
            'total_input_cost_usd' => $totalInputCost,
            'total_output_cost_usd' => $totalOutputCost,
            'total_cost_usd' => $totalCost,
            'total_final_user_cost_usd' => $totalFinalUserCost,
            'models_used' => $modelsUsed,
            'service_types_used' => $serviceTypesUsed,
        ];
    }

    /**
     * Simplifica el request_type eliminando sufijos de paso
     * Ej: "generarGenesis-Genesis" → "Genesis"
     *     "GenerarBrief-Brief" → "Brief"
     * 
     * @param string|null $requestType
     * @return string|null
     */
    protected static function simplifyRequestType(?string $requestType): ?string
    {
        if (!$requestType) {
            return null;
        }

        // Si termina en "-Genesis", retornar solo "Genesis"
        if (substr($requestType, -8) === '-Genesis') {
            return 'Genesis';
        }

        // Si termina en "-Brief", retornar solo "Brief"
        if (substr($requestType, -6) === '-Brief') {
            return 'Brief';
        }

        // Si termina en "-Investigacion", retornar solo "Investigacion"
        if (substr($requestType, -14) === '-Investigacion') {
            return 'Investigacion';
        }

        // Si termina en "-Concepto", retornar solo "Concepto"
        if (substr($requestType, -9) === '-Concepto') {
            return 'Concepto';
        }

        // Si no tiene sufijo, retornar tal cual
        return $requestType;
    }
/**
 * Registra uso del chat (un mensaje/respuesta del agente).
 *
 * @param int|null $accountId ID de la cuenta (puede ser null)
 * @param int $userId ID del usuario
 * @param string $modelName Nombre del modelo (ej: 'gpt-4o', 'claude-sonnet-4', 'gemini-2.0-flash')
 * @param array $usageMetrics ['tokens' => ['input' => int, 'output' => int]]
 * @param string $conversationKey session_key de la conversación (LarAgent) — no usado en tu trackUsage actual
 * @param string $serviceType 'openai' | 'anthropic' | 'gemini'
 * @return UsageRecord|null
 */
public static function trackChatUsage(
    ?int $accountId,
    int $userId,
    string $modelName,
    array $usageMetrics,
    string $conversationKey,
    string $serviceType
): ?UsageRecord {
    return self::trackUsage(
        $accountId,
        $userId,
        $modelName,
        $usageMetrics,
        Carbon::now(),
        'Chat',
        null,
        null,
        null,
        $serviceType
    );
}
}

