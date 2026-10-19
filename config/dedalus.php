<?php

return [
    // Configurações da API de consulta ao acervo bibliográfico Dedalus/ABCD da USP.
    'url' => env('DEDALUS_URL', 'https://app.abcd.usp.br/WSearch.php'),
    'library' => env('DEDALUS_LIBRARY', 'EESC'),
    'timeout' => (int) env('DEDALUS_TIMEOUT', 30),
];

