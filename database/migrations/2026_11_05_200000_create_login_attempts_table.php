<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            // Hash SHA-256 del email/ID: permite correlacionar intentos sin guardar el dato personal.
            $table->string('identifier_hash', 64)->nullable();
            $table->string('ip_address', 45);
            $table->string('status', 20); // success | failed | blocked
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ip_address', 'status', 'created_at']);
            $table->index(['identifier_hash', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
