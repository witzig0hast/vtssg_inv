<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Consumable;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\InspectionsDue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyDue extends Command
{
    protected $signature = 'inv:notify-due';
    protected $description = 'Sendet eine Übersicht fälliger Prüfungen und Mindestbestände.';

    public function handle(): int
    {
        $warn = (int) Setting::get('inspection_warn_days', 30);
        $overdue = Asset::with('model.manufacturer')->whereDate('next_inspection_due', '<', today())->orderBy('next_inspection_due')->get();
        $soon = Asset::with('model.manufacturer')->whereBetween('next_inspection_due', [today(), today()->addDays($warn)])->orderBy('next_inspection_due')->get();
        $low = Consumable::whereColumn('quantity', '<=', 'min_quantity')->where('min_quantity', '>', 0)->get();

        $late = \App\Models\Checkout::with('asset.model')->whereNull('returned_at')->where('expected_return_at', '<', now())->orderBy('expected_return_at')->get();

        if ($overdue->isEmpty() && $soon->isEmpty() && $low->isEmpty() && $late->isEmpty()) {
            return self::SUCCESS;
        }
        $n = new InspectionsDue($overdue, $soon, $low, $late);
        $users = User::role(['Administrator', 'Lager'])->where('is_active', true)->get();
        Notification::send($users, $n);
        if ($extra = Setting::get('notify_email')) {
            Notification::route('mail', $extra)->notify($n);
        }
        $this->info("Benachrichtigt: {$users->count()} Benutzer.");

        return self::SUCCESS;
    }
}
