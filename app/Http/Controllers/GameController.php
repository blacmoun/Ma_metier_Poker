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

    /**
     * Translates technical scores into readable hand names.
     */
    private function getHandRankName($score) {
        if ($score >= 8000000) return "Straight Flush";
        if ($score >= 7000000) return "Four of a Kind";
        if ($score >= 6000000) return "Full House";
        if ($score >= 5000000) return "Flush";
        if ($score >= 4000000) return "Straight";
        if ($score >= 3000000) return "Three of a Kind";
        if ($score >= 2000000) return "Two Pairs";
        if ($score >= 1000000) return "Pair";
        return "High Card";
    }

    /**
     * Collects all current player bets into the central pot.
     */
    private function collectBets($game) {
        $totalBets = $game->players->sum('current_bet');
        if ($totalBets > 0) {
            $game->increment('pot', $totalBets);
            foreach ($game->players as $p) {
                $p->update(['current_bet' => 0]);
            }
        }
    }

    public function show(PokerService $pokerService)
    {
        $game = Game::with('players')->first();
        if (!$game) {
            $game = Game::create(['status' => 'waiting', 'community_cards' => [], 'dealer_index' => rand(0, 1), 'pot' => 0]);
        }

        $now = Carbon::now();
        $players = $game->players;

        if ($players->count() < 2 && $game->status !== 'waiting') {
            $this->resetToWaiting($game);
            return $this->gameResponse($game, $pokerService);
        }

        if ($players->count() === 2 && $game->status === 'waiting') {
            $game->update([
                'status' => 'countdown',
                'timer_at' => $now->copy()->addSeconds(10),
                'pot' => 0
            ]);
        }

        if ($game->timer_at) {
            $timerValue = $now->diffInSeconds(Carbon::parse($game->timer_at), false);
            if ($timerValue <= 0) {
                $this->processTurnAction($game, $pokerService);
            }
        }

        return $this->gameResponse($game, $pokerService);
    }

    public function play(Request $request, PokerService $pokerService) {
        $game = Game::with('players')->first();
        $players = $game->players()->get()->values();

        if ($request->action === 'fold' && in_array($game->status, ['pre-flop', 'flop', 'turn', 'river'])) {
            $winner = ($game->current_turn == 0) ? $players[1] : $players[0];
            $totalWin = $game->pot + $players->sum('current_bet');
            $winner->increment('chips', $totalWin);

            foreach ($players as $p) {
                $p->update(['current_bet' => 0, 'hand' => null]);
            }

            $game->update([
                'status' => 'showdown',
                'pot' => 0,
                'timer_at' => Carbon::now()->addSeconds($this->showdownDuration)
            ]);

            return $this->gameResponse($game->fresh(['players']), $pokerService);
        }

        $this->processTurnAction($game, $pokerService);
        return $this->gameResponse($game->fresh(['players']), $pokerService);
    }

    public function processTurnAction($game, $pokerService) {
        $game->load('players');
        $players = $game->players->values();
        if ($players->count() < 2) return;

        $p1 = $players[0]; $p2 = $players[1];
        $currentPlayer = $players[$game->current_turn];
        $opponent = $players[($game->current_turn == 0) ? 1 : 0];

        $now = Carbon::now();
        $timerValue = $now->diffInSeconds(Carbon::parse($game->timer_at), false);

        // Timeout logic
        if ($timerValue <= 0 && in_array($game->status, ['pre-flop', 'flop', 'turn', 'river'])) {
            if ($opponent->current_bet > $currentPlayer->current_bet) {
                $opponent->increment('chips', $game->pot + $p1->current_bet + $p2->current_bet);
                foreach($players as $p) $p->update(['hand' => null, 'current_bet' => 0]);
                $game->update(['status' => 'showdown', 'pot' => 0, 'timer_at' => $now->addSeconds($this->showdownDuration)]);
                return;
            }
        }

        $betsEqual = ($p1->current_bet === $p2->current_bet);
        $someoneAllIn = ($p1->chips === 0 || $p2->chips === 0);

        $isPhaseOver = false;
        // Logic for All-In: If someone is All-in and bets are balanced, move to next phase immediately
        if ($someoneAllIn && $betsEqual) {
            $isPhaseOver = true;
        } else {
            if ($game->status === 'pre-flop') {
                $bbIndex = ($game->dealer_index == 0) ? 1 : 0;
                if ($game->current_turn == $bbIndex && $betsEqual) $isPhaseOver = true;
            } else {
                if ($game->current_turn == 1 && $betsEqual) $isPhaseOver = true;
            }
        }

        if ($isPhaseOver || in_array($game->status, ['showdown', 'countdown'])) {
            $this->advanceGameState($game, $pokerService);
        } else {
            $game->update([
                'current_turn' => ($game->current_turn == 0) ? 1 : 0,
                'timer_at' => Carbon::now()->addSeconds($this->turnDuration)
            ]);
        }
    }

    private function advanceGameState($game, $pokerService) {
        $now = Carbon::now();
        $players = $game->players->values();
        $deck = $game->deck ?? [];
        $someoneAllIn = ($players[0]->chips === 0 || $players[1]->chips === 0);
        $duration = $someoneAllIn ? $this->allInSpeed : $this->turnDuration;

        switch ($game->status) {
            case 'countdown':
                $newDeck = $pokerService->createDeck();
                $players[$game->dealer_index]->update(['current_bet' => 20, 'chips' => max(0, $players[$game->dealer_index]->chips - 20)]);
                $players[($game->dealer_index + 1) % 2]->update(['current_bet' => 40, 'chips' => max(0, $players[($game->dealer_index + 1) % 2]->chips - 40)]);
                foreach ($players as $p) $p->update(['hand' => $pokerService->deal($newDeck, 2)]);

                $game->update(['status' => 'pre-flop', 'deck' => $newDeck, 'community_cards' => [], 'timer_at' => $now->addSeconds($this->turnDuration), 'current_turn' => $game->dealer_index, 'pot' => 0]);
                break;

            case 'pre-flop':
                $this->collectBets($game);
                $game->update(['status' => 'flop', 'community_cards' => $pokerService->deal($deck, 3), 'deck' => $deck, 'timer_at' => $now->addSeconds($duration), 'current_turn' => 0]);
                break;

            case 'flop':
                $this->collectBets($game);
                $game->update(['status' => 'turn', 'community_cards' => array_merge($game->community_cards, $pokerService->deal($deck, 1)), 'deck' => $deck, 'timer_at' => $now->addSeconds($duration), 'current_turn' => 0]);
                break;

            case 'turn':
                $this->collectBets($game);
                $game->update(['status' => 'river', 'community_cards' => array_merge($game->community_cards, $pokerService->deal($deck, 1)), 'deck' => $deck, 'timer_at' => $now->addSeconds($duration), 'current_turn' => 0]);
                break;

            case 'river':
                $this->collectBets($game);
                $p1Score = $pokerService->evaluateHand($players[0]->hand, $game->community_cards);
                $p2Score = $pokerService->evaluateHand($players[1]->hand, $game->community_cards);
                if ($p1Score > $p2Score) $players[0]->increment('chips', $game->pot);
                elseif ($p2Score > $p1Score) $players[1]->increment('chips', $game->pot);
                else { $half = floor($game->pot / 2); $players[0]->increment('chips', $half); $players[1]->increment('chips', $half); }
                $game->update(['status' => 'showdown', 'pot' => 0, 'timer_at' => $now->addSeconds($this->showdownDuration)]);
                break;

            case 'showdown':
                if ($game->players()->where('chips', '<=', 0)->exists()) {
                    foreach ($game->players as $p) $p->delete();
                    $this->resetToWaiting($game);
                } else {
                    $game->update(['status' => 'countdown', 'timer_at' => $now->addSeconds(5), 'community_cards' => [], 'dealer_index' => ($game->dealer_index == 0 ? 1 : 0), 'pot' => 0]);
                    foreach ($game->players as $p) $p->update(['hand' => null, 'current_bet' => 0]);
                }
                break;
        }
    }

    public function gameResponse($game, $pokerService = null) {
        return response()->json([
            'players' => $game->players->map(function($p) use ($game, $pokerService) {
                $handName = null;
                if ($game->status === 'showdown' && $pokerService && !empty($p->hand)) {
                    $handName = $this->getHandRankName($pokerService->evaluateHand($p->hand, $game->community_cards));
                }
                return [
                    'name' => $p->name, 'chips' => $p->chips, 'current_bet' => $p->current_bet,
                    'is_me' => ($p->session_token === session('player_token')),
                    'hand' => ($p->session_token === session('player_token') || $game->status === 'showdown') ? $p->hand : null,
                    'has_cards' => !empty($p->hand), 'hand_name' => $handName
                ];
            }),
            'community_cards' => $game->community_cards ?? [],
            'status' => $game->status,
            'timer' => $game->timer_at ? max(0, Carbon::now()->diffInSeconds(Carbon::parse($game->timer_at), false)) : 0,
            'currentTurn' => (int)$game->current_turn, 'dealerIndex' => (int)$game->dealer_index, 'pot' => $game->pot
        ]);
    }

    public function resetToWaiting($game) {
        $game->update(['status' => 'waiting', 'timer_at' => null, 'community_cards' => [], 'deck' => null, 'current_turn' => 0, 'pot' => 0, 'dealer_index' => rand(0, 1)]);
        Player::where('game_id', $game->id)->update(['hand' => null, 'current_bet' => 0]);
    }

    public function join(Request $request) {
        $game = Game::firstOrCreate([], ['status' => 'waiting', 'pot' => 0, 'dealer_index' => rand(0, 1)]);
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
                $game = Game::find($player->game_id);
                if ($game) $this->resetToWaiting($game);
                $player->delete();
            }
            session()->forget('player_token');
        }
        return response()->json(['message' => 'Ok']);
    }
}
