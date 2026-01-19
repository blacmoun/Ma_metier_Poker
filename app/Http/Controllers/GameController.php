<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Str;

class GameController extends Controller
{
    public function join(Request $request)
    {
        $request->validate(['name' => 'required|string|max:50']);

        // Get or create a single game
        $game = Game::firstOrCreate([], ['status' => 'waiting']);

        // Check if game is full (2 players max)
        if ($game->players()->count() >= 2) {
            return response()->json(['error' => 'Game is full'], 403);
        }

        // Check if name already taken
        if ($game->players()->where('name', $request->name)->exists()) {
            return response()->json(['error' => 'Name already taken'], 403);
        }

        // Create player with session token
        $token = Str::random(32);
        $player = $game->players()->create([
            'name' => $request->name,
            'chips' => 1000,
            'session_token' => $token
        ]);

        // Save session
        session(['player_token' => $token]);

        return response()->json([
            'message' => 'Joined',
            'player' => $player
        ]);
    }

    public function show()
    {
        $game = Game::with('players')->first();
        return response()->json($game);
    }
}
