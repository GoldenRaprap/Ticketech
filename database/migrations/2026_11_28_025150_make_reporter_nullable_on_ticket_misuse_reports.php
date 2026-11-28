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
        Schema::table('ticket_misuse_reports', function (Blueprint $table) {
            $table->foreignId('reporter_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('ticket_misuse_reports')->whereNull('reporter_id')->exists()) {
            throw new RuntimeException('Automated client reports need a staff reporter before this migration can be rolled back.');
        }

        Schema::table('ticket_misuse_reports', function (Blueprint $table) {
            $table->foreignId('reporter_id')->nullable(false)->change();
        });
    }
};
