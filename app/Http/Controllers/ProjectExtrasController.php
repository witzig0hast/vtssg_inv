<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Beladefotos und digitale Unterschrift. */
class ProjectExtrasController extends Controller
{
    public function photo(Request $request, Project $project)
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('inventory.upload_max_kb')], 'label' => ['nullable', 'string', 'max:100']]);
        $f = $request->file('photo');
        $path = $f->storeAs('attachments/'.date('Y/m'), bin2hex(random_bytes(16)).'.'.$f->extension(), 'local');
        $project->attachments()->create([
            'uploaded_by' => $request->user()->id, 'disk' => 'local', 'path' => $path, 'original_name' => 'beladefoto-'.now()->format('Ymd-His').'.'.$f->extension(),
            'mime' => $f->getMimeType(), 'size' => $f->getSize(), 'sha256' => hash_file('sha256', $f->getRealPath()), 'label' => 'Beladefoto'.($request->filled('label') ? ': '.$request->input('label') : ''),
        ]);

        return back()->with('status', 'Foto gespeichert.');
    }

    public function signatureForm(Project $project)
    {
        return view('projects.signature', ['project' => $project->load('customer')]);
    }

    public function signatureStore(Request $request, Project $project)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'image' => ['required', 'string', 'max:400000']]);
        if (! str_starts_with($data['image'], 'data:image/png;base64,')) {
            return back()->withErrors(['image' => 'Ungültiges Unterschriftsformat.']);
        }
        $bin = base64_decode(substr($data['image'], 22), true);
        // Echte PNG-Datei erzwingen (Magic Bytes + Bildparser), Größe begrenzen
        if ($bin === false || strlen($bin) > 300000 || substr($bin, 0, 8) !== "\x89PNG\r\n\x1a\n" || ! @getimagesizefromstring($bin)) {
            return back()->withErrors(['image' => 'Die Unterschrift ist ungültig.']);
        }
        $path = 'attachments/'.date('Y/m').'/'.bin2hex(random_bytes(16)).'.png';
        Storage::disk('local')->put($path, $bin);
        $project->attachments()->where('label', 'Unterschrift')->get()->each->delete();
        $project->attachments()->create([
            'uploaded_by' => $request->user()->id, 'disk' => 'local', 'path' => $path, 'original_name' => 'unterschrift.png',
            'mime' => 'image/png', 'size' => strlen($bin), 'sha256' => hash('sha256', $bin), 'label' => 'Unterschrift',
        ]);
        $project->forceFill(['signed_at' => now(), 'signed_by' => $data['name']])->save();
        AuditLog::record('signature_captured', $project, summary: $project->number.' · '.$data['name']);

        return redirect()->route('projects.show', $project)->with('status', 'Unterschrift gespeichert.');
    }
}
