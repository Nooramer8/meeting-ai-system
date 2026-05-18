<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaTaskAssignmentService
{
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
                'description' => $task['description'] ?? $task['task'] ?? '',
                'priority' => $task['priority'] ?? null,
                'suggested_owner' => $task['assignee_name'] ?? $task['owner'] ?? null,
            ],
            $tasks,
            array_keys($tasks)
        ));

        $prompt = json_encode([
            'instruction' => 'Match each task to exactly one best employee by position/responsibility. Use meaning, not only exact words. Choose only employee ids from the list. If no reasonable employee fits, use null.',
            'language' => $language === 'en' ? 'English' : 'Arabic',
            'matching_rules' => [
                'Social media, Instagram, Facebook, posts, captions, reels, content, ads, campaigns -> marketing, social media, content, media positions.',
                'Design, UI, UX, visuals, graphics, wireframes, branding -> design positions.',
                'AI, backend, frontend, code, API, integration, database, data -> technical, developer, AI, data positions.',
                'Sales, customer, client follow-up -> sales or account positions.',
                'Operations, schedule, coordination, reports -> operations, admin, project manager positions.',
                'Prefer the most specific position match.',
                'Do not invent employees, emails, names, or ids.',
            ],
            'employees' => $employeeOptions,
            'tasks' => $taskOptions,
            'required_json_shape' => [
                'assignments' => [
                    [
                        'task_index' => 0,
                        'matched_user_id' => 123,
                        'confidence' => 0.85,
                        'reason' => 'short reason',
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $response = Http::timeout(config('ollama.timeout'))
            ->post(config('ollama.base_url').'/api/generate', [
                'model' => config('ollama.task_model'),
                'system' => 'You are a task routing assistant. Return one JSON object only. Never return Markdown.',
                'prompt' => $prompt,
                'format' => 'json',
                'stream' => false,
                'options' => [
                    'temperature' => 0,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Ollama task assignment failed: '.$response->body());
        }

        $content = Arr::get($response->json(), 'response');
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
