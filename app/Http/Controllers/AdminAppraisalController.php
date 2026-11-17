<?php

namespace App\\Http\\Controllers;

use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;

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
}
