<?php

return [
    'provider' => env('AI_PROVIDER', 'anthropic'),
    'api_key' => env('AI_API_KEY'),
    'model' => env('AI_MODEL', 'claude-sonnet-4-6'),
    'base_url' => 'https://api.anthropic.com/v1',
];
