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
    private $turnDuration = 20;

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
        $players = $game->players;

        if ($players->count() < 2 && $game->status !== 'waiting') {
            $this->resetToWaiting($game);
            return $this->gameResponse($game);
        }

        if ($players->count() === 2 && $game->status === 'waiting') {
            $game->update([
                'status' => 'countdown',
                'timer_at' => $now->copy()->addSeconds(10),
                'dealer_index' => rand(0, 1),
                'pot' => 0
            ]);
        }

        if ($game->timer_at) {
            $timerValue = $now->diffInSeconds(Carbon::parse($game->timer_at), false);
            if ($timerValue <= 0) {
                if (in_array($game->status, ['pre-flop', 'flop', 'turn', 'river'])) {
                    // Timeout : On force l'action pour passer au tour suivant
                    $this->processTurnAction($game, $pokerService);
                } else {
                    $this->advanceGameState($game, $pokerService);
                }
            }
        }

        return $this->gameResponse($game);
    }

    public function play(Request $request, PokerService $pokerService) {
        $game = Game::with('players')->first();
        $player = Player::where('session_token', session('player_token'))->first();
        $playersArray = $game->players->values();

        if (!$player || $playersArray[$game->current_turn]->id !== $player->id) {
            return response()->json(['error' => 'Not your turn'], 403);
        }

        $this->processTurnAction($game, $pokerService);
        return $this->gameResponse($game);
    }

    private function processTurnAction($game, $pokerService) {
        // Dans un duel (2 joueurs) :
        // Si c'est le tour du joueur 0 (le premier à parler), on passe au joueur 1.
        // Si c'est le tour du joueur 1, l'étape (round) est finie -> on avance le jeu.
        if ($game->current_turn == 1) {
            $this->advanceGameState($game, $pokerService);
        } else {
            $game->update([
                'current_turn' => 1,
                'timer_at' => Carbon::now()->addSeconds($this->turnDuration)
            ]);
        }
    }

    private function advanceGameState($game, $pokerService) {
        $now = Carbon::now();
        $deck = $game->deck ?? [];

        switch ($game->status) {
            case 'countdown':
                // --- SYSTÈME DE BLINDS ---
                $players = $game->players->values();
                if ($players->count() === 2) {
                    $sb = 20; // Small Blind
                    $bb = 40; // Big Blind

                    // Le Dealer (dealer_index) met la SB, l'autre met la BB
                    $sbPlayer = $players[$game->dealer_index];
                    $bbPlayer = $players[($game->dealer_index + 1) % 2];

                    $sbPlayer->decrement('chips', $sb);
                    $sbPlayer->update(['current_bet' => $sb]);

                    $bbPlayer->decrement('chips', $bb);
                    $bbPlayer->update(['current_bet' => $bb]);

                    $game->update(['pot' => $sb + $bb]);
                }

                // Passage direct au PRE-FLOP (on saute 'dealing')
                $newDeck = $pokerService->createDeck();
                foreach ($game->players as $p) {
                    $p->update(['hand' => $pokerService->deal($newDeck, 2)]);
                }

                $game->update([
                    'status' => 'pre-flop',
                    'deck' => $newDeck,
                    'community_cards' => [],
                    'timer_at' => $now->addSeconds($this->turnDuration),
                    'current_turn' => ($game->dealer_index === 0) ? 1 : 0 // C'est au tour du non-dealer de parler en premier
                ]);
                break;

            case 'pre-flop':
                $this->collectBets($game);
                $game->update(['status' => 'flop', 'community_cards' => $pokerService->deal($deck, 3), 'deck' => $deck, 'timer_at' => $now->addSeconds($this->turnDuration), 'current_turn' => 0]);
                break;
            case 'flop':
                $this->collectBets($game);
                $game->update(['status' => 'turn', 'community_cards' => array_merge($game->community_cards, $pokerService->deal($deck, 1)), 'deck' => $deck, 'timer_at' => $now->addSeconds($this->turnDuration), 'current_turn' => 0]);
                break;
            case 'turn':
                $this->collectBets($game);
                $game->update(['status' => 'river', 'community_cards' => array_merge($game->community_cards, $pokerService->deal($deck, 1)), 'deck' => $deck, 'timer_at' => $now->addSeconds($this->turnDuration), 'current_turn' => 0]);
                break;
            case 'river':
                $this->collectBets($game);
                $game->update(['status' => 'finished', 'timer_at' => null]);
                break;
        }
    }

    private function gameResponse($game) {
        return response()->json([
            'players' => $game->players->map(fn($p, $i) => [
                'name' => $p->name,
                'chips' => $p->chips,
                'current_bet' => $p->current_bet,
                'is_me' => ($p->session_token === session('player_token')),
                'hand' => ($p->session_token === session('player_token')) ? $p->hand : null,
                'has_cards' => !empty($p->hand)
            ]),
            'community_cards' => $game->community_cards ?? [],
            'status' => $game->status,
            'timer' => $game->timer_at ? max(0, Carbon::now()->diffInSeconds(Carbon::parse($game->timer_at), false)) : 0,
            'currentTurn' => (int)$game->current_turn,
            'dealerIndex' => (int)$game->dealer_index, // AJOUTER CETTE LIGNE
            'pot' => $game->pot
        ]);
    }

    private function resetToWaiting($game) {
        $game->update(['status' => 'waiting', 'timer_at' => null, 'community_cards' => [], 'deck' => null, 'current_turn' => 0, 'pot' => 0]);
        Player::where('game_id', $game->id)->update(['hand' => null, 'current_bet' => 0]);
    }

    public function restart() {
        $game = Game::first();
        if ($game) $this->resetToWaiting($game);
        return response()->json(['status' => 'success']);
    }

    public function join(Request $request) {
        $game = Game::firstOrCreate([], ['status' => 'waiting', 'pot' => 0]);
        if ($game->players()->count() >= 2) return response()->json(['error' => 'Full'], 403);
        $token = Str::random(32);
        $game->players()->create(['name' => $request->name, 'chips' => 1000, 'session_token' => $token]);
        session(['player_token' => $token]);
        return response()->json(['message' => 'Ok']);
    }

    public function logout() {
        if (session()->has('player_token')) {
            $player = Player::where('session_token', session('player_token'))->first();
            if ($player) { $this->resetToWaiting(Game::find($player->game_id)); $player->delete(); }
            session()->forget('player_token');
        }
        return response()->json(['message' => 'Ok']);
    }
}
