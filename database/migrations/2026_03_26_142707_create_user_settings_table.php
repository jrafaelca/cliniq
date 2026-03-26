<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();
            $table->json('preferences');
            $table->timestamps();
        });

        $defaultPreferences = json_encode([
            'auto_advance' => true,
            'auto_advance_delay' => 5,
        ], JSON_THROW_ON_ERROR);

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function (Collection $users) use ($defaultPreferences): void {
                $timestamp = now();

                $rows = $users
                    ->map(fn (object $user): array => [
                        'user_id' => (int) $user->id,
                        'preferences' => $defaultPreferences,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ])
                    ->values()
                    ->all();

                if ($rows !== []) {
                    DB::table('user_settings')->insert($rows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
