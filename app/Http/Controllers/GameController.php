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
    public function show(PokerService $pokerService)
    {
        $game = Game::with('players')->first();
        if (!$game) {
            $game = Game::create(['status' => 'waiting', 'community_cards' => [], 'dealer_index' => 0]);
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
                'current_turn' => 0
            ]);
            Player::where('game_id', $game->id)->update(['hand' => null]);
            $game->status = 'waiting';
        }

        // 2. INITIALISATION : COUNTDOWN & DEALER
        if ($playerCount === 2 && $game->status === 'waiting') {
            $game->update([
                'status' => 'countdown',
                'timer_at' => $now->addSeconds(10),
                'dealer_index' => rand(0, 1) // On choisit un dealer au hasard
            ]);
        }

        // 3. LOGIQUE DES ÉTATS (AUTOMATE)
        if ($game->timer_at) {
            $timerValue = $now->diffInSeconds(Carbon::parse($game->timer_at), false);

            if ($timerValue <= 0) {
                $deck = $game->deck ?? [];

                switch ($game->status) {
                    case 'countdown':
                        $game->update(['status' => 'dealing', 'timer_at' => $now->addSeconds(5)]);
                        break;

                    case 'dealing':
                        $newDeck = $pokerService->createDeck();
                        foreach ($players as $player) {
                            $player->update(['hand' => $pokerService->deal($newDeck, 2)]);
                        }
                        // PRE-FLOP : Le Dealer parle en premier (SB) en Heads-up
                        $game->update([
                            'status' => 'pre-flop',
                            'deck' => $newDeck,
                            'community_cards' => [],
                            'timer_at' => $now->addSeconds(15),
                            'current_turn' => $game->dealer_index
                        ]);
                        break;

                    case 'pre-flop':
                        $flop = $pokerService->deal($deck, 3);
                        // POST-FLOP : Le BB (Non-Dealer) parle en premier
                        $game->update([
                            'status' => 'flop',
                            'deck' => $deck,
                            'community_cards' => $flop,
                            'timer_at' => $now->addSeconds(15),
                            'current_turn' => 1 - $game->dealer_index
                        ]);
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
            'players' => $players->map(fn($p, $i) => [
                'name' => $p->name,
                'chips' => $p->chips,
                'is_me' => ($p->session_token === session('player_token')),
                'hand' => ($p->session_token === session('player_token')) ? $p->hand : null,
                'has_cards' => !empty($p->hand),
                'is_dealer' => ($i === $game->dealer_index),
                'role' => ($i === $game->dealer_index) ? 'SB' : 'BB'
            ]),
            'community_cards' => $game->community_cards ?? [],
            'status' => $game->status,
            'timer' => max(0, (int)$timerValue),
            'currentTurn' => (int)$game->current_turn
        ]);
    }

    public function restart() {
        $game = Game::first();
        if ($game) {
            $game->update([
                'status' => 'waiting',
                'timer_at' => null,
                'community_cards' => [],
                'deck' => null,
                'current_turn' => 0
            ]);
            Player::where('game_id', $game->id)->update(['hand' => null]);
        }
        return response()->json(['status' => 'success']);
    }

    public function join(Request $request) {
        $request->validate(['name' => 'required|string|max:50']);
        $game = Game::firstOrCreate([], ['status' => 'waiting', 'dealer_index' => 0]);
        if ($game->players()->count() >= 2) return response()->json(['error' => 'Full'], 403);

        $token = Str::random(32);
        $game->players()->create(['name' => $request->name, 'chips' => 1000, 'session_token' => $token]);
        session(['player_token' => $token]);
        return response()->json(['message' => 'Ok']);
    }

    public function logout() {
        if (session()->has('player_token')) {
            $player = Player::where('session_token', session('player_token'))->first();
            if ($player) {
                $gameId = $player->game_id;
                $player->delete();
                Game::where('id', $gameId)->update([
                    'status' => 'waiting',
                    'timer_at' => null,
                    'community_cards' => [],
                    'deck' => null
                ]);
                Player::where('game_id', $gameId)->update(['hand' => null]);
            }
            session()->forget('player_token');
        }
        return response()->json(['message' => 'Ok']);
    }
}
