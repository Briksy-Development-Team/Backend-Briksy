<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Console\Scheduling\Schedule;
use App\Models\AuctionCalendarEvent;
use App\Services\NotificationService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('briksy:sync-admin-role {email=admin@example.com} {--strip-super-admin : Remove the super_admin role if present}', function () {
    $email = (string) $this->argument('email');
    $stripSuperAdmin = (bool) $this->option('strip-super-admin');

    $user = User::query()->where('email', $email)->first();

    if (!$user) {
        $this->error("User not found: {$email}");

        return self::FAILURE;
    }

    $adminRole = Role::query()->firstOrCreate(
        ['name' => 'admin'],
        ['scope' => 'global', 'is_system' => true]
    );

    $superAdminRole = Role::query()->firstOrCreate(
        ['name' => 'super_admin'],
        ['scope' => 'global', 'is_system' => true]
    );

    DB::transaction(function () use ($user, $adminRole, $superAdminRole, $stripSuperAdmin): void {
        $user->roles()->syncWithoutDetaching([
            $adminRole->id => [
                'id' => (string) Str::uuid(),
                'organization_id' => $user->organization_id,
            ],
        ]);

        if ($stripSuperAdmin) {
            $user->roles()->detach($superAdminRole->id);
        }
    });

    $user->load('roles');

    $this->info(sprintf(
        'Normalized %s. Roles now: %s',
        $email,
        $user->roles->pluck('name')->join(', ')
    ));

    return self::SUCCESS;
})->purpose('Normalize a user to the admin role and optionally remove super_admin.');

Artisan::command('briksy:send-auction-reminders', function (NotificationService $notifications) {
    $reminders = ['P-7D' => 7, 'P-3D' => 3, 'P-1D' => 1, 'PT0S' => 0];
    $sent = 0;

    AuctionCalendarEvent::query()->with(['propertyListing', 'propertyListing.organization'])
        ->whereBetween('starts_at', [now(), now()->addDays(8)])
        ->chunkById(100, function ($events) use ($reminders, $notifications, &$sent): void {
            foreach ($events as $event) {
                foreach ($reminders as $code => $days) {
                    $target = $event->starts_at->copy()->subDays($days);
                    if ($target->between(now()->subMinute(), now())) {
                        $notifications->notifyUser(
                            User::query()->findOrFail($event->user_id),
                            $notifications->buildPayload(
                                'auction_reminder',
                                $days === 0 ? 'Auction starts today' : "Auction reminder: {$days} day" . ($days === 1 ? '' : 's') . ' to go',
                                "{$event->title} is scheduled for {$event->starts_at->timezone(config('app.timezone'))->format('j M Y, g:i A')}.",
                                'auction_calendar_event',
                                $event->id,
                                '/property/' . $event->property_listing_id,
                            ),
                            'Auction reminder: ' . $event->title,
                            'View auction'
                        );
                        $sent++;
                    }
                }
            }
        });

    $this->info("Processed {$sent} auction reminder(s).");
})->purpose('Send scheduled Briksy auction calendar reminders.');

app(Schedule::class)->command('briksy:send-auction-reminders')->everyMinute();
