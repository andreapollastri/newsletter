<?php

namespace Tests\Unit\Services;

use App\Services\ImapBounceDetector;
use Tests\TestCase;

class ImapBounceDetectorTest extends TestCase
{
    /**
     * Postfix non-delivery report shared in GitHub issue #9 (redacted by the reporter).
     */
    private const POSTFIX_NDR = <<<'NDR'
This is the mail system at host sending-smtp.example.com.

I'm sorry to have to inform you that your message could not
be delivered to one or more recipients. It's attached below.

<recipient@yahoo.com.sg>: host mx-apac.mail.gm0.yahoodns.net[106.10.248.74]
    said: 550 5.7.25 Forward-confirmed reverse DNS failed tnmpmscs (in reply to
    MAIL FROM command)

--790F7208DE.1788155155/sending-smtp.example.com
Content-Description: Delivery report
Content-Type: message/delivery-status

Reporting-MTA: dns; sending-smtp.example.com
X-Postfix-Queue-ID: 790F7208DE
X-Postfix-Sender: rfc822; me@sender.domain.com
Arrival-Date: Mon, 31 Aug 2026 05:45:53 +0000 (UTC)

Final-Recipient: rfc822; recipient@yahoo.com.sg
Original-Recipient: rfc822;recipient@yahoo.com.sg
Action: failed
Status: 5.7.25
Remote-MTA: dns; mx-apac.mail.gm0.yahoodns.net
Diagnostic-Code: smtp; 550 5.7.25 Forward-confirmed reverse DNS failed tnmpmscs

--790F7208DE.1788155155/sending-smtp.example.com
Content-Description: Undelivered Message
Content-Type: message/rfc822

From: Author <me@sender.domain.com>
To: recipient@yahoo.com.sg
Subject: Raw HTML Email
NDR;

    private ImapBounceDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = new ImapBounceDetector;
    }

    public function test_it_parses_rfc_3464_delivery_status_fields(): void
    {
        $this->assertSame([
            ['recipient' => 'recipient@yahoo.com.sg', 'action' => 'failed', 'status' => '5.7.25'],
        ], $this->detector->parseDeliveryStatus(self::POSTFIX_NDR));
    }

    public function test_failed_dsn_recipient_is_a_hard_bounce_and_sender_is_ignored(): void
    {
        $failures = $this->detector->detectFailedRecipients('Undelivered Mail Returned to Sender', self::POSTFIX_NDR);

        $this->assertSame([['email' => 'recipient@yahoo.com.sg', 'type' => 'hard']], $failures);
    }

    public function test_transient_status_codes_are_soft_bounces(): void
    {
        $report = "Final-Recipient: rfc822; full@example.com\nAction: failed\nStatus: 4.2.2\nDiagnostic-Code: smtp; 452 4.2.2 Mailbox full";

        $this->assertSame(
            [['email' => 'full@example.com', 'type' => 'soft']],
            $this->detector->detectFailedRecipients('Delivery Status Notification (Failure)', $report),
        );
    }

    public function test_delay_notifications_are_not_bounces(): void
    {
        $dsn = "Final-Recipient: rfc822; slow@example.com\nAction: delayed\nStatus: 4.4.1\nWill-Retry-Until: Tue, 8 Sep 2026 05:45:53 +0000";

        $this->assertSame([], $this->detector->detectFailedRecipients('Delayed Mail (still being retried)', $dsn));
        $this->assertSame([], $this->detector->detectFailedRecipients(
            'Delivery Status Notification (Delay)',
            'There was a temporary problem delivering your message to slow@example.com. Gmail will retry for 46 more hours.',
        ));
    }

    public function test_heuristic_fallback_ignores_our_own_addresses(): void
    {
        $failures = $this->detector->detectFailedRecipients(
            'Mail delivery failed',
            "From: news@sender.example\nThe following address failed: gone@example.com (user unknown)",
            ['news@sender.example', null],
        );

        $this->assertSame([['email' => 'gone@example.com', 'type' => 'hard']], $failures);
    }

    public function test_message_send_id_is_found_in_quoted_printable_originals(): void
    {
        $id = '019d6d6a-7303-71e5-b451-c303271926a5';
        $quotedPrintable = '<img src=3D"https://news.example.com/track/open/019d6d6a-7303-71e5-b4=
51-c303271926a5" width=3D"1">';

        $this->assertSame($id, $this->detector->extractMessageSendIdFromContent($quotedPrintable));
    }
}
