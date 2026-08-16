<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Member extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'member_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'mobile',
        'birth_date',
        'birth_place',
        'nationality',
        'gender',
        'street',
        'house_number',
        'zip_code',
        'city',
        'country',
        'membership_start',
        'membership_end',
        'status',
        'category',
        'membership_fee',
        'fee_interval',
        'language_preference',
        'sepa_active',
        'notes',
        'gdpr_consent',
        'gdpr_consent_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'membership_start' => 'date',
            'membership_end' => 'date',
            'gdpr_consent_at' => 'datetime',
            'membership_fee' => 'decimal:2',
            'sepa_active' => 'boolean',
            'gdpr_consent' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getFullAddressAttribute(): string
    {
        return "{$this->street} {$this->house_number}, {$this->zip_code} {$this->city}";
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(MemberGroup::class, 'member_group_pivot')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'aktiv');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function isActive(): bool
    {
        return $this->status === 'aktiv';
    }
}
