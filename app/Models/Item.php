<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Item extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'type',
        'title',
        'description',
        'status',
        'priority',
        'parent_id',
        'assignee_id',
        'reporter_id',
        'due_date',
        'estimated_minutes',
        'spent_minutes',
        'position',
    ];

    protected $casts = [
        'due_date'           => 'date',
        'estimated_minutes'  => 'integer',
        'spent_minutes'      => 'integer',
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

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->latest();
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeProjects($query)
    {
        return $query->where('type', 'project');
    }

    public function scopeEpics($query)
    {
        return $query->where('type', 'epic');
    }

    public function scopeTasks($query)
    {
        return $query->where('type', 'task');
    }

    public function scopeRootLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeAssignedTo($query, int $userId)
    {
        return $query->where('assignee_id', $userId);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Convert this item to another type.
     * Allowed conversions:
     *   task    → epic, project
     *   epic    → task, project
     *   project → epic
     *   case    → task, epic
     */
    public function convertTo(string $newType): static
    {
        $allowed = [
            'task'    => ['epic', 'project'],
            'epic'    => ['project', 'task'],
            'project' => ['epic'],
            'case'    => ['task', 'epic'],
        ];

        if (! in_array($newType, $allowed[$this->type] ?? [])) {
            throw new \InvalidArgumentException(
                "Cannot convert {$this->type} to {$newType}"
            );
        }

        $this->update(['type' => $newType]);

        return $this;
    }

    public function getEstimatedHoursAttribute(): ?float
    {
        return $this->estimated_minutes ? round($this->estimated_minutes / 60, 1) : null;
    }

    public function getSpentHoursAttribute(): float
    {
        return round($this->spent_minutes / 60, 1);
    }

    // ─── Activity log ─────────────────────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'priority', 'assignee_id', 'type', 'parent_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
