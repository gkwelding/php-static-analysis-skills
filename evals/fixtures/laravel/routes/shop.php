<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::get('/orders/unshipped', [OrderController::class, 'unshipped']);
Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
