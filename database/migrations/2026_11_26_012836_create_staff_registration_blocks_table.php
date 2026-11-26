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
        Schema::create('staff_registration_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->text('reason');
            $table->foreignId('blocked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_registration_blocks');
    }
};
