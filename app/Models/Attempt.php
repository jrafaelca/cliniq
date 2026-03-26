<?php

namespace App\Models;

use Database\Factories\AttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'status',
    'mode',
    'time_limit_seconds',
    'question_ids',
    'started_at',
    'last_activity_at',
    'finished_at',
    'score',
])]
class Attempt extends Model
{
    /** @use HasFactory<AttemptFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_EXPIRED = 'expired';

    public const MODE_PRACTICE = 'practice';

    public const MODE_REVIEW = 'review';

    public const MODE_TRAINING = 'training';

    public const MODE_SIMULATION = 'simulation';

    public const SIMULATION_TIME_LIMIT_SECONDS = 120;

    public const INACTIVITY_LIMIT_MINUTES = 30;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'question_ids' => 'array',
            'mode' => 'string',
            'time_limit_seconds' => 'integer',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'finished_at' => 'datetime',
            'score' => 'decimal:2',
        ];
    }

    /**
     * Get the user that owns the attempt.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the answers for the attempt.
     *
     * @return HasMany<AttemptAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }
}
