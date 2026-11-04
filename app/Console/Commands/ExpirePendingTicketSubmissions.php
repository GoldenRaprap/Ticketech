<?php

namespace App\Console\Commands;

use App\Models\PendingTicketSubmission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

#[Signature('tickets:expire-pending-email-verifications')]
#[Description('Remove expired unverified ticket submissions and their attachments')]
class ExpirePendingTicketSubmissions extends Command
{
    public function handle(): int
    {
        $removedSubmissions = 0;

        PendingTicketSubmission::query()
            ->where('expires_at', '<=', now())
            ->chunkById(100, function (EloquentCollection $submissions) use (&$removedSubmissions): void {
                foreach ($submissions as $submission) {
                    $disk = Storage::disk('private');

                    foreach ($submission->attachments as $attachment) {
                        if ($disk->exists($attachment['path']) && ! $disk->delete($attachment['path'])) {
                            throw new RuntimeException('Unable to remove an expired pending ticket attachment.');
                        }
                    }

                    $submission->delete();
                    $removedSubmissions++;
                }
            });

        $this->info("Removed {$removedSubmissions} expired unverified ticket submission(s).");

        return self::SUCCESS;
    }
}
