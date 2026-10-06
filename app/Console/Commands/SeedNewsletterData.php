<?php

namespace App\Console\Commands;

use App\Enums\MessageStatus;
use App\Enums\SubscriberStatus;
use App\Enums\UserRole;
use App\Models\Bounce;
use App\Models\Campaign;
use App\Models\Message;
use App\Models\MessageClick;
use App\Models\MessageOpen;
use App\Models\MessageSend;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Template;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

class SeedNewsletterData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newsletter:seed-data
                            {--subscribers=400 : Number of demo subscribers to create}
                            {--force : Allow seeding demo data in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed a realistic demo dataset: subscribers, tags, templates, campaigns and sent messages with opens, clicks, bounces and unsubscribes';

    /**
     * @var list<string>
     */
    private const FIRST_NAMES = [
        'Ada', 'Alan', 'Alice', 'Amara', 'Andrea', 'Ben', 'Bianca', 'Carlos', 'Chloe', 'Daniel',
        'Diego', 'Elena', 'Emma', 'Ethan', 'Fatima', 'Felix', 'Giulia', 'Hannah', 'Hugo', 'Ines',
        'Isaac', 'Jonas', 'Julia', 'Kai', 'Lara', 'Leo', 'Lucia', 'Marco', 'Maya', 'Mila',
        'Noah', 'Nora', 'Omar', 'Paula', 'Rafael', 'Sara', 'Sofia', 'Theo', 'Valentina', 'Yuki',
    ];

    /**
     * @var list<string>
     */
    private const LAST_NAMES = [
        'Adams', 'Bauer', 'Bianchi', 'Brown', 'Costa', 'Dubois', 'Esposito', 'Fischer', 'Garcia', 'Greco',
        'Hansen', 'Ito', 'Jensen', 'Keller', 'Kim', 'Lambert', 'Lopez', 'Martin', 'Meyer', 'Moreau',
        'Nakamura', 'Nielsen', 'Novak', 'Oliveira', 'Park', 'Petit', 'Quinn', 'Ricci', 'Rossi', 'Santos',
        'Schmidt', 'Silva', 'Smith', 'Suzuki', 'Taylor', 'Wagner', 'Walker', 'Weber', 'Wilson', 'Young',
    ];

    /**
     * Reserved documentation domains (RFC 2606): demo data can never reach a real inbox.
     *
     * @var list<string>
     */
    private const DOMAINS = ['example.com', 'example.org', 'example.net'];

    /**
     * @var list<string>
     */
    private const USER_AGENTS = [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko)',
        'Mozilla/5.0 (Windows NT 5.1; rv:11.0) Gecko Firefox/11.0 (via ggpht.com GoogleImageProxy)',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148',
        'Microsoft Office/16.0 (Windows NT 10.0; Microsoft Outlook 16.0.17928; Pro)',
    ];

    private Randomizer $random;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to seed demo data in production. Re-run with --force if you really mean it.');

            return self::FAILURE;
        }

        // Fixed seed: every run produces the same dataset (handy for docs and screenshots).
        $this->random = new Randomizer(new Mt19937(2026));

        $this->info('Seeding newsletter demo data...');

        DB::transaction(function (): void {
            $admin = $this->seedUsers();
            $tags = $this->seedTags();
            $subscribers = $this->seedSubscribers($tags, max(1, (int) $this->option('subscribers')));
            $templates = $this->seedTemplates();
            $campaigns = $this->seedCampaigns($admin);

            $this->seedMessages($campaigns, $templates, $tags, $subscribers);
        });

        $this->newLine();
        $this->table(['Records', 'Count'], [
            ['Users', User::count()],
            ['Subscribers', Subscriber::count()],
            ['Tags', Tag::count()],
            ['Templates', Template::count()],
            ['Campaigns', Campaign::count()],
            ['Messages', Message::count()],
            ['Message sends', MessageSend::count()],
            ['Opens', MessageOpen::count()],
            ['Clicks', MessageClick::count()],
            ['Bounces', Bounce::count()],
        ]);

        $this->info('Login: admin@newsletter.test / password');

        return self::SUCCESS;
    }

    private function seedUsers(): User
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@newsletter.test'],
            ['name' => 'Admin', 'password' => 'password', 'role' => UserRole::Administrator, 'locale' => 'en'],
        );

        // Extra accounts only populate the Users screen; their passwords are random and never shown.
        foreach ([
            ['Giulia Rossi', 'manager@newsletter.test', UserRole::Manager],
            ['Marco Bianchi', 'editor@newsletter.test', UserRole::Editor],
        ] as [$name, $email, $role]) {
            User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Str::password(32), 'role' => $role, 'locale' => 'en'],
            );
        }

        $this->line('  Users ready (admin is an Administrator)');

        return $admin;
    }

    /**
     * @return array<string, Tag>
     */
    private function seedTags(): array
    {
        $tags = [];

        foreach (['Customers' => false, 'Prospects' => false, 'Partners' => false, 'Webinar 2026' => false, 'QA team' => true] as $name => $isTesting) {
            $tags[$name] = Tag::firstOrCreate(['name' => $name], ['is_testing' => $isTesting]);
        }

        $this->line('  Tags ready');

        return $tags;
    }

    /**
     * @param  array<string, Tag>  $tags
     * @return Collection<int, array{subscriber: Subscriber, fate: string, engagement: float}>
     */
    private function seedSubscribers(array $tags, int $count): Collection
    {
        $pairs = [];
        foreach (self::FIRST_NAMES as $first) {
            foreach (self::LAST_NAMES as $last) {
                $pairs[] = [$first, $last];
            }
        }
        $pairs = array_slice($this->random->shuffleArray($pairs), 0, min($count, count($pairs)));

        $people = collect();

        foreach ($pairs as $index => [$first, $last]) {
            $roll = $this->chance();
            $fate = match (true) {
                $roll < 0.82 => 'confirmed',
                $roll < 0.88 => 'pending',
                $roll < 0.96 => 'unsubscribed',
                default => 'bounced',
            };

            // Growth curve: more recent signups than old ones.
            $joinedAt = now()->subMinutes((int) (200 * 1440 * $this->chance() ** 2) + 60);

            $subscriber = Subscriber::firstOrCreate(
                ['email' => strtolower("{$first}.{$last}@".self::DOMAINS[$index % count(self::DOMAINS)])],
                [
                    'name' => "{$first} {$last}",
                    'status' => $fate === 'pending' ? SubscriberStatus::Pending : SubscriberStatus::Confirmed,
                    'confirmation_token' => $fate === 'pending' ? Str::random(64) : null,
                    'confirmed_at' => $fate === 'pending' ? null : $joinedAt,
                ],
            );

            if ($subscriber->wasRecentlyCreated) {
                $subscriber->forceFill(['created_at' => $joinedAt, 'updated_at' => $joinedAt])->save();
            }

            $tagIds = [$this->chance() < 0.55 ? $tags['Customers']->id : $tags['Prospects']->id];
            if ($this->chance() < 0.12) {
                $tagIds[] = $tags['Partners']->id;
            }
            if ($this->chance() < 0.22) {
                $tagIds[] = $tags['Webinar 2026']->id;
            }
            $subscriber->tags()->syncWithoutDetaching($tagIds);
            $subscriber->load('tags');

            $people->push([
                'subscriber' => $subscriber,
                'fate' => $fate,
                'engagement' => 0.25 + $this->chance() * 0.75,
            ]);
        }

        foreach (['Alice QA', 'Ben QA', 'Chloe QA'] as $name) {
            $qa = Subscriber::firstOrCreate(
                ['email' => Str::slug($name, '.').'@example.com'],
                ['name' => $name, 'status' => SubscriberStatus::Confirmed, 'confirmed_at' => now()->subMonths(6)],
            );
            $qa->tags()->syncWithoutDetaching([$tags['QA team']->id]);
        }

        $this->line("  {$people->count()} subscribers ready (+3 QA testers)");

        return $people;
    }

    /**
     * @return array<string, Template>
     */
    private function seedTemplates(): array
    {
        $templates = [
            'Monthly digest' => $this->templateHtml('#1d6fd8', 'Acme Monthly', 'The stories, releases and tips worth your time.'),
            'Product announcement' => $this->templateHtml('#0f766e', 'Acme Launches', 'Something new just shipped.'),
            'Plain onboarding' => $this->templateHtml('#334155', 'Acme', 'Getting started, one step at a time.'),
        ];

        foreach ($templates as $name => $html) {
            $templates[$name] = Template::firstOrCreate(
                ['name' => $name],
                ['html_content' => $html, 'placeholders' => ['name', 'email', 'unsubscribe_url', 'body']],
            );
        }

        $this->line('  Templates ready');

        return $templates;
    }

    /**
     * @return array<string, Campaign>
     */
    private function seedCampaigns(User $owner): array
    {
        $campaigns = [];

        foreach ([
            'Monthly Digest' => 'News, releases and highlights sent on the first Tuesday of every month.',
            'Product Launches' => 'Announcements for new features and partner programs.',
            'Onboarding' => 'Welcome sequence for new prospects and trial users.',
        ] as $name => $description) {
            $campaigns[$name] = Campaign::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $description, 'user_id' => $owner->id],
            );
        }

        $this->line('  Campaigns ready');

        return $campaigns;
    }

    /**
     * @param  array<string, Campaign>  $campaigns
     * @param  array<string, Template>  $templates
     * @param  array<string, Tag>  $tags
     * @param  Collection<int, array{subscriber: Subscriber, fate: string, engagement: float}>  $people
     */
    private function seedMessages(array $campaigns, array $templates, array $tags, Collection $people): void
    {
        $plan = [
            ['July digest: what we shipped this summer', 'Monthly Digest', 'Monthly digest', [], [], 72, 0.46],
            ['August digest: faster exports, smarter segments', 'Monthly Digest', 'Monthly digest', [], [], 41, 0.43],
            ['September digest: the reporting issue', 'Monthly Digest', 'Monthly digest', [], [], 27, 0.48],
            ['Introducing Smart Segments', 'Product Launches', 'Product announcement', ['Customers'], ['Partners'], 13, 0.52],
            ['Partner program: early access to the AI assistant', 'Product Launches', 'Product announcement', ['Partners'], [], 6, 0.61],
            ['Welcome aboard: your first three steps', 'Onboarding', 'Plain onboarding', ['Prospects'], [], 2, 0.39],
        ];

        $unsubscribeAfter = [];

        foreach ($plan as [$subject, $campaign, $template, $include, $exclude, $daysAgo, $openRate]) {
            $sentAt = now()->subDays($daysAgo)->setTime(9, 30);
            $message = $this->message($subject, $campaigns[$campaign], $templates[$template], $tags, $include, $exclude, [
                'status' => MessageStatus::Sent,
                'scheduled_at' => $sentAt,
                'sent_at' => $sentAt->copy()->addMinutes(18),
            ]);

            // Re-runs keep the existing history instead of adding sends to people who already left.
            if (! $message->wasRecentlyCreated) {
                continue;
            }

            $includedIds = collect($include)->map(fn (string $tag): string => $tags[$tag]->id);
            $excludedIds = collect($exclude)->map(fn (string $tag): string => $tags[$tag]->id);

            $audience = $people
                ->filter(fn (array $person): bool => $person['fate'] !== 'pending'
                    && $person['subscriber']->confirmed_at?->lt($sentAt)
                    && ! isset($unsubscribeAfter[$person['subscriber']->id]))
                ->filter(function (array $person) use ($includedIds, $excludedIds): bool {
                    $subscriberTagIds = $person['subscriber']->tags->modelKeys();

                    return ($includedIds->isEmpty() || $includedIds->intersect($subscriberTagIds)->isNotEmpty())
                        && $excludedIds->intersect($subscriberTagIds)->isEmpty();
                })
                ->values();

            foreach ($audience as $position => $person) {
                $subscriber = $person['subscriber'];
                $send = MessageSend::firstOrCreate(
                    ['message_id' => $message->id, 'subscriber_id' => $subscriber->id],
                    ['sent_at' => $sentAt->copy()->addSeconds($position * 3)],
                );

                if (! $send->wasRecentlyCreated) {
                    continue;
                }

                if ($person['fate'] === 'bounced') {
                    $this->bounce($send, $subscriber);
                    $unsubscribeAfter[$subscriber->id] = true;

                    continue;
                }

                if ($position % 97 === 13) {
                    $send->update([
                        'sent_at' => null,
                        'failed_at' => $send->sent_at,
                        'error_message' => 'Expected response code "250" but got code "421", with message "421 4.7.0 Try again later".',
                    ]);

                    continue;
                }

                $this->engage($send, $person['engagement'] * $openRate * 1.6);

                if ($person['fate'] === 'unsubscribed' && $this->chance() < 0.45) {
                    $subscriber->update([
                        'status' => SubscriberStatus::Unsubscribed,
                        'unsubscribed_at' => $send->sent_at->copy()->addHours($this->random->getInt(1, 30)),
                        'unsubscribed_from_message_id' => $message->id,
                    ]);
                    $unsubscribeAfter[$subscriber->id] = true;
                }
            }
        }

        // Subscribers who were meant to leave but never received a message unsubscribe from the form.
        $people
            ->filter(fn (array $person): bool => in_array($person['fate'], ['unsubscribed', 'bounced'], true)
                && $person['subscriber']->status === SubscriberStatus::Confirmed)
            ->each(fn (array $person) => $person['subscriber']->update($person['fate'] === 'bounced'
                ? ['status' => SubscriberStatus::Bounced]
                : ['status' => SubscriberStatus::Unsubscribed, 'unsubscribed_at' => now()->subHours($this->random->getInt(2, 200))]));

        $this->message('Live webinar: AI-ready newsletters with Laravel', $campaigns['Product Launches'], $templates['Product announcement'], $tags, ['Prospects', 'Webinar 2026'], ['Customers'], [
            'status' => MessageStatus::Ready,
            'scheduled_at' => now()->addDays(3)->setTime(16, 0),
        ]);

        $this->message('October digest: building in public', $campaigns['Monthly Digest'], $templates['Monthly digest'], $tags, [], ['Partners'], [
            'status' => MessageStatus::Draft,
        ]);

        $this->message('[QA] Template smoke test', $campaigns['Onboarding'], $templates['Plain onboarding'], $tags, ['QA team'], [], [
            'status' => MessageStatus::Draft,
        ]);

        $this->line('  Messages, sends and engagement ready');
    }

    /**
     * @param  array<string, Tag>  $tags
     * @param  list<string>  $include
     * @param  list<string>  $exclude
     * @param  array<string, mixed>  $attributes
     */
    private function message(string $subject, Campaign $campaign, Template $template, array $tags, array $include, array $exclude, array $attributes): Message
    {
        $message = Message::firstOrCreate(
            ['subject' => $subject],
            [
                'campaign_id' => $campaign->id,
                'template_id' => $template->id,
                'html_content' => $this->messageBody($subject),
                ...$attributes,
            ],
        );

        if ($message->wasRecentlyCreated) {
            $message->tags()->sync(collect($include)->map(fn (string $tag): string => $tags[$tag]->id));
            $message->excludedTags()->sync(collect($exclude)->map(fn (string $tag): string => $tags[$tag]->id));
        }

        return $message;
    }

    private function engage(MessageSend $send, float $openProbability): void
    {
        if ($this->chance() >= min(0.95, $openProbability)) {
            return;
        }

        // Most opens happen in the first hours after delivery.
        $openedAt = $send->sent_at->copy()->addMinutes((int) (2880 * $this->chance() ** 3) + 2);
        $openedAt = $openedAt->isFuture() ? now()->subMinutes(5) : $openedAt;

        MessageOpen::create([
            'message_send_id' => $send->id,
            'opened_at' => $openedAt,
            'ip_address' => '203.0.113.'.$this->random->getInt(1, 254),
            'user_agent' => self::USER_AGENTS[$this->random->getInt(0, count(self::USER_AGENTS) - 1)],
        ]);

        $clicks = 0;
        foreach (['https://example.com/blog', 'https://example.com/pricing'] as $index => $url) {
            if ($this->chance() < ($index === 0 ? 0.24 : 0.08)) {
                MessageClick::create([
                    'message_send_id' => $send->id,
                    'url' => $url,
                    'clicked_at' => $openedAt->copy()->addSeconds($this->random->getInt(5, 600)),
                    'ip_address' => '203.0.113.'.$this->random->getInt(1, 254),
                    'user_agent' => self::USER_AGENTS[0],
                ]);
                $clicks++;
            }
        }

        $send->update(['opens_count' => 1, 'clicks_count' => $clicks]);
    }

    private function bounce(MessageSend $send, Subscriber $subscriber): void
    {
        Bounce::create([
            'message_send_id' => $send->id,
            'email' => $subscriber->email,
            'type' => 'hard',
            'raw_message' => "<{$subscriber->email}>: host mx.example.net said: 550 5.1.1 Recipient address rejected: User unknown",
            'detected_at' => $send->sent_at->copy()->addMinutes(2),
        ]);

        $subscriber->update(['status' => SubscriberStatus::Bounced]);
    }

    private function chance(): float
    {
        return $this->random->getFloat(0, 1);
    }

    private function messageBody(string $subject): string
    {
        $headline = e(Str::of($subject)->after(': ')->ucfirst()->toString());

        return <<<HTML
<h2>{$headline}</h2>
<p>Here is a short summary of what changed and why it matters for your team. We kept it brief: the details are one click away.</p>
<ul>
<li><strong>Faster workflows</strong>: fewer steps from draft to delivery.</li>
<li><strong>Clearer numbers</strong>: opens, clicks and unsubscribes per message.</li>
<li><strong>Better targeting</strong>: include and exclude audiences with tags.</li>
</ul>
<p><a href="https://example.com/blog">Read the full story</a> or <a href="https://example.com/pricing">compare plans</a>.</p>
<p>Thanks for reading,<br>The Acme team</p>
HTML;
    }

    private function templateHtml(string $accent, string $brand, string $tagline): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$brand}</title>
</head>
<body style="margin:0;padding:0;background:#eef2f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;padding:32px 12px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(15,23,42,0.08);">
<tr><td style="background:{$accent};padding:28px 36px;color:#ffffff;">
<div style="font-size:13px;letter-spacing:0.12em;text-transform:uppercase;opacity:0.85;">{$brand}</div>
<div style="font-size:15px;margin-top:6px;opacity:0.92;">{$tagline}</div>
</td></tr>
<tr><td style="padding:32px 36px 8px;font-size:16px;line-height:1.6;">
<p style="margin:0 0 16px;">Hi {{name}},</p>
{{body}}
</td></tr>
<tr><td style="padding:24px 36px 32px;font-size:12px;line-height:1.5;color:#6b7280;border-top:1px solid #e5e7eb;">
You are receiving this email at {{email}} because you subscribed to {$brand}.<br>
<a href="{{unsubscribe_url}}" style="color:{$accent};">Unsubscribe</a>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }
}
