<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Media\Controllers\UploadController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/upload', [UploadController::class, 'store']);
});
