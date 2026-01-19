<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    protected $fillable = ['status'];

    public function players()
    {
        return $this->hasMany(Player::class);
    }
}
