<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UserSettingsUpdateRequest;
use App\Models\UserSettings;
use Illuminate\Http\JsonResponse;

class UserSettingsController extends Controller
{
    public function update(UserSettingsUpdateRequest $request): JsonResponse
    {
        $settings = $request->user()
            ->settings()
            ->firstOrCreate([], [
                'preferences' => UserSettings::defaultPreferences(),
            ]);

        $existingPreferences = is_array($settings->preferences)
            ? $settings->preferences
            : [];

        $incomingPreferences = $request->safe()->only([
            'auto_advance',
            'auto_advance_delay',
        ]);

        $settings->update([
            'preferences' => array_merge($existingPreferences, $incomingPreferences),
        ]);

        return response()->json([
            'settings' => UserSettings::normalizePreferences(
                is_array($settings->preferences) ? $settings->preferences : null,
            ),
        ]);
    }
}
