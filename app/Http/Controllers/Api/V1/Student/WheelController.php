<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Services\RandomWheel\WheelSpinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WheelController extends Controller
{
    /**
     * The latest spin of the random wheel in the student's classes; null when none is recent.
     */
    public function show(Request $request, WheelSpinService $spins): JsonResponse
    {
        $spin = $spins->latestFor($request->user());

        return response()->json(['data' => $spin ? $spins->present($spin, $request->user()) : null]);
    }
}
