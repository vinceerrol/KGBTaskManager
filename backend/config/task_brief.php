<?php

return [
    'ai_enabled' => env('TASK_BRIEF_AI_ENABLED', false),
    'provider' => env('TASK_BRIEF_AI_PROVIDER', 'openai'),
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_TASK_MODEL', 'gpt-4.1-mini'),
    'groq_api_key' => env('GROQ_API_KEY'),
    'groq_model' => env('GROQ_TASK_MODEL', 'openai/gpt-oss-120b'),
    'timeout' => 12,
];
