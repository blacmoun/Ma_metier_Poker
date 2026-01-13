<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    public $timestamps = false;

    public function game()
    {
        return $this->belongsTo(Game::class);
    }

}
