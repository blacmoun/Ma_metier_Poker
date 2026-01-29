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
                $requestedAmount = (int) $request->input('amount', 0);
                $diff = ($opponent->current_bet ?? 0) - $player->current_bet;
                $minRequired = $diff + 40;

                $toAdd = max($minRequired, $requestedAmount);
                $actualAdd = min($player->chips, $toAdd);

                $player->decrement('chips', $actualAdd);
                $player->increment('current_bet', $actualAdd);
                break;
        }

        // On appelle play() SANS lui redemander de calculer les mises
        return app(GameController::class)->play($request, $pokerService);
    }
}
