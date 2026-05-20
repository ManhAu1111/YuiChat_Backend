<?php

require base_path('app/Modules/Auth/Routes/api.php');
require base_path('app/Modules/User/Routes/api.php');
require base_path('app/Modules/Chat/Routes/api.php');

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeviceController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/heartbeat', [DeviceController::class, 'heartbeat']);
});
