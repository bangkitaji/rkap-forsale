<?php

namespace App\Http\Controllers;

use App\Models\RkapActivityFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class RkapFileController extends Controller
{
    public function download($fileId)
    {
        $file = RkapActivityFile::findOrFail($fileId);
        
        if (!Storage::exists($file->file_path)) {
            abort(404, 'File tidak ditemukan di storage.');
        }
        
        return Storage::download($file->file_path, $file->original_name);
    }
    
    public function view($fileId)
    {
        $file = RkapActivityFile::findOrFail($fileId);
        
        if (!Storage::exists($file->file_path)) {
            abort(404, 'File tidak ditemukan di storage.');
        }
        
        $mimeType = Storage::mimeType($file->file_path);
        
        return response()->file(Storage::path($file->file_path), [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $file->original_name . '"'
        ]);
    }
}
