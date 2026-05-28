<?php

namespace App\Modules\Media\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Danh sách mime type được phép upload theo context.
     */
    private const ALLOWED_MIMES = [
        'image' => 'jpeg,jpg,png,gif,webp',
        'file'  => 'pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,7z,txt,csv',
    ];

    /**
     * Giới hạn kích thước (KB).
     */
    private const MAX_SIZE_KB = [
        'image' => 10240,  // 10 MB
        'file'  => 51200,  // 50 MB
    ];

    /**
     * Upload file lên server, trả về URL công khai.
     *
     * POST /api/upload
     * Body (multipart/form-data):
     *   - file    : binary
     *   - context : 'image' | 'file'
     *
     * Thiết kế hướng tới S3: chỉ cần đổi disk() sau này.
     */
    public function store(Request $request): JsonResponse
    {
        $context = $request->input('context', 'file');

        if (!array_key_exists($context, self::ALLOWED_MIMES)) {
            return response()->json(['message' => 'Context không hợp lệ. Dùng: image hoặc file.'], 422);
        }

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:' . self::ALLOWED_MIMES[$context],
                'max:' . self::MAX_SIZE_KB[$context],
            ],
        ], [
            'file.mimes' => 'Định dạng file không được hỗ trợ.',
            'file.max'   => $context === 'image'
                ? 'Ảnh không được vượt quá 10MB.'
                : 'File không được vượt quá 50MB.',
        ]);

        $file      = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $fileName  = Str::uuid() . '.' . $extension;
        $yearMonth = now()->format('Y/m');
        $path      = "uploads/{$yearMonth}/{$fileName}";

        // Lưu vào storage/app/public — đổi 'public' → 's3' để chuyển sang Cloud
        Storage::disk('public')->put($path, file_get_contents($file));

        $publicUrl = url('storage/' . $path);

        return response()->json([
            'url'       => $publicUrl,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'context'   => $context,
        ]);
    }
}
