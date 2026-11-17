<?php

namespace App\Studio\Models;

use Illuminate\Database\Eloquent\Model;

class StudioTemplate extends Model
{
    public const KINDS = ['segment', 'overlay', 'captions', 'transition'];

    public const CATEGORIES = ['Kinetic type', 'Product UI', 'Phone', 'Charts', 'Diagrams', 'Shapes', 'Particles', 'Characters', 'Photos', 'Music', 'Code'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'aspects' => 'array',
            'props_schema' => 'array',
            'default_props' => 'array',
            'previews' => 'array',
            'credit' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
            'default_duration' => 'float',
            'min_duration' => 'float',
            'max_duration' => 'float',
        ];
    }
}
