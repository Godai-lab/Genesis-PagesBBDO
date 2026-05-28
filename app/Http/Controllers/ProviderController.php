<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProviderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize('haveaccess', 'providers.index');
        
        $search = $request->search;
        $status = $request->status;
        
        $query = Provider::query();

        // Filtro por status
        if ($status) {
            $query->where('status', $status);
        }

        // Búsqueda por nombre
        if ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $providers = $query->orderBy('name')->paginate(5)->withQueryString();

        return view('costs.providers.index', compact('providers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('haveaccess', 'providers.create');
        return view('costs.providers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('haveaccess', 'providers.create');

        $validated = $request->validate([
            'name' => 'required|string|unique:providers,name|max:255',
        ]);

        $validated['status'] = $request->has('status') ? 'active' : 'inactive';

        Provider::create($validated);

        toast()->success('¡Registro exitoso!')->push();
        
        return redirect()->route('providers.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Provider $provider)
    {
        return;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Provider $provider)
    {
        Gate::authorize('haveaccess', 'providers.edit');
        return view('costs.providers.edit', compact('provider'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Provider $provider)
    {
        Gate::authorize('haveaccess', 'providers.edit');

        $validated = $request->validate([
            'name' => 'required|string|unique:providers,name,' . $provider->id . '|max:255',
        ]);

        $validated['status'] = $request->has('status') ? 'active' : 'inactive';

        $provider->update($validated);

        toast()->success('¡Actualización exitosa!')->push();
        
        return redirect()->route('providers.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Provider $provider)
    {
        Gate::authorize('haveaccess', 'providers.destroy');

        // Validar que no tenga modelos asociados
        if ($provider->models()->count() > 0) {
            toast()->danger('No se puede eliminar el proveedor porque tiene modelos asociados.')->push();
            return redirect()->route('providers.index');
        }

        if ($provider->delete()) {
            toast()->success('¡Eliminación exitosa!')->push();
        } else {
            toast()->danger('¡Eliminación erronea!')->push();
        }
        
        return redirect()->route('providers.index');
    }
}
