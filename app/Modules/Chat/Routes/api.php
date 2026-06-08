<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Chat\Controllers\ConversationController;
use App\Modules\Chat\Controllers\MessageController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/1on1', [ConversationController::class, 'getOrCreate1on1']);
    
    // Group Chat Routes
    Route::post('/groups', [ConversationController::class, 'storeGroup']);
    Route::post('/groups/{id}/members', [ConversationController::class, 'addMembers']);
    Route::delete('/groups/{id}/members/{userId}', [ConversationController::class, 'removeMember']);
    Route::put('/groups/{id}', [ConversationController::class, 'updateGroup']);
    
    Route::get('/conversations/{conversationId}/messages', [MessageController::class, 'index']);
    Route::post('/conversations/{conversationId}/messages', [MessageController::class, 'store']);
    Route::post('/messages/forward', [MessageController::class, 'forwardMessages']);
    Route::post('/conversations/{conversationId}/deliver', [ConversationController::class, 'markAsDelivered']);
    Route::post('/conversations/{conversationId}/read', [ConversationController::class, 'markAsRead']);
});
