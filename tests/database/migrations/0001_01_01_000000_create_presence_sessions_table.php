<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('filament-presence.table', 'presence_sessions'), function (Blueprint $table): void {
            $table->id();
            $table->string('user_id');
            $table->string('room_key');
            $table->text('url');
            $table->string('label')->nullable();
            $table->string('session_token');
            $table->timestampTz('entered_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('left_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestampsTz();
            $table->unique(['user_id', 'session_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('filament-presence.table', 'presence_sessions'));
    }
};
