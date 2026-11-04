<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('resolution_warning_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->unsignedInteger('sla_paused_minutes')->default(0);
            $table->timestamp('sla_paused_at')->nullable();
            $table->index(['status', 'resolution_warning_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status', 'resolution_warning_at']);
            $table->dropColumn([
                'resolution_warning_at',
                'resolution_due_at',
                'sla_paused_minutes',
                'sla_paused_at',
            ]);
        });
    }
};
