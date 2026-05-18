<?php

namespace App\Http\Controllers;

use App\Jobs\SendTaskEmailJob;
use App\Models\Task;
use App\Models\User;
use App\Services\OllamaTaskAssignmentService;
use App\Services\UserMatchingService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $tasks = Task::query()
            ->with(['meeting:id,title,status', 'matchedUser:id,name,email,phone,position'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('meeting_id'), fn ($q) => $q->where('meeting_id', $request->integer('meeting_id')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->success($tasks);
    }

    public function show(Task $task)
    {
        return $this->success($task->load(['meeting.minute', 'approvals.user:id,name,email', 'matchedUser:id,name,email,phone,position']));
    }

    public function updateAssignee(Request $request, Task $task)
    {
        $validated = $request->validate([
            'matched_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $user = empty($validated['matched_user_id'])
            ? null
            : User::query()->select('id', 'name', 'email', 'phone', 'position')->findOrFail($validated['matched_user_id']);

        $task->update([
            'matched_user_id' => $user?->id,
            'assignee_name' => $user?->name,
            'assignee_email' => $user?->email,
        ]);

        return $this->success($task->fresh()->load('matchedUser:id,name,email,phone,position'), 'Task assignee updated.');
    }

    public function autoAssign(Task $task, OllamaTaskAssignmentService $ollamaAssignment, UserMatchingService $matcher)
    {
        $users = User::query()->select('id', 'name', 'email', 'phone', 'position')->orderBy('name')->get();
        if ($users->isEmpty()) {
            return $this->error('Create employees in Assignees first, then AI can assign tasks.', 422);
        }

        try {
            $assignments = $ollamaAssignment->assignTasksToUsers([
                [
                    'title' => $task->title,
                    'description' => $task->description,
                    'priority' => $task->priority,
                    'owner' => $task->assignee_name,
                ],
            ], $users, $task->meeting?->language);
        } catch (\Throwable $exception) {
            $assignments = [];
        }

        $matched = null;
        if (! empty($assignments[0])) {
            $matched = $users->firstWhere('id', $assignments[0]);
        }

        $matched ??= $matcher->matchForTask($task->assignee_name, $task->assignee_email, $task->title, $task->description);

        if (! $matched) {
            return $this->error('Ollama could not find a matching employee. Check employee positions or choose one manually.', 422);
        }

        $task->update([
            'matched_user_id' => $matched->id,
            'assignee_name' => $matched->name,
            'assignee_email' => $matched->email,
        ]);

        return $this->success($task->fresh()->load('matchedUser:id,name,email,phone,position'), 'Task assigned by Ollama.');
    }

    public function sendEmail(Task $task)
    {
        if ($task->status !== Task::STATUS_APPROVED) {
            return $this->error('Only approved tasks can be emailed.', 422);
        }

        if (! $task->assignee_email) {
            return $this->error('Task has no assignee email.', 422);
        }

        SendTaskEmailJob::dispatch($task)->onQueue('emails');

        return $this->success($task->fresh(), 'Email queued for delivery.');
    }
}
