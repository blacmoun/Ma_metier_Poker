<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Player;
use App\Services\PokerService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GameController extends Controller
{
    // Fonction interne pour ramasser les mises et les mettre dans le pot
    private function collectBets($game) {
        $totalBets = $game->players->sum('current_bet');
        $game->increment('pot', $totalBets);
        foreach ($game->players as $p) {
            $p->update(['current_bet' => 0]);
        }
    }

    public function show(PokerService $pokerService)
    {
        $game = Game::with('players')->first();
        if (!$game) {
            $game = Game::create(['status' => 'waiting', 'community_cards' => [], 'dealer_index' => 0, 'pot' => 0]);
        }

        $now = Carbon::now();
        $timerValue = 0;
        $players = $game->players;
        $playerCount = $players->count();

        // 1. SÉCURITÉ : RESET SI JOUEUR MANQUANT
        if ($playerCount < 2 && $game->status !== 'waiting') {
            $game->update([
                'status' => 'waiting',
                'timer_at' => null,
                'community_cards' => [],
                'deck' => null,
                'current_turn' => 0,
                'pot' => 0
            ]);
            Player::where('game_id', $game->id)->update(['hand' => null, 'current_bet' => 0]);
            $game->status = 'waiting';
        }

        // 2. INITIALISATION
        if ($playerCount === 2 && $game->status === 'waiting') {
            $game->update([
                'status' => 'countdown',
                'timer_at' => $now->addSeconds(10),
                'dealer_index' => rand(0, 1),
                'pot' => 0
            ]);
        }

        // 3. LOGIQUE DES ÉTATS
        if ($game->timer_at) {
            $timerValue = $now->diffInSeconds(Carbon::parse($game->timer_at), false);

            if ($timerValue <= 0) {
                $deck = $game->deck ?? [];

                // On ramasse l'argent à chaque transition d'état de jeu
                if (in_array($game->status, ['pre-flop', 'flop', 'turn', 'river'])) {
                    $this->collectBets($game);
                }

                switch ($game->status) {
                    case 'countdown':
                        $game->update(['status' => 'dealing', 'timer_at' => $now->addSeconds(5)]);
                        break;

                    case 'dealing':
                        $newDeck = $pokerService->createDeck();
                        $sbIndex = $game->dealer_index;
                        $bbIndex = 1 - $sbIndex;

                        foreach ($players as $index => $player) {
                            $hand = $pokerService->deal($newDeck, 2);

                            // Déduction des Blinds
                            $blindAmount = ($index === $sbIndex) ? 20 : 40;

                            // Sécurité : on ne mise pas plus que ce qu'on a
                            $actualBet = min($player->chips, $blindAmount);

                            $player->update([
                                'hand' => $hand,
                                'current_bet' => $actualBet,
                                'chips' => $player->chips - $actualBet
                            ]);
                        }

                        // PRE-FLOP : Le Dealer (SB) commence
                        $game->update([
                            'status' => 'pre-flop',
                            'deck' => $newDeck,
                            'community_cards' => [],
                            'timer_at' => $now->addSeconds(15),
                            'current_turn' => $sbIndex
                        ]);
                        break;

                    case 'pre-flop':
                        $flop = $pokerService->deal($deck, 3);
                        $game->update(['status' => 'flop', 'deck' => $deck, 'community_cards' => $flop, 'timer_at' => $now->addSeconds(15), 'current_turn' => 1 - $game->dealer_index]);
                        break;

                    case 'flop':
                        $turn = array_merge($game->community_cards, $pokerService->deal($deck, 1));
                        $game->update(['status' => 'turn', 'deck' => $deck, 'community_cards' => $turn, 'timer_at' => $now->addSeconds(15), 'current_turn' => 1 - $game->dealer_index]);
                        break;

                    case 'turn':
                        $river = array_merge($game->community_cards, $pokerService->deal($deck, 1));
                        $game->update(['status' => 'river', 'deck' => $deck, 'community_cards' => $river, 'timer_at' => $now->addSeconds(15), 'current_turn' => 1 - $game->dealer_index]);
                        break;

                    case 'river':
                        $game->update(['status' => 'finished', 'timer_at' => null]);
                        break;
                }
                $timerValue = ($game->status === 'finished') ? 0 : 15;
            }
        }

        return response()->json([
            'players' => $game->players->map(fn($p, $i) => [
                'name' => $p->name,
                'chips' => $p->chips,
                'current_bet' => $p->current_bet, // Ajout de la mise
                'is_me' => ($p->session_token === session('player_token')),
                'hand' => ($p->session_token === session('player_token')) ? $p->hand : null,
                'has_cards' => !empty($p->hand),
                'is_dealer' => ($i === $game->dealer_index),
                'role' => ($i === $game->dealer_index) ? 'SB' : 'BB'
            ]),
            'community_cards' => $game->community_cards ?? [],
            'status' => $game->status,
            'timer' => max(0, (int)$timerValue),
            'currentTurn' => (int)$game->current_turn,
            'pot' => $game->pot // Ajout du Pot
        ]);
    }

    public function restart() {
        $game = Game::first();
        if ($game) {
            $game->update(['status' => 'waiting', 'timer_at' => null, 'community_cards' => [], 'deck' => null, 'current_turn' => 0, 'pot' => 0]);
            Player::where('game_id', $game->id)->update(['hand' => null, 'current_bet' => 0]);
        }
        return response()->json(['status' => 'success']);
    }

    public function join(Request $request) {
        $request->validate(['name' => 'required|string|max:50']);
        $game = Game::firstOrCreate([], ['status' => 'waiting', 'dealer_index' => 0, 'pot' => 0]);
        if ($game->players()->count() >= 2) return response()->json(['error' => 'Full'], 403);
        $token = Str::random(32);
        $game->players()->create(['name' => $request->name, 'chips' => 1000, 'session_token' => $token, 'current_bet' => 0]);
        session(['player_token' => $token]);
        return response()->json(['message' => 'Ok']);
    }

    public function logout() {
        if (session()->has('player_token')) {
            $player = Player::where('session_token', session('player_token'))->first();
            if ($player) {
                $gameId = $player->game_id;
                $player->delete();
                Game::where('id', $gameId)->update(['status' => 'waiting', 'timer_at' => null, 'pot' => 0]);
                Player::where('game_id', $gameId)->update(['hand' => null, 'current_bet' => 0]);
            }
            session()->forget('player_token');
        }
        return response()->json(['message' => 'Ok']);
    }
}
