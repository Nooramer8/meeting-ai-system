<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class WhisperService
{
    private const ARABIC_NORMALIZATIONS = [
        'مناقشه' => 'مناقشة',
        'مناقشةه' => 'مناقشة',
        'انستجرام' => 'إنستغرام',
        'انستغرام' => 'إنستغرام',
        'الانستغرام' => 'الإنستغرام',
        'الانستجرام' => 'الإنستغرام',
    ];

    private function normalizeLanguage(?string $language): string
    {
        $value = strtolower(trim((string) ($language ?: 'auto')));

        return match ($value) {
            'arabic', 'عربي', 'العربية' => 'ar',
            'english', 'انجليزي', 'الإنجليزية' => 'en',
            'ar', 'en' => $value,
            default => 'auto',
        };
    }

    private function cleanTranscript(string $text): string
    {
        $cleaned = trim(preg_replace('/[ \t]+/u', ' ', $text));

        foreach (self::ARABIC_NORMALIZATIONS as $from => $to) {
            $cleaned = preg_replace('/(?<![\p{L}\p{N}_])'.preg_quote($from, '/').'(?![\p{L}\p{N}_])/u', $to, $cleaned);
        }

        return $cleaned;
    }

    public function transcribe(string $storedPath, ?string $language = 'ar'): array
    {
        $absolutePath = Storage::disk(config('filesystems.default'))->path($storedPath);

        if (! file_exists($absolutePath)) {
            throw new RuntimeException('Meeting file not found: '.$storedPath);
        }

        $payload = [
            'model' => config('groq.whisper_model'),
            'response_format' => 'verbose_json',
            'temperature' => 0,
        ];

        $languageHint = $this->normalizeLanguage($language);
        if (in_array($languageHint, ['ar', 'en'], true)) {
            $payload['language'] = $languageHint;
        }

        $response = Http::timeout(config('groq.timeout'))
            ->withToken(config('groq.api_key'))
            ->attach('file', fopen($absolutePath, 'r'), basename($absolutePath))
            ->post(config('groq.base_url').'/audio/transcriptions', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Groq transcription failed: '.$response->body());
        }

        $json = $response->json();

        return [
            'text' => $this->cleanTranscript($json['text'] ?? ''),
            'language' => $this->normalizeLanguage($json['language'] ?? $languageHint),
            'segments' => $json['segments'] ?? [],
            'raw' => $json,
        ];
    }
}
