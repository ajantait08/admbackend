<?php
//by @bhijeet

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::controller(UserController::class)->group(function () {
    Route::post('sendSms', 'sendSms');
});