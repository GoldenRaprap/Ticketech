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
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('first_assigned_at')->nullable()->after('assigned_at');
            $table->timestamp('last_resolved_at')->nullable()->after('resolved_at');
            $table->timestamp('last_reopened_at')->nullable()->after('last_resolved_at');
        });

        DB::table('tickets')
            ->whereNotNull('assigned_at')
            ->update(['first_assigned_at' => DB::raw('assigned_at')]);
        DB::table('tickets')
            ->whereNotNull('resolved_at')
            ->update(['last_resolved_at' => DB::raw('resolved_at')]);

        Schema::create('ticket_misuse_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 50);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('action', 20)->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('client_moderations', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('status', 20);
            $table->text('reason');
            $table->foreignId('source_report_id')->nullable()->constrained('ticket_misuse_reports')->nullOnDelete();
            $table->foreignId('acted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'cleared_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_moderations');
        Schema::dropIfExists('ticket_misuse_reports');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['first_assigned_at', 'last_resolved_at', 'last_reopened_at']);
        });
    }
};
