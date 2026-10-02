<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Customer;
use App\Models\KitTemplate;
use App\Models\Location;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\PackingService;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $q = Project::query()->with(['customer', 'manager'])->withCount('items');
        if ($s = trim((string) $request->query('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%';
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('number', 'like', $like)->orWhere('venue', 'like', $like)
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)));
        }
        if ($st = $request->query('status')) $q->where('status', $st);
        if ($request->query('scope', 'upcoming') === 'upcoming' && ! $request->filled('status')) {
            $q->whereNotIn('status', ['closed', 'cancelled']);
        }

        return view('projects.index', ['projects' => $q->orderByRaw('COALESCE(setup_at, starts_at) DESC')->paginate(20)->withQueryString()]);
    }

    public function calendar(Request $request)
    {
        $month = Carbon::createFromFormat('Y-m', $request->query('month', now()->format('Y-m')))->startOfMonth();
        $from = $month->copy()->startOfWeek();
        $to = $month->copy()->endOfMonth()->endOfWeek();
        $projects = Project::query()->with('customer')->whereNotIn('status', ['cancelled'])->overlapping($from, $to)->orderBy('starts_at')->get();

        $days = [];
        for ($d = $from->copy(); $d <= $to; $d->addDay()) {
            $days[$d->toDateString()] = $projects->filter(fn ($p) => $p->windowStart()?->startOfDay() <= $d->copy()->endOfDay() && $p->windowEnd()?->endOfDay() >= $d->copy()->startOfDay());
        }

        return view('projects.calendar', ['month' => $month, 'days' => $days]);
    }

    public function create()
    {
        return view('projects.form', ['project' => new Project(['status' => 'draft', 'manager_id' => auth()->id()]), ...$this->lookups()]);
    }

    public function store(Request $request)
    {
        $project = Project::create($this->validated($request) + ['number' => Project::nextNumber()]);

        return redirect()->route('projects.show', $project)->with('status', "Projekt {$project->number} angelegt.");
    }

    public function show(Project $project, AvailabilityService $availability)
    {
        $project->load(['customer', 'manager', 'items.model.manufacturer', 'items.asset.model', 'items.supplier', 'venueLocation', 'attachments.uploader']);
        $conflicts = $availability->conflicts($project);
        $from = $project->windowStart();
        $to = $project->windowEnd();

        $avail = [];
        if ($from && $to) {
            foreach ($project->items->whereNotNull('asset_model_id')->pluck('model')->unique('id') as $model) {
                $avail[$model->id] = $availability->forModel($model, $from, $to, $project->id);
            }
        }

        $total = $project->items->where('is_external', false)->sum('quantity');
        $packed = $project->items->where('is_external', false)->sum('packed_quantity');
        $rentalSum = $project->items->sum(fn ($i) => $i->unitPriceCents() * $i->quantity) * $project->rentalDays();

        return view('projects.show', [
            'project' => $project, 'conflicts' => $conflicts, 'avail' => $avail,
            'groups' => $project->items->groupBy(fn ($i) => $i->group ?: 'Allgemein'),
            'progress' => $total ? round($packed / $total * 100) : 0,
            'rentalSum' => $rentalSum,
            'weightKg' => $project->items->where('is_external', false)->sum(fn ($i) => (float) ($i->model?->weight_kg ?? 0) * $i->quantity),
            'powerW' => $project->items->where('is_external', false)->sum(fn ($i) => (int) ($i->model?->power_w ?? 0) * $i->quantity),
            'vehicles' => Location::where('type', 'vehicle')->whereNotNull('max_load_kg')->orderBy('name')->get(),
            'models' => AssetModel::with('manufacturer')->orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'templates' => KitTemplate::orderBy('name')->get(),
            'transitions' => $this->transitions($project),
        ]);
    }

    public function edit(Project $project)
    {
        return view('projects.form', ['project' => $project, ...$this->lookups()]);
    }

    public function update(Request $request, Project $project)
    {
        $project->update($this->validated($request));

        return redirect()->route('projects.show', $project)->with('status', 'Gespeichert.');
    }

    public function destroy(Project $project)
    {
        if ($project->checkouts()->whereNull('returned_at')->exists()) {
            return back()->withErrors(['delete' => 'Es ist noch Material auf diesem Projekt ausgegeben.']);
        }
        $project->delete();

        return redirect()->route('projects.index')->with('status', 'Projekt gelöscht.');
    }

    public function status(Request $request, Project $project, AvailabilityService $availability)
    {
        $to = $request->validate(['status' => ['required', Rule::in(array_keys(Project::STATUSES))]])['status'];
        abort_unless(in_array($to, $this->transitions($project), true), 422, 'Statuswechsel nicht erlaubt.');

        if (in_array($to, ['planned', 'confirmed'], true) && ! $request->boolean('force')) {
            if (! $project->windowStart() || ! $project->windowEnd()) {
                return back()->withErrors(['status' => 'Bitte zuerst Aufbau-/Rückgabezeitraum angeben, damit Reservierungen geprüft werden können.']);
            }
            if ($c = $availability->conflicts($project)) {
                return back()->withErrors(['status' => 'Konflikte: '.collect($c)->pluck('message')->implode(' | ')]);
            }
        }
        if ($to === 'closed' && $project->checkouts()->whereNull('returned_at')->exists()) {
            return back()->withErrors(['status' => 'Abschluss nicht möglich: Es ist noch Material ausgegeben.']);
        }
        $project->update(['status' => $to]);
        \App\Services\Webhook::fire('project.status_changed', ['number' => $project->number, 'name' => $project->name, 'status' => $to]);

        return back()->with('status', 'Status: '.Project::STATUSES[$to]);
    }

    /** Erlaubter Statusfluss. */
    protected function transitions(Project $p): array
    {
        return match ($p->status) {
            'draft' => ['planned', 'confirmed', 'cancelled'],
            'planned' => ['draft', 'confirmed', 'cancelled'],
            'confirmed' => ['planned', 'loaded', 'cancelled'],
            'loaded' => ['running', 'returned'],
            'running' => ['returned'],
            'returned' => ['closed', 'running'],
            'cancelled' => ['draft'],
            default => [],
        };
    }

    public function addItem(Request $request, Project $project)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:model,asset,external'],
            'asset_model_id' => ['nullable', 'required_if:kind,model', 'exists:asset_models,id'],
            'asset_tag' => ['nullable', 'required_if:kind,asset', 'string', 'max:80'],
            'external_name' => ['nullable', 'required_if:kind,external', 'string', 'max:160'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'external_cost' => ['nullable', 'string', 'max:20'],
            'price' => ['nullable', 'string', 'max:20'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'group' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $row = ['project_id' => $project->id, 'quantity' => $data['quantity'], 'group' => $data['group'] ?? null, 'note' => $data['note'] ?? null,
            'price_cents' => Money::toCents($data['price'] ?? null)];

        if ($data['kind'] === 'asset') {
            $asset = Asset::where('asset_tag', $data['asset_tag'])->first();
            if (! $asset) {
                return back()->withErrors(['asset_tag' => 'Asset-Tag nicht gefunden.'])->withInput();
            }
            $row += ['asset_id' => $asset->id, 'asset_model_id' => $asset->asset_model_id, 'quantity' => $asset->model->isQuantity() ? $data['quantity'] : 1];
        } elseif ($data['kind'] === 'model') {
            $row += ['asset_model_id' => $data['asset_model_id']];
        } else {
            $row += ['is_external' => true, 'external_name' => $data['external_name'], 'supplier_id' => $data['supplier_id'] ?? null,
                'external_cost_cents' => Money::toCents($data['external_cost'] ?? null)];
        }
        ProjectItem::create($row);

        return back()->with('status', 'Position hinzugefügt.');
    }

    public function updateItem(Request $request, Project $project, ProjectItem $item)
    {
        abort_unless($item->project_id === $project->id, 404);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:100000'], 'group' => ['nullable', 'string', 'max:80'], 'note' => ['nullable', 'string', 'max:255']]);
        if ($data['quantity'] < $item->packed_quantity) {
            return back()->withErrors(['quantity' => 'Menge kann nicht unter die bereits verladene Menge gesenkt werden.']);
        }
        $item->update($data);

        return back()->with('status', 'Position aktualisiert.');
    }

    public function removeItem(Project $project, ProjectItem $item)
    {
        abort_unless($item->project_id === $project->id, 404);
        if ($item->packed_quantity > $item->returned_quantity) {
            return back()->withErrors(['item' => 'Position ist bereits verladen und nicht zurückgebucht.']);
        }
        $item->delete();

        return back()->with('status', 'Position entfernt.');
    }

    public function applyTemplate(Request $request, Project $project)
    {
        $data = $request->validate(['kit_template_id' => ['required', 'exists:kit_templates,id'], 'multiplier' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $tpl = KitTemplate::with('items')->findOrFail($data['kit_template_id']);
        $mult = $data['multiplier'] ?? 1;
        DB::transaction(function () use ($tpl, $project, $mult) {
            foreach ($tpl->items as $i) {
                ProjectItem::create(['project_id' => $project->id, 'asset_model_id' => $i->asset_model_id, 'quantity' => $i->quantity * $mult, 'group' => $i->group ?: $tpl->name]);
            }
        });

        return back()->with('status', "Vorlage „{$tpl->name}“ übernommen.");
    }

    public function duplicate(Project $project)
    {
        $copy = DB::transaction(function () use ($project) {
            $copy = $project->replicate(['number', 'status', 'setup_at', 'starts_at', 'ends_at', 'teardown_at']);
            $copy->number = Project::nextNumber();
            $copy->status = 'draft';
            $copy->name = $project->name.' (Kopie)';
            $copy->save();
            foreach ($project->items as $i) {
                $c = $i->replicate(['packed_quantity', 'returned_quantity']);
                $c->project_id = $copy->id;
                $c->packed_quantity = $c->returned_quantity = 0;
                $c->save();
            }

            return $copy;
        });

        return redirect()->route('projects.edit', $copy)->with('status', 'Projekt dupliziert – bitte Zeiten setzen.');
    }

    public function saveAsTemplate(Request $request, Project $project)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        DB::transaction(function () use ($data, $project) {
            $t = KitTemplate::create(['name' => $data['name'], 'description' => "Aus Projekt {$project->number}"]);
            foreach ($project->items->where('is_external', false)->whereNotNull('asset_model_id') as $i) {
                $t->items()->create(['asset_model_id' => $i->asset_model_id, 'quantity' => $i->quantity, 'group' => $i->group]);
            }
        });

        return back()->with('status', 'Vorlage gespeichert.');
    }

    // ── Packen per Scan ────────────────────────────────────────────────
    public function pack(Project $project)
    {
        $project->load('items.model', 'items.asset');

        return view('projects.pack', ['project' => $project]);
    }

    public function packScan(Request $request, Project $project, PackingService $packing)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:120'], 'mode' => ['required', 'in:load,unload'],
            'quantity' => ['nullable', 'integer', 'min:1'], 'condition' => ['nullable', 'in:ok,worn,damaged']]);
        try {
            $r = $data['mode'] === 'load'
                ? $packing->load($project, $data['code'], $request->user(), $data['quantity'] ?? 1)
                : $packing->unload($project, $data['code'], $request->user(), $data['condition'] ?? 'ok');
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        $project->refresh()->load('items');
        $items = $project->items->where('is_external', false);

        return response()->json([
            'ok' => true, 'message' => $r['message'], 'status' => $project->status,
            'packed' => $items->sum('packed_quantity'), 'returned' => $items->sum('returned_quantity'), 'total' => $items->sum('quantity'),
            'item' => $r['item'] ? ['id' => $r['item']->id, 'packed' => $r['item']->packed_quantity, 'returned' => $r['item']->returned_quantity, 'quantity' => $r['item']->quantity] : null,
        ]);
    }

    // ── Dokumente ──────────────────────────────────────────────────────
    public function document(Project $project, string $type)
    {
        abort_unless(in_array($type, ['delivery-note', 'packing-list'], true), 404);
        $project->load(['customer', 'manager', 'items.model.manufacturer', 'items.asset', 'items.supplier', 'venueLocation']);
        $items = $project->items->groupBy(fn ($i) => $i->group ?: 'Allgemein');

        $sig = $project->attachments()->where('label', 'Unterschrift')->latest()->first();
        $sigData = $sig && \Illuminate\Support\Facades\Storage::disk($sig->disk)->exists($sig->path)
            ? 'data:image/png;base64,'.base64_encode(\Illuminate\Support\Facades\Storage::disk($sig->disk)->get($sig->path)) : null;

        $pdf = Pdf::loadView('projects.pdf-'.$type, [
            'signature' => $sigData,
            'project' => $project, 'groups' => $items, 'company' => Setting::get('company_name'), 'companyAddress' => Setting::get('company_address'),
        ])->setPaper('a4');

        return $pdf->stream(($type === 'delivery-note' ? 'Lieferschein' : 'Packliste').'-'.$project->number.'.pdf');
    }

    protected function lookups(): array
    {
        return [
            'customers' => Customer::orderBy('name')->get(),
            'managers' => User::where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::where('type', 'venue')->orderBy('name')->get(),
        ];
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'venue_location_id' => ['nullable', 'exists:locations,id'],
            'venue' => ['nullable', 'string', 'max:200'],
            'setup_at' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'teardown_at' => ['nullable', 'date', 'after_or_equal:setup_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
