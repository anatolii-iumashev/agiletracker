<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes;

    protected $fillable = ['item_id', 'user_id', 'body'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Extract @mentions from comment body */
    public function getMentionsAttribute(): array
    {
        preg_match_all('/@([\w]+)/', $this->body, $matches);

        return $matches[1] ?? [];
    }
}
