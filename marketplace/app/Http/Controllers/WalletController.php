<?php

namespace App\Http\Controllers;

use App\Models\WalletLedgerEntry;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function __invoke(Request $request, WalletService $wallets): View
    {
        $wallet = $wallets->walletFor($request->user())->refresh();

        return view('wallet.index', [
            'wallet' => $wallet,
            'entries' => WalletLedgerEntry::query()
                ->where('wallet_id', $wallet->id)
                ->with('transaction')
                ->latest('id')
                ->paginate(20),
        ]);
    }
}
