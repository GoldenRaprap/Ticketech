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
        Schema::table('pending_ticket_submissions', function (Blueprint $table) {
            $table->string('completed_ticket_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pending_ticket_submissions', function (Blueprint $table) {
            $table->dropColumn('completed_ticket_number');
        });
    }
};
