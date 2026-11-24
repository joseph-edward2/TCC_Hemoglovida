<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Metas de estoque por tipo sanguíneo
    |--------------------------------------------------------------------------
    |
    | Quantidade ideal de bolsas por tipo. Usada para calcular o percentual
    | disponível e decidir quando um tipo entra em estado crítico.
    |
    */

    'metas' => [
        'O-' => 30,
        'O+' => 100,
        'A-' => 40,
        'A+' => 80,
        'B-' => 35,
        'B+' => 60,
        'AB-' => 30,
        'AB+' => 50,
    ],

    'critico_percentual' => 25,

    'dias_a_vencer' => 7,

];
