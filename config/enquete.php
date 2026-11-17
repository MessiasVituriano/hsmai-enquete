<?php

/*
 * O conteúdo da enquete. Trechos entre **asteriscos** saem em negrito.
 *
 * O seeder sincroniza as perguntas com o banco pela posição (1, 2, 3, 4):
 * mudar um texto aqui e rodar `php artisan db:seed --force` atualiza o texto
 * sem apagar respostas. Tirar uma alternativa apaga os votos dela.
 */
return [
    // Segredo único do painel /master (ver EnsureMasterToken).
    'master_token' => env('MASTER_TOKEN', 'master-dev-token'),

    'event_name' => env('EVENT_NAME', 'Commercial Strategy Conference'),
    'title' => 'Enquete ao vivo: onde estamos?',
    'subtitle' => 'Responda pelo **QR code**: vamos usar os resultados ao longo do painel',
    'call_to_action' => 'Responda em **30 segundos**',
    'call_to_action_note' => 'Resultados voltam ao longo do painel',

    // Logos opcionais: coloque os arquivos oficiais em public/brand/. Sem o
    // arquivo, o espaço some e o telão segue sem ele.
    'logos' => [
        'header' => env('LOGO_HEADER', '/brand/logo-conferencia.png'),
        'footer' => env('LOGO_FOOTER', '/brand/logo-hsmai.png'),
    ],

    'questions' => [
        [
            'prompt' => 'Quantos de vocês, nos últimos anos, foram **abordados por TMCs ou Travel Techs** pedindo uma condição de negociação **diferente do “comum”** (comissão ou NET)?',
            'options' => ['Sim', 'Não'],
        ],
        [
            'prompt' => 'Quantos ficaram **preocupados ou confusos** sobre quais critérios analisar e quais condições aplicar nesses novos modelos?',
            'options' => ['Sim', 'Não'],
        ],
        [
            'prompt' => 'Quantos **revisaram os critérios** dessas parcerias nos últimos 12 meses e fazem **análises recorrentes**?',
            'options' => ['Sim', 'Não'],
        ],
        [
            'prompt' => 'Qual é hoje o seu **principal critério** para fechar com uma Travel Tech?',
            'options' => ['Comissão', 'Mark-up', 'Volume de pernoites', 'Facilidade de integração e distribuição'],
            'inline_options' => true,
        ],
    ],
];
