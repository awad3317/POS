<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GlobalBarcodeCatalog extends Model
{
    use HasFactory;

    protected $table = 'global_barcode_catalog';

    protected $fillable = [
        'barcode',
        'name_ar',
        'name_en',
        'category_hint',
        'default_unit',
        'image_path',
    ];
}
