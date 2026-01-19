<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GameController extends Controller
{
    public function show()
    {
        $game = Game::with('players')->first();
        if (!$game) {
            return response()->json(['players' => [], 'gameStarted' => false]);
        }

        $myToken = session('player_token');
        $players = $game->players->map(function($p) use ($myToken) {
            return [
                'name' => $p->name,
                'chips' => $p->chips,
                'is_me' => ($p->session_token === $myToken)
            ];
        });

        // --- LOGIQUE DE SYNCHRONISATION DU TIMER ---
        $now = Carbon::now();
        $timerValue = 0;

        // Si on attend le 2ème joueur
        if ($game->players->count() < 2) {
            $game->update(['status' => 'waiting', 'timer_at' => null]);
        }
        // Si les 2 sont là et qu'on n'a pas encore lancé le décompte
        elseif ($game->status === 'waiting' && $game->players->count() === 2) {
            $game->update([
                'status' => 'countdown',
                'timer_at' => $now->addSeconds(5) // Décompte de 5s
            ]);
        }

        // Calcul du temps restant à envoyer au JS
        if ($game->timer_at) {
            $timerValue = $now->diffInSeconds(Carbon::parse($game->timer_at), false);

            // Si le temps est écoulé
            if ($timerValue <= 0) {
                if ($game->status === 'countdown') {
                    // On passe en jeu réel
                    $game->update([
                        'status' => 'playing',
                        'timer_at' => Carbon::now()->addSeconds(10), // 10s pour le 1er tour
                        'current_turn' => 0
                    ]);
                    $timerValue = 10;
                } elseif ($game->status === 'playing') {
                    // On passe au joueur suivant
                    $nextTurn = ($game->current_turn + 1) % 2;
                    $game->update([
                        'timer_at' => Carbon::now()->addSeconds(10),
                        'current_turn' => $nextTurn
                    ]);
                    $timerValue = 10;
                }
            }
        }

        return response()->json([
            'players' => $players,
            'gameStarted' => ($game->status === 'playing'),
            'timer' => max(0, (int)$timerValue),
            'currentTurn' => $game->current_turn ?? 0,
            'status' => $game->status
        ]);
    }

    public function join(Request $request)
    {
        $request->validate(['name' => 'required|string|max:50']);
        $game = Game::firstOrCreate([], ['status' => 'waiting']);

        if (session()->has('player_token')) {
            $alreadySeated = Player::where('session_token', session('player_token'))
                ->where('game_id', $game->id)->exists();
            if ($alreadySeated) return response()->json(['error' => 'Déjà à table'], 403);
        }

        if ($game->players()->count() >= 2) {
            return response()->json(['error' => 'Table pleine'], 403);
        }

        $token = Str::random(32);
        $player = $game->players()->create([
            'name' => $request->name,
            'chips' => 1000,
            'session_token' => $token
        ]);

        session(['player_token' => $token]);
        return response()->json(['message' => 'Ok', 'player' => $player]);
    }

    public function logout()
    {
        if (session()->has('player_token')) {
            $player = Player::where('session_token', session('player_token'))->first();
            if ($player) {
                $game = Game::find($player->game_id);
                $player->delete();
                // Si un joueur part, on reset le jeu en attente
                if ($game) $game->update(['status' => 'waiting', 'timer_at' => null]);
            }
            session()->forget('player_token');
        }
        return response()->json(['message' => 'Logged out']);
    }
}
