<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contratti', function (Blueprint $table) {
            $table->id();
            $table->integer('client_id')->unsigned();
            $table->foreign('client_id')->references('id')->on('clienti');
            $table->integer('piazzuola_id')->unsigned();
            $table->foreign('piazzuola_id')->references('id')->on('piazzuole');
            $table->date('data_start');
            $table->date('data_end')->nullable();
            $table->decimal('importo_totale', 10, 2);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contratti');
    }
};
