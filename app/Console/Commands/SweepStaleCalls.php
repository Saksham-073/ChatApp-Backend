<?php

namespace App\Console\Commands;

use App\Events\CallEnded;
use App\Events\CallMissed;
use App\Models\Call;
use Illuminate\Console\Command;

class SweepStaleCalls extends Command
{
    protected $signature = 'calls:sweep';

    protected $description = 'Mark stale ringing calls missed and stale ongoing calls ended';

    public function handle(): int
    {
        Call::where('status', 'ringing')
            ->where('created_at', '<', now()->subSeconds(60))
            ->each(function (Call $call) {
                $call->update(['status' => 'missed', 'ended_at' => now()]);
                broadcast(new CallMissed($call));
                broadcast(new CallEnded($call));
            });

        Call::where('status', 'ongoing')
            ->where(function ($q) {
                $q->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', now()->subSeconds(90));
            })
            ->each(function (Call $call) {
                $call->update(['status' => 'ended', 'ended_at' => now()]);
                broadcast(new CallEnded($call));
            });

        return self::SUCCESS;
    }
}
