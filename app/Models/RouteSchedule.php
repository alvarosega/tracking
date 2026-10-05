<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouteSchedule extends Model
{
    protected $connection = 'supervisor';

    protected $table = 'route_schedules';

    protected $fillable = [
        'route',
        'day_of_week',
        'start_time',
        'end_time',
        'is_working_day',
    ];

    protected $casts = [
        'day_of_week'    => 'integer',
        'is_working_day' => 'boolean',
    ];
}