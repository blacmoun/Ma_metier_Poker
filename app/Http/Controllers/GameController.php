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

        // Récupère ou crée la partie unique
        $game = Game::firstOrCreate([], ['status' => 'waiting']);

        // 1. Vérification : L'utilisateur est-il déjà assis (via session) ?
        if (session()->has('player_token')) {
            $alreadySeated = Player::where('session_token', session('player_token'))
                ->where('game_id', $game->id)
                ->exists();
            if ($alreadySeated) {
                return response()->json(['error' => 'You are already at the table!'], 403);
            }
        }

        // 2. Vérification : La table est-elle pleine ?
        if ($game->players()->count() >= 2) {
            return response()->json(['error' => 'Game is full'], 403);
        }

        // 3. Vérification : Le nom est-il déjà pris ?
        if ($game->players()->where('name', $request->name)->exists()) {
            return response()->json(['error' => 'Name already taken'], 403);
        }

        // Création du joueur
        $token = Str::random(32);
        $player = $game->players()->create([
            'name' => $request->name,
            'chips' => 1000,
            'session_token' => $token
        ]);

        // Sauvegarde du token en session PHP
        session(['player_token' => $token]);

        return response()->json([
            'message' => 'Joined successfully',
            'player' => $player
        ]);
    }

    public function show()
    {
        // On récupère le jeu, les joueurs, et on ajoute un indicateur "me"
        $game = Game::with('players')->first();
        $myToken = session('player_token');

        // On transforme la réponse pour que le JS sache si l'utilisateur actuel est à table
        $players = $game ? $game->players->map(function($p) use ($myToken) {
            return [
                'name' => $p->name,
                'chips' => $p->chips,
                'is_me' => ($p->session_token === $myToken)
            ];
        }) : [];

        return response()->json([
            'players' => $players,
            'status' => $game->status ?? 'waiting'
        ]);
    }

    public function logout()
    {
        if (session()->has('player_token')) {
            Player::where('session_token', session('player_token'))->delete();
            session()->forget('player_token');
        }
        return response()->json(['message' => 'Logged out']);
    }
}
