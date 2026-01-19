<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $fillable = ['name', 'chips', 'game_id', 'session_token'];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}
