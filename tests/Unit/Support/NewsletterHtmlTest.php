<?php

namespace Tests\Unit\Support;

use App\Models\Message;
use App\Models\Template;
use App\Support\NewsletterHtml;
use Tests\TestCase;

class NewsletterHtmlTest extends TestCase
{
    public function test_body_replaces_the_template_placeholder(): void
    {
        $this->assertSame(
            '<main><p>Hi</p></main>',
            NewsletterHtml::mergeIntoTemplate('<main>{{body}}</main>', '<p>Hi</p>'),
        );
    }

    public function test_body_is_appended_when_template_has_no_placeholder(): void
    {
        $this->assertSame('<header></header><p>Hi</p>', NewsletterHtml::mergeIntoTemplate('<header></header>', '<p>Hi</p>'));
        $this->assertSame('<p>Hi</p>', NewsletterHtml::mergeIntoTemplate(null, '<p>Hi</p>'));
    }

    public function test_compose_uses_the_message_template(): void
    {
        $message = new Message(['html_content' => '<p>Body</p>']);
        $message->setRelation('template', new Template(['html_content' => '<div>{{body}}</div>']));

        $this->assertSame('<div><p>Body</p></div>', NewsletterHtml::compose($message));
    }

    public function test_relative_image_sources_become_absolute_public_storage_urls(): void
    {
        config(['app.url' => 'https://news.example.com/']);

        $html = NewsletterHtml::absolutizeImageSources(
            '<img src="newsletter-images/a.png"><img src="/storage/newsletter-images/b.png">'
        );

        $this->assertSame(
            '<img src="https://news.example.com/storage/newsletter-images/a.png">'
            .'<img src="https://news.example.com/storage/newsletter-images/b.png">',
            $html,
        );
    }

    public function test_absolute_inline_and_protocol_relative_sources_are_untouched(): void
    {
        $html = '<img src="https://cdn.example.com/a.png"><img src="//cdn.example.com/b.png">'
            .'<img src="data:image/png;base64,AAAA"><img src="cid:logo">';

        $this->assertSame($html, NewsletterHtml::absolutizeImageSources($html));
    }

    public function test_placeholders_are_filled(): void
    {
        $this->assertSame(
            'Hi Ada (ada@example.com) - https://x.test/u',
            NewsletterHtml::fillPlaceholders('Hi {{name}} ({{email}}) - {{unsubscribe_url}}', 'Ada', 'ada@example.com', 'https://x.test/u'),
        );
    }
}
