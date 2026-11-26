<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table) {
            $table->text('details')->nullable()->after('body');
        });

        Schema::table('responses', function (Blueprint $table) {
            $table->text('prompt_details_snapshot')->nullable()->after('prompt_body_snapshot');
            $table->json('answers')->nullable()->after('body');
            $table->json('answer_save_seqs')->nullable()->after('save_seq');
        });

        // Existing single-body responses become a one-item answers list.
        DB::table('responses')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('responses')->where('id', $row->id)->update([
                    'answers' => json_encode([(string) $row->body], JSON_UNESCAPED_UNICODE),
                    'answer_save_seqs' => json_encode([(int) $row->save_seq]),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('responses', function (Blueprint $table) {
            $table->dropColumn(['prompt_details_snapshot', 'answers', 'answer_save_seqs']);
        });

        Schema::table('prompts', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
