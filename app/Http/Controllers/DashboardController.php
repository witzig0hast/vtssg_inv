<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Checkout;
use App\Models\Consumable;
use App\Models\MaintenanceTicket;
use App\Models\Project;
use App\Models\Setting;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $warn = (int) Setting::get('inspection_warn_days', 30);

        return view('dashboard', [
            'stats' => [
                'assets' => Asset::count(),
                'out' => Checkout::whereNull('returned_at')->distinct('asset_id')->count('asset_id'),
                'overdue_inspections' => Asset::whereDate('next_inspection_due', '<', today())->count(),
                'soon_inspections' => Asset::whereBetween('next_inspection_due', [today(), today()->addDays($warn)])->count(),
                'open_tickets' => MaintenanceTicket::whereIn('status', ['open', 'in_progress', 'waiting_parts'])->count(),
                'overdue_returns' => Checkout::whereNull('returned_at')->where('expected_return_at', '<', now())->count(),
            ],
            'warn' => $warn,
            'upcoming' => Project::with('customer')->whereIn('status', Project::RESERVING)->where(fn ($q) => $q->where('setup_at', '>=', now()->subDay())->orWhere('starts_at', '>=', now()->subDay()))
                ->orderByRaw('COALESCE(setup_at, starts_at)')->limit(6)->get(),
            'toPack' => Project::whereIn('status', ['planned', 'confirmed'])->whereBetween('setup_at', [now()->startOfDay(), now()->addDays(3)])->orderBy('setup_at')->get(),
            'dueAssets' => Asset::with('model')->whereNotNull('next_inspection_due')->whereDate('next_inspection_due', '<=', today()->addDays($warn))->orderBy('next_inspection_due')->limit(8)->get(),
            'tickets' => MaintenanceTicket::with('asset.model')->whereIn('status', ['open', 'in_progress', 'waiting_parts'])->latest()->limit(6)->get(),
            'lowStock' => Consumable::whereColumn('quantity', '<=', 'min_quantity')->where('min_quantity', '>', 0)->orderBy('name')->limit(6)->get(),
            'overdueReturns' => Checkout::with('asset.model')->whereNull('returned_at')->where('expected_return_at', '<', now())->orderBy('expected_return_at')->limit(6)->get(),
        ]);
    }
}
