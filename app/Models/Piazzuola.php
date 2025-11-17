<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Piazzuola extends Model
{
    protected $table = 'piazzuole';
    protected $guarded = [];

    public function contratti()
    {
        return $this->hasMany(Contratto::class, 'piazzuola_id');
    }

    public function contratto()
    {
        return $this->hasOne(Contratto::class, 'piazzuola_id');
    }

}
