<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MetaAdAccount;
use App\Models\MetaAdAccountStatusHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MetaAdAccountStatusHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $items = MetaAdAccountStatusHistory::query()
            ->with(['customer:id,name', 'account:id,meta_account_id,name', 'webhookEvent:id,object,field'])
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('meta_ad_account_id'), fn ($query) => $query->where('meta_ad_account_id', $request->integer('meta_ad_account_id')))
            ->when($request->filled('estado_meta'), function ($query) use ($request) {
                $value = $request->string('estado_meta')->toString();
                $query->where(function ($innerQuery) use ($value) {
                    $innerQuery->where('estado_meta_nuevo', $value)
                        ->orWhere('estado_meta_anterior', $value);
                });
            })
            ->when($request->filled('query_type'), fn ($query) => $query->where('query_type', $request->string('query_type')->toString()))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('consulted_at', '>=', $request->date('from')->toDateString()))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('consulted_at', '<=', $request->date('to')->toDateString()))
            ->orderByDesc('consulted_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('meta.ad_accounts.status_history', [
            'items' => $items,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'accounts' => MetaAdAccount::query()->orderBy('name')->get(['id', 'meta_account_id', 'name']),
            'statuses' => MetaAdAccountStatusHistory::query()
                ->select(['estado_meta_nuevo', 'estado_meta_nuevo_nombre'])
                ->whereNotNull('estado_meta_nuevo')
                ->distinct()
                ->orderBy('estado_meta_nuevo')
                ->get(),
            'queryTypes' => MetaAdAccountStatusHistory::query()
                ->whereNotNull('query_type')
                ->distinct()
                ->orderBy('query_type')
                ->pluck('query_type'),
        ]);
    }
}
