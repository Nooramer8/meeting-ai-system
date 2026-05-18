<?php

namespace App\Services;

use App\Jobs\SendTaskEmailJob;
use App\Models\Task;

class EmailService
{
    public function queueTaskEmail(Task $task): void
    {
        SendTaskEmailJob::dispatch($task)->onQueue('emails');
    }
}
