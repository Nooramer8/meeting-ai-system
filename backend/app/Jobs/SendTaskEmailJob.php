<?php

namespace App\Jobs;

use App\Mail\TaskAssignedMailable;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTaskEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public Task $task)
    {
    }

    public function handle(): void
    {
        $task = $this->task->fresh(['meeting', 'meeting.minute']);

        Mail::to($task->assignee_email)->send(new TaskAssignedMailable($task));

        $task->update([
            'status' => Task::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
