<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasFactory;

    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_TRANSCRIBING = 'transcribing';
    public const STATUS_TRANSCRIBED = 'transcribed';
    public const STATUS_AI_PROCESSING = 'ai_processing';
    public const STATUS_NEEDS_APPROVAL = 'needs_approval';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'title',
        'original_filename',
        'file_path',
        'mime_type',
        'status',
        'language',
        'transcript',
        'failure_reason',
        'uploaded_by',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function minute()
    {
        return $this->hasOne(MeetingMinute::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
