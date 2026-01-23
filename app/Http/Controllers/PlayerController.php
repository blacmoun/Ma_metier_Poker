<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\Game;
use App\Services\PokerService;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function action(Request $request, PokerService $pokerService)
    {
        // On récupère le jeu et le joueur qui effectue l'action
        $game = Game::with('players')->first();
        $player = Player::where('session_token', session('player_token'))->first();

        if (!$player) {
            return response()->json(['error' => 'Player not found'], 404);
        }

        // Sécurité : Vérifier si c'est bien le tour de ce joueur
        $playersArray = $game->players->values();
        if (!isset($playersArray[$game->current_turn]) || $playersArray[$game->current_turn]->id !== $player->id) {
            return response()->json(['error' => 'Not your turn'], 403);
        }

        $type = $request->input('action');
        $opponent = $game->players->where('id', '!=', $player->id)->first();

        switch ($type) {
            case 'fold':
                // On laisse le GameController gérer le Fold pour centraliser la distribution du pot
                // La méthode play() du GameController contient déjà la logique :
                // Don au gagnant + passage en showdown.
                break;

            case 'call':
                // Calcul de la différence pour égaliser la mise de l'adversaire
                $diff = ($opponent->current_bet ?? 0) - $player->current_bet;
                if ($diff > 0) {
                    $amountToPay = min($player->chips, $diff);
                    $player->decrement('chips', $amountToPay);
                    $player->increment('current_bet', $amountToPay);
                }
                // Si l'adversaire n'avait pas misé (Parole), on ne fait rien, on valide juste le tour.
                break;

            case 'allin':
                // Mise de la totalité des jetons restants
                $remainingChips = $player->chips;
                if ($remainingChips > 0) {
                    $player->increment('current_bet', $remainingChips);
                    $player->update(['chips' => 0]);
                }
                break;

            case 'raise':
                // Le montant envoyé est le TOTAL que le joueur veut avoir sur la table
                $totalSaisi = (int) $request->input('amount', 0);

                // Sécurité : On ne peut pas relancer moins que la mise de l'adversaire
                $minRequired = ($opponent->current_bet ?? 0);
                if ($totalSaisi < $minRequired) {
                    $totalSaisi = $minRequired;
                }

                $toAdd = $totalSaisi - $player->current_bet;

                // Si le joueur a assez de jetons
                if ($toAdd > 0) {
                    $actualAdd = min($player->chips, $toAdd);
                    $player->decrement('chips', $actualAdd);
                    $player->increment('current_bet', $actualAdd);
                }
                break;
        }

        // Une fois l'action enregistrée en base de données, on appelle la logique
        // du GameController pour changer de tour ou changer de phase (Flop, Turn, etc.)
        return app(GameController::class)->play($request, $pokerService);
    }
}
