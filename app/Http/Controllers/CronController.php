<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CronController extends Controller
{
    /**
     * External-cron entry point for scheduled tasks on hosts without a
     * native scheduler (e.g. Render's free tier). Token-gated since it's
     * unauthenticated by design — pinged by an external cron service.
     */
    public function sweepCalls(Request $request)
    {
        $token = $request->query('token', '');
        $expected = (string) config('services.cron.secret');

        abort_if($expected === '', 500, 'CRON_SECRET is not configured.');
        abort_unless(hash_equals($expected, (string) $token), 403);

        Artisan::call('calls:sweep');

        return response()->json(['ok' => true]);
    }
}
