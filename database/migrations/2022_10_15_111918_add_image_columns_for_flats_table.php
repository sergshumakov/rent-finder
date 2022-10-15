<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('flats', function (Blueprint $table) {
            $table->timestamp('compared_at')->nullable();
            $table->timestamp('duplicate_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('flats', function (Blueprint $table) {
            $table->dropColumn([
                'compared_at',
                'duplicate_at',
            ]);
        });
    }
};
