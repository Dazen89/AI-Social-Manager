<?php

return [
    'connections' => [
        'openai' => [
            'endpoint' => 'https://api.openai.com/v1/responses',
        ],
        'anthropic' => [
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'api_version' => '2023-06-01',
        ],
    ],

    'providers' => [
        'openai' => [
            'name' => 'OpenAI',
            'models' => [
                'gpt-6-astra' => 'GPT-6 Astra',
                'gpt-6.1-sol' => 'GPT-6.1 Sol',
                'gpt-6-sol' => 'GPT-6 Sol',
                'gpt-6-luna' => 'GPT-6 Luna',
                'gpt-5.6-sol' => 'GPT-5.6 Sol',
                'gpt-5.6-terra' => 'GPT-5.6 Terra',
                'gpt-5.6-luna' => 'GPT-5.6 Luna',
                'gpt-5.5' => 'GPT-5.5',
                'gpt-5.5-pro' => 'GPT-5.5 Pro',
                'gpt-5.4' => 'GPT-5.4',
                'gpt-5.4-pro' => 'GPT-5.4 Pro',
                'gpt-5.4-mini' => 'GPT-5.4 Mini',
            ],
        ],
        'anthropic' => [
            'name' => 'Anthropic (Claude)',
            'models' => [
                'claude-fable-5-1' => 'Claude Fable 5.1',
                'claude-opus-5-5' => 'Claude Opus 5.5',
                'claude-sonnet-5-5' => 'Claude Sonnet 5.5',
                'claude-haiku-5-5' => 'Claude Haiku 5.5',
            ],
        ],
    ],
];
