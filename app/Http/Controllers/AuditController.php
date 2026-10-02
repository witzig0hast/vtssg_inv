<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $q = AuditLog::query()->with('user');
        if ($v = $request->query('event')) $q->where('event', $v);
        if ($v = $request->query('user')) $q->where('user_id', $v);
        if ($v = $request->query('q')) $q->where('summary', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $v).'%');
        if ($v = $request->query('from')) $q->whereDate('created_at', '>=', $v);
        if ($v = $request->query('to')) $q->whereDate('created_at', '<=', $v);

        return view('audit.index', [
            'logs' => $q->orderByDesc('id')->paginate(40)->withQueryString(),
            'events' => AuditLog::query()->select('event')->distinct()->orderBy('event')->pluck('event'),
            'users' => User::withTrashed()->orderBy('name')->get(['id', 'name']),
            'verify' => $request->boolean('verify') ? AuditLog::verifyChain() : null,
        ]);
    }
}
