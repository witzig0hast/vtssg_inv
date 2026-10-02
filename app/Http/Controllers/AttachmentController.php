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

    public function store(Request $request, string $type, int $id)
    {
        $class = self::TYPES[$type] ?? abort(404);
        abort_unless($request->user()->canAny(['assets.update', 'projects.update', 'inspections.record', 'maintenance.report']), 403);
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
        abort_unless($request->user()->canAny(['assets.view', 'projects.view', 'inspections.view', 'maintenance.view']), 403);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    public function destroy(Request $request, Attachment $attachment)
    {
        abort_unless($request->user()->canAny(['assets.update', 'projects.update', 'inspections.manage', 'maintenance.manage']), 403);
        $attachment->delete();

        return back()->with('status', 'Datei gelöscht.');
    }
}
