<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_intents', function (Blueprint $table) {

            $table->id();

            $table->string('intent')->unique();

            $table->json('keywords');

            $table->json('responses');

            $table->boolean('active')->default(true);

            $table->integer('priority')->default(100);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_intents');
    }
};
