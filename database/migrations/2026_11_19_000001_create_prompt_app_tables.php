<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedInteger('sort')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->string('language', 2)->default('en');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->index(['stage_id', 'sort']);
        });

        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('access_token', 64)->unique();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained()->restrictOnDelete();
            $table->foreignId('prompt_id')->constrained()->restrictOnDelete();
            $table->foreignId('stage_id')->constrained()->restrictOnDelete();
            $table->text('prompt_body_snapshot');
            $table->longText('body');
            $table->boolean('is_selected')->default(true);
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamps();

            $table->unique(['participant_id', 'prompt_id']);
            $table->index(['prompt_id', 'is_selected']);
            $table->index(['participant_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responses');
        Schema::dropIfExists('participants');
        Schema::dropIfExists('prompts');
        Schema::dropIfExists('stages');
    }
};
