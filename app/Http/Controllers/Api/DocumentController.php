<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function download(Request $request, Document $document)
    {
        abort_unless($document->canAccess($request->user()), 403);
        Gate::authorize('view', $document);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'Document file is missing.');

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function preview(Request $request, Document $document)
    {
        abort_unless($document->canAccess($request->user()), 403);
        Gate::authorize('view', $document);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'Document file is missing.');
        $mime = $document->mime_type ?: 'application/octet-stream';
        abort_unless(str_starts_with($mime, 'image/') || $mime === 'application/pdf', 415, 'Preview is not available for this file type.');

        return response()->file(Storage::disk('local')->path($document->file_path), ['Content-Type' => $mime, 'Content-Disposition' => 'inline; filename="'.addslashes($document->original_name).'"']);
    }
}
