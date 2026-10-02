<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Inspection;
use App\Models\MaintenanceTicket;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    private const TYPES = ['asset' => Asset::class, 'project' => Project::class, 'inspection' => Inspection::class, 'ticket' => MaintenanceTicket::class];

    /** Berechtigungen je Objekttyp: [ansehen, hochladen, löschen] */
    private const PERMS = [
        Asset::class => ['assets.view', 'assets.update', 'assets.update'],
        Project::class => ['projects.view', 'projects.update', 'projects.update'],
        Inspection::class => ['inspections.view', 'inspections.record', 'inspections.manage'],
        MaintenanceTicket::class => ['maintenance.view', 'maintenance.report', 'maintenance.manage'],
    ];

    public function store(Request $request, string $type, int $id)
    {
        $class = self::TYPES[$type] ?? abort(404);
        abort_unless($request->user()->can(self::PERMS[$class][1]), 403);
        $target = $class::findOrFail($id);
        $request->validate([
            'file' => ['required', 'file', 'max:'.config('inventory.upload_max_kb'), 'mimes:'.implode(',', config('inventory.upload_mimes'))],
            'label' => ['nullable', 'string', 'max:120'],
        ]);
        $file = $request->file('file');
        // Zufälliger Dateiname außerhalb des Web-Roots (privates Disk) – Original nur als Metadatum.
        $path = $file->storeAs('attachments/'.date('Y/m'), bin2hex(random_bytes(16)).'.'.$file->extension(), 'local');

        $target->attachments()->create([
            'uploaded_by' => $request->user()->id, 'disk' => 'local', 'path' => $path,
            'original_name' => mb_substr(preg_replace('/[^\w.\- äöüÄÖÜß()]+/u', '_', $file->getClientOriginalName()), 0, 200),
            'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()),
            'label' => $request->input('label'),
        ]);

        return back()->with('status', 'Datei hochgeladen.');
    }

    public function download(Request $request, Attachment $attachment)
    {
        abort_unless($request->user()->can(self::PERMS[$attachment->attachable_type][0] ?? 'users.manage'), 403);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function destroy(Request $request, Attachment $attachment)
    {
        abort_unless($request->user()->can(self::PERMS[$attachment->attachable_type][2] ?? 'users.manage'), 403);
        $attachment->delete();

        return back()->with('status', 'Datei gelöscht.');
    }
}
