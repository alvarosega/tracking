<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferenceClient extends Model
{
    use HasFactory;

    protected $table = 'reference_clients';

    protected $guarded = [];
}