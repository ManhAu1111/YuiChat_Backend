<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Chat\Controllers\ConversationController;
use App\Modules\Chat\Controllers\MessageController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/1on1', [ConversationController::class, 'getOrCreate1on1']);
    
    Route::get('/conversations/{conversationId}/messages', [MessageController::class, 'index']);
    Route::post('/conversations/{conversationId}/messages', [MessageController::class, 'store']);
});
