<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectTaskRequest;
use App\Models\Task;
use App\Models\TaskApproval;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    use ApiResponse;

    public function approve(Request $request, Task $task)
    {
        if (! $task->matched_user_id && ! $task->assignee_email) {
            return $this->error('Select an assignee before approving this task.', 422);
        }

        DB::transaction(function () use ($request, $task) {
            $task->update(['status' => Task::STATUS_APPROVED]);

            TaskApproval::create([
                'task_id' => $task->id,
                'user_id' => $request->user()->id,
                'status' => 'approved',
                'comment' => $request->input('comment'),
            ]);
        });

        return $this->success($task->fresh()->load('approvals.user:id,name,email'), 'Task approved.');
    }

    public function reject(RejectTaskRequest $request, Task $task)
    {
        DB::transaction(function () use ($request, $task) {
            $task->update(['status' => Task::STATUS_REJECTED]);

            TaskApproval::create([
                'task_id' => $task->id,
                'user_id' => $request->user()->id,
                'status' => 'rejected',
                'comment' => (string) $request->string('comment'),
            ]);
        });

        return $this->success($task->fresh()->load('approvals.user:id,name,email'), 'Task rejected.');
    }
}
