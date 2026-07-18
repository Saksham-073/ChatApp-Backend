<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('key_version')->default(1);
            $table->text('wrapped_key');
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id', 'key_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_keys');
    }
};
