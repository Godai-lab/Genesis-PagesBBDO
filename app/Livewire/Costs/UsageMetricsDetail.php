<?php

namespace App\Livewire\Costs;

use App\Models\UsageRecord;
use App\Models\Account;
use App\Models\User;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

#[Layout('layouts.app')]
class UsageMetricsDetail extends Component
{
    public $userId;
    public $accountId;
    public $dateFrom;
    public $dateTo;
    
    // Información del usuario y cuenta
    public $user;
    public $account;
    
    public function mount($userId, $accountId, $dateFrom = null, $dateTo = null)
    {
        Gate::authorize('haveaccess', 'usage-records.index');
        
        $this->userId = $userId;
        $this->accountId = $accountId;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        
        // Cargar información del usuario y cuenta
        $this->user = User::find($userId);
        $this->account = Account::find($accountId);
        
        if (!$this->user || !$this->account) {
            abort(404, 'Usuario o cuenta no encontrada');
        }
    }

    public function render()
    {
        // Construir query base
        $query = UsageRecord::query()
            ->where('user_id', $this->userId)
            ->where('account_id', $this->accountId)
            ->with(['generated', 'modelPricing.model']); // Cargar relaciones para evitar N+1
        
        // Aplicar filtro de fechas si existen
        if ($this->dateFrom && $this->dateTo) {
            try {
                $from = Carbon::parse($this->dateFrom)->startOfDay();
                $to = Carbon::parse($this->dateTo)->endOfDay();
                $query->whereBetween('created_at', [$from, $to]);
            } catch (\Exception $e) {
                // Si hay error al parsear, ignorar el filtro de fechas
            }
        }
        
        // Obtener todos los registros de uso ordenados por fecha descendente
        $usageRecords = $query->orderBy('created_at', 'desc')->get();
        
        // Calcular métricas totales
        $totalRecords = $usageRecords->count();
        $totalCostReal = $usageRecords->sum('cost_total_usd');
        $totalCostFinal = $usageRecords->sum('cost_final_user_usd');
        
        // Agrupar por herramienta para resumen
        $toolsSummary = $usageRecords->groupBy('request_type')->map(function ($group, $toolName) {
            return [
                'name' => $toolName ?: 'Sin especificar',
                'count' => $group->count(),
                'total_cost' => (float) $group->sum('cost_final_user_usd'),
                'percentage' => 0, // Se calculará después
            ];
        })->sortByDesc('total_cost');
        
        // Calcular porcentajes
        if ($totalCostFinal > 0) {
            $toolsSummary = $toolsSummary->map(function ($tool) use ($totalCostFinal) {
                $tool['percentage'] = ($tool['total_cost'] / $totalCostFinal) * 100;
                return $tool;
            });
        }
        
        // Preparar datos detallados para la tabla
        $detailedRecords = $usageRecords->map(function ($record) {
            $processesDetail = $record->processes_detail ?? null;
            
            // Enriquecer procesos con información de disponibilidad de modelos
            if ($processesDetail && isset($processesDetail['processes'])) {
                foreach ($processesDetail['processes'] as &$process) {
                    if (isset($process['model'])) {
                        $modelName = $process['model'];
                        $aiModel = \App\Models\AiModel::where(function($query) use ($modelName) {
                            $query->where('name', $modelName)->orWhere('slug', $modelName);
                        })->first();
                        
                        $process['model_availability'] = [
                            'available_until' => $aiModel ? $aiModel->available_until : null,
                            'days_until_expiration' => $aiModel ? $aiModel->days_until_expiration : null,
                            'availability_status' => $aiModel ? $aiModel->availability_status : null,
                        ];
                    }
                }
            }
            
            return [
                'id' => $record->id,
                'date' => $record->created_at,
                'tool' => $record->request_type ?: 'Sin especificar',
                'generated_id' => $record->generated_id,
                'generated_name' => $record->generated ? $record->generated->name : null,
                'cost_real' => (float) $record->cost_total_usd,
                'cost_final' => (float) $record->cost_final_user_usd,
                'tokens_input' => $this->extractInputTokens($record),
                'tokens_output' => $this->extractOutputTokens($record),
                'duration' => $record->duration ?? null,
                'models' => $this->extractModels($record),
                'has_processes' => $record->processes_detail && isset($record->processes_detail['processes']) && count($record->processes_detail['processes']) > 0,
                'processes_detail' => $processesDetail,
            ];
        });
        
        return view('livewire.costs.usage-metrics.detail', [
            'user' => $this->user,
            'account' => $this->account,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'totalRecords' => $totalRecords,
            'totalCostReal' => $totalCostReal,
            'totalCostFinal' => $totalCostFinal,
            'toolsSummary' => $toolsSummary,
            'detailedRecords' => $detailedRecords,
        ]);
    }
    
    /**
     * Extrae tokens de entrada del registro
     */
    private function extractInputTokens($record)
    {
        $total = 0;
        
        // Si tiene processes_detail, sumar todos los procesos
        if ($record->processes_detail && isset($record->processes_detail['processes'])) {
            foreach ($record->processes_detail['processes'] as $process) {
                $processMetrics = $process['usage_metrics'] ?? [];
                if (isset($processMetrics['tokens']['input'])) {
                    $total += $processMetrics['tokens']['input'];
                }
            }
        } else {
            // Registro simple, usar usage_metrics directamente
            $metrics = $record->usage_metrics ?? [];
            if (isset($metrics['tokens']['input'])) {
                $total = $metrics['tokens']['input'];
            }
        }
        
        return $total;
    }
    
    /**
     * Extrae tokens de salida del registro
     */
    private function extractOutputTokens($record)
    {
        $total = 0;
        
        // Si tiene processes_detail, sumar todos los procesos
        if ($record->processes_detail && isset($record->processes_detail['processes'])) {
            foreach ($record->processes_detail['processes'] as $process) {
                $processMetrics = $process['usage_metrics'] ?? [];
                if (isset($processMetrics['tokens']['output'])) {
                    $total += $processMetrics['tokens']['output'];
                }
            }
        } else {
            // Registro simple, usar usage_metrics directamente
            $metrics = $record->usage_metrics ?? [];
            if (isset($metrics['tokens']['output'])) {
                $total = $metrics['tokens']['output'];
            }
        }
        
        return $total;
    }
    
    /**
     * Extrae los modelos usados en el registro con información de disponibilidad
     */
    private function extractModels($record)
    {
        $models = [];
        
        // Si tiene processes_detail, extraer modelos de todos los procesos
        if ($record->processes_detail && isset($record->processes_detail['processes'])) {
            foreach ($record->processes_detail['processes'] as $process) {
                if (isset($process['model'])) {
                    $modelName = $process['model'];
                    
                    // Buscar información del modelo en BD
                    $aiModel = \App\Models\AiModel::where(function($query) use ($modelName) {
                        $query->where('name', $modelName)->orWhere('slug', $modelName);
                    })->first();
                    
                    $models[] = [
                        'name' => $modelName,
                        'available_until' => $aiModel ? $aiModel->available_until : null,
                        'days_until_expiration' => $aiModel ? $aiModel->days_until_expiration : null,
                        'availability_status' => $aiModel ? $aiModel->availability_status : null,
                    ];
                }
            }
            // Eliminar duplicados basados en el nombre
            $models = collect($models)->unique('name')->values()->toArray();
        } else {
            // Registro simple, usar la relación modelPricing
            if ($record->modelPricing && $record->modelPricing->model) {
                $model = $record->modelPricing->model;
                $models[] = [
                    'name' => $model->name,
                    'available_until' => $model->available_until,
                    'days_until_expiration' => $model->days_until_expiration,
                    'availability_status' => $model->availability_status,
                ];
            }
        }
        
        return $models;
    }
    
    /**
     * Formatea un valor monetario con precisión adecuada según su tamaño
     */
    public function formatCurrency($value)
    {
        $value = (float) $value;
        
        if ($value == 0) {
            return number_format($value, 2);
        }
        
        if ($value >= 1) {
            return number_format($value, 2);
        }
        
        if ($value >= 0.01) {
            return number_format($value, 4);
        }
        
        return number_format($value, 6);
    }
    
    /**
     * Volver a la vista principal
     */
    public function goBack()
    {
        return redirect()->route('usage-metrics');
    }
}
