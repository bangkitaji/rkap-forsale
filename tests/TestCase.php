<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Set the currently logged in user for the application.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string|null  $driver
     * @return $this
     */
    public function actingAs($user, $driver = null)
    {
        if ($user instanceof \App\Models\User) {
            $user->must_change_password = false;
        }

        return parent::actingAs($user, $driver);
    }
}
