<?php

namespace App\Http\Controllers;

use App\Support\MasterData;
use Illuminate\Http\Request;

class MasterDataController extends Controller
{
    protected function def(Request $request): array
    {
        $key = $request->route('resource');
        $def = MasterData::get($key);
        $def['key'] = $key;
        $manage = $def['perm'].'.'.($def['perm_manage'] ?? 'manage');
        abort_unless($request->user()->can($def['perm'].'.view') || $request->user()->can($manage), 403);
        $def['can_manage'] = $request->user()->can($manage);

        return $def;
    }

    protected function manage(array $def): void
    {
        abort_unless($def['can_manage'], 403);
    }

    public function index(Request $request)
    {
        $def = $this->def($request);
        $q = $def['model']::query()->with($def['with'] ?? []);
        if ($s = trim((string) $request->query('q'))) {
            $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $s).'%');
        }

        return view('master.index', ['def' => $def, 'rows' => $q->orderBy('name')->paginate(25)->withQueryString()]);
    }

    public function create(Request $request)
    {
        $def = $this->def($request);
        $this->manage($def);

        return view('master.form', ['def' => $def, 'row' => new $def['model']]);
    }

    public function store(Request $request)
    {
        $def = $this->def($request);
        $this->manage($def);
        $row = $def['model']::create($this->validated($request, $def));

        return redirect()->route('master.index', $def['key'])->with('status', $def['singular'].' „'.$row->name.'“ angelegt.');
    }

    public function edit(Request $request, string $resource, int $id)
    {
        $def = $this->def($request);
        $this->manage($def);

        return view('master.form', ['def' => $def, 'row' => $def['model']::findOrFail($id)]);
    }

    public function update(Request $request, string $resource, int $id)
    {
        $def = $this->def($request);
        $this->manage($def);
        $row = $def['model']::findOrFail($id);
        $row->update($this->validated($request, $def, $id));

        return redirect()->route('master.index', $def['key'])->with('status', $def['singular'].' gespeichert.');
    }

    public function destroy(Request $request, string $resource, int $id)
    {
        $def = $this->def($request);
        $this->manage($def);
        $row = $def['model']::findOrFail($id);
        foreach ($def['protect'] ?? [] as $rel) {
            if ($row->{$rel}()->exists()) {
                return back()->withErrors(['delete' => '„'.$row->name.'“ wird noch verwendet und kann nicht gelöscht werden.']);
            }
        }
        $row->delete();

        return redirect()->route('master.index', $def['key'])->with('status', $def['singular'].' gelöscht.');
    }

    protected function validated(Request $request, array $def, ?int $id = null): array
    {
        $rules = [];
        foreach ($def['fields'] as $f) {
            $rules[$f[0]] = str_replace('{id}', (string) ($id ?? 'NULL'), $f[3] ?? 'nullable');
        }
        $data = $request->validate($rules);
        foreach ($def['fields'] as $f) {
            if ($f[2] === 'checkbox') {
                $data[$f[0]] = $request->boolean($f[0]);
            }
        }

        return $data;
    }
}
