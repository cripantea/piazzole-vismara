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
            $table->foreignId('piazzola_id')->constrained('piazzole')->onDelete('cascade');
            $table->foreignId('cliente_id')->constrained('clienti')->onDelete('cascade');
            $table->date('data_inizio');
            $table->date('data_fine');
            $table->decimal('valore', 10, 2);
            $table->integer('numero_rate');
            $table->enum('stato', ['attivo', 'completato', 'sospeso'])->default('attivo');
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
