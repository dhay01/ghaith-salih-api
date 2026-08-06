<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class Workshop extends Model
{
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
}
