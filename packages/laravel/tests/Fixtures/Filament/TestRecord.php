<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Illuminate\Database\Eloquent\Model;

final class TestRecord extends Model
{
    protected $table = 'filament_test_records';

    protected $guarded = [];

    public $timestamps = false;
}
