<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PlayerController;

Route::get('/', function () {
    return view('show');
});
Route::resource('players', PlayerController::class)->only(['store']);

