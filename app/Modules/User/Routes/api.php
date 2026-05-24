<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Modules\User\Controllers\UserController;
use App\Modules\User\Controllers\NotificationController;
use App\Modules\User\Controllers\FriendshipController;
use App\Modules\User\Controllers\DeviceController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/heartbeat', [DeviceController::class, 'heartbeat']);

    Route::get('/search', [UserController::class, 'search']);
    Route::get('/friendship-states', [FriendshipController::class, 'index']);
    Route::get('/friends', [FriendshipController::class, 'friends']);
    Route::post('/friendships/request', [FriendshipController::class, 'store']);
    Route::post('/friendships/accept', [FriendshipController::class, 'accept']);
    Route::delete('/friendships/decline', [FriendshipController::class, 'decline']);
    Route::delete('/friendships/unfriend', [FriendshipController::class, 'destroy']);


    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markAsRead']);
});
