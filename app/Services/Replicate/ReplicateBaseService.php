<?php

namespace App\Services\Replicate;

use Illuminate\Support\Facades\Log;

/**
 * ReplicateBaseService - Clase base para servicios de Replicate
 * 
 * Contiene métodos comunes compartidos por todos los servicios de Replicate:
 * - Consulta de estado de predicciones
 * - Cancelación de predicciones
 * - Helpers para peticiones HTTP
 */
abstract class ReplicateBaseService
{
    /**
     * URL base de la API de Replicate
     */
    protected const API_BASE_URL = 'https://api.replicate.com/v1';

    /**
     * Consulta el estado de una predicción en Replicate
     * 
     * @param string $predictionId ID de la predicción a consultar
     * 
     * @return array Respuesta con 'success', 'status', 'output' o 'error'
     */
    public static function getPredictionStatus(string $predictionId): array
    {
        try {
            $apiKey = env('REPLICATE_API_KEY');
            
            if (empty($apiKey)) {
                return [
                    'success' => false,
                    'error' => 'API Key de Replicate no configurada'
                ];
            }

            $url = self::API_BASE_URL . '/predictions/' . $predictionId;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                return [
                    'success' => false,
                    'error' => 'Error de conexión: ' . $curlError
                ];
            }

            $responseData = json_decode($response, true);

            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'error' => $responseData['detail'] ?? 'Error al consultar predicción'
                ];
            }

            $status = $responseData['status'] ?? 'unknown';
            
            Log::debug('🔍 [REPLICATE] Estado de predicción', [
                'predictionId' => $predictionId,
                'status' => $status
            ]);

            // Estados posibles: starting, processing, succeeded, failed, canceled
            $result = [
                'success' => true,
                'status' => $status,
                'raw' => $responseData
            ];

            // Si completó exitosamente, incluir el output
            if ($status === 'succeeded') {
                $result['output'] = $responseData['output'] ?? null;
                Log::info('✅ [REPLICATE] Predicción completada', [
                    'predictionId' => $predictionId,
                    'output' => $result['output']
                ]);
            }

            // Si falló, incluir el error
            if ($status === 'failed') {
                $result['error'] = $responseData['error'] ?? 'Error desconocido en la generación';
                Log::error('❌ [REPLICATE] Predicción fallida', [
                    'predictionId' => $predictionId,
                    'error' => $result['error']
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('❌ [REPLICATE] Excepción al consultar estado', [
                'predictionId' => $predictionId,
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancela una predicción en progreso
     * 
     * @param string $predictionId ID de la predicción a cancelar
     * 
     * @return array Respuesta con 'success' o 'error'
     */
    public static function cancelPrediction(string $predictionId): array
    {
        try {
            $apiKey = env('REPLICATE_API_KEY');
            
            if (empty($apiKey)) {
                return [
                    'success' => false,
                    'error' => 'API Key de Replicate no configurada'
                ];
            }

            $url = self::API_BASE_URL . '/predictions/' . $predictionId . '/cancel';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                Log::info('🛑 [REPLICATE] Predicción cancelada', ['predictionId' => $predictionId]);
                return ['success' => true];
            }

            return [
                'success' => false,
                'error' => 'No se pudo cancelar la predicción'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Helper para hacer peticiones POST a Replicate API
     * 
     * @param string $endpoint Endpoint relativo (ej: '/models/google/veo-3.1/predictions')
     * @param array $payload Datos a enviar
     * 
     * @return array Respuesta con 'success', 'response', 'httpCode' o 'error'
     */
    protected static function makePostRequest(string $endpoint, array $payload): array
    {
        try {
            $apiKey = env('REPLICATE_API_KEY');
            
            if (empty($apiKey)) {
                Log::error('❌ [REPLICATE] API Key no configurada');
                return [
                    'success' => false,
                    'error' => 'API Key de Replicate no configurada'
                ];
            }

            $url = self::API_BASE_URL . $endpoint;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                Log::error('❌ [REPLICATE] Error CURL', ['error' => $curlError]);
                return [
                    'success' => false,
                    'error' => 'Error de conexión: ' . $curlError
                ];
            }

            $responseData = json_decode($response, true);

            Log::debug('🔍 [REPLICATE] Respuesta de la API', [
                'statusCode' => $httpCode,
                'endpoint' => $endpoint,
                'response' => $responseData
            ]);

            return [
                'success' => $httpCode === 201 || $httpCode === 200,
                'httpCode' => $httpCode,
                'response' => $responseData,
                'error' => ($httpCode !== 201 && $httpCode !== 200) 
                    ? ($responseData['detail'] ?? $responseData['error'] ?? 'Error desconocido')
                    : null
            ];

        } catch (\Exception $e) {
            Log::error('❌ [REPLICATE] Excepción en petición', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

