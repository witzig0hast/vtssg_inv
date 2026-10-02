<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\MaintenanceTicket;
use App\Models\StatusLabel;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $q = MaintenanceTicket::query()->with(['asset.model', 'assignee', 'reporter']);
        $status = $request->query('status', 'active');
        match ($status) {
            'active' => $q->whereIn('status', ['open', 'in_progress', 'waiting_parts']),
            'all' => null,
            default => $q->where('status', $status),
        };
        if ($p = $request->query('priority')) $q->where('priority', $p);

        return view('maintenance.index', ['tickets' => $q->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")->latest()->paginate(25)->withQueryString()]);
    }

    public function create(Request $request)
    {
        return view('maintenance.form', ['asset' => $request->filled('asset') ? Asset::with('model')->find($request->integer('asset')) : null, 'users' => User::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_tag' => ['required', 'exists:assets,asset_tag'], 'title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', 'in:low,normal,high,critical'], 'assigned_to' => ['nullable', 'exists:users,id'], 'blocks_asset' => ['nullable', 'boolean'],
        ]);
        $asset = Asset::where('asset_tag', $data['asset_tag'])->firstOrFail();
        $block = $request->boolean('blocks_asset');
        $ticket = MaintenanceTicket::create([
            'asset_id' => $asset->id, 'reported_by' => $request->user()->id, 'assigned_to' => $data['assigned_to'] ?? null,
            'title' => $data['title'], 'description' => $data['description'] ?? null, 'priority' => $data['priority'], 'blocks_asset' => $block,
        ]);
        if ($block && ($label = StatusLabel::where('kind', 'undeployable')->first())) {
            $asset->update(['status_label_id' => $label->id]);
        }

        return redirect()->route('maintenance.show', $ticket)->with('status', 'Ticket erstellt.');
    }

    public function show(MaintenanceTicket $ticket)
    {
        return view('maintenance.show', [
            't' => $ticket->load(['asset.model', 'asset.status', 'reporter', 'assignee', 'comments.user', 'attachments.uploader']),
            'users' => User::where('is_active', true)->orderBy('name')->get(),
            'statuses' => StatusLabel::where('kind', 'deployable')->get(),
        ]);
    }

    public function update(Request $request, MaintenanceTicket $ticket)
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,in_progress,waiting_parts,done,cancelled'], 'priority' => ['required', 'in:low,normal,high,critical'],
            'assigned_to' => ['nullable', 'exists:users,id'], 'cost' => ['nullable', 'string', 'max:20'], 'release_asset' => ['nullable', 'boolean'],
        ]);
        $closing = in_array($data['status'], ['done', 'cancelled'], true);
        $ticket->update([
            'status' => $data['status'], 'priority' => $data['priority'], 'assigned_to' => $data['assigned_to'] ?? null,
            'cost_cents' => Money::toCents($data['cost'] ?? null), 'closed_at' => $closing ? ($ticket->closed_at ?? now()) : null,
        ]);
        // Nach Abschluss Gerät wieder freigeben (nur wenn keine weiteren offenen blockierenden Tickets existieren)
        if ($closing && $request->boolean('release_asset')) {
            $stillBlocked = $ticket->asset->tickets()->where('id', '!=', $ticket->id)->where('blocks_asset', true)->whereIn('status', ['open', 'in_progress', 'waiting_parts'])->exists();
            $default = StatusLabel::where('is_default', true)->first();
            if (! $stillBlocked && $default) {
                $ticket->asset->update(['status_label_id' => $default->id]);
            }
        }

        return back()->with('status', 'Ticket aktualisiert.');
    }

    public function comment(Request $request, MaintenanceTicket $ticket)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        TicketComment::create(['maintenance_ticket_id' => $ticket->id, 'user_id' => $request->user()->id, 'body' => $data['body']]);

        return back()->with('status', 'Kommentar gespeichert.');
    }
}
