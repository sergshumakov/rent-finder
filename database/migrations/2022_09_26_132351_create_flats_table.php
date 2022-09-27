<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('flats', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('uuid')->unique();
            $table->string('link');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('price')->nullable();
            $table->string('address')->nullable();
            $table->string('flat_area')->nullable();
            $table->string('flat_type')->nullable();
            $table->string('flat_floor')->nullable();
            $table->json('photos')->nullable();
            $table->string('time')->nullable();
            $table->timestamps();
            $table->timestamp('published_at')->nullable();
        });
    }
};
