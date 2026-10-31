<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\Support\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function __construct(private readonly SupportService $support) {}

    public function index(Request $request): View
    {
        $status = $request->query('durum', 'aktif');

        return view('admin.support.index', [
            'status' => $status,
            'tickets' => SupportTicket::query()
                ->with('user:id,username,email', 'assignee:id,username')
                ->when($status === 'aktif', fn ($q) => $q->whereIn('status', ['open', 'awaiting_staff']))
                ->when(array_key_exists($status, SupportTicket::STATUSES), fn ($q) => $q->where('status', $status))
                ->orderByRaw("case priority when 'high' then 0 else 1 end")
                ->latest('last_reply_at')
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        return view('admin.support.show', ['ticket' => $ticket->load('messages.user', 'user', 'pointPurchase')]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000'], 'attachment' => ['nullable', 'file', 'max:10240']]);

        try {
            $this->support->reply($ticket, $request->user(), $data['body'], true, $request->file('attachment'));
        } catch (MarketplaceException $exception) {
            return back()->withErrors(['body' => $exception->getMessage()]);
        }

        if ($request->boolean('close')) {
            $this->support->close($ticket);
        }

        return back()->with('status', __('Yanıt gönderildi.'));
    }

    public function close(SupportTicket $ticket): RedirectResponse
    {
        $this->support->close($ticket);

        return back()->with('status', __('Talep kapatıldı.'));
    }
}
