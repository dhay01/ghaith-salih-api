<?php

namespace App\Models;

use App\Models\Concerns\HasCoverImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Spatie\MediaLibrary\HasMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class Workshop extends Model implements HasMedia
{
    use HasCoverImage;
    use HasFactory;
    use HasSlug;
    use HasTranslations;

    protected $guarded = [];

    /** Columns stored as {"en": "...", "ar": "..."}. */
    public array $translatable = [
        'title',
        'mode',
        'level',
        'location',
        'overview',
        'duration',
        'attendees',
    ];

    protected function casts(): array
    {
        return [
            'outcomes' => 'array',
            'syllabus' => 'array',
            'included' => 'array',
            'prerequisites' => 'array',
            'faqs' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_published' => 'boolean',
            'accepts_reservations' => 'boolean',
        ];
    }

    /**
     * A row added in the dashboard and left blank used to be saved as it was, and
     * the workshop page rendered it as an empty bullet, day or question.
     */
    protected static function booted(): void
    {
        static::saving(function (self $workshop): void {
            foreach (['outcomes', 'included', 'prerequisites', 'syllabus', 'faqs'] as $column) {
                $workshop->{$column} = static::withoutBlankRows($workshop->{$column});
            }
        });
    }

    /**
     * @param  array<int, mixed>|null  $rows
     * @return array<int, mixed>|null
     */
    protected static function withoutBlankRows(?array $rows): ?array
    {
        if ($rows === null) {
            return null;
        }

        $kept = [];

        foreach ($rows as $row) {
            if (is_array($row) && is_array($row['slots'] ?? null)) {
                $row['slots'] = static::withoutBlankRows($row['slots']);
            }

            $filled = is_array($row)
                ? collect(Arr::flatten($row))->contains(fn ($value) => filled($value))
                : filled($row);

            if ($filled) {
                $kept[] = $row;
            }
        }

        return $kept;
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn (self $w) => $w->getTranslation('title', 'en'))
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Seats held by reservations that have not been cancelled. Derived rather
     * than denormalised so a cancellation can never leave the count drifting.
     */
    public function seatsTaken(): int
    {
        return (int) $this->reservations()
            ->whereIn('status', [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED])
            ->sum('seats');
    }

    public function seatsLeft(): int
    {
        return max(0, $this->seats_total - $this->seatsTaken());
    }

    public function isFull(): bool
    {
        return $this->seatsLeft() <= 0;
    }

    public function canAcceptReservations(): bool
    {
        return $this->is_published
            && $this->accepts_reservations
            && ! $this->isFull();
    }

    public function getPriceAttribute(): string
    {
        return $this->currency.' '.number_format($this->price_minor / 100, 2);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('starts_on', '>=', now()->toDateString());
    }

    public function isPast(): bool
    {
        return $this->starts_on?->isBefore(now()->startOfDay()) ?? false;
    }
}
