<?php

namespace App\Notifications;

use App\Models\Admin;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskStatusUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Task $task, protected Admin $actor)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $label = ucwords(str_replace('_', ' ', $this->task->status));

        return [
            'type'      => 'task_status_updated',
            'task_id'   => $this->task->id,
            'task_name' => $this->task->task_name,
            'status'    => $this->task->status,
            'actor'     => $this->actor->name,
            'message'   => "{$this->actor->name} marked \"{$this->task->task_name}\" as {$label}",
        ];
    }
}
