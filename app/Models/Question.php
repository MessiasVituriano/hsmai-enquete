<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    protected $fillable = ['position', 'prompt', 'inline_options', 'revealed_at'];

    protected $casts = [
        'position' => 'integer',
        'inline_options' => 'boolean',
        'revealed_at' => 'datetime',
    ];

    public function options(): HasMany
    {
        return $this->hasMany(Option::class)->orderBy('position');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}
