<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Task;
use App\Models\TaskActivityLog;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCommentedNotification;
use App\Notifications\TaskStatusUpdatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskController extends Controller
{
    /* =========================================================
     |  Page
     * ========================================================= */

    public function index()
    {
        $admin = auth('admin')->user();

        return view('pages.tasks.index', [
            'admins'       => Admin::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'priorities'   => ['low', 'medium', 'high', 'urgent'],
            'statuses'     => ['pending', 'in_progress', 'completed', 'reverted', 'on_hold'],
            'isSuperAdmin' => $admin->isSuperAdmin(),
        ]);
    }

    /* =========================================================
     |  List + stats (JSON, scoped by role)
     * ========================================================= */

    public function data(Request $request)
    {
        $admin = auth('admin')->user();

        $tasks = Task::query()
            ->visibleTo($admin)
            ->with(['assignee:id,name', 'assigner:id,name'])
            ->withCount(['comments', 'attachments'])
            ->latest()
            ->get();

        $overdue = $tasks->filter->isOverdue()->count();

        return response()->json([
            'success' => true,
            'tasks'   => $tasks,
            'current_admin' => [
                'id'             => $admin->id,
                'name'           => $admin->name,
                'is_super_admin' => $admin->isSuperAdmin(),
            ],
            'stats' => [
                'total'       => $tasks->count(),
                'pending'     => $tasks->where('status', 'pending')->count(),
                'in_progress' => $tasks->where('status', 'in_progress')->count(),
                'completed'   => $tasks->where('status', 'completed')->count(),
                'reverted'    => $tasks->where('status', 'reverted')->count(),
                'overdue'     => $overdue,
            ],
        ]);
    }

    /* =========================================================
     |  Create — super admin only
     * ========================================================= */

    public function store(Request $request)
    {
        $admin = auth('admin')->user();

        if (!$admin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only a super admin can create and assign tasks.'], 403);
        }

        $data = $request->validate([
            'task_name'   => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority'    => 'required|in:low,medium,high,urgent',
            'due_date'    => 'nullable|date',
            'assigned_to' => 'required|exists:admins,id',
        ]);

        $task = Task::create($data + [
            'assigned_by' => $admin->id,
            'status'      => 'pending',
        ]);

        $this->log($task, $admin, 'created', "{$admin->name} created this task.");
        $this->log($task, $admin, 'assigned', "{$admin->name} assigned this task to {$task->assignee->name}.");

        if ($task->assignee && $task->assignee->id !== $admin->id) {
            $task->assignee->notify(new TaskAssignedNotification($task));
        }

        return response()->json([
            'success' => true,
            'message' => 'Task created and assigned.',
            'task'    => $task->load(['assignee:id,name', 'assigner:id,name'])->loadCount(['comments', 'attachments']),
        ]);
    }

    /* =========================================================
     |  Update details / reassign — super admin only
     * ========================================================= */

    public function update(Request $request, Task $task)
    {
        $admin = auth('admin')->user();

        if (!$admin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only a super admin can edit or reassign a task.'], 403);
        }

        $data = $request->validate([
            'task_name'   => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority'    => 'required|in:low,medium,high,urgent',
            'due_date'    => 'nullable|date',
            'assigned_to' => 'required|exists:admins,id',
        ]);

        $previousAssignee = $task->assigned_to;
        $task->update($data);

        $this->log($task, $admin, 'updated', "{$admin->name} updated this task's details.");

        if ($previousAssignee !== $task->assigned_to) {
            $this->log($task, $admin, 'assigned', "{$admin->name} reassigned this task to {$task->assignee->name}.");
            if ($task->assignee && $task->assignee->id !== $admin->id) {
                $task->assignee->notify(new TaskAssignedNotification($task));
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Task updated.',
            'task'    => $task->fresh(['assignee:id,name', 'assigner:id,name'])->loadCount(['comments', 'attachments']),
        ]);
    }

    /* =========================================================
     |  Show single task detail (comments/attachments/activity)
     * ========================================================= */

    public function show(Task $task)
    {
        $admin = auth('admin')->user();

        if (!$task->canBeUpdatedBy($admin)) {
            return response()->json(['success' => false, 'message' => 'You do not have access to this task.'], 403);
        }

        $task->load([
            'assignee:id,name',
            'assigner:id,name',
            'comments.author:id,name',
            'attachments.uploader:id,name',
            'activityLogs.admin:id,name',
        ]);

        return response()->json(['success' => true, 'task' => $task]);
    }

    /* =========================================================
     |  Status change — assignee (their own task) OR super admin
     * ========================================================= */

    public function updateStatus(Request $request, Task $task)
    {
        $admin = auth('admin')->user();

        if (!$task->canBeUpdatedBy($admin)) {
            return response()->json(['success' => false, 'message' => 'You can only update tasks assigned to you.'], 403);
        }

        $data = $request->validate([
            'status' => 'required|in:pending,in_progress,completed,reverted,on_hold',
        ]);

        $task->status = $data['status'];
        $task->completed_at = $data['status'] === 'completed' ? now() : null;
        $task->save();

        $label = ucwords(str_replace('_', ' ', $task->status));
        $this->log($task, $admin, 'status_changed', "{$admin->name} changed the status to {$label}.");

        // Notify whoever isn't the one making the change.
        $recipient = $admin->id === $task->assigned_to ? $task->assigner : $task->assignee;
        if ($recipient && $recipient->id !== $admin->id) {
            $recipient->notify(new TaskStatusUpdatedNotification($task, $admin));
        }

        return response()->json([
            'success' => true,
            'message' => "Status updated to {$label}.",
            'task'    => $task->fresh(['assignee:id,name', 'assigner:id,name']),
        ]);
    }

    /* =========================================================
     |  Delete — super admin only
     * ========================================================= */

    public function destroy(Task $task)
    {
        $admin = auth('admin')->user();

        if (!$admin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only a super admin can delete tasks.'], 403);
        }

        foreach ($task->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $task->delete();

        return response()->json(['success' => true, 'message' => 'Task deleted.']);
    }

    public function bulkDestroy(Request $request)
    {
        $admin = auth('admin')->user();

        if (!$admin->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only a super admin can delete tasks.'], 403);
        }

        $ids = $request->validate(['ids' => 'required|array'])['ids'];
        $tasks = Task::whereIn('id', $ids)->get();

        foreach ($tasks as $task) {
            foreach ($task->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }
            $task->delete();
        }

        return response()->json(['success' => true, 'message' => count($ids) . ' task(s) deleted.']);
    }

    /* =========================================================
     |  Comments — assignee or assigner (super admin) of that task
     * ========================================================= */

    public function storeComment(Request $request)
    {
        $admin = auth('admin')->user();

        $data = $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'comment' => 'required|string',
        ]);

        $task = Task::findOrFail($data['task_id']);

        if (!$task->canBeUpdatedBy($admin)) {
            return response()->json(['success' => false, 'message' => 'You cannot comment on this task.'], 403);
        }

        $comment = $task->comments()->create([
            'admin_id' => $admin->id,
            'comment'  => $data['comment'],
        ]);

        $this->log($task, $admin, 'commented', "{$admin->name} added a comment.");

        $recipient = $admin->id === $task->assigned_to ? $task->assigner : $task->assignee;
        if ($recipient && $recipient->id !== $admin->id) {
            $recipient->notify(new TaskCommentedNotification($task, $admin, $data['comment']));
        }

        return response()->json([
            'success' => true,
            'message' => 'Comment added.',
            'comment' => $comment->load('author:id,name'),
        ]);
    }

    /* =========================================================
     |  Attachments
     * ========================================================= */

    public function storeAttachment(Request $request)
    {
        $admin = auth('admin')->user();

        $data = $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'file'    => 'required|file|max:10240', // 10MB
        ]);

        $task = Task::findOrFail($data['task_id']);

        if (!$task->canBeUpdatedBy($admin)) {
            return response()->json(['success' => false, 'message' => 'You cannot upload files to this task.'], 403);
        }

        $file = $request->file('file');
        $path = $file->store("task-attachments/{$task->id}", 'public');

        $attachment = $task->attachments()->create([
            'admin_id'      => $admin->id,
            'original_name' => $file->getClientOriginalName(),
            'file_path'     => $path,
            'file_size'     => $file->getSize(),
            'mime_type'     => $file->getClientMimeType(),
        ]);

        $this->log($task, $admin, 'attachment_added', "{$admin->name} attached \"{$attachment->original_name}\".");

        return response()->json([
            'success'    => true,
            'message'    => 'File attached.',
            'attachment' => $attachment->load('uploader:id,name'),
        ]);
    }

    public function destroyAttachment(\App\Models\TaskAttachment $attachment)
    {
        $admin = auth('admin')->user();
        $task = $attachment->task;

        if (!$admin->isSuperAdmin() && $attachment->admin_id !== $admin->id) {
            return response()->json(['success' => false, 'message' => 'You can only remove files you uploaded.'], 403);
        }

        Storage::disk('public')->delete($attachment->file_path);
        $this->log($task, $admin, 'attachment_removed', "{$admin->name} removed \"{$attachment->original_name}\".");
        $attachment->delete();

        return response()->json(['success' => true, 'message' => 'Attachment removed.']);
    }

    /* =========================================================
     |  Bell notifications
     * ========================================================= */

    public function notificationsCheck(Request $request)
    {
        $admin = auth('admin')->user();

        $unread = $admin->unreadNotifications()->take(15)->get();

        return response()->json([
            'success' => true,
            'count'   => $admin->unreadNotifications()->count(),
            'notifications' => $unread->map(fn($n) => [
                'id'         => $n->id,
                'task_id'    => $n->data['task_id'] ?? null,
                'message'    => $n->data['message'] ?? '',
                'created_at' => $n->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function notificationsMarkRead(Request $request)
    {
        $admin = auth('admin')->user();
        $id = $request->input('id');

        if ($id) {
            $admin->unreadNotifications()->where('id', $id)->first()?->markAsRead();
        } else {
            $admin->unreadNotifications->markAsRead();
        }

        return response()->json(['success' => true]);
    }

    /* =========================================================
     |  Helper
     * ========================================================= */

    private function log(Task $task, Admin $admin, string $action, string $description): void
    {
        TaskActivityLog::create([
            'task_id'     => $task->id,
            'admin_id'    => $admin->id,
            'action'      => $action,
            'description' => $description,
        ]);
    }
}
