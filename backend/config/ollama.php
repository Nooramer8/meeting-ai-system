<?php

return [
    'base_url' => rtrim(env('OLLAMA_BASE_URL', 'http://ollama:11434'), '/'),
    'task_model' => env('OLLAMA_TASK_MODEL', 'qwen2.5:3b'),
    'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
];
