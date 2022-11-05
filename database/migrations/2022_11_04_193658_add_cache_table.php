<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cache', static function ($table) {
            $table->string('key')->unique();
            $table->text('value');
            $table->integer('expiration');
        });
    }
};
