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

    private function collectBets($game) {
        $players = $game->players;
        if ($players->count() < 2) return;

        $p1 = $players[0];
        $p2 = $players[1];

        // Équilibrage des mises avant de collecter dans le pot
        if ($p1->current_bet !== $p2->current_bet) {
            $minBet = min($p1->current_bet, $p2->current_bet);
            if ($p1->current_bet > $p2->current_bet) {
                $extra = $p1->current_bet - $minBet;
                $p1->increment('chips', $extra);
                $p1->update(['current_bet' => $minBet]);
            } else {
                $extra = $p2->current_bet - $minBet;
                $p2->increment('chips', $extra);
                $p2->update(['current_bet' => $minBet]);
            }
        }

        $game->refresh();
        $totalBets = $game->players->sum('current_bet');
        if ($totalBets > 0) {
            $game->increment('pot', $totalBets);
            foreach ($game->players as $p) {
                $p->update(['current_bet' => 0]);
            }
        }
    }

    public function show(PokerService $pokerService) {
        $game = Game::with('players')->first();
        if (!$game) {
            $game = Game::create(['status' => 'waiting', 'community_cards' => [], 'dealer_index' => rand(0, 1), 'pot' => 0]);
        }

        $players = $game->players()->orderBy('id', 'asc')->get();
        if ($players->count() < 2 && $game->status !== 'waiting') {
            $this->resetToWaiting($game);
        }

        if ($players->count() === 2 && $game->status === 'waiting') {
            $game->update([
                'status' => 'countdown',
                'timer_at' => Carbon::now()->addSeconds(10),
                'current_turn' => $game->dealer_index
            ]);
        }

        if ($game->timer_at && Carbon::now()->greaterThanOrEqualTo(Carbon::parse($game->timer_at))) {
            $this->processTurnAction($game, $pokerService);
        }

        return $this->gameResponse($game->fresh(), $pokerService);
    }

    public function play(Request $request, PokerService $pokerService) {
        $game = Game::with('players')->first();
        $players = $game->players()->orderBy('id', 'asc')->get();
        $me = $players->firstWhere('session_token', session('player_token'));

        // FIX BLOCAGE : Vérification stricte de l'index du tour
        $isMyTurn = false;
        if ($me && isset($players[$game->current_turn]) && $players[$game->current_turn]->id === $me->id) {
            $isMyTurn = true;
        }

        if (!$isMyTurn || in_array($game->status, ['showdown', 'countdown', 'waiting'])) {
            return $this->gameResponse($game, $pokerService);
        }

        if ($request->action === 'fold') {
            $this->handleFold($game, $players);
            return $this->gameResponse($game->fresh(), $pokerService);
        }

        $this->processTurnAction($game, $pokerService);
        return $this->gameResponse($game->fresh(), $pokerService);
    }

    private function handleFold($game, $players) {
        if ($players->count() < 2) return;

        $winnerIndex = ($game->current_turn == 0) ? 1 : 0;
        $winner = $players[$winnerIndex];

        // On récupère les mises actuelles avant reset
        $p1 = $players[0]; $p2 = $players[1];
        $commonBet = min($p1->current_bet, $p2->current_bet);

        // Le gagnant prend le pot + les mises équilibrées
        $winner->increment('chips', $game->pot + ($commonBet * 2));

        // Remboursement du surplus à celui qui a trop misé
        if ($p1->current_bet > $p2->current_bet) $p1->increment('chips', $p1->current_bet - $p2->current_bet);
        if ($p2->current_bet > $p1->current_bet) $p2->increment('chips', $p2->current_bet - $p1->current_bet);

        foreach ($players as $p) $p->update(['current_bet' => 0, 'hand' => null]);
        $game->update(['status' => 'showdown', 'pot' => 0, 'timer_at' => Carbon::now()->addSeconds($this->showdownDuration)]);
    }

    public function processTurnAction($game, $pokerService) {
        $players = $game->players()->orderBy('id', 'asc')->get();
        if ($players->count() < 2) return;

        $p1 = $players[0]; $p2 = $players[1];
        $betsEqual = ($p1->current_bet === $p2->current_bet);

        // All-in ?
        $someoneZero = ($p1->chips === 0 || $p2->chips === 0);
        $allInResolved = $someoneZero && (
                ($p1->chips === 0 && $p2->current_bet >= $p1->current_bet) ||
                ($p2->chips === 0 && $p1->current_bet >= $p2->current_bet)
            );

        $isPhaseOver = false;

        if ($allInResolved) {
            $isPhaseOver = true;
        } elseif ($betsEqual) {
            if ($game->status === 'pre-flop') {
                $bbIndex = ($game->dealer_index == 0) ? 1 : 0;
                if ($game->current_turn == $bbIndex) $isPhaseOver = true;
            } else {
                if ($game->current_turn == $game->dealer_index) $isPhaseOver = true;
            }
        }

        if ($isPhaseOver) {
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
        $players = $game->players()->orderBy('id', 'asc')->get();
        if ($players->count() < 2) return;

        $p1 = $players[0]; $p2 = $players[1];

        if ($p1->chips == 0 || $p2->chips == 0) {
            if ($p1->current_bet != $p2->current_bet) {
                if ($p1->current_bet > $p2->current_bet) {
                    $p1->increment('chips', $p1->current_bet - $p2->current_bet);
                    $p1->update(['current_bet' => $p2->current_bet]);
                } else {
                    $p2->increment('chips', $p2->current_bet - $p1->current_bet);
                    $p2->update(['current_bet' => $p1->current_bet]);
                }
            }
        }

        $someoneZero = ($p1->chips == 0 || $p2->chips == 0);
        $duration = $someoneZero ? $this->allInSpeed : $this->turnDuration;
        $deck = $game->deck ?? [];

        switch ($game->status) {
            case 'countdown':
                $newDeck = $pokerService->createDeck();
                $p1_bet = ($game->dealer_index == 0) ? 20 : 40;
                $p2_bet = ($game->dealer_index == 1) ? 20 : 40;
                $p1->update(['current_bet' => $p1_bet, 'chips' => max(0, $p1->chips - $p1_bet), 'hand' => $pokerService->deal($newDeck, 2)]);
                $p2->update(['current_bet' => $p2_bet, 'chips' => max(0, $p2->chips - $p2_bet), 'hand' => $pokerService->deal($newDeck, 2)]);

                $game->update([
                    'status' => 'pre-flop',
                    'deck' => $newDeck,
                    'community_cards' => [],
                    'timer_at' => $now->addSeconds($this->turnDuration),
                    'current_turn' => (int)$game->dealer_index,
                    'pot' => 0
                ]);
                break;

            case 'pre-flop':
            case 'flop':
            case 'turn':
                $this->collectBets($game);
                $nextStatus = ($game->status === 'pre-flop') ? 'flop' : (($game->status === 'flop') ? 'turn' : 'river');
                $cardsToDeal = ($game->status === 'pre-flop') ? 3 : 1;
                $game->update([
                    'status' => $nextStatus,
                    'community_cards' => array_merge($game->community_cards ?? [], $pokerService->deal($deck, $cardsToDeal)),
                    'deck' => $deck,
                    'timer_at' => $now->addSeconds($duration),
                    'current_turn' => ($game->dealer_index == 0) ? 1 : 0
                ]);
                break;

            case 'river':
                $this->collectBets($game);
                $p1S = $pokerService->evaluateHand($p1->hand, $game->community_cards);
                $p2S = $pokerService->evaluateHand($p2->hand, $game->community_cards);

                if ($p1S > $p2S) {
                    $p1->increment('chips', $game->pot);
                } elseif ($p2S > $p1S) {
                    $p2->increment('chips', $game->pot);
                } else {
                    $half = floor($game->pot / 2);
                    $p1->increment('chips', $half);
                    $p2->increment('chips', $game->pot - $half);
                }
                $game->update(['status' => 'showdown', 'pot' => 0, 'timer_at' => $now->addSeconds($this->showdownDuration)]);
                break;

            case 'showdown':
                $bkr = Player::where('game_id', $game->id)->where('chips', '<=', 0)->first();
                if ($bkr) {
                    $bkr->delete();
                    $this->resetToWaiting($game);
                } else {
                    $newDealer = ($game->dealer_index == 0 ? 1 : 0);
                    Player::where('game_id', $game->id)->update(['hand' => null, 'current_bet' => 0]);
                    $game->update([
                        'status' => 'countdown',
                        'community_cards' => [],
                        'dealer_index' => $newDealer,
                        'pot' => 0,
                        'current_turn' => $newDealer,
                        'timer_at' => $now->addSeconds(5)
                    ]);
                }
                break;
        }
    }

    public function gameResponse($game, $pokerService = null) {
        $p = $game->players()->orderBy('id', 'asc')->get();
        $isLocked = ($p->count() === 2 && ($p[0]->chips == 0 || $p[1]->chips == 0) && ($p[0]->current_bet === $p[1]->current_bet || ($p[0]->chips == 0 && $p[0]->current_bet <= $p[1]->current_bet) || ($p[1]->chips == 0 && $p[1]->current_bet <= $p[0]->current_bet)) && !in_array($game->status, ['showdown', 'waiting', 'countdown']));

        return response()->json([
            'players' => $p->map(function($player) use ($game, $pokerService, $p) {
                $opp = $p->firstWhere('session_token', '!=', $player->session_token);
                $neededToCall = $opp ? max(0, $opp->current_bet - $player->current_bet) : 0;
                return [
                    'name' => $player->name,
                    'chips' => $player->chips,
                    'current_bet' => $player->current_bet,
                    'is_me' => ($player->session_token === session('player_token')),
                    'hand' => ($player->session_token === session('player_token') || $game->status === 'showdown') ? $player->hand : null,
                    'has_cards' => !empty($player->hand),
                    'call_amount' => min($player->chips, $neededToCall),
                    'hand_name' => ($game->status === 'showdown' && $pokerService && !empty($player->hand)) ? $this->getHandRankName($pokerService->evaluateHand($player->hand, $game->community_cards)) : null
                ];
            }),
            'community_cards' => $game->community_cards,
            'status' => $game->status,
            'is_all_in' => $isLocked,
            'timer' => $game->timer_at ? max(0, Carbon::now()->diffInSeconds(Carbon::parse($game->timer_at), false)) : 0,
            'currentTurn' => (int)$game->current_turn,
            'dealerIndex' => (int)$game->dealer_index,
            'pot' => $game->pot
        ]);
    }

    public function resetToWaiting($game) {
        $game->update(['status' => 'waiting', 'timer_at' => null, 'community_cards' => [], 'deck' => null, 'current_turn' => 0, 'pot' => 0, 'dealer_index' => rand(0,1)]);
        Player::where('game_id', $game->id)->update(['hand' => null, 'current_bet' => 0]);
    }

    public function join(Request $request) {
        $game = Game::firstOrCreate([], ['status' => 'waiting', 'pot' => 0, 'dealer_index' => rand(0, 1)]);
        Player::where('session_token', session('player_token'))->delete();
        if ($game->players()->count() >= 2) return response()->json(['error' => 'Full'], 403);
        $token = Str::random(32);
        $game->players()->create(['name' => $request->name, 'chips' => 1000, 'session_token' => $token]);
        session(['player_token' => $token]);
        return response()->json(['message' => 'Success']);
    }

    public function logout() {
        $p = Player::where('session_token', session('player_token'))->first();
        if ($p) {
            $gameId = $p->game_id;
            $p->delete();
            $g = Game::find($gameId);
            if ($g) $this->resetToWaiting($g);
        }
        session()->forget('player_token');
        return response()->json(['message' => 'Success']);
    }
}
