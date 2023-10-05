<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\admission\AdmissionController;
use App\Models\Product;

Route::controller(AdmissionController::class)->group(function () {
    // Route::post('check_postman','check_postman');
    // Route::post('check_admnno','check_admnno');
    Route::post('register_user','register_user');
    // Route::post('create_products','create_products');
    // Route::get('get_products','get_products');
    // Route::post('check_admnno_login','check_admnno_login');
    // Route::post('login_user', 'login_user');
});

?>