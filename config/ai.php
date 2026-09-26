<?php

return [
    'openai_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'rate_limit' => (int) env('AI_RATE_LIMIT', 30),
];
