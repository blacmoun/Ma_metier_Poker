<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PlayerController;

Route::get('/', function () {
    return view('poker_table');
});
Route::resource('players', PlayerController::class)->only(['store']);

