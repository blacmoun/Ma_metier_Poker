<?php

namespace App\Services;

class PokerService
{
    private $ranks = ['2','3','4','5','6','7','8','9','10','V','D','R','A'];
    private $suits = ['car', 'coe', 'pic', 'tre'];

    public function createDeck(): array
    {
        $deck = [];
        foreach ($this->ranks as $r) {
            foreach ($this->suits as $s) {
                $deck[] = "{$r}_{$s}.png";
            }
        }
        shuffle($deck);
        return $deck;
    }

    public function deal(&$deck, $count = 2): array
    {
        return array_splice($deck, 0, $count);
    }
}
