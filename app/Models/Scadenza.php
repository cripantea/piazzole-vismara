<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Scadenza extends Model
{
    /** @use HasFactory<\Database\Factories\ScadenzaFactory> */
    use HasFactory;
    protected $table = 'scadenze';
    protected $guarded=[];
}
