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
            $table->foreignId('contratto_id')->constrained('contratti')->onDelete('cascade');
            $table->integer('numero_rata');
            $table->date('data');
            $table->decimal('importo', 10, 2);
            $table->date('data_pagamento')->nullable();
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
