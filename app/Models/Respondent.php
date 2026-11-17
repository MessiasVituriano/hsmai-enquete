<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Respondent extends Model
{
    protected $fillable = ['token', 'name', 'email', 'phone', 'hotel'];

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'respondent', 'token');
    }

    /**
     * Só os dígitos, sem o código do país: "+55 (11) 99999-9999" e
     * "11999999999" têm de ser a mesma pessoa, senão o índice único não pega.
     */
    public static function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if (strlen($digits) >= 12 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        return $digits === '' ? null : $digits;
    }

    public static function formatPhone(?string $digits): string
    {
        if (! $digits) {
            return '';
        }

        return strlen($digits) === 11
            ? sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7))
            : sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6));
    }

    /** O próprio cadastro, para o celular do dono preencher o formulário de edição. */
    public function toProfile(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'hotel' => $this->hotel,
        ];
    }
}
