<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tab = in_array($request->query('tur'), ['denetim', 'yonetici', 'guvenlik'], true) ? $request->query('tur') : 'denetim';

        $records = match ($tab) {
            'yonetici' => DB::table('admin_logs')->leftJoin('users', 'users.id', '=', 'admin_logs.admin_id')
                ->select('admin_logs.*', 'users.username')->orderByDesc('admin_logs.id')->paginate(40)->withQueryString(),
            'guvenlik' => DB::table('security_events')->leftJoin('users', 'users.id', '=', 'security_events.user_id')
                ->select('security_events.*', 'users.username')->orderByDesc('security_events.id')->paginate(40)->withQueryString(),
            default => AuditLog::query()->with('actor:id,username')
                ->when($request->filled('olay'), fn ($q) => $q->where('event', 'like', $request->string('olay').'%'))
                ->latest('id')->paginate(40)->withQueryString(),
        };

        return view('admin.logs', ['tab' => $tab, 'records' => $records]);
    }
}
