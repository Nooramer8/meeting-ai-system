<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SENT = 'sent';

    protected $fillable = [
        'meeting_id',
        'title',
        'description',
        'assignee_name',
        'assignee_email',
        'matched_user_id',
        'due_date',
        'priority',
        'status',
        'ai_confidence',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'ai_confidence' => 'float',
        ];
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function matchedUser()
    {
        return $this->belongsTo(User::class, 'matched_user_id');
    }

    public function approvals()
    {
        return $this->hasMany(TaskApproval::class);
    }
}
