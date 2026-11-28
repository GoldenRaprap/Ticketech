<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_article_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_article_id')->nullable()->constrained('knowledge_articles')->nullOnDelete();
            $table->string('title', 180);
            $table->string('category', 80);
            $table->text('summary');
            $table->longText('proposed_body');
            $table->json('source_ticket_ids');
            $table->json('shared_terms')->nullable();
            $table->unsignedTinyInteger('similarity_score')->default(0);
            $table->string('status', 20)->default('pending');
            $table->string('fingerprint', 64)->unique();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->string('draft_title', 180)->nullable();
            $table->string('draft_category', 80)->nullable();
            $table->text('draft_summary')->nullable();
            $table->longText('draft_body')->nullable();
            $table->timestamp('draft_generated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_articles', function (Blueprint $table) {
            $table->dropColumn([
                'draft_title',
                'draft_category',
                'draft_summary',
                'draft_body',
                'draft_generated_at',
            ]);
        });

        Schema::dropIfExists('knowledge_article_suggestions');
    }
};
