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
        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->nullable()->after('type');
            $table->unsignedBigInteger('category_id')->nullable()->after('subject_id');
            $table->unsignedBigInteger('topic_id')->nullable()->after('category_id');

            $table->index('subject_id');
            $table->index('category_id');
            $table->index('topic_id');

            $table->foreign('subject_id')->references('id')->on('subjects');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('topic_id')->references('id')->on('topics')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->dropForeign(['category_id']);
            $table->dropForeign(['topic_id']);

            $table->dropIndex(['subject_id']);
            $table->dropIndex(['category_id']);
            $table->dropIndex(['topic_id']);

            $table->dropColumn(['subject_id', 'category_id', 'topic_id']);
        });
    }
};
