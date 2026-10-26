<?php

declare(strict_types=1);

namespace App\Services\Tender;

use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A game rule the request broke. The message is shown to the person as is.
 */
class TenderRuleException extends RuntimeException
{
    public function render(Request $request): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
