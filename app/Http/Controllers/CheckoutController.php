<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Project;
use App\Models\User;
use App\Services\CheckoutService;
use DomainException;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(private CheckoutService $service) {}

    public function form(Asset $asset)
    {
        $asset->load('model', 'status');

        return view('assets.checkout', [
            'asset' => $asset,
            'users' => User::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::reserving()->orWhere('status', 'draft')->orderByDesc('setup_at')->limit(100)->get(),
            'locations' => Location::orderBy('name')->get(),
            'customers' => Customer::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'assignee_type' => ['required', 'in:user,project,location,customer'],
            'assignee_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'expected_return_at' => ['nullable', 'date'],
            'condition' => ['nullable', 'in:ok,worn,damaged'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $this->assertAssigneeExists($data['assignee_type'], (int) $data['assignee_id']);

        try {
            $this->service->checkout($asset, $data['assignee_type'], (int) $data['assignee_id'], $request->user(), $data);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['checkout' => $e->getMessage()]);
        }

        return redirect()->route('assets.show', $asset)->with('status', "{$asset->asset_tag} ausgegeben.");
    }

    public function checkin(Request $request, Checkout $checkout)
    {
        $data = $request->validate([
            'condition' => ['required', 'in:ok,worn,damaged'], 'note' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:1'], 'hours' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);
        try {
            $this->service->checkin($checkout, $data['condition'], $data['note'] ?? null, $request->user(), $data);
        } catch (DomainException $e) {
            return back()->withErrors(['checkin' => $e->getMessage()]);
        }

        return back()->with('status', 'Rücknahme gebucht.'.($data['condition'] === 'damaged' ? ' Wartungsticket wurde erstellt.' : ''));
    }

    public function overdue()
    {
        return view('checkouts.overdue', [
            'checkouts' => Checkout::query()->with(['asset.model', 'performer'])->whereNull('returned_at')
                ->orderByRaw('expected_return_at IS NULL')->orderBy('expected_return_at')->paginate(30),
        ]);
    }

    protected function assertAssigneeExists(string $type, int $id): void
    {
        $class = ['user' => User::class, 'project' => Project::class, 'location' => Location::class, 'customer' => Customer::class][$type];
        abort_unless($class::query()->whereKey($id)->exists(), 422, 'Empfänger existiert nicht.');
    }
}
