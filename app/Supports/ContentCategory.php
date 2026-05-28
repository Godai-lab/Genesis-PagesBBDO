<?php

namespace App\Supports;

final class ContentCategory
{
    /**
     * Devuelve el array completo de categorías.
     * @return array<int, array{id:string,name:string,vector_store:string}>
     */
    public static function all(): array
    {
        return [
            ['id' => 'alimentacion',      'name' => 'Alimentación y Bebidas',                      'vector_store' => 'vs_69cb3d60175c8191b45280336e0c747b'],
            ['id' => 'moda',              'name' => 'Moda y Belleza',                              'vector_store' => 'vs_69cb3d1816ac81918bc3b6c4919619b9'],
            ['id' => 'salud',             'name' => 'Salud y Bienestar',                           'vector_store' => 'vs_69cc54c846588191b89b6b541ea4e627'],
            ['id' => 'tecnologia',        'name' => 'Tecnología y Electrónica',                    'vector_store' => 'vs_69cb36e321c881919088b2fd363d4a6e'],
            ['id' => 'educacion',         'name' => 'Educación y Formación',                       'vector_store' => 'vs_69cc54aebf948191a97577cb962e7e77'],
            ['id' => 'turismo',           'name' => 'Turismo y Entretenimiento',                   'vector_store' => 'vs_69cb35e6801081919b7b2adb8c365115'],
            ['id' => 'automotriz',        'name' => 'Automotriz y Transporte',                     'vector_store' => 'vs_69cb3cf6d3948191939d681fd0c409b6'],
            ['id' => 'bienes_raices',     'name' => 'Bienes Raíces y Construcción',                'vector_store' => 'vs_69cb366da6648191a4042387d2861b8f'],
            ['id' => 'servicios',         'name' => 'Servicios Profesionales',                     'vector_store' => 'vs_69cc537015048191ad2fa5c31dd0f43c'],
            ['id' => 'deportes',          'name' => 'Deportes y Fitness',                          'vector_store' => 'vs_69cc534c3a548191932b0596301397a9'],
            ['id' => 'medicina',          'name' => 'Salud y Medicina',                            'vector_store' => 'vs_69cb43b91ad48191b9fdfcb09ee14453'],
            ['id' => 'ecommerce',         'name' => 'E-commerce y Tiendas Online',                 'vector_store' => 'vs_69cc548f7d3c8191aad6f7cdf183b023'],
            ['id' => 'bienestar',         'name' => 'Bienestar y Estilo de Vida',                  'vector_store' => 'vs_69cb396f24208191baba0ba86f0a9b58'],
            ['id' => 'hogar',             'name' => 'Hogar y Decoración',                          'vector_store' => 'vs_69cc51a7c2d881918eb2a23cf165cff7'],
            ['id' => 'financiero',        'name' => 'Servicios Financieros',                       'vector_store' => 'vs_69cb3b3ea3d8819199d25e3fbe4d4cb4'],
            ['id' => 'energia',           'name' => 'Energía y Sostenibilidad',                    'vector_store' => 'vs_69cda81c3e508191866430d9475f6152'],
            ['id' => 'agronegocios',      'name' => 'Agronegocios y Agroindustria',                'vector_store' => 'vs_69cda839458c8191bc4e76b69927c377'],
            ['id' => 'medios',            'name' => 'Medios, Comunicación y Contenido Digital',    'vector_store' => 'vs_69cb3eaa88808191824afabbd0e859da'],
            ['id' => 'logistica',         'name' => 'Logística y Cadena de Suministro',            'vector_store' => 'vs_69cda84e931c8191b1d14d97762326d0'],
            ['id' => 'emprendimiento',    'name' => 'Emprendimiento e Innovación',                 'vector_store' => 'vs_69cda878349c81919a10f21551ba2976'],
            ['id' => 'arte',              'name' => 'Arte, Cultura y Creatividad',                 'vector_store' => 'vs_69cda8bff8e08191ae766c8e1a17f2b9'],
            ['id' => 'b2b',               'name' => 'Negocios B2B y Servicios Industriales',       'vector_store' => 'vs_69cb3b0c98d0819185677cb353ad4c06'],
            ['id' => 'gaming',            'name' => 'Gaming y eSports',                            'vector_store' => 'vs_69cc532a65088191ac6b57431e55025f'],
        ];
    }

    /**
     * Devuelve solo los vector_store_id indexados por slug.
     * @return array<string,string>
     */
    public static function vectorMap(): array
    {
        return array_column(self::all(), 'vector_store', 'id');
    }
}