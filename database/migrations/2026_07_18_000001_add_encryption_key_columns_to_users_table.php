<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('public_key')->nullable();
            $table->text('encrypted_private_key')->nullable();
            $table->string('key_salt', 64)->nullable();
            $table->string('key_nonce', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['public_key', 'encrypted_private_key', 'key_salt', 'key_nonce']);
        });
    }
};
