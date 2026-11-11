<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisionStep extends Model
{
    protected $fillable = ['key', 'title', 'options', 'is_required', 'sort_order'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    /** @return list<string> */
    public function optionList(): array
    {
        return array_values(array_filter(array_map('trim', explode('·', $this->options))));
    }
}
