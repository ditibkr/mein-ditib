<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'subject_de',
        'subject_tr',
        'body_de',
        'body_tr',
        'variables',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function render(string $language = 'de', array $data = []): array
    {
        $subject = $language === 'tr' && $this->subject_tr
            ? $this->subject_tr
            : $this->subject_de;

        $body = $language === 'tr' && $this->body_tr
            ? $this->body_tr
            : $this->body_de;

        foreach ($data as $key => $value) {
            $subject = str_replace("{{$key}}", $value, $subject);
            $body = str_replace("{{$key}}", $value, $body);
        }

        return ['subject' => $subject, 'body' => $body];
    }
}
