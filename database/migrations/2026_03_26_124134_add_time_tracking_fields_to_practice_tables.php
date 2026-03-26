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
        Schema::table('attempts', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('started_at');
        });

        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->unsignedInteger('time_spent_seconds')->default(0)->after('is_correct');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn('last_activity_at');
        });

        Schema::table('attempt_answers', function (Blueprint $table) {
            $table->dropColumn('time_spent_seconds');
        });
    }
};
