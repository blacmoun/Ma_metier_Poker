<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = ['name', 'chips'];


    public function game()
    {
        return $this->belongsTo(Game::class);
    }

}
