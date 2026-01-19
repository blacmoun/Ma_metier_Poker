<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'chips',
        'game_id',
        'session_token',
        'last_activity'
    ];

    protected $hidden = ['session_token'];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}
