<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PollState extends Model
{
    /*
     * invite   — só o QR code; os celulares aguardam
     * question — a pergunta N sendo respondida (telão e celular, sem resultado)
     * results  — o resultado da pergunta N (telão e celular)
     */
    public const SCREENS = ['invite', 'question', 'results'];

    protected $fillable = ['accepting', 'screen', 'screen_question_id'];

    protected $casts = [
        'accepting' => 'boolean',
        'screen_question_id' => 'integer',
    ];

    /** A pergunta em foco (sendo respondida ou com o resultado na tela), ou null no convite. */
    public function focusId(): ?int
    {
        return in_array($this->screen, ['question', 'results'], true) ? $this->screen_question_id : null;
    }

    /** A linha única do estado; criada no primeiro acesso se o seed não rodou. */
    public static function current(): self
    {
        return static::query()->orderBy('id')->first()
            ?? static::create(['accepting' => true, 'screen' => 'invite']);
    }
}
