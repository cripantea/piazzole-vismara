<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('scadenze', function (Blueprint $table) {
            $table->foreignId('scadenza_originale_id')->nullable()->after('contratto_id')->constrained('scadenze')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('scadenze', function (Blueprint $table) {
            $table->dropForeign(['scadenza_originale_id']);
            $table->dropColumn('scadenza_originale_id');
        });
    }
};
