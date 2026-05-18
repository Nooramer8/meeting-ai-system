<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GroqService
{
    public function generateMinutesAndTasks(string $transcript, ?string $language = 'ar'): array
    {
        $outputLanguage = $language === 'en' ? 'English' : 'Arabic';
        $languageRules = $language === 'en'
            ? 'The recording language is English. Read the transcript as an English meeting and write every user-facing value in polished business English. Correct obvious speech-to-text mistakes only when the intended meaning is clear. Keep names, emails, product names, code terms, numbers, and dates unchanged.'
            : 'The recording language is Arabic. Read the transcript as an Arabic meeting and write every user-facing value in polished business Arabic. Correct obvious speech-to-text mistakes only when the intended meaning is clear. Keep names, emails, product names, code terms, numbers, and dates unchanged.';

        $schemaDescription = <<<'TEXT'
Return only valid JSON in this exact shape:
{
  "language": "Arabic or English",
  "title": "short meeting title",
  "short_summary": "one concise paragraph",
  "key_points": ["important point 1"],
  "decisions": ["decision 1"],
  "risks_or_sensitive_items": ["risk, blocker, or review warning 1"],
  "tasks": [
    {
      "owner": "employee/person responsible, or غير محدد/Not specified",
      "task": "clear task details",
      "deadline": "deadline text, YYYY-MM-DD, غير محدد, or Not specified",
      "title": "short action item title",
      "description": "clear task details",
      "assignee_name": "same as owner if mentioned, otherwise null",
      "assignee_email": "email if mentioned or inferable from transcript, otherwise null",
      "due_date": "YYYY-MM-DD or null",
      "priority": "low|medium|high|urgent",
      "confidence": 0.0
    }
  ]
}
Rules:
- Process the transcript in the selected recording language. Do not switch output languages unless a name, email, product name, code term, number, or date is better kept exactly as spoken.
- Do not summarize away task ownership, deadlines, decisions, or blockers.
- The fields title, short_summary, key_points, decisions, risks_or_sensitive_items, tasks.title, tasks.task, and tasks.description must be written in the requested output language.
- Use clear professional business wording in the requested output language.
- Clean up obvious transcription noise before writing the minutes.
- Do not copy malformed mixed-language fragments such as "والdesire", "والreps", broken English words, or phonetic ASR artifacts into the output.
- If an ASR artifact is unclear, omit that fragment instead of inventing meaning.
- If an English technical term is clear and commonly used, keep it as the correct English term only, not as a broken or Arabized fragment.
- Keep names exactly as spoken when possible.
- Do not invent an email address. Use null when not present.
- Split separate responsibilities into separate tasks.
- Create tasks from the meeting summary and transcript. Each task must be assignable to an employee/person when one is mentioned.
- If no owner is mentioned for a task, set owner to "غير محدد" for Arabic or "Not specified" for English.
- If no deadline is mentioned, set deadline to "غير محدد" for Arabic or "Not specified" for English and due_date to null.
- Include decisions and risks/blockers if present.
- Return JSON only. No Markdown.
TEXT;

        $response = Http::timeout(config('groq.timeout'))
            ->withToken(config('groq.api_key'))
            ->post(config('groq.base_url').'/chat/completions', [
                'model' => config('groq.chat_model'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an expert Arabic-English meeting secretary and project manager. Produce clean professional minutes, never noisy speech-to-text artifacts.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Requested output language: {$outputLanguage}\nLanguage rule: {$languageRules}\n\n".$schemaDescription."\n\nTranscript:\n".$transcript,
                    ],
                ],
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Groq AI processing failed: '.$response->body());
        }

        $content = Arr::get($response->json(), 'choices.0.message.content');
        $decoded = json_decode($content ?: '{}', true);

        if (! is_array($decoded) || ! array_key_exists('tasks', $decoded)) {
            throw new RuntimeException('Groq returned invalid meeting JSON: '.$content);
        }

        return $decoded;
    }

    public function assignTasksToUsers(array $tasks, Collection $users, ?string $language = 'ar'): array
    {
        $employeeOptions = $users
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'position' => $user->position,
            ])
            ->values()
            ->all();

        if ($tasks === [] || $employeeOptions === []) {
            return [];
        }

        $taskOptions = array_values(array_map(
            fn ($task, $index) => [
                'index' => $index,
                'title' => $task['title'] ?? '',
                'description' => $task['description'] ?? '',
                'priority' => $task['priority'] ?? null,
                'suggested_owner' => $task['assignee_name'] ?? $task['owner'] ?? null,
            ],
            $tasks,
            array_keys($tasks)
        ));

        $response = Http::timeout(config('groq.timeout'))
            ->withToken(config('groq.api_key'))
            ->post(config('groq.base_url').'/chat/completions', [
                'model' => config('groq.chat_model'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an operations coordinator. Match tasks to the best employee using their position/responsibility. Choose only from the provided employee ids. Return JSON only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'language' => $language === 'en' ? 'English' : 'Arabic',
                            'rules' => [
                                'For each task, choose the employee whose position best matches the work.',
                                'Use task meaning, not only exact words.',
                                'If task mentions social media, Instagram, Facebook, posts, captions, reels, or marketing, prefer marketing/social media/content roles.',
                                'If task mentions design, UI, visuals, graphics, or wireframes, prefer design roles.',
                                'If task mentions AI, data, backend, code, integration, or technical work, prefer technical/developer/AI roles.',
                                'If no employee is a reasonable match, use null.',
                                'Do not invent employees.',
                            ],
                            'employees' => $employeeOptions,
                            'tasks' => $taskOptions,
                            'return_shape' => [
                                'assignments' => [
                                    [
                                        'task_index' => 0,
                                        'matched_user_id' => 123,
                                        'confidence' => 0.85,
                                        'reason' => 'short reason',
                                    ],
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Groq task assignment failed: '.$response->body());
        }

        $content = Arr::get($response->json(), 'choices.0.message.content');
        $decoded = json_decode($content ?: '{}', true);
        $validUserIds = $users->pluck('id')->map(fn ($id) => (int) $id)->all();
        $assignments = [];

        foreach (($decoded['assignments'] ?? []) as $assignment) {
            $taskIndex = $assignment['task_index'] ?? null;
            $matchedUserId = $assignment['matched_user_id'] ?? null;
            if (! is_numeric($taskIndex)) {
                continue;
            }

            $assignments[(int) $taskIndex] = in_array((int) $matchedUserId, $validUserIds, true)
                ? (int) $matchedUserId
                : null;
        }

        return $assignments;
    }
}
