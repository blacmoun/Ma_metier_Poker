<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Game extends Model
{
    public $timestamps = false;

    public function players()
    {
        return $this->hasMany(Player::class);
    }
}
