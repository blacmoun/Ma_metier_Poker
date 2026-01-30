<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    protected $fillable = [
        'status',
        'timer_at',
        'current_turn',
        'dealer_index',
        'deck',
        'pot',
        'community_cards'
    ];

    protected $casts = [
        'deck' => 'array',
        'community_cards' => 'array',
        'timer_at' => 'datetime',
    ];

    public function players()
    {
        return $this->hasMany(Player::class);
    }
}
