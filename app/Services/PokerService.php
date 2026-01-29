<?php

namespace App\Services;

class PokerService
{
    private $ranks = ['2'=>2,'3'=>3,'4'=>4,'5'=>5,'6'=>6,'7'=>7,'8'=>8,'9'=>9,'10'=>10,'V'=>11,'D'=>12,'R'=>13,'A'=>14];
    private $suits = ['car'=>1, 'coe'=>2, 'pic'=>3, 'tre'=>4];

    public function createDeck(): array {
        $deck = [];
        foreach (array_keys($this->ranks) as $r) {
            foreach (['car', 'coe', 'pic', 'tre'] as $s) {
                $deck[] = "{$r}_{$s}.png";
            }
        }
        shuffle($deck);
        return $deck;
    }

    public function deal(&$deck, $count = 2): array {
        return array_splice($deck, 0, $count);
    }

    public function evaluateHand($hand, $community) {
        $allCards = array_merge($hand, $community);
        $cards = [];
        foreach($allCards as $c) {
            preg_match('/(.*)_(.*)\.png/', $c, $m);
            $cards[] = ['r' => (int)$this->ranks[$m[1]], 's' => $m[2]];
        }

        // Tri principal par rang décroissant
        usort($cards, fn($a, $b) => $b['r'] <=> $a['r']);

        $ranksOnly = array_column($cards, 'r');
        $suitsOnly = array_column($cards, 's');
        $counts = array_count_values($ranksOnly);

        // 1. Flush (Couleur)
        $isFlush = false;
        $flushSuit = '';
        foreach (array_count_values($suitsOnly) as $suit => $count) {
            if ($count >= 5) {
                $isFlush = true;
                $flushSuit = $suit;
                break;
            }
        }

        // 2. Straight (Quinte)
        $straightHigh = 0;
        $uniqueRanks = array_values(array_unique($ranksOnly));

        // Cas spécial Roue (A-2-3-4-5)
        if (count(array_intersect([14, 2, 3, 4, 5], $uniqueRanks)) === 5) {
            $straightHigh = 5;
        }
        // Autres quintes
        for ($i = 0; $i <= count($uniqueRanks) - 5; $i++) {
            if ($uniqueRanks[$i] - $uniqueRanks[$i + 4] === 4) {
                $straightHigh = max($straightHigh, $uniqueRanks[$i]);
                break;
            }
        }

        // 3. Straight Flush
        if ($isFlush && $straightHigh > 0) {
            $fCards = array_values(array_filter($cards, fn($c) => $c['s'] === $flushSuit));
            $fRanks = array_column($fCards, 'r');
            // Check quinte dans la couleur
            if (count(array_intersect([14, 2, 3, 4, 5], $fRanks)) === 5) {
                return 8000000 + 5;
            }
            for ($i = 0; $i <= count($fRanks) - 5; $i++) {
                if ($fRanks[$i] - $fRanks[$i + 4] === 4) return 8000000 + $fRanks[$i];
            }
        }

        // 4. Carré (Four of a Kind)
        foreach ($counts as $r => $c) {
            if ($c === 4) return 7000000 + ($r * 100) + $this->getKickers($ranksOnly, [$r], 1);
        }

        // 5. Full House
        $three = 0; $pair = 0;
        foreach ($counts as $r => $c) {
            if ($c === 3 && $three === 0) $three = $r;
            elseif ($c >= 2) $pair = max($pair, $r);
        }
        if ($three > 0 && $pair > 0) return 6000000 + ($three * 100) + $pair;

        // 6. Couleur (Flush)
        if ($isFlush) {
            $fRanks = array_values(array_filter($ranksOnly, fn($i, $k) => $suitsOnly[$k] === $flushSuit, ARRAY_FILTER_USE_BOTH));
            return 5000000 + $this->getKickers($fRanks, [], 5);
        }

        // 7. Quinte (Straight)
        if ($straightHigh > 0) return 4000000 + $straightHigh;

        // 8. Brelan (Three of a Kind)
        if ($three > 0) return 3000000 + ($three * 100) + $this->getKickers($ranksOnly, [$three], 2);

        // 9. Deux Paires
        $pairs = [];
        foreach ($counts as $r => $c) { if ($c === 2) $pairs[] = $r; }
        if (count($pairs) >= 2) {
            rsort($pairs);
            return 2000000 + ($pairs[0] * 100) + ($pairs[1] * 10) + $this->getKickers($ranksOnly, [$pairs[0], $pairs[1]], 1);
        }

        // 10. Paire
        if (count($pairs) === 1) return 1000000 + ($pairs[0] * 100) + $this->getKickers($ranksOnly, [$pairs[0]], 3);

        // 11. Carte Haute
        return $this->getKickers($ranksOnly, [], 5);
    }

    /**
     * Calcule un score pondéré pour les cartes restantes (départage)
     */
    private function getKickers($ranks, $exclude, $count) {
        $filtered = array_values(array_filter($ranks, fn($r) => !in_array($r, $exclude)));
        $score = 0;
        for ($i = 0; $i < $count && $i < count($filtered); $i++) {
            $score += $filtered[$i] * pow(15, ($count - $i - 1));
        }
        return (int)$score;
    }
}
