<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('client_conduct_phrases', function (Blueprint $table) {
            $table->id();
            $table->string('phrase', 150)->unique();
            $table->string('reason', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $phrases = [
            'threatening' => [
                'i will hurt you',
                'i will harm you',
                'i will kill you',
                'i ll hurt you',
                'i ll harm you',
                'i ll kill you',
                'i am going to hurt you',
                'i am going to kill you',
                'i m going to hurt you',
                'i m going to kill you',
                'you will regret this',
                'you ll regret this',
                'papatayin kita',
                'sasaktan kita',
            ],
            'abusive' => [
                'fuck you',
                'shut up',
                'you are an idiot',
                'you re an idiot',
                'you are stupid',
                'you re stupid',
                'you are useless',
                'you re useless',
                'you are an asshole',
                'you re an asshole',
                'bobo ka',
                'tanga ka',
                'gago ka',
                'putang ina mo',
                'walang kwenta ka',
            ],
        ];
        $records = [];

        foreach ($phrases as $reason => $reasonPhrases) {
            foreach ($reasonPhrases as $phrase) {
                $records[] = [
                    'phrase' => $phrase,
                    'reason' => $reason,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('client_conduct_phrases')->insert($records);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_conduct_phrases');
    }
};
