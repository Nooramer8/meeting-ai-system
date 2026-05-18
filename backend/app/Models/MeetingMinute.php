<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeetingMinute extends Model
{
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'summary',
        'decisions',
        'risks',
        'raw_ai_output',
    ];

    protected function casts(): array
    {
        return [
            'decisions' => 'array',
            'risks' => 'array',
            'raw_ai_output' => 'array',
        ];
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }
}
