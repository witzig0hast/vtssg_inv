<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class InspectionsDue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Collection $overdue, public Collection $soon, public Collection $lowStock, public Collection $lateReturns = new Collection) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $m = (new MailMessage)->subject('Inventar: '.$this->overdue->count().' Prüfungen überfällig, '.$this->soon->count().' bald fällig')
            ->greeting('Tägliche Inventarübersicht');
        if ($this->overdue->isNotEmpty()) {
            $m->line('**Überfällige Prüfungen (Ausgabe gesperrt, sofern Prüfart sperrend):**');
            foreach ($this->overdue->take(25) as $a) {
                $m->line("- {$a->asset_tag} {$a->model->full_name} – fällig seit {$a->next_inspection_due->format('d.m.Y')}");
            }
        }
        if ($this->soon->isNotEmpty()) {
            $m->line('**Bald fällig:**');
            foreach ($this->soon->take(25) as $a) {
                $m->line("- {$a->asset_tag} {$a->model->full_name} – {$a->next_inspection_due->format('d.m.Y')}");
            }
        }
        if ($this->lateReturns->isNotEmpty()) {
            $m->line('**Überfällige Rückgaben:**');
            foreach ($this->lateReturns->take(25) as $c) {
                $m->line("- {$c->asset->asset_tag} {$c->asset->model->full_name} – {$c->assigneeLabel()} (fällig {$c->expected_return_at->format('d.m.Y')})");
            }
        }
        if ($this->lowStock->isNotEmpty()) {
            $m->line('**Mindestbestand unterschritten:**');
            foreach ($this->lowStock as $c) {
                $m->line("- {$c->name}: {$c->quantity} {$c->unit} (Minimum {$c->min_quantity})");
            }
        }

        return $m->action('Inventar öffnen', url('/inspections'));
    }
}
