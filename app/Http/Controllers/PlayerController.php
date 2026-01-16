<?php

namespace App\Http\Controllers;

use App\Models\Player;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function store(Request $request, Player $player)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'chips' => 'required|integer|min:0',
        ]);

        $player = Player::create([
            'name' => $request->name,
            'chips' => $request->chips
        ]);
    }
}
