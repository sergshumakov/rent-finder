<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('flats', function (Blueprint $table) {
            $table->timestamp('error_at')->nullable()->after('published_at');
        });
    }

    public function down()
    {
        Schema::table('flats', function (Blueprint $table) {
            $table->dropColumn('error_at');
        });
    }
};
