<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('flats', function (Blueprint $table) {
            $table->string('cad_number')
                ->nullable()
                ->unique()
                ->after('uuid');

            $table->string('lat')->nullable()->after('photos');
            $table->string('lng')->nullable()->after('lat');
        });
    }

    public function down()
    {
        Schema::table('flats', function (Blueprint $table) {
            $table->dropColumn(['cad_number', 'lat', 'lng']);
        });
    }
};
