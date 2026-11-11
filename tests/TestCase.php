<?php

namespace Tests;

use App\Support\Settings;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Settings memoizes in a static; reset it so tests never see another test's values.
        Settings::flush();
    }

    /** Tests switch users inside one session, which a browser cannot: clear the session's password fingerprint. */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        session()->forget('password_hash_'.($guard ?? config('auth.defaults.guard')));

        return parent::actingAs($user, $guard);
    }
}
