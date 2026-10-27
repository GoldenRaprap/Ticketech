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
        Schema::table('ticket_events', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
            $table->foreignId('ticket_id')->nullable()->change();
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();

            $table->string('actor_name')->nullable()->after('actor_id');
            $table->string('request_method', 10)->nullable()->after('description');
            $table->string('route_name', 150)->nullable()->after('request_method');
            $table->string('subject_type', 150)->nullable()->after('route_name');
            $table->string('subject_id', 100)->nullable()->after('subject_type');
            $table->string('subject_name')->nullable()->after('subject_id');
            $table->string('ip_address', 45)->nullable()->after('subject_name');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->json('properties')->nullable()->after('user_agent');

            $table->index(['actor_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ticket_events')->whereNull('ticket_id')->delete();

        Schema::table('ticket_events', function (Blueprint $table) {
            $table->dropIndex(['actor_id', 'created_at']);
            $table->dropIndex(['event', 'created_at']);
            $table->dropForeign(['ticket_id']);
            $table->unsignedBigInteger('ticket_id')->nullable(false)->change();
            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
            $table->dropColumn([
                'actor_name',
                'request_method',
                'route_name',
                'subject_type',
                'subject_id',
                'subject_name',
                'ip_address',
                'user_agent',
                'properties',
            ]);
        });
    }
};
