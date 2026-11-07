<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Materi;
use App\Models\MateriVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MateriVideoController
{
    public function index(Materi $materi): JsonResponse
    {
        Gate::authorize('view', $materi);
        return response()->json(['message' => 'OK', 'data' => $materi->videos()->orderBy('urutan')->orderBy('id')->get()]);
    }

    public function store(Request $request, Materi $materi): JsonResponse
    {
        Gate::authorize('update', $materi);
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:160'],
            'youtube_url' => ['required', 'url', 'max:2048'],
            'tipe' => ['required', 'in:video,playlist'],
            'urutan' => ['nullable', 'integer', 'min:1'],
        ]);
        [$id, $type] = $this->youtube($data['youtube_url']);
        if (($data['tipe'] === 'playlist' && $type !== 'playlist') || ($data['tipe'] === 'video' && $type !== 'video')) {
            throw ValidationException::withMessages(['youtube_url' => ['Jenis tautan YouTube tidak sesuai dengan tipe yang dipilih.']]);
        }
        $video = $materi->videos()->create($data + ['youtube_id' => $id, 'urutan' => $data['urutan'] ?? ($materi->videos()->max('urutan') + 1)]);
        return response()->json(['message' => 'Video ditambahkan', 'data' => $video], 201);
    }

    public function update(Request $request, Materi $materi, MateriVideo $video): JsonResponse
    {
        Gate::authorize('update', $materi);
        abort_unless((int) $video->materi_id === (int) $materi->id, 404);
        $data = $request->validate([
            'judul' => ['sometimes', 'required', 'string', 'max:160'],
            'youtube_url' => ['sometimes', 'required', 'url', 'max:2048'],
            'tipe' => ['sometimes', 'required', 'in:video,playlist'],
            'urutan' => ['sometimes', 'required', 'integer', 'min:1'],
        ]);
        if (isset($data['youtube_url'])) {
            [$data['youtube_id'], $type] = $this->youtube($data['youtube_url']);
            $expected = $data['tipe'] ?? $video->tipe;
            if (($expected === 'playlist' && $type !== 'playlist') || ($expected === 'video' && $type !== 'video')) {
                throw ValidationException::withMessages(['youtube_url' => ['Jenis tautan YouTube tidak sesuai dengan tipe yang dipilih.']]);
            }
        }
        $video->update($data);
        return response()->json(['message' => 'Video diperbarui', 'data' => $video->refresh()]);
    }

    public function destroy(Materi $materi, MateriVideo $video): JsonResponse
    {
        Gate::authorize('update', $materi);
        abort_unless((int) $video->materi_id === (int) $materi->id, 404);
        $video->delete();
        return response()->json(['message' => 'Video dihapus']);
    }

    private function youtube(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = trim($parts['path'] ?? '', '/');
        parse_str($parts['query'] ?? '', $query);
        if (in_array($host, ['youtu.be'], true) && $path !== '') return [$path, 'video'];
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            if (($query['v'] ?? '') !== '') return [$query['v'], 'video'];
            if (($query['list'] ?? '') !== '') return [$query['list'], 'playlist'];
            if (str_starts_with($path, 'embed/')) return [substr($path, 6), 'video'];
        }
        throw ValidationException::withMessages(['youtube_url' => ['Gunakan tautan video atau playlist YouTube yang valid.']]);
    }
}
