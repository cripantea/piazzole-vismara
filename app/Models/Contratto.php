<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contratto extends Model
{
    /** @use HasFactory<\Database\Factories\ContrattoFactory> */
    use HasFactory;

    protected $table = 'contratti';
    protected $guarded = [];
}
