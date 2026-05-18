<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Models\MeetingMinute;
use App\Models\Task;
use App\Models\User;
use App\Services\GroqService;
use App\Services\OllamaTaskAssignmentService;
use App\Services\UserMatchingService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessMeetingAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 900;

    public function __construct(public Meeting $meeting)
    {
    }

    public function handle(GroqService $groq, OllamaTaskAssignmentService $ollamaAssignment, UserMatchingService $matcher): void
    {
        $this->meeting->update(['status' => Meeting::STATUS_AI_PROCESSING]);

        $output = $groq->generateMinutesAndTasks($this->meeting->transcript ?? '', $this->meeting->language);
        $taskItems = $output['tasks'] ?? [];
        $users = User::query()->select('id', 'name', 'email', 'position')->orderBy('name')->get();
        try {
            $aiAssignments = $ollamaAssignment->assignTasksToUsers($taskItems, $users, $this->meeting->language);
        } catch (Throwable) {
            $aiAssignments = [];
        }

        DB::transaction(function () use ($output, $matcher, $taskItems, $users, $aiAssignments) {
            MeetingMinute::updateOrCreate(
                ['meeting_id' => $this->meeting->id],
                [
                    'summary' => $output['short_summary'] ?? $output['summary'] ?? '',
                    'decisions' => $output['decisions'] ?? [],
                    'risks' => $output['risks_or_sensitive_items'] ?? $output['risks'] ?? [],
                    'raw_ai_output' => $output,
                ]
            );

            $this->meeting->tasks()->delete();

            foreach ($taskItems as $index => $item) {
                $assigneeName = $item['assignee_name'] ?? $item['owner'] ?? null;
                if (in_array($assigneeName, ['غير محدد', 'Not specified'], true)) {
                    $assigneeName = null;
                }

                $title = $item['title'] ?? $item['task'] ?? 'Untitled task';
                $description = $item['description'] ?? $item['task'] ?? '';
                $matched = null;
                if (array_key_exists($index, $aiAssignments) && $aiAssignments[$index]) {
                    $matched = $users->firstWhere('id', $aiAssignments[$index]);
                }

                $matched ??= $matcher->matchForTask($assigneeName, $item['assignee_email'] ?? null, $title, $description);
                $dueDate = null;

                $rawDueDate = $item['due_date'] ?? $item['deadline'] ?? null;
                if (! empty($rawDueDate) && ! in_array($rawDueDate, ['غير محدد', 'Not specified'], true)) {
                    try {
                        $dueDate = Carbon::parse($rawDueDate)->toDateString();
                    } catch (Throwable) {
                        $dueDate = null;
                    }
                }

                Task::create([
                    'meeting_id' => $this->meeting->id,
                    'title' => $title,
                    'description' => $description,
                    'assignee_name' => $assigneeName ?? $matched?->name,
                    'assignee_email' => $item['assignee_email'] ?? $matched?->email,
                    'matched_user_id' => $matched?->id,
                    'due_date' => $dueDate,
                    'priority' => in_array(($item['priority'] ?? 'medium'), ['low', 'medium', 'high', 'urgent'], true) ? $item['priority'] : 'medium',
                    'status' => Task::STATUS_PENDING_APPROVAL,
                    'ai_confidence' => $item['confidence'] ?? null,
                ]);
            }

            $this->meeting->update(['status' => Meeting::STATUS_NEEDS_APPROVAL]);
        });
    }

    public function failed(Throwable $exception): void
    {
        $this->meeting->update([
            'status' => Meeting::STATUS_FAILED,
            'failure_reason' => $exception->getMessage(),
        ]);
    }
}
