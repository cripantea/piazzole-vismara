<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('piazzole', function (Blueprint $table) {
            $table->id();

            $table->string('identificativo')->unique();
            $table->string('nome');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('piazzole');
    }
};
