<?php

namespace App\Http\Controllers;

use App\Models\AiModel;
use App\Models\Provider;
use App\Models\ModelPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

class AiModelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize('haveaccess', 'ai-models.index');
        
        $search = $request->search;
        $status = $request->status;
        $provider_id = $request->provider_id;
        $model_type = $request->model_type;
        
        $query = AiModel::with('provider');

        // Filtro por proveedor
        if ($provider_id) {
            $query->where('provider_id', $provider_id);
        }

        // Filtro por tipo de modelo
        if ($model_type) {
            $query->where('model_type', $model_type);
        }

        // Filtro por status
        if ($status) {
            $query->where('status', $status);
        }

        // Búsqueda por nombre
        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $models = $query->orderBy('name')->paginate(5)->withQueryString();
        $providers = Provider::active()->orderBy('name')->get();

        return view('costs.ai-models.index', compact('models', 'providers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('haveaccess', 'ai-models.create');
        
        $providers = Provider::active()->orderBy('name')->get();
        return view('costs.ai-models.create', compact('providers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('haveaccess', 'ai-models.create');

        // Validación del modelo
        $modelValidated = $request->validate([
            'provider_id' => 'required|exists:providers,id',
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'model_type' => 'required|in:text,image,video,audio,service,presentation',
            'available_until' => 'nullable|date|after:today',
        ]);

        // Validación del pricing
        $pricingValidated = $request->validate([
            'pricing_type' => 'required|in:per_token,per_generation,per_second,per_credit',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'markup_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        // Validación de campos dinámicos según el tipo de pricing
        // NOTA: Para 'per_token', los precios se configuran como "por millón de tokens"
        // El CostCalculationService divide automáticamente los tokens por 1,000,000 antes de calcular
        $unitDefinition = [];
        if ($request->pricing_type === 'per_token') {
            $request->validate([
                'input_price' => 'required|numeric|min:0',
                'output_price' => 'required|numeric|min:0',
                'citation_price' => 'nullable|numeric|min:0',
                'reasoning_price' => 'nullable|numeric|min:0',
                'search_query_price' => 'nullable|numeric|min:0',
            ]);
            $unitDefinition = [
                'input_price' => (float) $request->input_price, // Precio por millón de tokens de entrada
                'output_price' => (float) $request->output_price, // Precio por millón de tokens de salida
                'unit' => 'token'
            ];
            
            // Agregar precios adicionales opcionales si están presentes
            if ($request->filled('citation_price')) {
                $unitDefinition['citation_price'] = (float) $request->citation_price;
            }
            if ($request->filled('reasoning_price')) {
                $unitDefinition['reasoning_price'] = (float) $request->reasoning_price;
            }
            if ($request->filled('search_query_price')) {
                $unitDefinition['search_query_price'] = (float) $request->search_query_price;
            }
        } elseif ($request->pricing_type === 'per_generation') {
            $request->validate([
                'price_per_generation' => 'required|numeric|min:0',
            ]);
            $unitDefinition = [
                'price_per_generation' => (float) $request->price_per_generation,
                'unit' => 'generation'
            ];
        } elseif ($request->pricing_type === 'per_second') {
            $request->validate([
                'price_per_second' => 'required|numeric|min:0',
                'minimum_seconds' => 'required|integer|min:1',
            ]);
            $unitDefinition = [
                'price_per_second' => (float) $request->price_per_second,
                'minimum_seconds' => (int) $request->minimum_seconds,
                'unit' => 'second'
            ];
        }

        try {
            DB::beginTransaction();

            // Usar firstOrCreate para el modelo
            $model = AiModel::firstOrCreate(
                [
                    'provider_id' => $modelValidated['provider_id'],
                    'name' => $modelValidated['name'],
                    'model_type' => $modelValidated['model_type'],
                ],
                [
                    'slug' => $request->slug ?? null,
                    'status' => $request->has('status') ? 'active' : 'inactive',
                    'available_until' => $request->available_until ?? null,
                ]
            );
            
            // Actualizar slug y available_until si se proporcionan
            if ($request->filled('slug')) {
                $model->slug = $request->slug;
            }
            
            if ($request->has('available_until')) {
                $model->available_until = $request->available_until;
            }

            // Si el modelo ya existía, actualizar su status si es necesario
            if ($model->wasRecentlyCreated === false) {
                $model->status = $request->has('status') ? 'active' : 'inactive';
                $model->save();
            }

            // Crear el pricing asociado
            ModelPricing::create([
                'ai_model_id' => $model->id,
                'pricing_type' => $pricingValidated['pricing_type'],
                'unit_definition' => $unitDefinition,
                'effective_from' => $pricingValidated['effective_from'],
                'effective_to' => $pricingValidated['effective_to'] ?? null,
                'markup_percentage' => $request->markup_percentage ? (float) $request->markup_percentage : null,
                'status' => $request->has('pricing_status') ? 'active' : 'inactive',
            ]);

            DB::commit();

            toast()->success('¡Registro exitoso!')->push();
            
            return redirect()->route('ai-models.index');
        } catch (\Exception $e) {
            DB::rollBack();
            toast()->danger('Error al registrar el modelo: ' . $e->getMessage())->push();
            return redirect()->back()->withInput();
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AiModel $aiModel)
    {
        Gate::authorize('haveaccess', 'ai-models.edit');
        
        $providers = Provider::active()->orderBy('name')->get();
        
        // Obtener el pricing actual (el más reciente y activo)
        $currentPricing = $aiModel->getCurrentPricing();
        
        return view('costs.ai-models.edit', compact('aiModel', 'providers', 'currentPricing'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AiModel $aiModel)
    {
        Gate::authorize('haveaccess', 'ai-models.edit');

        // Validación del modelo
        $modelValidated = $request->validate([
            'provider_id' => 'required|exists:providers,id',
            'name' => 'required|string|max:255',
            'model_type' => 'required|in:text,image,video,audio',
            'available_until' => 'nullable|date|after:today',
        ]);

        // Validación del pricing (solo si se envía información de pricing)
        $hasPricingData = $request->has('pricing_type');
        
        if ($hasPricingData) {
            $pricingValidated = $request->validate([
                'pricing_type' => 'required|in:per_token,per_generation,per_second,per_credit',
                'effective_from' => 'required|date',
                'effective_to' => 'nullable|date|after:effective_from',
                'markup_percentage' => 'nullable|numeric|min:0|max:100',
            ]);

            // Validación de campos dinámicos según el tipo de pricing
            // NOTA: Para 'per_token', los precios se configuran como "por millón de tokens"
            $unitDefinition = [];
            if ($request->pricing_type === 'per_token') {
                $request->validate([
                    'input_price' => 'required|numeric|min:0',
                    'output_price' => 'required|numeric|min:0',
                    'citation_price' => 'nullable|numeric|min:0',
                    'reasoning_price' => 'nullable|numeric|min:0',
                    'search_query_price' => 'nullable|numeric|min:0',
                ]);
                $unitDefinition = [
                    'input_price' => (float) $request->input_price,
                    'output_price' => (float) $request->output_price,
                    'unit' => 'token'
                ];
                
                // Agregar precios adicionales opcionales si están presentes
                if ($request->filled('citation_price')) {
                    $unitDefinition['citation_price'] = (float) $request->citation_price;
                }
                if ($request->filled('reasoning_price')) {
                    $unitDefinition['reasoning_price'] = (float) $request->reasoning_price;
                }
                if ($request->filled('search_query_price')) {
                    $unitDefinition['search_query_price'] = (float) $request->search_query_price;
                }
            } elseif ($request->pricing_type === 'per_generation') {
                $request->validate([
                    'price_per_generation' => 'required|numeric|min:0',
                ]);
                $unitDefinition = [
                    'price_per_generation' => (float) $request->price_per_generation,
                    'unit' => 'generation'
                ];
            } elseif ($request->pricing_type === 'per_second') {
                $request->validate([
                    'price_per_second' => 'required|numeric|min:0',
                    'minimum_seconds' => 'required|integer|min:1',
                ]);
                $unitDefinition = [
                    'price_per_second' => (float) $request->price_per_second,
                    'minimum_seconds' => (int) $request->minimum_seconds,
                    'unit' => 'second'
                ];
            } elseif ($request->pricing_type === 'per_credit') {
                $request->validate([
                    'price_per_credit' => 'required|numeric|min:0',
                ]);
                $unitDefinition = [
                    'price_per_credit' => (float) $request->price_per_credit,
                    'unit' => 'credit'
                ];
            }
        }

        try {
            DB::beginTransaction();

            // Actualizar el modelo
            $modelValidated['status'] = $request->has('status') ? 'active' : 'inactive';
            $modelValidated['available_until'] = $request->available_until ?? null;
            $aiModel->update($modelValidated);

            // Si hay datos de pricing, crear o actualizar el pricing
            if ($hasPricingData) {
                $currentPricing = $aiModel->getCurrentPricing();
                
                // Si existe un pricing actual y la fecha efectiva desde es diferente,
                // cerramos el pricing anterior y creamos uno nuevo
                if ($currentPricing && $currentPricing->effective_from->format('Y-m-d') !== $pricingValidated['effective_from']) {
                    // Cerrar el pricing anterior
                    $currentPricing->update([
                        'effective_to' => \Carbon\Carbon::parse($pricingValidated['effective_from'])->subDay()->format('Y-m-d'),
                    ]);
                    
                    // Crear nuevo pricing
                    ModelPricing::create([
                        'ai_model_id' => $aiModel->id,
                        'pricing_type' => $pricingValidated['pricing_type'],
                        'unit_definition' => $unitDefinition,
                        'effective_from' => $pricingValidated['effective_from'],
                        'effective_to' => $pricingValidated['effective_to'] ?? null,
                        'markup_percentage' => $request->markup_percentage ? (float) $request->markup_percentage : null,
                        'status' => $request->has('pricing_status') ? 'active' : 'inactive',
                    ]);
                } else {
                    // Si no hay pricing actual o la fecha es la misma, actualizar el existente
                    if ($currentPricing) {
                        $currentPricing->update([
                            'pricing_type' => $pricingValidated['pricing_type'],
                            'unit_definition' => $unitDefinition,
                            'effective_from' => $pricingValidated['effective_from'],
                            'effective_to' => $pricingValidated['effective_to'] ?? null,
                            'markup_percentage' => $request->markup_percentage ? (float) $request->markup_percentage : null,
                            'status' => $request->has('pricing_status') ? 'active' : 'inactive',
                        ]);
                    } else {
                        // Si no existe pricing, crear uno nuevo
                        ModelPricing::create([
                            'ai_model_id' => $aiModel->id,
                            'pricing_type' => $pricingValidated['pricing_type'],
                            'unit_definition' => $unitDefinition,
                            'effective_from' => $pricingValidated['effective_from'],
                            'effective_to' => $pricingValidated['effective_to'] ?? null,
                            'markup_percentage' => $request->markup_percentage ? (float) $request->markup_percentage : null,
                            'status' => $request->has('pricing_status') ? 'active' : 'inactive',
                        ]);
                    }
                }
            }

            DB::commit();

            toast()->success('¡Actualización exitosa!')->push();
            
            return redirect()->route('ai-models.index');
        } catch (\Exception $e) {
            DB::rollBack();
            toast()->danger('Error al actualizar el modelo: ' . $e->getMessage())->push();
            return redirect()->back()->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AiModel $aiModel)
    {
        Gate::authorize('haveaccess', 'ai-models.destroy');

        // Validar que no tenga registros de uso
        $hasUsage = \App\Models\UsageRecord::whereHas('modelPricing', function ($query) use ($aiModel) {
            $query->where('ai_model_id', $aiModel->id);
        })->exists();

        if ($hasUsage) {
            toast()->danger('No se puede eliminar el modelo porque tiene registros de uso asociados.')->push();
            return redirect()->route('ai-models.index');
        }

        // Validar que no tenga precios asociados
        if ($aiModel->pricings()->count() > 0) {
            toast()->danger('No se puede eliminar el modelo porque tiene precios asociados.')->push();
            return redirect()->route('ai-models.index');
        }

        if ($aiModel->delete()) {
            toast()->success('¡Eliminación exitosa!')->push();
        } else {
            toast()->danger('¡Eliminación erronea!')->push();
        }
        
        return redirect()->route('ai-models.index');
    }
}
