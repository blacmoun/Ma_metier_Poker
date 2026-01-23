<?php

namespace App\Services;

class PokerService
{
    private $ranks = ['2'=>2,'3'=>3,'4'=>4,'5'=>5,'6'=>6,'7'=>7,'8'=>8,'9'=>9,'10'=>10,'V'=>11,'D'=>12,'R'=>13,'A'=>14];
    private $suits = ['car'=>1, 'coe'=>2, 'pic'=>3, 'tre'=>4];

    public function createDeck(): array {
        $deck = [];
        foreach (array_keys($this->ranks) as $r) {
            foreach (['car', 'coe', 'pic', 'tre'] as $s) { $deck[] = "{$r}_{$s}.png"; }
        }
        shuffle($deck);
        return $deck;
    }

    public function deal(&$deck, $count = 2): array {
        return array_splice($deck, 0, $count);
    }

    /**
     * Évalue la meilleure main de 5 cartes parmi 7
     * Retourne un score numérique (plus haut = meilleur)
     */
    public function evaluateHand($hand, $community) {
        $allCards = array_merge($hand, $community);
        $cards = [];
        foreach($allCards as $c) {
            preg_match('/(.*)_(.*)\.png/', $c, $m);
            $cards[] = ['r' => $this->ranks[$m[1]], 's' => $m[2]];
        }

        // Tri par rang décroissant
        usort($cards, fn($a, $b) => $b['r'] <=> $a['r']);

        // Logique simplifiée de scoring (Exemple: Brelan > Paire > Carte Haute)
        // Pour un système complet, il faudrait implémenter Quinte, Flush, Full, etc.
        $counts = array_count_values(array_column($cards, 'r'));
        arsort($counts);

        $score = 0;
        $firstCount = reset($counts);
        $firstRank = key($counts);

        if ($firstCount == 4) $score = 800 + $firstRank; // Carré
        elseif ($firstCount == 3) $score = 400 + $firstRank; // Brelan
        elseif ($firstCount == 2) {
            $pairRank = $firstRank;
            next($counts);
            if (current($counts) == 2) $score = 300 + $pairRank; // Double Paire
            else $score = 200 + $pairRank; // Paire
        } else {
            $score = $cards[0]['r']; // Carte Haute
        }

        return $score;
    }
}
