<x-mail::message>
# New Approved Task

Hello {{ $task->assignee_name ?: 'there' }},

A task from the meeting **{{ $task->meeting->title }}** has been approved and assigned to you.

<x-mail::panel>
**Task:** {{ $task->title }}

**Description:**  
{{ $task->description }}

**Priority:** {{ ucfirst($task->priority) }}

@if($task->due_date)
**Due date:** {{ $task->due_date->format('Y-m-d') }}
@endif
</x-mail::panel>

@if($task->meeting->minute)
## Meeting summary
{{ $task->meeting->minute->summary }}
@endif

Thanks,  
{{ config('app.name') }}
</x-mail::message>
