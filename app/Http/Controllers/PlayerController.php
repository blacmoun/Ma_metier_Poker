<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Game;
use App\Services\PokerService;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function action(Request $request, PokerService $pokerService)
    {
        $game = Game::with('players')->first();
        $player = Player::where('session_token', session('player_token'))->first();

        if (!$player) return response()->json(['error' => 'Player not found'], 404);

        $playersArray = $game->players->values();

        // Vérification stricte du tour
        if (!isset($playersArray[$game->current_turn]) || $playersArray[$game->current_turn]->id !== $player->id) {
            return response()->json(['error' => 'Not your turn'], 403);
        }

        // On délègue tout au GameController pour éviter les doubles débits de jetons
        return app(GameController::class)->play($request, $pokerService);
    }
}
