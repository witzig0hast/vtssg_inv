<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Consumable;
use App\Models\Project;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $term = trim((string) $request->query('q'));
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
        $u = $request->user();

        // Direkttreffer auf exakten Asset-Tag → direkt springen (Barcode-Scanner im Suchfeld)
        if ($term !== '' && $u->can('assets.view') && ($hit = Asset::where('asset_tag', $term)->first())) {
            return redirect()->route('assets.show', $hit);
        }

        return view('search', [
            'term' => $term,
            'assets' => $term && $u->can('assets.view') ? Asset::with('model')->search($term)->limit(15)->get() : collect(),
            'models' => $term && $u->can('models.view') ? AssetModel::with('manufacturer')->where('name', 'like', $like)->limit(10)->get() : collect(),
            'projects' => $term && $u->can('projects.view') ? Project::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('number', 'like', $like))->limit(10)->get() : collect(),
            'consumables' => $term && $u->can('consumables.view') ? Consumable::where('name', 'like', $like)->limit(10)->get() : collect(),
        ]);
    }
}
