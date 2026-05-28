<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Permisos para el módulo de gestión de uso (proveedores, modelos, métricas).
 * Los usuarios con full_access en su rol no necesitan estos permisos.
 */
class UsageManagementPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $slugs = [
            ['name' => 'Listar proveedores IA', 'slug' => 'providers.index', 'description' => 'Ver y listar proveedores de IA'],
            ['name' => 'Crear proveedores IA', 'slug' => 'providers.create', 'description' => 'Crear proveedores de IA'],
            ['name' => 'Editar proveedores IA', 'slug' => 'providers.edit', 'description' => 'Editar proveedores de IA'],
            ['name' => 'Eliminar proveedores IA', 'slug' => 'providers.destroy', 'description' => 'Eliminar proveedores de IA'],
            ['name' => 'Listar modelos IA', 'slug' => 'ai-models.index', 'description' => 'Ver y listar modelos de IA'],
            ['name' => 'Crear modelos IA', 'slug' => 'ai-models.create', 'description' => 'Crear modelos de IA'],
            ['name' => 'Editar modelos IA', 'slug' => 'ai-models.edit', 'description' => 'Editar modelos de IA'],
            ['name' => 'Eliminar modelos IA', 'slug' => 'ai-models.destroy', 'description' => 'Eliminar modelos de IA'],
            ['name' => 'Ver métricas de uso', 'slug' => 'usage-records.index', 'description' => 'Ver métricas y registros de uso de IA'],
            ['name' => 'Gestionar créditos por cuenta', 'slug' => 'costs.account-credits', 'description' => 'Ver y configurar límites de créditos mensuales por cuenta'],
        ];

        $permissionIds = [];
        foreach ($slugs as $data) {
            $permission = Permission::firstOrCreate(
                ['slug' => $data['slug']],
                $data
            );
            $permissionIds[] = $permission->id;
        }

        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole) {
            $adminRole->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
