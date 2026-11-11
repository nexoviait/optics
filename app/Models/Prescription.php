<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prescription extends Model
{
    protected $fillable = [
        'user_id', 'source', 'patient_name', 'phone', 'r_sph', 'r_cyl', 'r_axis',
        'l_sph', 'l_cyl', 'l_axis', 'add_power', 'pd', 'file_path', 'file_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function summary(): string
    {
        if ($this->source === 'upload') {
            return 'File: '.$this->file_name;
        }

        return sprintf(
            'OD %s / %s × %s · OS %s / %s × %s',
            $this->r_sph ?? '—', $this->r_cyl ?? '—', $this->r_axis ?? '—',
            $this->l_sph ?? '—', $this->l_cyl ?? '—', $this->l_axis ?? '—',
        );
    }
}
