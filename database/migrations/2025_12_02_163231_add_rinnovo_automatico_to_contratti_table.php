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
        Schema::table('contratti', function (Blueprint $table) {
            $table->boolean('rinnovo_automatico')->default(false)->after('stato');
            $table->timestamp('rinnovo_automatico_at')->nullable()->after('rinnovo_automatico');
        });
    }

    public function down()
    {
        Schema::table('contratti', function (Blueprint $table) {
            $table->dropColumn(['rinnovo_automatico', 'rinnovo_automatico_at']);
        });
    }
};
