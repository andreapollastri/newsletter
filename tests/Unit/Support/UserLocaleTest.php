<?php

namespace Tests\Unit\Support;

use App\Support\UserLocale;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserLocaleTest extends TestCase
{
    #[Test]
    public function allowed_locales_include_german_french_spanish_and_portuguese(): void
    {
        $this->assertContains('de', UserLocale::ALLOWED);
        $this->assertContains('fr', UserLocale::ALLOWED);
        $this->assertContains('es', UserLocale::ALLOWED);
        $this->assertContains('pt', UserLocale::ALLOWED);
    }

    #[Test]
    public function is_allowed_accepts_configured_locales(): void
    {
        foreach (UserLocale::ALLOWED as $locale) {
            $this->assertTrue(UserLocale::isAllowed($locale));
        }
        $this->assertFalse(UserLocale::isAllowed(null));
        $this->assertFalse(UserLocale::isAllowed('xx'));
    }

    #[Test]
    public function default_locale_is_english(): void
    {
        $this->assertSame('en', UserLocale::default());
    }

    #[Test]
    public function negotiate_from_request_matches_accept_language(): void
    {
        $request = Request::create('/login', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'pt-BR,pt;q=0.9',
        ]);

        $this->assertSame('pt', UserLocale::negotiateFromRequest($request));
    }

    #[Test]
    public function negotiate_from_request_falls_back_when_no_match(): void
    {
        $request = Request::create('/login', 'GET', [], [], [], [
            'HTTP_ACCEPT_LANGUAGE' => 'zh-CN,zh;q=0.9',
        ]);

        $this->assertSame('en', UserLocale::negotiateFromRequest($request));
    }

    #[Test]
    public function negotiate_from_request_without_request_uses_default(): void
    {
        $this->assertSame('en', UserLocale::negotiateFromRequest(null));
    }
}
