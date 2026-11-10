<?php

namespace App\Console\Commands;

use App\Services\Notifications\AnnouncementService;
use Illuminate\Console\Command;

class PublishScheduledAnnouncements extends Command
{
    protected $signature = 'announcements:publish-due';

    protected $description = 'Publish scheduled announcements whose publication time has passed';

    public function handle(AnnouncementService $service): int
    {
        $count = $service->publishDue();

        if ($count > 0) {
            $this->info("Published {$count} scheduled announcement(s).");
        } else {
            $this->info('No scheduled announcements are due.');
        }

        return self::SUCCESS;
    }
}
