<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EditorImageController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $path = $request->file('image')->store('editor-images/' . date('Y/m'), 'public');

        return response()->json([
            'url' => asset('storage/' . $path),
        ]);
    }
}
