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
            $table->timestamp('requester_email_verified_at')->nullable();
            $table->string('requester_email_verification_token_hash', 64)->nullable();
            $table->timestamp('requester_email_verification_expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'requester_email_verified_at',
                'requester_email_verification_token_hash',
                'requester_email_verification_expires_at',
            ]);
        });
    }
};
