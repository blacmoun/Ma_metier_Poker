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
    private $showdownDuration = 8;
    private $allInSpeed = 2;

    private function getHandRankName($score) {
        $ranks = [
            8000000 => "Quinte Flush", 7000000 => "Carré", 6000000 => "Full",
            5000000 => "Couleur", 4000000 => "Quinte", 3000000 => "Brelan",
            2000000 => "Double Paire", 1000000 => "Paire"
        ];
        foreach ($ranks as $limit => $name) {
            if ($score >= $limit) return $name;
        }
        return "Hauteur";
    }

    private function collectBets($game) {
        $totalBets = $game->players->sum('current_bet');
        if ($totalBets > 0) {
            $game->increment('pot', $totalBets);
            $game->players()->update(['current_bet' => 0]);
        }
    }

    public function show(PokerService $pokerService) {
        $game = Game::with('players')->first() ?? Game::create([
            'status' => 'waiting', 'community_cards' => [], 'dealer_index' => rand(0, 1), 'pot' => 0
        ]);

        $players = $game->players->values();

        if ($players->count() < 2 && $game->status !== 'waiting') {
            $this->resetToWaiting($game);
        }

        if ($players->count() === 2 && $game->status === 'waiting') {
            $game->update(['status' => 'countdown', 'timer_at' => now()->addSeconds(10)]);
        }

        // Vérification du timer pour l'auto-advance
        if ($game->timer_at && now()->greaterThanOrEqualTo(Carbon::parse($game->timer_at))) {
            $this->processTurnAction($game, $pokerService);
        }

        return $this->gameResponse($game->fresh(), $pokerService);
    }

    public function play(Request $request, PokerService $pokerService) {
        $game = Game::with('players')->first();
        $players = $game->players->values();
        $me = $players->firstWhere('session_token', session('player_token'));

        // Vérification : est-ce mon tour ?
        if (!$me || $game->status === 'showdown' || !isset($players[$game->current_turn]) || $players[$game->current_turn]->id !== $me->id) {
            return $this->gameResponse($game, $pokerService);
        }

        if ($request->action === 'fold') {
            $winner = ($game->current_turn == 0) ? $players[1] : $players[0];
            $winner->increment('chips', $game->pot + $players->sum('current_bet'));
            $game->players()->update(['current_bet' => 0, 'hand' => null]);
            $game->update(['status' => 'showdown', 'pot' => 0, 'timer_at' => now()->addSeconds($this->showdownDuration)]);
            return $this->gameResponse($game->fresh(), $pokerService);
        }

        // Gestion des mises
        if ($request->action === 'call') {
            $opp = $players[($game->current_turn == 0) ? 1 : 0];
            $amt = min($me->chips, max(0, $opp->current_bet - $me->current_bet));
            $me->update(['current_bet' => $me->current_bet + $amt, 'chips' => $me->chips - $amt]);
        } elseif ($request->action === 'raise') {
            $amt = min($me->chips, (int)$request->amount);
            $me->update(['current_bet' => $me->current_bet + $amt, 'chips' => $me->chips - $amt]);
        } elseif ($request->action === 'allin') {
            $me->update(['current_bet' => $me->current_bet + $me->chips, 'chips' => 0]);
        }

        $this->processTurnAction($game->fresh(), $pokerService);
        return $this->gameResponse($game->fresh(), $pokerService);
    }

    public function processTurnAction($game, $pokerService) {
        $players = $game->players->values();
        if ($players->count() < 2) return;

        $p1 = $players[0]; $p2 = $players[1];
        $betsEqual = ($p1->current_bet === $p2->current_bet);
        $someoneAllIn = ($p1->chips === 0 || $p2->chips === 0);

        $isPhaseOver = false;

        if ($betsEqual) {
            if ($someoneAllIn) {
                $isPhaseOver = true; // Si tapis et égalité, on avance direct
            } else {
                if ($game->status === 'pre-flop') {
                    $bbIndex = ($game->dealer_index == 0) ? 1 : 0;
                    if ($game->current_turn == $bbIndex) $isPhaseOver = true;
                } else {
                    if ($game->current_turn == 1) $isPhaseOver = true;
                }
            }
        }

        if ($isPhaseOver || in_array($game->status, ['showdown', 'countdown'])) {
            $this->advanceGameState($game, $pokerService);
        } else {
            // On passe au joueur suivant
            $game->update([
                'current_turn' => ($game->current_turn == 0) ? 1 : 0,
                'timer_at' => now()->addSeconds($this->turnDuration)
            ]);
        }
    }

    private function advanceGameState($game, $pokerService) {
        $players = $game->players->values();
        $p1 = $players[0]; $p2 = $players[1];
        $deck = $game->deck ?? [];

        // Si tapis, les cartes s'enchaînent vite
        $isAllInAuto = ($p1->chips == 0 || $p2->chips == 0);
        $nextTimer = $isAllInAuto ? now()->addSeconds($this->allInSpeed) : now()->addSeconds($this->turnDuration);

        switch ($game->status) {
            case 'countdown':
                $newDeck = $pokerService->createDeck();
                $p1_bet = ($game->dealer_index == 0) ? 20 : 40;
                $p2_bet = ($game->dealer_index == 1) ? 20 : 40;
                $p1->update(['current_bet' => $p1_bet, 'chips' => max(0, $p1->chips - $p1_bet), 'hand' => $pokerService->deal($newDeck, 2)]);
                $p2->update(['current_bet' => $p2_bet, 'chips' => max(0, $p2->chips - $p2_bet), 'hand' => $pokerService->deal($newDeck, 2)]);
                $game->update(['status' => 'pre-flop', 'deck' => $newDeck, 'community_cards' => [], 'timer_at' => $nextTimer, 'current_turn' => $game->dealer_index, 'pot' => 0]);
                break;

            case 'pre-flop':
                $this->collectBets($game);
                $game->update(['status' => 'flop', 'community_cards' => $pokerService->deal($deck, 3), 'deck' => $deck, 'timer_at' => $nextTimer, 'current_turn' => 0]);
                break;

            case 'flop':
            case 'turn':
                $this->collectBets($game);
                $nextStatus = ($game->status === 'flop') ? 'turn' : 'river';
                $game->update(['status' => $nextStatus, 'community_cards' => array_merge($game->community_cards, $pokerService->deal($deck, 1)), 'deck' => $deck, 'timer_at' => $nextTimer, 'current_turn' => 0]);
                break;

            case 'river':
                $this->collectBets($game);
                $s1 = $pokerService->evaluateHand($p1->hand, $game->community_cards);
                $s2 = $pokerService->evaluateHand($p2->hand, $game->community_cards);
                if ($s1 > $s2) $p1->increment('chips', $game->pot);
                elseif ($s2 > $s1) $p2->increment('chips', $game->pot);
                else { $h = floor($game->pot / 2); $p1->increment('chips', $h); $p2->increment('chips', $h); }
                $game->update(['status' => 'showdown', 'timer_at' => now()->addSeconds($this->showdownDuration)]);
                break;

            case 'showdown':
                $bkr = Player::where('game_id', $game->id)->where('chips', '<=', 0)->first();
                if ($bkr) {
                    $bkr->delete();
                    $this->resetToWaiting($game);
                } else {
                    $game->update(['status' => 'countdown', 'timer_at' => now()->addSeconds(5), 'community_cards' => [], 'dealer_index' => ($game->dealer_index == 0 ? 1 : 0), 'pot' => 0]);
                    $game->players()->update(['hand' => null, 'current_bet' => 0]);
                }
                break;
        }
    }

    public function gameResponse($game, $pokerService = null) {
        $p = $game->players->values();
        $meToken = session('player_token');

        return response()->json([
            'players' => $p->map(fn($player) => [
                'name' => $player->name,
                'chips' => $player->chips,
                'current_bet' => $player->current_bet,
                'is_me' => ($player->session_token === $meToken),
                'hand' => ($player->session_token === $meToken || $game->status === 'showdown') ? $player->hand : null,
                'hand_name' => ($game->status === 'showdown' && $pokerService && !empty($player->hand)) ? $this->getHandRankName($pokerService->evaluateHand($player->hand, $game->community_cards)) : null
            ]),
            'community_cards' => $game->community_cards,
            'status' => $game->status,
            'timer' => $game->timer_at ? max(0, now()->diffInSeconds(Carbon::parse($game->timer_at), false)) : 0,
            'currentTurn' => (int)$game->current_turn,
            'pot' => $game->pot
        ]);
    }

    public function resetToWaiting($game) {
        $game->update(['status' => 'waiting', 'timer_at' => null, 'community_cards' => [], 'pot' => 0]);
        $game->players()->update(['hand' => null, 'current_bet' => 0]);
    }

    public function join(Request $request) {
        $game = Game::firstOrCreate([], ['status' => 'waiting', 'pot' => 0]);
        if ($game->players()->count() >= 2) return response()->json(['error' => 'Full'], 403);
        $token = Str::random(32);
        $game->players()->create(['name' => $request->name, 'chips' => 1000, 'session_token' => $token]);
        session(['player_token' => $token]);
        return response()->json(['message' => 'Success']);
    }
}
