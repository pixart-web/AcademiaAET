<?php

/*
|--------------------------------------------------------------------------
| Rastreio automático de linguagem e fala (3–7 anos)
|--------------------------------------------------------------------------
| Tudo o que é decisão clínica vive aqui (ou no banco de itens), nunca
| espalhado pelo código, para a clínica poder rever e ajustar. Os valores
| abaixo seguem o esquema de requisitos fornecido; ver docs/screening.md
| para os pontos que a clínica deve confirmar.
*/

return [
    // Blocos etários em meses completos [mínimo, máximo] (inclusive).
    'blocks' => [
        '3' => [36, 47],
        '4' => [48, 59],
        '5' => [60, 71],
        '6-7' => [72, 95],
    ],

    // Pontuação percentual abaixo da qual se sugere avaliação formal.
    'attention_threshold' => 0.70,

    // 3 falhas seguidas na mesma subsecção encerram essa subsecção.
    'consecutive_errors_to_stop' => 3,
    'stop_rule_kinds' => ['speech', 'comprehension', 'naming', 'repetition'],

    // Itens "Não aplicado / Sem pontuação" ficam fora do denominador.
    'count_not_applied_as_zero' => false,

    // Email da clínica que recebe cópia do relatório (pode ser
    // sobreposto por organização em organizations.settings.screening_clinic_email).
    'clinic_email' => env('SCREENING_CLINIC_EMAIL'),

    /*
     | Aquisição fonética (português europeu). `expected_by_months` é o limite
     | superior da faixa etária indicada na tabela normativa: um som alterado é
     | assinalado como "já deveria estar adquirido" quando a idade cronológica
     | em meses é SUPERIOR a esse limite.
     */
    'phonemes' => [
        'p' => ['label' => '/p/', 'expected_by_months' => 36],
        'b' => ['label' => '/b/', 'expected_by_months' => 36],
        't' => ['label' => '/t/', 'expected_by_months' => 36],
        'd' => ['label' => '/d/', 'expected_by_months' => 36],
        'k' => ['label' => '/k/', 'expected_by_months' => 36],
        'g' => ['label' => '/g/', 'expected_by_months' => 36],
        'm' => ['label' => '/m/', 'expected_by_months' => 36],
        'n' => ['label' => '/n/', 'expected_by_months' => 36],
        'f' => ['label' => '/f/', 'expected_by_months' => 47],
        'v' => ['label' => '/v/', 'expected_by_months' => 47],
        's' => ['label' => '/s/', 'expected_by_months' => 47],
        'z' => ['label' => '/z/', 'expected_by_months' => 47],
        'ɲ' => ['label' => '/ɲ/ (nh)', 'expected_by_months' => 47],
        'l' => ['label' => '/l/', 'expected_by_months' => 47],
        'ʃ' => ['label' => '/ʃ/ (ch, x)', 'expected_by_months' => 59],
        'ʒ' => ['label' => '/ʒ/ (j, ge, gi)', 'expected_by_months' => 59],
        'ʎ' => ['label' => '/ʎ/ (lh)', 'expected_by_months' => 59],
        'R' => ['label' => '/R/ (r forte)', 'expected_by_months' => 59],
        'S_coda' => ['label' => '/S/ em fim de sílaba', 'expected_by_months' => 59],
        'r' => ['label' => '/r/ (r fraco)', 'expected_by_months' => 71],
        'R_coda' => ['label' => '/R/ em fim de sílaba', 'expected_by_months' => 71],
        'PL' => ['label' => 'grupo PL', 'expected_by_months' => 71],
        'BL' => ['label' => 'grupo BL', 'expected_by_months' => 71],
        'CL' => ['label' => 'grupo CL', 'expected_by_months' => 71],
        'GL' => ['label' => 'grupo GL', 'expected_by_months' => 71],
        'FL' => ['label' => 'grupo FL', 'expected_by_months' => 71],
        'PR' => ['label' => 'grupo PR', 'expected_by_months' => 84],
        'BR' => ['label' => 'grupo BR', 'expected_by_months' => 84],
        'TR' => ['label' => 'grupo TR', 'expected_by_months' => 84],
        'DR' => ['label' => 'grupo DR', 'expected_by_months' => 84],
        'CR' => ['label' => 'grupo CR', 'expected_by_months' => 84],
        'GR' => ['label' => 'grupo GR', 'expected_by_months' => 84],
        'FR' => ['label' => 'grupo FR', 'expected_by_months' => 84],
        'CONS' => ['label' => 'consolidação do sistema fonológico', 'expected_by_months' => 84],
    ],

    'conclusions' => [
        'attention' => 'O desenvolvimento da fala/linguagem apresenta pontos que necessitam de atenção. Sugerida Avaliação Formal em Terapia da Fala.',
        'expected' => 'O desenvolvimento da fala e da linguagem encontra-se de acordo com o esperado para a faixa etária.',
    ],
];
