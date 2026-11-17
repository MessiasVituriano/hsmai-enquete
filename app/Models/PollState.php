<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PollState extends Model
{
    public const SCREENS = ['invite', 'question', 'all'];

    protected $fillable = ['accepting', 'screen', 'screen_question_id'];

    protected $casts = [
        'accepting' => 'boolean',
        'screen_question_id' => 'integer',
    ];

    /** A linha única do estado; criada no primeiro acesso se o seed não rodou. */
    public static function current(): self
    {
        return static::query()->orderBy('id')->first()
            ?? static::create(['accepting' => true, 'screen' => 'invite']);
    }
}
