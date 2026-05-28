<?php

namespace App\Http\Controllers;

use App\Models\ModelPricing;
use App\Models\AiModel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ModelPricingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ModelPricing::with('model.provider');

        // Filtro por modelo
        if ($request->has('ai_model_id')) {
            $query->where('ai_model_id', $request->ai_model_id);
        }

        // Filtro por status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filtro por tipo de pricing
        if ($request->has('pricing_type')) {
            $query->where('pricing_type', $request->pricing_type);
        }

        $pricings = $query->orderBy('effective_from', 'desc')->paginate($request->get('per_page', 15));

        return response()->json($pricings);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ai_model_id' => 'required|exists:ai_models,id',
            'pricing_type' => 'required|in:per_token,per_generation,per_second',
            'unit_definition' => 'required|json',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'status' => 'required|in:active,inactive',
        ]);

        // Validar que unit_definition sea JSON válido
        $unitDefinition = json_decode($validated['unit_definition'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'message' => 'unit_definition debe ser un JSON válido'
            ], 422);
        }

        // Si hay un precio vigente para este modelo, actualizar su effective_to
        $currentPricing = ModelPricing::where('ai_model_id', $validated['ai_model_id'])
            ->where('status', 'active')
            ->whereNull('effective_to')
            ->first();

        if ($currentPricing && $validated['effective_from'] <= $currentPricing->effective_from) {
            return response()->json([
                'message' => 'La fecha effective_from debe ser posterior a la fecha del precio vigente actual.'
            ], 422);
        }

        if ($currentPricing) {
            // Establecer effective_to del precio anterior un día antes del nuevo precio
            $newEffectiveFrom = Carbon::parse($validated['effective_from']);
            $currentPricing->update([
                'effective_to' => $newEffectiveFrom->copy()->subDay()->format('Y-m-d')
            ]);
        }

        $pricing = ModelPricing::create($validated);
        $pricing->load('model.provider');

        return response()->json($pricing, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $pricing = ModelPricing::with('model.provider')->findOrFail($id);

        return response()->json($pricing);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $pricing = ModelPricing::findOrFail($id);

        // Validar que no tenga registros de uso
        if ($pricing->usageRecords()->count() > 0) {
            return response()->json([
                'message' => 'No se puede actualizar un precio que tiene registros de uso asociados. Crea un nuevo precio en su lugar.'
            ], 422);
        }

        $validated = $request->validate([
            'pricing_type' => 'required|in:per_token,per_generation,per_second',
            'unit_definition' => 'required|json',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'status' => 'required|in:active,inactive',
        ]);

        // Validar que unit_definition sea JSON válido
        $unitDefinition = json_decode($validated['unit_definition'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'message' => 'unit_definition debe ser un JSON válido'
            ], 422);
        }

        $pricing->update($validated);
        $pricing->load('model.provider');

        return response()->json($pricing);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $pricing = ModelPricing::findOrFail($id);

        // Validar que no tenga registros de uso
        if ($pricing->usageRecords()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un precio que tiene registros de uso asociados.'
            ], 422);
        }

        $pricing->delete();

        return response()->json(['message' => 'Precio eliminado correctamente']);
    }
}
