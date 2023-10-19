<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;
use App\Models\Group;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    // All the routes for an organisation
    Route::prefix('organisations')->group(function () {
        Route::get('', [ApiController::class, 'getOrganisations']);
        Route::get('/{organisation}', [ApiController::class, 'getOrganisation'])->whereNumber('organisation');
        Route::get('/groups/{group}', [ApiController::class, 'getSubgroup'])->whereNumber('group');
        Route::post('/group', [ApiController::class, 'addSubgroup'])->middleware('can:create,App\Models\Group');
        Route::get('/groups/{group}/waitlist', [ApiController::class, 'getWaitlist'])->whereNumber('group');
        Route::patch('/groups/{group}/waitlist/{child}', [ApiController::class, 'updateWaitlister'])
        ->middleware('can:update,App\Models\Group,group')
        ->whereNumber(['group', 'child']);
    
        // Voorbeeld voor bepaalde notificatie op te halen: /notifications?id=12
        Route::get('/groups/{group}/notifications', [ApiController::class, 'getNotifications'])->whereNumber('group');
        Route::post('/groups/{group}/notification', [ApiController::class, 'addNotification'])->whereNumber('group');
        Route::patch('/groups/{group}/notifications/{notification}', [ApiController::class, 'updateNotification'])
            ->middleware(['can:update,group'], ['can:update,notification'])
            ->whereNumber(['group', 'notification']);
    });
    // Idem zoals notificatie
    Route::get('/messages', [ApiController::class, 'getMessages']);
    Route::get('/messages/{id}', [ApiController::class, 'getMessage'])->whereNumber('id');
    Route::post('/message', [ApiController::class, 'addMessage']);

    // All the routes for a normal user
    Route::prefix('user')->group(function () {
        // Onderstaande route geld voor zowel een organisatie ALS een gewone user
        Route::get('', [ApiController::class, 'getUser']);
        Route::post('', [ApiController::class, 'addUser']);
        Route::put('', [ApiController::class, 'updateUser']);

        // Status ophalen van een gebruiker
        Route::get('/status/{id}', [ApiController::class, 'getStatus'])->whereNumber('id');
        Route::patch('/status', [ApiController::class, 'updateStatus']);
    });

    // Get child specific items, only for users who's role is 'user'
    Route::prefix('children')->group(function () {
        Route::get('/subscriptions', [ApiController::class, 'getSubscriptions']);
        Route::get('/subscriptions/all', [ApiController::class, 'getAllSubscriptions']);
        Route::post('/subscription', [ApiController::class, 'addSubscription']);
        Route::delete('/subscription', [ApiController::class, 'deleteSubscription']);

        Route::get('/notifications/unseen/{id}', [ApiController::class, 'getNotificationsChild'])->whereNumber('id');
        Route::get('/notifications/all/{id}', [ApiController::class, 'getAllNotificationsChild'])->whereNumber('id');
        Route::get('/notifications/allTodo/{id}', [ApiController::class, 'getAllTodoChild'])->whereNumber('id');
        
        Route::get('', [ApiController::class, 'getChildren']);
        Route::post('', [ApiController::class, 'addChild']);

        Route::patch('/notification/filled', [ApiController::class, 'updateFilled']);
        Route::patch('/notification/seen', [ApiController::class, 'updateSeen']);

    })->middleware(['can:view,App\Models\Child', 'can:create,App\Models\Child']);
});
