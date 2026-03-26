<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserSettingsUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_update_auto_advance_without_overwriting_other_preferences(): void
    {
        $user = User::factory()->create();
        $user->settings()->create([
            'preferences' => [
                'auto_advance' => true,
                'auto_advance_delay' => 9,
            ],
        ]);

        $response = $this->actingAs($user)->patchJson('/user/settings', [
            'auto_advance' => false,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'settings' => [
                    'auto_advance' => false,
                    'auto_advance_delay' => 9,
                ],
            ]);

        $preferences = $user->fresh()->settings?->preferences ?? [];

        $this->assertSame(false, $preferences['auto_advance'] ?? null);
        $this->assertSame(9, $preferences['auto_advance_delay'] ?? null);
    }

    public function test_endpoint_creates_settings_when_missing_and_applies_defaults(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseMissing('user_settings', [
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->patchJson('/user/settings', [
            'auto_advance' => false,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'settings' => [
                    'auto_advance' => false,
                    'auto_advance_delay' => UserSettings::DEFAULT_AUTO_ADVANCE_DELAY,
                ],
            ]);

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
        ]);
    }

    public function test_delay_must_be_between_one_and_thirty_seconds(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/user/settings', [
                'auto_advance_delay' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['auto_advance_delay']);

        $this->actingAs($user)
            ->patchJson('/user/settings', [
                'auto_advance_delay' => 31,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['auto_advance_delay']);
    }

    public function test_profile_settings_page_exposes_normalized_practice_preferences(): void
    {
        $user = User::factory()->create();
        $user->settings()->create([
            'preferences' => [
                'auto_advance' => false,
                'auto_advance_delay' => 100,
            ],
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Profile')
                ->where('settings.auto_advance', false)
                ->where('settings.auto_advance_delay', UserSettings::AUTO_ADVANCE_DELAY_MAX),
            );
    }

    public function test_profile_settings_page_creates_default_preferences_if_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Profile')
                ->where('settings.auto_advance', UserSettings::DEFAULT_AUTO_ADVANCE)
                ->where('settings.auto_advance_delay', UserSettings::DEFAULT_AUTO_ADVANCE_DELAY),
            );

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
        ]);
    }
}
