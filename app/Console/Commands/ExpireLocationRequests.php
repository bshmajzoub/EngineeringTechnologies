<?php

namespace App\Console\Commands;

use App\Enums\LocationRequestStatus;
use App\Models\LocationRequest;
use Illuminate\Console\Command;

class ExpireLocationRequests extends Command
{
    protected $signature = 'location:expire-requests';

    protected $description = 'Mark active location requests as expired if their time has passed';

    public function handle(): int
    {
        // Single atomic UPDATE — eliminates the TOCTOU race between the
        // previous SELECT + separate UPDATE. DB::affectedRows() via the
        // Eloquent builder gives us the precise count without a second query.
        $expiredCount = LocationRequest::query()
            ->where('status', LocationRequestStatus::Active)
            ->where('expires_at', '<=', now())
            ->update(['status' => LocationRequestStatus::Expired]);

        if ($expiredCount > 0) {
            $this->info("Expired {$expiredCount} location request(s).");
        }

        return self::SUCCESS;
    }
}
