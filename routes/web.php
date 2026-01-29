<?php

use Illuminate\Support\Facades\Route;
use App\Models\Player;
use App\Http\Controllers\GameController;
use App\Http\Controllers\PlayerController;

Route::get('/', function () {
    return view('poker_table');
});

Route::post('/join', [GameController::class, 'join']);
Route::get('/game', [GameController::class, 'show']);


Route::post('/logout', [GameController::class, 'logout']);
Route::post('/restart', [GameController::class, 'restart']);
Route::post('/action', [PlayerController::class, 'action']);
