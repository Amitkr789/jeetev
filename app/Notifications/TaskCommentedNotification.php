<?php

namespace App\Notifications;

use App\Models\Admin;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TaskCommentedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Task $task, protected Admin $actor, protected string $comment)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'      => 'task_commented',
            'task_id'   => $this->task->id,
            'task_name' => $this->task->task_name,
            'actor'     => $this->actor->name,
            'comment'   => Str::limit($this->comment, 80),
            'message'   => "{$this->actor->name} commented on \"{$this->task->task_name}\"",
        ];
    }
}
