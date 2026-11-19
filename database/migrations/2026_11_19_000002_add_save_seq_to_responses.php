<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            // Monotonic client sequence so overlapping autosaves never last-write-wins older text.
            $table->unsignedBigInteger('save_seq')->default(0)->after('last_saved_at');
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropColumn('save_seq');
        });
    }
};
