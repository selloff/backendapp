<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\InteractsWithAdminPin;

abstract class TestCase extends BaseTestCase
{
    use InteractsWithAdminPin;
}
