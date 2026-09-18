<?php
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PagesController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LeadManageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\InventoryManageController;
use App\Http\Controllers\BillingManageController;
use App\Http\Controllers\MetaLeadController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatbotSettingController;
use App\Http\Controllers\WhatsAppWebhookController;
/* ==========================================================================
   Guest routes (no auth required)
   ========================================================================== */
 // Guest-only (already logged-in admin yahan nahi aa sakta)
Route::middleware('guest:admin')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/admin/login', [AuthController::class, 'login']);
});

// Login (Laravel Breeze / Auth::routes() handles these — add yours if custom)
    Route::prefix('admin')->middleware(['auth:admin'])->group(function () {

    // Route::get('/admin-manage', [PagesController::class, 'users'])->name('admin.register');
    Route::get('/', [DashboardController::class, 'index'])->name('auth.home.main');
    Route::post('logout', [AuthController::class, 'logout'])->name('admin.logout');
    Route::get('users/list', [AuthController::class, 'index']);
    Route::post('users', [AuthController::class, 'store']);
    Route::get('users/{id}', [AuthController::class, 'show']);
    Route::put('users/{id}', [AuthController::class, 'update']);
    Route::delete('users/{id}', [AuthController::class, 'destroy']);
    Route::post('users/bulk-delete', [AuthController::class, 'bulkDelete']);
    Route::patch('users/{id}/toggle-status', [AuthController::class, 'toggleStatus']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('admin.profile');
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
});

Route::prefix('admin')->middleware(['auth:admin'])->group(function () {

    Route::get('/leads', [LeadManageController::class, 'Leadmanage'])->name('leads.index');
    Route::get('/leads/data', [LeadManageController::class, 'data'])->name('leads.data');

    Route::post('/leads', [LeadManageController::class, 'store'])->name('leads.store');
    Route::put('/leads/{id}', [LeadManageController::class, 'update'])->name('leads.update');
    Route::get('/leads/{id}', [LeadManageController::class, 'show'])->name('leads.show');
    Route::delete('/leads/{id}', [LeadManageController::class, 'destroy'])->name('leads.destroy');
    Route::post('/leads/bulk-delete', [LeadManageController::class, 'bulkDestroy'])->name('leads.bulkDestroy');
    Route::post('/leads/{id}/toggle-pin', [LeadManageController::class, 'togglePin'])->name('leads.togglePin');

    Route::post('/leads/reminders', [LeadManageController::class, 'storeReminder'])->name('leads.reminders.store');
    Route::post('/leads/reminders/{id}/status', [LeadManageController::class, 'updateReminderStatus'])->name('leads.reminders.status');
    Route::delete('/leads/reminders/{id}', [LeadManageController::class, 'destroyReminder'])->name('leads.reminders.destroy');
    Route::get('/leads/reminders/check', [LeadManageController::class, 'checkReminders'])->name('leads.reminders.check');

    // Inline status (lead type) update — called from the "view lead" panel.
Route::patch('/leads/{lead}/type', [LeadManageController::class, 'updateType'])
    ->name('leads.updateType');

// Reminders page — lists every reminder across every lead the admin can see.
Route::get('/reminders', [LeadManageController::class, 'remindersPage'])
    ->name('reminders.page');

Route::get('/reminders/data', [LeadManageController::class, 'remindersData'])
    ->name('reminders.data');

     /* ==================== BILLING ==================== */

    Route::get('/billing', [BillingManageController::class, 'billingPage'])->name('billing.index');
    Route::get('/billing/data', [BillingManageController::class, 'data'])->name('billing.data');
    Route::post('/billing', [BillingManageController::class, 'store'])->name('billing.store');
    Route::get('/billing/{id}', [BillingManageController::class, 'show'])->name('billing.show');
    Route::put('/billing/{id}', [BillingManageController::class, 'update'])->name('billing.update');
    Route::delete('/billing/{id}', [BillingManageController::class, 'destroy'])->name('billing.destroy');
    Route::post('/billing/bulk-delete', [BillingManageController::class, 'bulkDestroy'])->name('billing.bulkDestroy');
    Route::get('/billing/{id}/pdf', [BillingManageController::class, 'downloadPdf'])->name('billing.pdf');

    // Banks (added from the billing page's filter section)
    Route::post('/billing/banks', [BillingManageController::class, 'storeBank'])->name('billing.banks.store');
    Route::put('/billing/banks/{id}', [BillingManageController::class, 'updateBank'])->name('billing.banks.update');
    Route::delete('/billing/banks/{id}', [BillingManageController::class, 'destroyBank'])->name('billing.banks.destroy');

    // Billing headers / company profiles (added from the billing page's filter section)
    Route::post('/billing/headers', [BillingManageController::class, 'storeHeader'])->name('billing.headers.store');
    Route::put('/billing/headers/{id}', [BillingManageController::class, 'updateHeader'])->name('billing.headers.update');
    Route::delete('/billing/headers/{id}', [BillingManageController::class, 'destroyHeader'])->name('billing.headers.destroy');
});



Route::prefix('admin')->middleware(['auth:admin'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Meta Lead Management
    |--------------------------------------------------------------------------
    */

    // Page

Route::post('knowledge-base/regenerate-catalog', [App\Http\Controllers\KnowledgeBaseController::class, 'regenerateCatalogOverview'])
    ->name('kb.regenerateCatalog');


Route::get('tasks', [TaskController::class, 'index'])->name('tasks.index');
Route::get('tasks/data', [TaskController::class, 'data'])->name('tasks.data');
Route::post('tasks', [TaskController::class, 'store'])->name('tasks.store');
Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.updateStatus');
Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
Route::post('tasks/bulk-destroy', [TaskController::class, 'bulkDestroy'])->name('tasks.bulkDestroy');

Route::post('tasks/comments', [TaskController::class, 'storeComment'])->name('tasks.comments.store');
Route::post('tasks/attachments', [TaskController::class, 'storeAttachment'])->name('tasks.attachments.store');
Route::delete('tasks/attachments/{attachment}', [TaskController::class, 'destroyAttachment'])->name('tasks.attachments.destroy');

Route::get('tasks-notifications/check', [TaskController::class, 'notificationsCheck'])->name('tasks.notifications.check');
Route::post('tasks-notifications/read', [TaskController::class, 'notificationsMarkRead'])->name('tasks.notifications.read');

});

Route::get('/admin/meta/webhook', [MetaLeadController::class, 'webhookVerify']);
Route::post('/admin/meta/webhook', [MetaLeadController::class, 'webhookReceive']);
Route::get('/admin/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify']);
Route::post('/admin/whatsapp/webhook', [WhatsAppWebhookController::class, 'receive']);

Route::get('/admin/test-notify', function () {

    $admin = \App\Models\Admin::first();
    $task = \App\Models\Task::first();

    $admin->notify(new \App\Notifications\TaskAssignedNotification($task));

    return 'Done';
});


Route::prefix('admin')
    ->middleware(['auth:admin', 'super_admin'])
    ->group(function () {

        Route::get('/admin-manage', [PagesController::class, 'users'])
            ->name('admin.register');


  Route::get('/inventory', [InventoryManageController::class, 'inventoryPage'])->name('inventory.index');
    Route::get('/inventory/data', [InventoryManageController::class, 'data'])->name('inventory.data');
    Route::post('/inventory', [InventoryManageController::class, 'store'])->name('inventory.store');
    Route::put('/inventory/{id}', [InventoryManageController::class, 'update'])->name('inventory.update');
    Route::delete('/inventory/{id}', [InventoryManageController::class, 'destroy'])->name('inventory.destroy');
    Route::post('/inventory/bulk-delete', [InventoryManageController::class, 'bulkDestroy'])->name('inventory.bulkDestroy');

    // Quick-add endpoints used by the "+" buttons next to the product /
    // dealer search-selects on the stock-entry modal. storeProduct is also
    // reused as the plain "Add product" action from the Products tab.
    Route::post('/inventory/products', [InventoryManageController::class, 'storeProduct'])->name('inventory.products.store');
    Route::post('/inventory/dealers', [InventoryManageController::class, 'storeDealer'])->name('inventory.dealers.store');

    // Products tab: full product list, detail view, edit, delete.
    // /inventory/products/data MUST be registered before the {id} routes
    // below so "data" doesn't get swallowed as an :id segment.
    Route::get('/inventory/products/data', [InventoryManageController::class, 'productsData'])->name('inventory.products.data');
    Route::get('/inventory/products/{id}', [InventoryManageController::class, 'productShow'])->name('inventory.products.show');
    Route::put('/inventory/products/{id}', [InventoryManageController::class, 'updateProduct'])->name('inventory.products.update');
    Route::delete('/inventory/products/{id}', [InventoryManageController::class, 'destroyProduct'])->name('inventory.products.destroy');

    // Product image gallery — add (multiple files) / remove (one at a time).
    Route::post('/inventory/products/{id}/images', [InventoryManageController::class, 'storeProductImages'])->name('inventory.products.images.store');
    Route::delete('/inventory/products/images/{imageId}', [InventoryManageController::class, 'destroyProductImage'])->name('inventory.products.images.destroy');


     Route::get('/meta-leads', [MetaLeadController::class, 'index'])->name('metaLeads.index');

    // Ajax
    Route::get('/meta-leads/data', [MetaLeadController::class, 'data'])->name('metaLeads.data');

    // Single Lead
    Route::get('/meta-leads/{id}', [MetaLeadController::class, 'show'])->name('metaLeads.show');

    // Convert to CRM Lead
    Route::post('/meta-leads/{id}/convert', [MetaLeadController::class, 'convert'])->name('metaLeads.convert');

    // Discard
    Route::post('/meta-leads/{id}/discard', [MetaLeadController::class, 'discard'])->name('metaLeads.discard');

    // Delete
    Route::delete('/meta-leads/{id}', [MetaLeadController::class, 'destroy'])->name('metaLeads.destroy');

    // Bulk Delete
    Route::post('/meta-leads/bulk-delete', [MetaLeadController::class, 'bulkDestroy'])->name('metaLeads.bulkDestroy');


    Route::prefix('knowledge-base')->name('kb.')->group(function () {
    // Page
    Route::get('/', [KnowledgeBaseController::class, 'index'])->name('index');
    // Ajax list
    Route::get('/data', [KnowledgeBaseController::class, 'data'])->name('data');

    // CRUD
    Route::get('/{id}', [KnowledgeBaseController::class, 'show'])->name('show');
    Route::post('/', [KnowledgeBaseController::class, 'store'])->name('store');
    Route::put('/{id}', [KnowledgeBaseController::class, 'update'])->name('update');
    Route::delete('/{id}', [KnowledgeBaseController::class, 'destroy'])->name('destroy');
    Route::post('/bulk-delete', [KnowledgeBaseController::class, 'bulkDestroy'])->name('bulkDestroy');

    // Approval workflow
    Route::post('/{id}/submit', [KnowledgeBaseController::class, 'submit'])->name('submit');
    Route::post('/{id}/approve', [KnowledgeBaseController::class, 'approve'])->name('approve');
    Route::post('/{id}/reject', [KnowledgeBaseController::class, 'reject'])->name('reject');
    Route::post('/{id}/archive', [KnowledgeBaseController::class, 'archive'])->name('archive');

    // Version history
    Route::post('/{id}/restore-version', [KnowledgeBaseController::class, 'restoreVersion'])->name('restoreVersion');

    // Media (PDF / Image uploads)
    Route::post('/{id}/media', [KnowledgeBaseController::class, 'uploadMedia'])->name('media.upload');
    Route::delete('/media/{mediaId}', [KnowledgeBaseController::class, 'destroyMedia'])->name('media.destroy');

    // Categories
    Route::post('/categories', [KnowledgeBaseController::class, 'storeCategory'])->name('categories.store');
    Route::put('/categories/{id}', [KnowledgeBaseController::class, 'updateCategory'])->name('categories.update');
    Route::delete('/categories/{id}', [KnowledgeBaseController::class, 'destroyCategory'])->name('categories.destroy');
});



});

// Ye wala group jaisa hai waisa rehne do (kb, chatbot-settings, inventory, meta-leads):
Route::prefix('admin')
    ->middleware(['auth:admin', 'super_admin'])
    ->group(function () {

        // ... inventory, meta-leads, knowledge-base ...

        Route::prefix('chatbot-settings')->name('chatbotSettings.')->middleware('super_admin')->group(function () {
            Route::get('/', [ChatbotSettingController::class, 'index'])->name('index');
            Route::get('/data', [ChatbotSettingController::class, 'show'])->name('show');
            Route::put('/', [ChatbotSettingController::class, 'update'])->name('update');
            Route::post('/regenerate-webhook-token', [ChatbotSettingController::class, 'regenerateWebhookToken'])->name('regenerateToken');
        });
});

// Naya, alag group — sirf auth:admin, koi bhi admin/agent access kar sake:
Route::prefix('admin')->middleware(['auth:admin'])->group(function () {
    Route::prefix('chat')->name('chat.')->group(function () {
        Route::get('/', [ChatController::class, 'index'])->name('index');
        Route::get('/data', [ChatController::class, 'data'])->name('data');
        Route::get('/employees', [ChatController::class, 'employees'])->name('employees');
        Route::get('/{id}', [ChatController::class, 'show'])->name('show');
    Route::post('/{id}/send-media', [ChatController::class, 'sendMedia'])->name('chat.sendMedia');

        Route::post('/{id}/send', [ChatController::class, 'send'])->name('send');
        Route::post('/{id}/takeover', [ChatController::class, 'takeover'])->name('takeover');
        Route::post('/{id}/resume-ai', [ChatController::class, 'resumeAi'])->name('resumeAi');
        Route::post('/{id}/transfer', [ChatController::class, 'transfer'])->name('transfer');
        Route::post('/{id}/assign', [ChatController::class, 'assign'])->name('assign');
        Route::post('/{id}/lock', [ChatController::class, 'lock'])->name('lock');
        Route::post('/{id}/unlock', [ChatController::class, 'unlock'])->name('unlock');
        Route::patch('/{id}/ai-toggle', [ChatController::class, 'aiToggle'])->name('aiToggle');

        Route::post('/{id}/notes', [ChatController::class, 'storeNote'])->name('notes.store');
        Route::post('/{id}/convert-to-lead', [ChatController::class, 'convertToLead'])->name('convertToLead');
    });
    Route::post('/leads/attachments', [LeadManageController::class, 'storeAttachment'])->name('leads.attachments.store');
Route::delete('/leads/attachments/{id}', [LeadManageController::class, 'destroyAttachment'])->name('leads.attachments.destroy');
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
});
