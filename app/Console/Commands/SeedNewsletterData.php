<?php

namespace App\Console\Commands;

use App\Enums\MessageStatus;
use App\Enums\SubscriberStatus;
use App\Models\Campaign;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Template;
use App\Models\User;
use Illuminate\Console\Command;

class SeedNewsletterData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newsletter:seed-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed newsletter system with sample data including sent messages';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🌱 Seeding newsletter data...');

        // Ensure admin user exists
        $user = User::firstOrCreate(
            ['email' => 'admin@newsletter.test'],
            ['name' => 'Admin', 'password' => bcrypt('password')]
        );
        $this->info('✅ Admin user: '.$user->email);

        // Create tags
        $clientiTag = Tag::firstOrCreate(['name' => 'Clienti']);
        $fornitoriTag = Tag::firstOrCreate(['name' => 'Fornitori']);
        $partnerTag = Tag::firstOrCreate(['name' => 'Partner']);
        $this->info('✅ Created 3 tags');

        // Create subscribers
        $subscribers = [];
        for ($i = 1; $i <= 10; $i++) {
            $subscriber = Subscriber::firstOrCreate(
                ['email' => "subscriber{$i}@example.com"],
                [
                    'name' => "Subscriber {$i}",
                    'status' => SubscriberStatus::Confirmed,
                    'confirmed_at' => now(),
                ]
            );
            $subscribers[] = $subscriber;

            // Assign random tags
            if ($i <= 5) {
                $subscriber->tags()->syncWithoutDetaching([$clientiTag->id]);
            }
            if ($i >= 3 && $i <= 7) {
                $subscriber->tags()->syncWithoutDetaching([$fornitoriTag->id]);
            }
            if ($i >= 8) {
                $subscriber->tags()->syncWithoutDetaching([$partnerTag->id]);
            }
        }
        $this->info('✅ Created 10 subscribers with tags');

        // Create template
        $template = Template::firstOrCreate(
            ['name' => 'Template Base'],
            [
                'html_content' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Newsletter</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
        <h1 style="color: #1a1a1a;">Ciao {{name}}!</h1>
        {{body}}
        <hr style="margin: 30px 0; border: none; border-top: 1px solid #e5e5e5;">
        <p style="font-size: 12px; color: #666; text-align: center;">Grazie per la tua iscrizione alla nostra newsletter!</p>
    </div>
</body>
</html>',
                'placeholders' => ['name', 'email', 'unsubscribe_url', 'body'],
            ]
        );
        $this->info('✅ Created template');

        // Create campaigns
        $campaign1 = Campaign::firstOrCreate(
            ['name' => 'Newsletter Gennaio 2026'],
            ['user_id' => $user->id, 'description' => 'Newsletter mensile di gennaio']
        );

        $campaign2 = Campaign::firstOrCreate(
            ['name' => 'Promo Speciale'],
            ['user_id' => $user->id, 'description' => 'Campagna promozionale']
        );
        $this->info('✅ Created 2 campaigns');

        // Create a sent message
        $sentMessage = Message::firstOrCreate(
            ['subject' => 'Benvenuto nella Newsletter - Gennaio 2026'],
            [
                'campaign_id' => $campaign1->id,
                'template_id' => $template->id,
                'html_content' => '<h2>Benvenuto!</h2>
<p>Grazie per esserti iscritto alla nostra newsletter. Riceverai aggiornamenti mensili sulle nostre novità.</p>
<p>Questa è la prima newsletter del 2026!</p>
<p><a href="https://example.com">Visita il nostro sito</a></p>',
                'status' => MessageStatus::Sent,
                'sent_at' => now()->subDays(2),
            ]
        );

        // Create MessageSends for sent message
        foreach ($subscribers as $subscriber) {
            $messageSend = MessageSend::firstOrCreate(
                ['message_id' => $sentMessage->id, 'subscriber_id' => $subscriber->id],
                ['sent_at' => now()->subDays(2)]
            );
        }
        $this->info('✅ Created sent message with '.count($subscribers).' sends');

        // Create a ready message (not sent yet)
        $readyMessage = Message::firstOrCreate(
            ['subject' => 'Prossima Newsletter - Febbraio 2026'],
            [
                'campaign_id' => $campaign1->id,
                'template_id' => $template->id,
                'html_content' => '<h2>News di Febbraio</h2>
<p>Ecco le novità per questo mese!</p>
<ul>
<li>Novità 1</li>
<li>Novità 2</li>
<li>Novità 3</li>
</ul>',
                'status' => MessageStatus::Ready,
                'scheduled_at' => now()->addWeek(),
            ]
        );
        $this->info('✅ Created scheduled message');

        // Create a draft message
        $draftMessage = Message::firstOrCreate(
            ['subject' => 'Bozza - Newsletter Marzo'],
            [
                'campaign_id' => $campaign2->id,
                'template_id' => $template->id,
                'html_content' => '<h2>In lavorazione...</h2><p>Questa newsletter è ancora in bozza.</p>',
                'status' => MessageStatus::Draft,
            ]
        );
        $this->info('✅ Created draft message');

        $this->newLine();
        $this->info('🎉 Newsletter data seeded successfully!');
        $this->info('📊 Summary:');
        $this->info('  - '.User::count().' users');
        $this->info('  - '.Subscriber::count().' subscribers');
        $this->info('  - '.Tag::count().' tags');
        $this->info('  - '.Template::count().' templates');
        $this->info('  - '.Campaign::count().' campaigns');
        $this->info('  - '.Message::count().' messages');
        $this->info('  - '.MessageSend::count().' message sends');

        $this->newLine();
        $this->info('🌐 Visit https://newsletter.test to see your newsletter system!');
        $this->info('📧 Login: admin@newsletter.test / password');

        return self::SUCCESS;
    }
}
