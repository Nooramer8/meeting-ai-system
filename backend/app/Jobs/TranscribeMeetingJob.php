<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Services\WhisperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class TranscribeMeetingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 900;

    public function __construct(public Meeting $meeting)
    {
    }

    public function handle(WhisperService $whisper): void
    {
        $this->meeting->update(['status' => Meeting::STATUS_TRANSCRIBING]);

        $result = $whisper->transcribe($this->meeting->file_path, $this->meeting->language);

        $this->meeting->update([
            'status' => Meeting::STATUS_TRANSCRIBED,
            'transcript' => $result['text'],
            'language' => $result['language'] ?: $this->meeting->language,
        ]);

        ProcessMeetingAiJob::dispatch($this->meeting->fresh())->onQueue('meetings');
    }

    public function failed(Throwable $exception): void
    {
        $this->meeting->update([
            'status' => Meeting::STATUS_FAILED,
            'failure_reason' => $exception->getMessage(),
        ]);
    }
}
