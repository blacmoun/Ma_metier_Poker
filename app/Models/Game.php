<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    protected $fillable = ['status', 'timer_at', 'current_turn'];

    public function players()
    {
        return $this->hasMany(Player::class);
    }
}
