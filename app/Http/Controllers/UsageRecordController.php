<?php

namespace App\Http\Controllers;

use App\Models\UsageRecord;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UsageRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     * Los registros son inmutables, solo lectura.
     */
    public function index(Request $request): JsonResponse
    {
        $query = UsageRecord::with(['user', 'account', 'modelPricing.model.provider']);

        // Filtro por usuario
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filtro por cuenta
        if ($request->has('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        // Filtro por modelo
        if ($request->has('model_id')) {
            $query->byModel($request->model_id);
        }

        // Filtro por rango de fechas
        if ($request->has('date_from') && $request->has('date_to')) {
            $query->whereBetween('created_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ]);
        } elseif ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from . ' 00:00:00');
        } elseif ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $records = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return response()->json($records);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $record = UsageRecord::with(['user', 'account', 'modelPricing.model.provider'])
            ->findOrFail($id);

        return response()->json($record);
    }
}
