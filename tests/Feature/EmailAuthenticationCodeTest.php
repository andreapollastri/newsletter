<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailAuthenticationCodeTest extends TestCase
{
    use RefreshDatabase;

    /** A store that serializes, like the database store in production: the array store would hide the bug. */
    public function test_the_code_in_the_email_is_accepted(): void
    {
        config(['cache.default' => 'database']);
        Notification::fake();
        $user = User::factory()->create();
        $email = collect(Filament::getPanel('newsletter')->getMultiFactorAuthenticationProviders())
            ->first(fn ($provider) => $provider instanceof EmailAuthentication)
            ->generateCodesUsing(fn (): string => '123456');

        $this->assertTrue($email->sendCode($user));

        $this->assertFalse($email->verifyCode('654321', $user));
        $this->assertTrue($email->verifyCode('123456', $user));
        $this->assertFalse($email->verifyCode('123456', $user), 'A code works once.');
    }
}
