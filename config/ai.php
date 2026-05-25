<?php

return [
    'provider' => env('AI_PROVIDER', 'anthropic'),
    'providers' => [
        'anthropic' => [
            'api_key' => env('AI_API_KEY'),
            'base_url' => 'https://api.anthropic.com/v1',
            'model' => env('AI_MODEL', 'claude-sonnet-4-6'),
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => 'https://api.openai.com/v1',
            'model' => env('AI_MODEL', 'gpt-4o'),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'model' => env('AI_MODEL', 'gemini-2.0-flash'),
        ],
    ],
];
