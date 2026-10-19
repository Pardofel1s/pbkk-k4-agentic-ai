<?php

namespace Tests;

use Database\Seeders\RolesAndAdminSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roles, permissions and the admin (docs/rbac.md) exist in every database test,
     * exactly as in the real app.
     */
    protected $seed = true;

    protected $seeder = RolesAndAdminSeeder::class;
}
