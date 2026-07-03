<?php

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MetaAdAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetaAdAccountController extends Controller
{
    public function index(Request $request)
    {
        $q = MetaAdAccount::with('customer');

        if ($request->filled('customer_id')) {
            $q->where('customer_id', $request->integer('customer_id'));
        }

        if ($request->filled('search')) {
            $s = $request->string('search')->toString();
            $q->where(function ($qq) use ($s) {
                $qq->where('meta_account_id', 'like', "%{$s}%")
                   ->orWhere('name', 'like', "%{$s}%");
            });
        }

        return view('meta.ad_accounts.index', [
            'items' => $q->orderByDesc('id')->paginate(15)->withQueryString(),
            'customers' => Customer::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $selectedCustomerId = $request->filled('customer_id')
            && Customer::query()->whereKey($request->integer('customer_id'))->exists()
                ? $request->integer('customer_id')
                : null;

        return view('meta.ad_accounts.create', [
            'customers' => Customer::orderBy('name')->get(),
            'ad_account' => new MetaAdAccount(['customer_id' => $selectedCustomerId]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'meta_account_id' => ['required','string','max:64', 'unique:meta_ad_accounts,meta_account_id'],
            'name' => ['nullable','string','max:255'],
            'status' => ['required', Rule::in(['active','inactive'])],
        ]);

        MetaAdAccount::create($data);

        return redirect()->route('meta.ad-accounts.index')->with('success', 'Cuenta creada.');
    }

    public function show(MetaAdAccount $ad_account)
    {
        $ad_account->load('customer');
        return view('meta.ad_accounts.show', compact('ad_account'));
    }

    public function edit(MetaAdAccount $ad_account)
    {
        $ad_account->load('customer');

        return view('meta.ad_accounts.edit', [
            'ad_account' => $ad_account,
            'customers' => Customer::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, MetaAdAccount $ad_account)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'meta_account_id' => ['required','string','max:64', Rule::unique('meta_ad_accounts','meta_account_id')->ignore($ad_account->id)],
            'name' => ['nullable','string','max:255'],
            'status' => ['required', Rule::in(['active','inactive'])],
        ]);

        $ad_account->update($data);

        return redirect()->route('meta.ad-accounts.index')->with('success', 'Cuenta actualizada.');
    }

    public function destroy(MetaAdAccount $ad_account)
    {
        $ad_account->delete();
        return redirect()->route('meta.ad-accounts.index')->with('success', 'Cuenta eliminada.');
    }
}
