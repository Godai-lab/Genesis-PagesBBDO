<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Usuarios
            ['name' => 'Listar usuarios',            'slug' => 'user.index',                  'description' => 'El usuario puede listar los usuarios'],
            ['name' => 'Crear usuarios',             'slug' => 'user.create',                 'description' => 'El usuario puede crear nuevos usuarios'],
            ['name' => 'Editar usuarios',            'slug' => 'user.edit',                   'description' => 'El usuario puede editar datos de los usuarios'],
            ['name' => 'Eliminar usuarios',          'slug' => 'user.destroy',                'description' => 'El usuario puede eliminar usuarios'],

            // Roles
            ['name' => 'Listar roles',               'slug' => 'role.index',                  'description' => 'El usuario puede listar los roles'],
            ['name' => 'Crear roles',                'slug' => 'role.create',                 'description' => 'El usuario puede crear nuevos roles'],
            ['name' => 'Editar roles',               'slug' => 'role.edit',                   'description' => 'El usuario puede editar datos de los roles'],
            ['name' => 'Eliminar roles',             'slug' => 'role.destroy',                'description' => 'El usuario puede eliminar roles'],

            // Cuentas
            ['name' => 'Listar Cuentas',             'slug' => 'account.index',               'description' => 'El usuario puede listar las Cuentas'],
            ['name' => 'Crear Cuentas',              'slug' => 'account.create',              'description' => 'El usuario puede crear nuevas Cuentas'],
            ['name' => 'Editar Cuentas',             'slug' => 'account.edit',                'description' => 'El usuario puede editar datos de las Cuentas'],
            ['name' => 'Eliminar Cuentas',           'slug' => 'account.destroy',             'description' => 'El usuario puede eliminar Cuentas'],

            // Generados
            ['name' => 'Listar Generados',           'slug' => 'generated.index',             'description' => 'El usuario puede listar los Generados'],
            ['name' => 'Crear Generados',            'slug' => 'generated.create',            'description' => 'El usuario puede crear nuevos Generados'],
            ['name' => 'Editar Generados',           'slug' => 'generated.edit',              'description' => 'El usuario puede editar datos de los Generados'],
            ['name' => 'Eliminar Generados',         'slug' => 'generated.destroy',           'description' => 'El usuario puede eliminar Generados'],

            // Herramientas principales
            ['name' => 'Permitir Generar Brief',                'slug' => 'brief.index',                  'description' => 'El usuario puede usar brief'],
            ['name' => 'Permitir Generar Génesis',              'slug' => 'genesis.index',                'description' => 'El usuario puede usar genesis'],
            ['name' => 'Permitir Asistente Creativo',           'slug' => 'asistentecreativo.index',      'description' => 'El usuario puede usar asistente creativo'],
            ['name' => 'Permitir Asistente Social Media',       'slug' => 'asistentesocialmedia.index',   'description' => 'El usuario puede usar asistente social media'],
            ['name' => 'Permitir Asistente Gráfica',            'slug' => 'asistentegrafica.index',       'description' => 'El usuario puede usar asistente gráfica'],
            ['name' => 'Permitir Generar Investigación',        'slug' => 'investigacion.index',          'description' => 'El usuario puede usar la herramienta de investigación'],

            // Chat, imagen, video y prompt del generador
            ['name' => 'Permitir Usar Chat',                    'slug' => 'chat.index',                   'description' => 'El usuario puede usar el chat del sistema'],
            ['name' => 'Permitir Generador de Prompts',         'slug' => 'generador.prompt',             'description' => 'El usuario puede usar el generador de prompts'],
            ['name' => 'Permitir Generar Imagen',               'slug' => 'generador.imagen',             'description' => 'El usuario puede usar el generador de imágenes'],
            ['name' => 'Permitir Generar Video',                'slug' => 'generador.video',              'description' => 'El usuario puede generar y editar videos en el generador'],

            // Edición de imagen
            ['name' => 'Permitir Edición de Imagen',            'slug' => 'edit.image',                   'description' => 'El usuario puede editar imágenes en la herramienta de generación'],
            ['name' => 'Permitir Expansión de Imagen',          'slug' => 'edit.expand.image',            'description' => 'El usuario puede expandir imágenes en la herramienta de generación'],
            ['name' => 'Permitir Rellenar Imagen',              'slug' => 'edit.fill.image',              'description' => 'El usuario puede rellenar áreas faltantes en una imagen'],

            // Proveedores IA
            ['name' => 'Listar proveedores IA',      'slug' => 'providers.index',             'description' => 'Ver y listar proveedores de IA'],
            ['name' => 'Crear proveedores IA',       'slug' => 'providers.create',            'description' => 'Crear proveedores de IA'],
            ['name' => 'Editar proveedores IA',      'slug' => 'providers.edit',              'description' => 'Editar proveedores de IA'],
            ['name' => 'Eliminar proveedores IA',    'slug' => 'providers.destroy',           'description' => 'Eliminar proveedores de IA'],

            // Modelos IA
            ['name' => 'Listar modelos IA',          'slug' => 'ai-models.index',             'description' => 'Ver y listar modelos de IA'],
            ['name' => 'Crear modelos IA',           'slug' => 'ai-models.create',            'description' => 'Crear modelos de IA'],
            ['name' => 'Editar modelos IA',          'slug' => 'ai-models.edit',              'description' => 'Editar modelos de IA'],
            ['name' => 'Eliminar modelos IA',        'slug' => 'ai-models.destroy',           'description' => 'Eliminar modelos de IA'],

            // Métricas y créditos
            ['name' => 'Ver métricas de uso',                   'slug' => 'usage-records.index',          'description' => 'Ver métricas y registros de uso de IA'],
            ['name' => 'Gestionar créditos por cuenta',         'slug' => 'costs.account-credits',        'description' => 'Ver y configurar límites de créditos mensuales por cuenta'],
        ];

        foreach ($permissions as $data) {
            Permission::firstOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
