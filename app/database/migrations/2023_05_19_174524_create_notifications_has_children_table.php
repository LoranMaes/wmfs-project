<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::create('notifications_has_children', function (Blueprint $table) {
            // $table->id();
            // $table->timestamps();
            $table->foreignId('notification_id')->references('id')->on('notifications')->onDelete('cascade');
            $table->foreignId('child_id')->references('id')->on('children')->onDelete('cascade');
            $table->boolean('seen');
            $table->boolean('filled_in');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notifications_has_children');
    }
};
