<?php

namespace App\Http\Controllers;

use App\Enums\PointPurchaseStatus;
use App\Exceptions\MarketplaceException;
use App\Models\PointPurchase;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\Support\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportController extends Controller
{
    public function __construct(private readonly SupportService $support) {}

    public function index(Request $request): View
    {
        return view('support.index', [
            'tickets' => SupportTicket::query()->where('user_id', $request->user()->id)->latest('last_reply_at')->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        return view('support.create', [
            'purchases' => PointPurchase::query()->where('user_id', $request->user()->id)->where('status', PointPurchaseStatus::Paid)->latest('paid_at')->get(),
            'category' => $request->query('konu'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:5', 'max:160'],
            'category' => ['required', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'point_purchase_id' => ['required_if:category,refund', 'nullable', Rule::exists('point_purchases', 'id')->where('user_id', $request->user()->id)->where('status', PointPurchaseStatus::Paid->value)],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        try {
            $ticket = $this->support->open($request->user(), $data['subject'], $data['category'], $data['body'], $data['point_purchase_id'] ?? null, $request->file('attachment'));
        } catch (MarketplaceException $exception) {
            return back()->withInput()->withErrors(['body' => $exception->getMessage()]);
        }

        return redirect()->route('support.show', $ticket)->with('status', __('Talebin alındı. En kısa sürede yanıtlayacağız.'));
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);

        return view('support.show', ['ticket' => $ticket->load('messages.user', 'pointPurchase')]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000'], 'attachment' => ['nullable', 'file', 'max:10240']]);

        try {
            $this->support->reply($ticket, $request->user(), $data['body'], false, $request->file('attachment'));
        } catch (MarketplaceException $exception) {
            return back()->withErrors(['body' => $exception->getMessage()]);
        }

        return back()->with('status', __('Mesajın gönderildi.'));
    }

    public function close(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        $this->support->close($ticket);

        return back()->with('status', __('Talep kapatıldı.'));
    }

    public function attachment(Request $request, SupportMessage $message): StreamedResponse
    {
        $ticket = $message->ticket;
        abort_unless($message->attachment_path !== null && ($ticket->user_id === $request->user()->id || $request->user()->can('support.manage')), 404);

        return Storage::disk('private')->download($message->attachment_path, 'ek-'.$message->id.'.'.pathinfo($message->attachment_path, PATHINFO_EXTENSION));
    }
}
