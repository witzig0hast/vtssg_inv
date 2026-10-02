<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IcalController extends Controller
{
    /** Abo-URL für Kalender-Apps; der Token ersetzt die Anmeldung (nur Lesezugriff auf Projekttermine). */
    public function feed(string $token)
    {
        $user = User::where('ical_token', $token)->where('is_active', true)->first();
        abort_unless($user && $user->can('projects.view'), 404);

        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//VTSSG//Inventar//DE', 'CALSCALE:GREGORIAN', 'X-WR-CALNAME:Projekte', 'X-WR-TIMEZONE:Europe/Berlin'];
        foreach (Project::with('customer')->whereNotIn('status', ['cancelled', 'draft'])->where(fn ($q) => $q->whereNotNull('setup_at')->orWhereNotNull('starts_at'))->orderBy('starts_at')->limit(500)->get() as $p) {
            $start = $p->windowStart();
            $end = $p->windowEnd() ?? $start->copy()->addHours(2);
            $lines = array_merge($lines, [
                'BEGIN:VEVENT',
                'UID:project-'.$p->id.'@vtssg-inv02',
                'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
                'DTSTART:'.$start->copy()->utc()->format('Ymd\THis\Z'),
                'DTEND:'.$end->copy()->utc()->format('Ymd\THis\Z'),
                'SUMMARY:'.$this->esc($p->number.' '.$p->name),
                'LOCATION:'.$this->esc((string) $p->venue),
                'DESCRIPTION:'.$this->esc(trim(($p->customer?->name ?? '')."\nStatus: ".Project::STATUSES[$p->status].($p->starts_at ? "\nEinsatz: ".$p->starts_at->format('d.m.Y H:i') : ''))),
                'END:VEVENT',
            ]);
        }
        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", array_map([$this, 'fold'], $lines))."\r\n", 200, ['Content-Type' => 'text/calendar; charset=utf-8', 'Cache-Control' => 'private, max-age=300', 'X-Robots-Tag' => 'noindex']);
    }

    public function regenerate(Request $request)
    {
        $request->user()->forceFill(['ical_token' => Str::random(48)])->save();

        return back()->with('status', 'Neuer Kalender-Link erzeugt (alter Link ist ungültig).');
    }

    public function revoke(Request $request)
    {
        $request->user()->forceFill(['ical_token' => null])->save();

        return back()->with('status', 'Kalender-Link widerrufen.');
    }

    private function esc(string $s): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $s);
    }

    private function fold(string $l): string
    {
        return mb_strlen($l, '8bit') <= 73 ? $l : implode("\r\n ", mb_str_split($l, 70, 'UTF-8'));
    }
}
