<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Piazzola extends Model
{
    protected $table = 'piazzole';
    protected $guarded = [];

    public function contratti()
    {
        return $this->hasMany(Contratto::class, 'piazzola_id');
    }

    public function contratto()
    {
        return $this->hasOne(Contratto::class, 'piazzola_id');
    }

}
