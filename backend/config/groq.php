<?php

return [
    'api_key' => env('GROQ_API_KEY'),
    'base_url' => rtrim(env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'), '/'),
    'transcription_backend' => env('TRANSCRIPTION_BACKEND', 'groq'),
    'summary_backend' => env('SUMMARY_BACKEND', 'groq'),
    'whisper_model' => env('TRANSCRIPTION_MODEL', env('GROQ_WHISPER_MODEL', 'whisper-large-v3')),
    'chat_model' => env('SUMMARY_MODEL', env('GROQ_CHAT_MODEL', 'llama-3.3-70b-versatile')),
    'timeout' => (int) env('GROQ_TIMEOUT', 600),
];
