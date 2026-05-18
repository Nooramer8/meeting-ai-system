<?php

namespace App\Http\Controllers;

use App\Http\Requests\MeetingUploadRequest;
use App\Jobs\TranscribeMeetingJob;
use App\Models\Meeting;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $meetings = Meeting::query()
            ->withCount('tasks')
            ->with('uploader:id,name,email')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->success($meetings);
    }

    public function upload(MeetingUploadRequest $request)
    {
        $file = $request->file('meeting_file');
        $path = $file->store('meetings');

        $meeting = Meeting::create([
            'title' => (string) $request->string('title'),
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'status' => Meeting::STATUS_UPLOADED,
            'language' => $request->input('language', 'ar'),
            'uploaded_by' => $request->user()->id,
        ]);

        TranscribeMeetingJob::dispatch($meeting)->onQueue('meetings');

        return $this->success($meeting->load('uploader:id,name,email'), 'Meeting uploaded. Transcription has started.', 201);
    }

    public function show(Meeting $meeting)
    {
        return $this->success($meeting->load([
            'uploader:id,name,email',
            'minute',
            'tasks.approvals.user:id,name,email',
            'tasks.matchedUser:id,name,email,phone,position',
        ]));
    }

    public function reprocess(Meeting $meeting)
    {
        $meeting->update([
            'status' => Meeting::STATUS_UPLOADED,
            'failure_reason' => null,
        ]);

        TranscribeMeetingJob::dispatch($meeting)->onQueue('meetings');

        return $this->success($meeting, 'Meeting reprocessing has started.');
    }
}
