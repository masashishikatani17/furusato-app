<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;

class UploadController extends Controller
{
    /** 使用ディスク（.env: FILE_UPLOAD_DISK=public|s3） */
    private function disk()
    {
        return config('filesystems.default_upload_disk', env('FILE_UPLOAD_DISK', 'public'));
    }

    public function index(Request $request)
    {
        $disk = $this->disk();
        $folder = 'uploads';
        $files = collect(Storage::disk($disk)->files($folder))
            ->filter(fn($p) => !str_ends_with($p, '/'))
            ->values()
            ->map(function ($path) use ($disk) {
                $name = basename($path);
                $url  = Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(15));
                // public ディスクは temporaryUrl 非対応なので url() にフォールバック
                if (!$url) { $url = Storage::disk($disk)->url($path); }
                return compact('path','name','url');
            });

        return view('upload.index', compact('files'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                File::image()
                    ->types(['jpeg','png','webp','gif'])
                    ->max(5 * 1024), // 5MB
            ],
        ]);

        $disk = $this->disk();
        $path = $request->file('file')->store('uploads', $disk); // 'uploads/xxxx.ext'

        return redirect()->route('upload.index')
            ->with('success', 'アップロードしました: '.$path);
    }

    public function destroy(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        $disk = $this->disk();
        Storage::disk($disk)->delete($request->path);
        return back()->with('success', '削除しました: '.$request->path);
    }
}