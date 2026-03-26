<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'preferences',
])]
class UserSettings extends Model
{
    public const DEFAULT_AUTO_ADVANCE = true;

    public const DEFAULT_AUTO_ADVANCE_DELAY = 5;

    public const AUTO_ADVANCE_DELAY_MIN = 1;

    public const AUTO_ADVANCE_DELAY_MAX = 30;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferences' => 'array',
        ];
    }

    /**
     * Get the user that owns the settings.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array{auto_advance: bool, auto_advance_delay: int}
     */
    public static function defaultPreferences(): array
    {
        return [
            'auto_advance' => self::DEFAULT_AUTO_ADVANCE,
            'auto_advance_delay' => self::DEFAULT_AUTO_ADVANCE_DELAY,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $preferences
     * @return array{auto_advance: bool, auto_advance_delay: int}
     */
    public static function normalizePreferences(?array $preferences): array
    {
        $preferences ??= [];

        $autoAdvance = array_key_exists('auto_advance', $preferences)
            ? (bool) $preferences['auto_advance']
            : self::DEFAULT_AUTO_ADVANCE;

        $autoAdvanceDelay = array_key_exists('auto_advance_delay', $preferences)
            ? (int) $preferences['auto_advance_delay']
            : self::DEFAULT_AUTO_ADVANCE_DELAY;

        $autoAdvanceDelay = min(
            self::AUTO_ADVANCE_DELAY_MAX,
            max(self::AUTO_ADVANCE_DELAY_MIN, $autoAdvanceDelay),
        );

        return [
            'auto_advance' => $autoAdvance,
            'auto_advance_delay' => $autoAdvanceDelay,
        ];
    }
}
