<?php

namespace Tests\Unit\Support;

use App\Support\NewsletterUrlUtm;
use PHPUnit\Framework\TestCase;

class NewsletterUrlUtmTest extends TestCase
{
    public function test_appends_utm_parameters_to_https_url(): void
    {
        $result = NewsletterUrlUtm::append(
            'https://example.com/foo',
            'my-campaign',
            'msg-uuid-123'
        );

        $this->assertStringContainsString('utm_source=nl', $result);
        $this->assertStringContainsString('utm_medium=newsletter', $result);
        $this->assertStringContainsString('utm_campaign=my-campaign', $result);
        $this->assertStringContainsString('utm_content=msg-uuid-123', $result);
    }

    public function test_merges_with_existing_query_string(): void
    {
        $result = NewsletterUrlUtm::append(
            'https://example.com/path?x=1',
            'c',
            'm'
        );

        $this->assertStringContainsString('x=1', $result);
        $this->assertStringContainsString('utm_campaign=c', $result);
    }

    public function test_preserves_fragment(): void
    {
        $result = NewsletterUrlUtm::append(
            'https://example.com/page#section',
            'c',
            'm'
        );

        $this->assertStringEndsWith('#section', $result);
        $this->assertStringContainsString('utm_medium=newsletter', $result);
    }

    public function test_leaves_mailto_unchanged(): void
    {
        $url = 'mailto:test@example.com';

        $this->assertSame($url, NewsletterUrlUtm::append($url, 'c', 'm'));
    }

    public function test_overrides_existing_utm_keys(): void
    {
        $result = NewsletterUrlUtm::append(
            'https://example.com/?utm_source=old',
            'new-campaign',
            'mid'
        );

        $this->assertStringContainsString('utm_source=nl', $result);
        $this->assertStringNotContainsString('utm_source=old', $result);
    }
}
