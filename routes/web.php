<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ConsolidatedReportController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DeliveryPlanController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MessengerPageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RdDashboardController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RequestLetterController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/rd-dashboard/{incident?}', [RdDashboardController::class, 'index'])
        ->middleware('role:regional_director')
        ->name('rd-dashboard');

    Route::resource('incidents', IncidentController::class)->except(['destroy']);

    Route::resource('incidents.reports', ReportController::class);

    Route::post('/incidents/{incident}/reports/{report}/validate', [ReportController::class, 'validateReport'])
        ->name('incidents.reports.validate');

    Route::post('/incidents/{incident}/reports/{report}/return', [ReportController::class, 'returnReport'])
        ->name('incidents.reports.return');

    Route::get('/incidents/{incident}/consolidated', [ConsolidatedReportController::class, 'show'])
        ->middleware('role:admin,regional,provincial')
        ->name('incidents.consolidated');

    Route::post('/incidents/{incident}/request-letters', [RequestLetterController::class, 'store'])
        ->name('incidents.request-letters.store');
    Route::get('/incidents/{incident}/request-letters/{requestLetter}', [RequestLetterController::class, 'show'])
        ->name('incidents.request-letters.show');
    Route::delete('/incidents/{incident}/request-letters/{requestLetter}', [RequestLetterController::class, 'destroy'])
        ->name('incidents.request-letters.destroy');
    Route::post('/incidents/{incident}/request-letters/{requestLetter}/endorse', [RequestLetterController::class, 'endorse'])
        ->name('incidents.request-letters.endorse');
    Route::post('/incidents/{incident}/request-letters/{requestLetter}/approve', [RequestLetterController::class, 'approve'])
        ->name('incidents.request-letters.approve');

    Route::post('/incidents/{incident}/request-letters/{requestLetter}/deliveries', [DeliveryController::class, 'store'])
        ->name('incidents.request-letters.deliveries.store');
    Route::patch('/deliveries/{delivery}', [DeliveryController::class, 'update'])
        ->name('deliveries.update');

    Route::post('/incidents/{incident}/request-letters/{requestLetter}/delivery-plan', [DeliveryPlanController::class, 'store'])
        ->name('incidents.request-letters.delivery-plan.store');
    Route::delete('/incidents/{incident}/request-letters/{requestLetter}/delivery-plan/{deliveryPlan}', [DeliveryPlanController::class, 'destroy'])
        ->name('incidents.request-letters.delivery-plan.destroy');

    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::get('/api/barangays', [ReportController::class, 'barangays'])->name('reports.barangays');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllRead');

    // Messenger
    Route::get('/messenger', MessengerPageController::class)->name('messenger');
    Route::prefix('messenger')->group(function () {
        Route::get('/conversations', [ConversationController::class, 'index'])->name('messenger.conversations.index');
        Route::post('/conversations/dm', [ConversationController::class, 'storeDm'])->name('messenger.conversations.dm');
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('messenger.conversations.show');
        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->name('messenger.messages.store');
        Route::get('/users', [ConversationController::class, 'users'])->name('messenger.users');
        Route::get('/unread-count', [ConversationController::class, 'unreadCount'])->name('messenger.unread-count');
    });
});
