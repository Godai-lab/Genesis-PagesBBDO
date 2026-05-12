<?php

namespace App\AiAgents\Tools;

use LarAgent\Tool;
class Clima extends Tool
{
    protected string $name = 'obtener_clima';

    protected string $description = 'Obtener el clima en una ubicación dada';

    protected array $properties = [
        'location' => [
            'type' => 'string',
            'description' => 'La ciudad y pais o solo ciudad',
        ]
    ];

    protected array $required = ['location'];

    protected array $metaData = ['sent_at' => '2024-01-01'];

    public function execute(array $input): mixed
    {
        
        return 'El clima en '.$input['location'].' es '.rand(10, 60).' grados centigrados';
        
    }
}