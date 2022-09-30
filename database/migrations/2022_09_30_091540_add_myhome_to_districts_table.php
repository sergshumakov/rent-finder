<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('districts', function (Blueprint $table) {
            $table->string('myhome_url',1024)
                ->after('source_url');

            $table->renameColumn('source_url', 'ss_url');
        });
    }

    public function down()
    {
        Schema::table('districts', function (Blueprint $table) {
            $table->renameColumn('ss_url', 'source_url');
            $table->dropColumn('myhome_url');
        });
    }
};
