<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_WAITLISTED = 'waitlisted';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_CONFIRMED => 'Confirmed',
        self::STATUS_WAITLISTED => 'Waitlisted',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    /**
     * Human-readable answers for the dashboard and emails, resolving stored
     * option values back to their labels via the question set.
     */
    public function answerSummary(): array
    {
        $questions = collect(config('reservation_questions.'.$this->question_set_version, []));

        return $questions->mapWithKeys(function (array $q) {
            $value = $this->answers[$q['id']] ?? null;

            $label = match ($q['type']) {
                'checkbox' => collect((array) $value)
                    ->map(fn ($v) => $this->optionLabel($q, $v))
                    ->filter()
                    ->join(', '),
                'radio' => $this->optionLabel($q, $value),
                default => is_array($value) ? implode(', ', $value) : (string) $value,
            };

            return [$q['label'] => $label === '' ? '—' : $label];
        })->all();
    }

    private function optionLabel(array $question, mixed $value): ?string
    {
        foreach ($question['options'] ?? [] as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
