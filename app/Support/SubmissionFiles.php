<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;

/**
 * What a student may upload to an assessment, on the web and the mobile API.
 */
class SubmissionFiles
{
    // `mod` is a CAEPIPE model (BTG3333 labs).
    public const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'zip', 'mod'];

    public const MAX_KB = 25600;

    // A CAEPIPE model has no registered content type, so only its name is checked;
    // every other file must also look like what its extension says.
    private const NAME_ONLY = ['mod'];

    public static function rules(): array
    {
        return ['file', 'max:'.self::MAX_KB, 'extensions:'.implode(',', self::EXTENSIONS), self::contentRule()];
    }

    private static function contentRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof UploadedFile
                || in_array(strtolower($value->getClientOriginalExtension()), self::NAME_ONLY, true)) {
                return;
            }

            $checked = array_diff(self::EXTENSIONS, self::NAME_ONLY);
            if (! in_array($value->guessExtension(), $checked, true)) {
                $fail('The file does not match its extension.');
            }
        };
    }
}
