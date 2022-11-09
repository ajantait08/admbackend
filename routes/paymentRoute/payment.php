<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\payment\PaymentController;

Route::controller(PaymentController::class)->group(function () {
    Route::get('testAPI', 'testAPI');
    Route::post('register_api','register_api');
});
