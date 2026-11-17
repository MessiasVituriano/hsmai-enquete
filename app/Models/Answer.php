<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    protected $fillable = ['respondent', 'question_id', 'option_id'];

    protected $casts = [
        'question_id' => 'integer',
        'option_id' => 'integer',
    ];
}
