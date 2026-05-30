<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Favoritable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Item extends Model
{
    use Favoritable, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'parent_id',
        'assignee_id',
        'reporter_id',
        'due_date',
        'start_date',
        'end_date',
        'etd_date',
        'eta_date',
        'position',
        'to',
        'cc',
    ];

    protected $casts = [
        'due_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'etd_date' => 'date',
        'eta_date' => 'date',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Item::class, 'parent_id');
    }

    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'item_label');
    }

    public function to(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'item_to');
    }

    public function cc(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'item_cc');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeRootLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assignee_id', $userId);
    }

    public function scopeWithLabel($query, string $labelName)
    {
        return $query->whereHas('labels', fn ($q) => $q->where('name', $labelName));
    }

    // ─── Activity log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'assignee_id', 'parent_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
