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
        if (!$game) $game = Game::create(['status' => 'waiting']);

        $now = Carbon::now();
        $timerValue = 0;
        $playerCount = $game->players->count();

        // 1. FORCE RESET SI JOUEUR MANQUANT
        if ($playerCount < 2 && $game->status !== 'waiting') {
            $game->update(['status' => 'waiting', 'timer_at' => null, 'current_turn' => 0]);
            Player::where('game_id', $game->id)->update(['hand' => null]);
        }

        // 2. LOGIQUE DES ÉTATS
        if ($playerCount === 2 && $game->status === 'waiting') {
            $game->update(['status' => 'countdown', 'timer_at' => $now->addSeconds(10)]);
        }

        if ($game->timer_at) {
            $timerValue = $now->diffInSeconds(Carbon::parse($game->timer_at), false);

            // Countdown (10s) -> Dealing (10s)
            if ($timerValue <= 0 && $game->status === 'countdown') {
                $game->update(['status' => 'dealing', 'timer_at' => Carbon::now()->addSeconds(10)]);
                $timerValue = 10;
            }

            // Dealing (20s) -> Playing (Distribution réelle des cartes ici)
            if ($timerValue <= 0 && $game->status === 'dealing') {
                $deck = $pokerService->createDeck();
                foreach ($game->players as $player) {
                    $player->update(['hand' => $pokerService->deal($deck, 2)]);
                }
                $game->update(['status' => 'playing', 'timer_at' => Carbon::now()->addSeconds(15), 'current_turn' => 0]);
                $timerValue = 15;
            }

            // Playing -> Changement de tour automatique
            if ($timerValue <= 0 && $game->status === 'playing') {
                $game->update(['current_turn' => ($game->current_turn + 1) % 2, 'timer_at' => Carbon::now()->addSeconds(15)]);
                $timerValue = 15;
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
            'gameStarted' => ($game->status === 'playing'),
            'status' => $game->status, // TRÈS IMPORTANT
            'timer' => max(0, (int)$timerValue),
            'currentTurn' => (int)$game->current_turn
        ]);
    }

    public function join(Request $request)
    {
        $request->validate(['name' => 'required|string|max:50']);
        $game = Game::firstOrCreate([], ['status' => 'waiting']);

        if (session()->has('player_token')) {
            $exists = Player::where('session_token', session('player_token'))->exists();
            if ($exists) return response()->json(['error' => 'Déjà à table'], 403);
        }

        if ($game->players()->count() >= 2) {
            return response()->json(['error' => 'Table pleine'], 403);
        }

        $token = Str::random(32); // Fonctionne maintenant grâce à l'import
        $player = $game->players()->create([
            'name' => $request->name,
            'chips' => 1000,
            'session_token' => $token
        ]);

        session(['player_token' => $token]);
        return response()->json(['message' => 'Ok']);
    }

    public function logout()
    {
        if (session()->has('player_token')) {
            $player = Player::where('session_token', session('player_token'))->first();
            if ($player) {
                $game = Game::find($player->game_id); // Recherche directe par ID
                $player->delete();

                if ($game) {
                    // On remet la partie en attente s'il reste 1 ou 0 joueur
                    $game->update(['status' => 'waiting', 'timer_at' => null]);
                    // On vide les mains des joueurs restants pour le prochain tour
                    Player::where('game_id', $game->id)->update(['hand' => null]);
                }
            }
            session()->forget('player_token');
        }
        return response()->json(['message' => 'Logged out']);
    }
}
