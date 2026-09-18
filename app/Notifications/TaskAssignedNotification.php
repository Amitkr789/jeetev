<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Task $task)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'        => 'task_assigned',
            'task_id'     => $this->task->id,
            'task_name'   => $this->task->task_name,
            'priority'    => $this->task->priority,
            'due_date'    => optional($this->task->due_date)->toDateString(),
            'assigned_by' => optional($this->task->assigner)->name,
            'message'     => "You've been assigned a new task: \"{$this->task->task_name}\"",
        ];
    }
}
