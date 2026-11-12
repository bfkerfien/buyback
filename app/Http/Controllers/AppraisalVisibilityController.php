<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppraisalVisibilityController extends Controller
{
    public function hide(Request $request, int $quote): RedirectResponse
    {
        $user = $request->user();
        $appraisal = DB::table('appraisal_quotes')->where('id', $quote)->first();
        abort_unless($appraisal, 404);
        abort_unless((int) $appraisal->created_by === (int) $user->id, 403);
        abort_if($appraisal->archived_at !== null, 409, 'Archived appraisals cannot be hidden from the player view.');

        DB::table('appraisal_quote_hidden')->insertOrIgnore([
            'appraisal_quote_id' => $quote,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Appraisal hidden from your dashboard. The appraisal data remains available to administrators.');
    }

    public function archive(Request $request, int $quote): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $changed = DB::table('appraisal_quotes')
            ->where('id', $quote)
            ->whereNull('archived_at')
            ->update(['archived_at' => now(), 'archived_by' => $request->user()->id, 'updated_at' => now()]);

        abort_unless($changed === 1, 404, 'Appraisal not found or already archived.');

        return back()->with('status', 'Appraisal moved to the archive.');
    }

    public function restore(Request $request, int $quote): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $changed = DB::table('appraisal_quotes')
            ->where('id', $quote)
            ->whereNotNull('archived_at')
            ->update(['archived_at' => null, 'archived_by' => null, 'updated_at' => now()]);

        abort_unless($changed === 1, 404, 'Archived appraisal not found.');

        return back()->with('status', 'Appraisal restored to the active appraisal list.');
    }
}
