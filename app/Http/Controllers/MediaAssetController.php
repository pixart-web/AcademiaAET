<?php

namespace App\Http\Controllers;

use App\Enums\MediaKind;
use App\Models\MediaAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MediaAssetController extends Controller
{
    /**
     * Allow-listed by real (finfo-detected) mime type, not by extension or
     * the browser-supplied Content-Type.
     */
    private const ALLOWED_MIME_BY_KIND = [
        'image' => ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'],
        'audio' => ['audio/mpeg', 'audio/mp4', 'audio/ogg', 'audio/webm', 'audio/wav'],
        'video' => ['video/mp4', 'video/webm', 'video/quicktime'],
        'document' => ['application/pdf'],
    ];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', MediaAsset::class);

        $media = $request->user()->organization->mediaAssets()
            ->where('status', 'active')
            ->latest()
            ->paginate(24)
            ->withQueryString();

        $media->getCollection()->transform(fn (MediaAsset $asset) => [
            ...$asset->toArray(),
            'preview_url' => in_array($asset->kind, [MediaKind::Image, MediaKind::Audio, MediaKind::Video], true)
                ? URL::temporarySignedRoute('media.show', now()->addMinutes(15), ['media' => $asset->id])
                : null,
        ]);

        return Inertia::render('Media/Index', ['media' => $media]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', MediaAsset::class);

        $data = $request->validate([
            'kind' => ['required', Rule::in(array_column(MediaKind::cases(), 'value'))],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'alt_text' => ['nullable', 'string', 'max:1000'],
            'transcript' => ['nullable', 'string', 'max:10000'],
            'license' => ['nullable', 'string', 'max:255'],
            'attribution' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $request->validate([
            'file' => ['required', 'file', 'max:'.config('media.max_size_kb.'.$data['kind'])],
        ]);

        $detectedMime = $file->getMimeType();

        if (! in_array($detectedMime, self::ALLOWED_MIME_BY_KIND[$data['kind']], true)) {
            return back()->withErrors([
                'file' => 'Tipo de ficheiro não permitido para esta categoria.',
            ])->withInput();
        }

        if (in_array($data['kind'], ['image', 'audio', 'video'], true) && blank($data['alt_text'] ?? null) && blank($data['transcript'] ?? null)) {
            return back()->withErrors([
                'alt_text' => 'Indique uma alternativa textual (descrição ou transcrição) para este conteúdo.',
            ])->withInput();
        }

        $path = $file->store('media/'.$request->user()->organization_id, 'local');

        $media = MediaAsset::create([
            ...$data,
            'organization_id' => $request->user()->organization_id,
            'uploaded_by_user_id' => $request->user()->id,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $detectedMime,
            'size_bytes' => $file->getSize(),
            'status' => 'active',
        ]);

        return back()->with('status', 'Conteúdo carregado.')->with('mediaId', $media->id);
    }

    public function destroy(Request $request, MediaAsset $media): RedirectResponse
    {
        $this->authorize('delete', $media);

        $media->update(['status' => 'archived']);

        return back()->with('status', 'Conteúdo arquivado.');
    }
}
