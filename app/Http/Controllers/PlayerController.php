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
        if (!isset($playersArray[$game->current_turn]) || $playersArray[$game->current_turn]->id !== $player->id) {
            return response()->json(['error' => 'Not your turn'], 403);
        }

        $type = $request->input('action');
        $opponent = $game->players->where('id', '!=', $player->id)->first();

        switch ($type) {
            case 'call':
                $diff = ($opponent->current_bet ?? 0) - $player->current_bet;
                if ($diff > 0) {
                    $amountToPay = min($player->chips, $diff);
                    $player->decrement('chips', $amountToPay);
                    $player->increment('current_bet', $amountToPay);
                }
                break;

            case 'allin':
                $allInAmount = $player->chips;
                $player->increment('current_bet', $allInAmount);
                $player->update(['chips' => 0]);
                break;

            case 'raise':
                $totalSaisi = (int) $request->input('amount', 0);
                $minRequired = ($opponent->current_bet ?? 0);
                if ($totalSaisi <= $minRequired) $totalSaisi = $minRequired + 20;

                $toAdd = $totalSaisi - $player->current_bet;
                $actualAdd = min($player->chips, $toAdd);

                $player->decrement('chips', $actualAdd);
                $player->increment('current_bet', $actualAdd);
                break;
        }

        return app(GameController::class)->play($request, $pokerService);
    }
}
