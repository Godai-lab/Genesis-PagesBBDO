<?php

namespace App\Livewire\Costs;

use App\Models\UsageRecord;
use App\Models\Account;
use App\Models\User;
use App\Models\AiModel;
use App\Supports\CreditHelper;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

#[Layout('layouts.app')]
class UsageMetrics extends Component
{
    public $dateFrom = null;
    public $dateTo = null;
    public $selectedAccountId = null;
    public $selectedUserId = null;
    public $selectedTool = null; // Filtro por herramienta
    
    public function mount()
    {
        Gate::authorize('haveaccess', 'usage-records.index');
        
        // Establecer fechas por defecto: este mes
        $this->dateFrom = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = Carbon::now()->endOfMonth()->format('Y-m-d');
    }

    public function render()
    {
        $query = UsageRecord::query();
        
        // Filtrar por cuenta si está seleccionada
        if ($this->selectedAccountId) {
            $query->where('account_id', $this->selectedAccountId);
        }
        
        // Filtrar por usuario si está seleccionado
        if ($this->selectedUserId) {
            $query->where('user_id', $this->selectedUserId);
        }
        
        // Filtrar por herramienta si está seleccionada
        // Nota: Ahora los request_type están simplificados ('Genesis', 'Brief', etc.)
        if ($this->selectedTool) {
            $query->where('request_type', $this->selectedTool);
        }
        
        // Filtrar por rango de fechas
        $dateRange = $this->getDateRange();
        if ($dateRange) {
            $query->whereBetween('created_at', $dateRange);
        }
        
        // Métricas generales
        $totalRecords = (clone $query)->count();
        $totalCostReal = (clone $query)->sum('cost_total_usd');
        $totalCostFinal = (clone $query)->sum('cost_final_user_usd');
        $totalRevenue = $totalCostFinal - $totalCostReal;
        
        // Calcular total de tokens (desde usage_metrics y processes_detail)
        $usageRecords = (clone $query)->get();
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        foreach ($usageRecords as $record) {
            // Si tiene processes_detail, extraer tokens de todos los procesos
            if ($record->processes_detail && isset($record->processes_detail['processes'])) {
                foreach ($record->processes_detail['processes'] as $process) {
                    $processMetrics = $process['usage_metrics'] ?? [];
                    if (isset($processMetrics['tokens'])) {
                        $totalInputTokens += $processMetrics['tokens']['input'] ?? 0;
                        $totalOutputTokens += $processMetrics['tokens']['output'] ?? 0;
                    }
                }
            } else {
                // Registro simple, usar usage_metrics directamente
                $metrics = $record->usage_metrics ?? [];
                if (isset($metrics['tokens'])) {
                    $totalInputTokens += $metrics['tokens']['input'] ?? 0;
                    $totalOutputTokens += $metrics['tokens']['output'] ?? 0;
                }
            }
        }
        $totalTokens = $totalInputTokens + $totalOutputTokens;
        
        // Métricas por modelo (top 5)
        // Extraer modelos tanto de registros simples como de processes_detail
        $modelStats = [];
        foreach ($usageRecords as $record) {
            // Si tiene processes_detail, extraer modelos de todos los procesos
            if ($record->processes_detail && isset($record->processes_detail['processes'])) {
                foreach ($record->processes_detail['processes'] as $process) {
                    $modelName = $process['model'] ?? 'Desconocido';
                    $processCost = $process['cost_final_user_usd'] ?? 0;
                    
                    if (!isset($modelStats[$modelName])) {
                        // Buscar el modelo en la BD para obtener el proveedor
                        $aiModel = \App\Models\AiModel::with('provider')
                            ->where(function($query) use ($modelName) {
                                $query->where('name', $modelName)
                                      ->orWhere('slug', $modelName);
                            })
                            ->first();
                        
                        $modelStats[$modelName] = [
                            'name' => $modelName,
                            'provider' => $aiModel && $aiModel->provider ? $aiModel->provider->name : 'Desconocido',
                            'count' => 0,
                            'total_cost' => 0,
                            'available_until' => $aiModel ? $aiModel->available_until : null,
                            'days_until_expiration' => $aiModel ? $aiModel->days_until_expiration : null,
                            'availability_status' => $aiModel ? $aiModel->availability_status : null,
                        ];
                    }
                    $modelStats[$modelName]['count']++;
                    $modelStats[$modelName]['total_cost'] += $processCost;
                }
            } else {
                // Registro simple, usar model_pricing_id
                $model = $record->modelPricing?->model;
                $modelName = $model ? $model->name : 'Desconocido';
                $recordCost = $record->cost_final_user_usd ?? 0;
                
                if (!isset($modelStats[$modelName])) {
                    $modelStats[$modelName] = [
                        'name' => $modelName,
                        'provider' => $model ? $model->provider->name : 'Desconocido',
                        'count' => 0,
                        'total_cost' => 0,
                        'available_until' => $model ? $model->available_until : null,
                        'days_until_expiration' => $model ? $model->days_until_expiration : null,
                        'availability_status' => $model ? $model->availability_status : null,
                    ];
                }
                $modelStats[$modelName]['count']++;
                $modelStats[$modelName]['total_cost'] += $recordCost;
            }
        }
        
        // Ordenar por costo total (mantener TODOS los modelos, no solo top 5)
        $topModels = collect($modelStats)
            ->sortByDesc('total_cost')
            ->values()
            ->map(function ($item) {
                return [
                    'name' => $item['name'],
                    'provider' => $item['provider'] ?? 'Desconocido',
                    'count' => $item['count'],
                    'total_cost' => (float) $item['total_cost'],
                    'available_until' => $item['available_until'],
                    'days_until_expiration' => $item['days_until_expiration'],
                    'availability_status' => $item['availability_status'],
                ];
            });
        
        // Métricas por cuenta (top 5)
        $topAccounts = (clone $query)
            ->selectRaw('account_id, COUNT(*) as count, SUM(cost_final_user_usd) as total_cost')
            ->groupBy('account_id')
            ->orderBy('total_cost', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                if (!$item->account_id) {
                    return [
                        'name' => 'Sin cuenta asignada',
                        'count' => $item->count,
                        'total_cost' => (float) $item->total_cost,
                        'effective_limit' => null,
                        'usage_credits' => CreditHelper::usdToCredits((float) $item->total_cost),
                        'remaining_credits' => null,
                        'percentage_used' => 0,
                    ];
                }
                $account = Account::with('creditLimit')->find($item->account_id);
                
                $effectiveLimit = $account ? $account->getEffectiveCreditLimit() : null;
                $usageCredits = CreditHelper::usdToCredits((float) $item->total_cost);
                $remainingCredits = $account ? $account->getRemainingCredits() : null;
                $percentageUsed = $effectiveLimit ? ($usageCredits / $effectiveLimit) * 100 : 0;
                
                return [
                    'name' => $account ? $account->name : 'Cuenta eliminada',
                    'count' => $item->count,
                    'total_cost' => (float) $item->total_cost,
                    'effective_limit' => $effectiveLimit,
                    'usage_credits' => $usageCredits,
                    'remaining_credits' => $remainingCredits,
                    'percentage_used' => $percentageUsed,
                ];
            });
        
        // Métricas por herramienta (TODAS, no solo top 5)
        // Ahora los request_type están simplificados ('Genesis', 'Brief', etc.)
        $allTools = (clone $query)
            ->whereNotNull('request_type')
            ->get()
            ->groupBy('request_type')
            ->map(function ($group, $toolName) {
                return [
                    'name' => $toolName ?: 'Sin especificar',
                    'count' => $group->count(),
                    'total_cost' => (float) $group->sum('cost_final_user_usd'),
                    'total_cost_real' => (float) $group->sum('cost_total_usd'),
                    'avg_cost' => $group->count() > 0 ? (float) $group->sum('cost_final_user_usd') / $group->count() : 0,
                ];
            })
            ->sortByDesc('total_cost')
            ->values();
        
        // Top 5 herramientas (para mantener compatibilidad)
        $topTools = $allTools->take(5);
        
        // Comparación con período anterior
        $previousPeriodRange = $this->getPreviousPeriodRange();
        $previousTotalCost = 0;
        if ($previousPeriodRange) {
            $previousQuery = UsageRecord::query();
            if ($this->selectedAccountId) {
                $previousQuery->where('account_id', $this->selectedAccountId);
            }
            if ($this->selectedUserId) {
                $previousQuery->where('user_id', $this->selectedUserId);
            }
            if ($this->selectedTool) {
                $previousQuery->where('request_type', $this->selectedTool);
            }
            $previousTotalCost = $previousQuery
                ->whereBetween('created_at', $previousPeriodRange)
                ->sum('cost_final_user_usd');
        }
        
        $growthPercentage = $previousTotalCost > 0 
            ? (($totalCostFinal - $previousTotalCost) / $previousTotalCost) * 100 
            : 0;
        
        // Obtener cuentas para el filtro
        $accounts = Account::fullaccess()->orderBy('name')->get();
        
        // Obtener usuarios disponibles para el filtro
        // Si hay cuenta seleccionada, mostrar solo usuarios de esa cuenta
        // Si no, mostrar todos los usuarios que tienen registros de uso
        $availableUsers = $this->getAvailableUsers();
        
        // Métricas por usuario (solo cuando hay cuenta seleccionada)
        $usersByAccount = collect([]);
        if ($this->selectedAccountId) {
            $usersByAccount = (clone $query)
                ->where('account_id', $this->selectedAccountId)
                ->get()
                ->groupBy('user_id')
                ->map(function ($group, $userId) {
                    $user = User::find($userId);
                    return [
                        'id' => $userId,
                        'name' => $user ? $user->name : 'Usuario eliminado',
                        'email' => $user ? $user->email : 'N/A',
                        'count' => $group->count(),
                        'total_cost_real' => (float) $group->sum('cost_total_usd'),
                        'total_cost_final' => (float) $group->sum('cost_final_user_usd'),
                        'avg_cost' => $group->count() > 0 ? (float) $group->sum('cost_final_user_usd') / $group->count() : 0,
                    ];
                })
                ->sortByDesc('total_cost_final')
                ->values();
        }
        
        // Obtener lista de herramientas disponibles para el filtro
        // Obtener herramientas únicas desde los registros
        $availableTools = UsageRecord::whereNotNull('request_type')
            ->distinct()
            ->pluck('request_type')
            ->filter()
            ->mapWithKeys(function ($tool) {
                return [$tool => $tool];
            })
            ->toArray();
        
        // Si no hay herramientas, usar las por defecto
        if (empty($availableTools)) {
            $availableTools = [
                'Genesis' => 'Genesis',
                'Brief' => 'Brief',
                'Asistente Creativo' => 'Asistente Creativo',
                'Asistente Social Media' => 'Asistente Social Media',
                'Investigacion' => 'Investigacion',
            ];
        }
        
        return view('livewire.costs.usage-metrics.index', [
            'totalRecords' => $totalRecords,
            'totalCostReal' => $totalCostReal,
            'totalCostFinal' => $totalCostFinal,
            'totalRevenue' => $totalRevenue,
            'totalTokens' => $totalTokens,
            'totalInputTokens' => $totalInputTokens,
            'totalOutputTokens' => $totalOutputTokens,
            'topModels' => $topModels,
            'topAccounts' => $topAccounts,
            'topTools' => $topTools,
            'allTools' => $allTools,
            'usersByAccount' => $usersByAccount,
            'growthPercentage' => $growthPercentage,
            'accounts' => $accounts,
            'availableUsers' => $availableUsers,
            'availableTools' => $availableTools,
        ]);
    }
    
    /**
     * Obtiene los usuarios disponibles según la cuenta seleccionada
     */
    private function getAvailableUsers()
    {
        if ($this->selectedAccountId) {
            // Si hay cuenta seleccionada, obtener usuarios de esa cuenta
            $account = Account::find($this->selectedAccountId);
            if ($account) {
                return $account->users()
                    ->orderBy('name')
                    ->get()
                    ->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                        ];
                    });
            }
            return collect([]);
        }
        
        // Si no hay cuenta seleccionada, obtener todos los usuarios que tienen registros de uso
        // en el rango de fechas seleccionado (para optimizar, solo mostrar usuarios con registros)
        $dateRange = $this->getDateRange();
        $userIds = UsageRecord::query()
            ->when($dateRange, function ($query) use ($dateRange) {
                $query->whereBetween('created_at', $dateRange);
            })
            ->distinct()
            ->pluck('user_id')
            ->filter()
            ->unique();
        
        if ($userIds->isEmpty()) {
            return collect([]);
        }
        
        return User::whereIn('id', $userIds)
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            });
    }
    
    private function getDateRange()
    {
        // Si no hay fechas seleccionadas, usar este mes por defecto
        if (!$this->dateFrom || !$this->dateTo) {
            $now = Carbon::now();
            return [
                $now->copy()->startOfMonth()->startOfDay(),
                $now->copy()->endOfMonth()->endOfDay()
            ];
        }
        
        try {
            $from = Carbon::parse($this->dateFrom)->startOfDay();
            $to = Carbon::parse($this->dateTo)->endOfDay();
            
            // Validar que la fecha de inicio sea anterior o igual a la fecha de fin
            if ($from->gt($to)) {
                // Si la fecha de inicio es mayor, intercambiar
                return [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }
            
            return [$from, $to];
        } catch (\Exception $e) {
            // Si hay error al parsear, usar este mes por defecto
            $now = Carbon::now();
            return [
                $now->copy()->startOfMonth()->startOfDay(),
                $now->copy()->endOfMonth()->endOfDay()
            ];
        }
    }
    
    private function getPreviousPeriodRange()
    {
        // Calcular el período anterior basado en el rango actual
        $currentRange = $this->getDateRange();
        if (!$currentRange) {
            return null;
        }
        
        $from = $currentRange[0];
        $to = $currentRange[1];
        
        // Calcular la duración del período
        $daysDiff = $from->diffInDays($to);
        
        // Calcular el período anterior (misma duración, antes del período actual)
        $previousTo = $from->copy()->subDay()->endOfDay();
        $previousFrom = $previousTo->copy()->subDays($daysDiff)->startOfDay();
        
        return [$previousFrom, $previousTo];
    }
    
    public function updatedDateFrom()
    {
        // Validar que dateFrom no sea mayor que dateTo
        if ($this->dateFrom && $this->dateTo) {
            try {
                $from = Carbon::parse($this->dateFrom);
                $to = Carbon::parse($this->dateTo);
                if ($from->gt($to)) {
                    // Si dateFrom es mayor, ajustar dateTo
                    $this->dateTo = $this->dateFrom;
                }
            } catch (\Exception $e) {
                // Ignorar errores de parsing
            }
        }
    }
    
    public function updatedDateTo()
    {
        // Validar que dateTo no sea menor que dateFrom
        if ($this->dateFrom && $this->dateTo) {
            try {
                $from = Carbon::parse($this->dateFrom);
                $to = Carbon::parse($this->dateTo);
                if ($to->lt($from)) {
                    // Si dateTo es menor, ajustar dateFrom
                    $this->dateFrom = $this->dateTo;
                }
            } catch (\Exception $e) {
                // Ignorar errores de parsing
            }
        }
    }
    
    public function updatedSelectedAccountId()
    {
        // Cuando cambia la cuenta, resetear el usuario seleccionado
        // porque los usuarios disponibles cambiarán
        $this->selectedUserId = null;
    }
    
    public function updatedSelectedUserId()
    {
        // Livewire automáticamente re-renderizará cuando cambie el usuario
    }
    
    public function updatedSelectedTool()
    {
        // Livewire automáticamente re-renderizará cuando cambie la herramienta
    }
    
    /**
     * Establece un período rápido predefinido
     */
    public function setQuickPeriod($period)
    {
        $now = Carbon::now();
        
        switch ($period) {
            case 'today':
                $this->dateFrom = $now->format('Y-m-d');
                $this->dateTo = $now->format('Y-m-d');
                break;
            case 'week':
                $this->dateFrom = $now->copy()->startOfWeek()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfWeek()->format('Y-m-d');
                break;
            case 'month':
                $this->dateFrom = $now->copy()->startOfMonth()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfMonth()->format('Y-m-d');
                break;
            case 'lastMonth':
                $this->dateFrom = $now->copy()->subMonth()->startOfMonth()->format('Y-m-d');
                $this->dateTo = $now->copy()->subMonth()->endOfMonth()->format('Y-m-d');
                break;
            case 'year':
                $this->dateFrom = $now->copy()->startOfYear()->format('Y-m-d');
                $this->dateTo = $now->copy()->endOfYear()->format('Y-m-d');
                break;
            case 'all':
                // Para "todo el tiempo", establecer fechas muy amplias
                $this->dateFrom = '2020-01-01';
                $this->dateTo = $now->copy()->addYear()->format('Y-m-d');
                break;
        }
    }
    
    /**
     * Resetea todos los filtros a sus valores por defecto
     */
    public function resetFilters()
    {
        $this->selectedAccountId = null;
        $this->selectedUserId = null;
        $this->selectedTool = null;
        $now = Carbon::now();
        $this->dateFrom = $now->copy()->startOfMonth()->format('Y-m-d');
        $this->dateTo = $now->copy()->endOfMonth()->format('Y-m-d');
    }
    
    /**
     * Formatea un valor monetario con precisión adecuada según su tamaño
     * Para valores muy pequeños, muestra más decimales para evitar mostrar $0.00
     * 
     * @param float $value
     * @return string
     */
    public function formatCurrency($value)
    {
        $value = (float) $value;
        
        // Si es 0, mostrar 0.00
        if ($value == 0) {
            return number_format($value, 2);
        }
        
        // Si es >= 1, mostrar 2 decimales
        if ($value >= 1) {
            return number_format($value, 2);
        }
        
        // Si es >= 0.01, mostrar 4 decimales
        if ($value >= 0.01) {
            return number_format($value, 4);
        }
        
        // Si es < 0.01, mostrar 6 decimales
        return number_format($value, 6);
    }
}

