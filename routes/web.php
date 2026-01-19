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

Route::post('/logout', function(){
    $token = session('player_token');
    if($token){
        // Remove player from database
        Player::where('session_token', $token)->delete();
        session()->forget('player_token');
    }
    return response()->json(['message'=>'Logged out']);
});
