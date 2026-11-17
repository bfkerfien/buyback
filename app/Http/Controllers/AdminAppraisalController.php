<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAppraisalController extends Controller
{
    public function purge(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $quoteCount = DB::table('appraisal_quotes')->count();
        $itemCount = DB::table('appraisal_quote_items')->count();

        DB::transaction(function (): void {
            // Foreign keys cascade appraisal item and hidden-visibility rows.
            // Runner jobs use nullOnDelete, so their operational records survive.
            DB::table('appraisal_quotes')->delete();
        });

        return redirect()
            ->route('dashboard', ['tab' => 'tab-admin-tools'])
            ->with('status', "Purged {$quoteCount} appraisals and {$itemCount} appraisal item lines. Runner jobs and finance ledger entries were retained.");
    }
    public function purgeArchived(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $archived = DB::table('appraisal_quotes')->whereNotNull('archived_at');
        $quoteCount = (clone $archived)->count();
        $itemCount = DB::table('appraisal_quote_items')
            ->whereIn('appraisal_quote_id', (clone $archived)->select('id'))
            ->count();

        DB::transaction(function () use ($archived): void {
            // Only archived quotes are removed; active and expired non-archived quotes remain.
            // Dependent item/hidden rows cascade and runner-job references are nulled.
            $archived->delete();
        });

        return redirect()
            ->route('dashboard', ['tab' => 'tab-admin-tools'])
            ->with('status', "Purged {$quoteCount} archived appraisals and {$itemCount} appraisal item lines. All non-archived appraisals were retained.");
    }

}
