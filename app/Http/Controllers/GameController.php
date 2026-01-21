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
        if (!$game) $game = Game::create(['status' => 'waiting', 'community_cards' => []]);

        $now = Carbon::now();
        $timerValue = 0;
        $playerCount = $game->players->count();

        // 1. RESET SI JOUEUR MANQUANT
        if ($playerCount < 2 && $game->status !== 'waiting') {
            $game->update(['status' => 'waiting', 'timer_at' => null, 'community_cards' => [], 'deck' => null]);
            Player::where('game_id', $game->id)->update(['hand' => null]);
        }

        // 2. LOGIQUE DES ÉTATS (AUTOMATE)
        if ($playerCount === 2 && $game->status === 'waiting') {
            $game->update(['status' => 'countdown', 'timer_at' => $now->addSeconds(10)]);
        }

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
                        foreach ($game->players as $player) {
                            $player->update(['hand' => $pokerService->deal($newDeck, 2)]);
                        }
                        $game->update([
                            'status' => 'pre-flop',
                            'deck' => $newDeck,
                            'community_cards' => [],
                            'timer_at' => $now->addSeconds(5),
                            'current_turn' => 0
                        ]);
                        break;

                    case 'pre-flop':
                        $flop = $pokerService->deal($deck, 3);
                        $game->update(['status' => 'flop', 'deck' => $deck, 'community_cards' => $flop, 'timer_at' => $now->addSeconds(15)]);
                        break;

                    case 'flop':
                        $turn = array_merge($game->community_cards, $pokerService->deal($deck, 1));
                        $game->update(['status' => 'turn', 'deck' => $deck, 'community_cards' => $turn, 'timer_at' => $now->addSeconds(15)]);
                        break;

                    case 'turn':
                        $river = array_merge($game->community_cards, $pokerService->deal($deck, 1));
                        $game->update(['status' => 'river', 'deck' => $deck, 'community_cards' => $river, 'timer_at' => $now->addSeconds(15)]);
                        break;

                    case 'river':
                        $game->update(['status' => 'finished', 'timer_at' => null]);
                        break;
                }
                $timerValue = ($game->status === 'finished') ? 0 : 15;
            }
        }

        return response()->json([
            'players' => $game->players->map(fn($p) => [
                'name' => $p->name,
                'chips' => $p->chips,
                'is_me' => ($p->session_token === session('player_token')),
                'hand' => ($p->session_token === session('player_token')) ? $p->hand : null,
                'has_cards' => !empty($p->hand)
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
        $game = Game::firstOrCreate([], ['status' => 'waiting']);
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
                Game::where('id', $gameId)->update(['status' => 'waiting', 'timer_at' => null]);
                Player::where('game_id', $gameId)->update(['hand' => null]);
            }
            session()->forget('player_token');
        }
        return response()->json(['message' => 'Ok']);
    }
}
