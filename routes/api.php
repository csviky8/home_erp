<?php

use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\HouseholdWorkflowController;
use App\Http\Controllers\Api\HouseholdController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\NotificationsController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::put('/auth/working-family', [AuthController::class, 'updateHouseholdPreference']);
    Route::get('/auth/sessions', [AuthController::class, 'sessions']);
    Route::delete('/auth/sessions/{session}', [AuthController::class, 'revokeSession']);

    Route::get('/dashboard', DashboardController::class);
    Route::get('/search', SearchController::class);
    Route::get('/lookups/{resource}/{type?}', LookupController::class);

    Route::post('/bills/{bill}/pay', [HouseholdWorkflowController::class, 'payBill']);
    Route::post('/inventory/{item}/movement', [HouseholdWorkflowController::class, 'moveInventory']);
    Route::get('/tasks/{task}/details', [HouseholdWorkflowController::class, 'taskDetails']);
    Route::post('/tasks/{task}/checklist', [HouseholdWorkflowController::class, 'addChecklistItem']);
    Route::post('/tasks/{task}/comments', [HouseholdWorkflowController::class, 'addTaskComment']);
    Route::get('/assets/{asset}/history', [HouseholdWorkflowController::class, 'assetHistory']);
    Route::get('/vehicles/{vehicle}/history', [HouseholdWorkflowController::class, 'vehicleHistory']);
    Route::post('/vehicles/{vehicle}/expenses', [HouseholdWorkflowController::class, 'addVehicleExpense']);
    Route::get('/security/visitors', [HouseholdWorkflowController::class, 'listVisitors']);
    Route::post('/security/visitors', [HouseholdWorkflowController::class, 'storeVisitor']);
    Route::get('/pets/{pet}/health', [HouseholdWorkflowController::class, 'petHealth']);
    Route::post('/pets/{pet}/health', [HouseholdWorkflowController::class, 'addPetHealth']);
    Route::post('/garden/{plant}/expenses', [HouseholdWorkflowController::class, 'addGardenExpense']);

    Route::get('/modules/{module}/schema', [ModuleController::class, 'schema']);
    Route::get('/modules/{module}', [ModuleController::class, 'index']);
    Route::post('/modules/{module}', [ModuleController::class, 'store']);
    Route::get('/modules/{module}/{record}', [ModuleController::class, 'show'])->whereNumber('record');
    Route::put('/modules/{module}/{record}', [ModuleController::class, 'update'])->whereNumber('record');
    Route::delete('/modules/{module}/{record}', [ModuleController::class, 'destroy'])->whereNumber('record');

    Route::get('/reports/export/{type}/{format}', [ReportsController::class, 'export']);
    Route::get('/reports/{type}', [ReportsController::class, 'index']);
    Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
    Route::get('/documents/{document}/preview', [DocumentController::class, 'preview']);

    Route::get('/notifications', [NotificationsController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationsController::class, 'read'])->whereNumber('notification');
    Route::post('/notifications/read-all', [NotificationsController::class, 'readAll']);

    Route::get('/households', [HouseholdController::class, 'index']);
    Route::post('/households', [HouseholdController::class, 'store']);
    Route::put('/households/{household}', [HouseholdController::class, 'update']);
    Route::get('/households/{household}/users', [HouseholdController::class, 'users']);
    Route::post('/households/{household}/users', [HouseholdController::class, 'storeUser']);

    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::get('/settings/access', [SettingsController::class, 'access']);
    Route::post('/settings/roles', [SettingsController::class, 'storeRole']);
    Route::put('/settings/roles/{role}', [SettingsController::class, 'updateRole']);
    Route::delete('/settings/roles/{role}', [SettingsController::class, 'destroyRole']);
    Route::get('/settings/users', [SettingsController::class, 'users']);
    Route::post('/settings/users', [SettingsController::class, 'storeUser']);
    Route::put('/settings/users/{user}', [SettingsController::class, 'updateUser']);
    Route::delete('/settings/users/{user}', [SettingsController::class, 'destroyUser']);
    Route::put('/settings/users/{user}/role', [SettingsController::class, 'updateUserRole']);
    Route::get('/settings/users/{user}/permissions', [SettingsController::class, 'userPermissions']);
    Route::put('/settings/users/{user}/permissions', [SettingsController::class, 'updateUserPermissions']);
    Route::post('/settings/categories', [SettingsController::class, 'saveCategory']);

    Route::middleware('throttle:ai')->group(function (): void {
        Route::get('/ai/history', [AiChatController::class, 'history']);
        Route::post('/ai/chat', [AiChatController::class, 'chat']);
        Route::delete('/ai/history', [AiChatController::class, 'clear']);
    });
});
