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
        Schema::create('scadenze', function (Blueprint $table) {
            $table->id();
            $table->integer('contratto_id')->unsigned();
            $table->foreign('contratto_id')->references('id')->on('contratti');
            $table->date('data_scadenza');
            $table->decimal('importo', 8, 2);
            $table->decimal('pagato', 8, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scadenze');
    }
};
