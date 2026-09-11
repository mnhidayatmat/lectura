<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TenantResource;
use App\Models\Course;
use App\Models\Section;
use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * JSON counterpart of Auth\OnboardingController for the mobile app.
 */
class OnboardingController extends Controller
{
    public function tenants(): AnonymousResourceCollection
    {
        return TenantResource::collection(
            Tenant::where('is_active', true)->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => ['nullable', 'exists:tenants,id'],
            'new_tenant_name' => ['nullable', 'string', 'max:255', 'min:3'],
            'role' => ['required', 'in:lecturer,student'],
            'invite_code' => ['nullable', 'string'],
        ]);

        if (! $request->tenant_id && ! $request->new_tenant_name) {
            throw ValidationException::withMessages([
                'tenant_id' => 'Please select an institution or type a new one.',
            ]);
        }

        $user = $request->user();
        $role = $request->input('role');

        if ($request->filled('new_tenant_name') && ! $request->tenant_id) {
            $baseSlug = $this->generateSlug($request->new_tenant_name);

            $slug = $baseSlug;
            $i = 2;
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $baseSlug.$i++;
            }

            $tenant = Tenant::create([
                'name' => $request->new_tenant_name,
                'slug' => $slug,
                'timezone' => 'Asia/Kuala_Lumpur',
                'locale' => 'en',
                'is_active' => true,
                'settings' => [
                    'auth' => ['allow_google_login' => true, 'sso_enabled' => false],
                    'ai' => ['enabled' => true, 'provider' => 'claude'],
                ],
            ]);

            $this->addMembership($tenant, $user->id, $role);

            return $this->joined($tenant, $role, 'Welcome! '.$tenant->name.' has been created. You are the first '.ucfirst($role).'.', 201);
        }

        $tenant = Tenant::findOrFail($request->tenant_id);

        if ($user->tenantUsers()->where('tenant_id', $tenant->id)->exists()) {
            return $this->joined($tenant, $user->roleInTenant($tenant->id), 'You are already a member of '.$tenant->name.'.');
        }

        if ($role === 'student' && $request->filled('invite_code')) {
            $code = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $request->invite_code));

            $section = Section::where('invite_code', $code)
                ->whereHas('course', fn ($q) => $q->where('tenant_id', $tenant->id))
                ->first();

            if (! $section) {
                $isCourseCode = $code !== '' && Course::where('invite_code', $code)
                    ->where('tenant_id', $tenant->id)
                    ->exists();

                throw ValidationException::withMessages([
                    'invite_code' => $isCourseCode
                        ? 'That code belongs to a course, not a section. Please ask your lecturer for the section invite code (shown as "Student code" next to each section).'
                        : 'Invalid invite code for this institution.',
                ]);
            }

            if (! $section->is_active) {
                throw ValidationException::withMessages([
                    'invite_code' => 'This section is not accepting enrollments. Please contact your lecturer.',
                ]);
            }

            $this->addMembership($tenant, $user->id, 'student');

            $section->students()->syncWithoutDetaching([
                $user->id => [
                    'enrolled_at' => now(),
                    'enrollment_method' => 'invite_code',
                    'is_active' => true,
                ],
            ]);

            return $this->joined($tenant, 'student', 'Welcome! You joined '.$tenant->name.' and enrolled in '.$section->name.'.');
        }

        if ($role === 'lecturer' && $request->filled('invite_code')) {
            $code = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $request->invite_code));

            $course = Course::where('invite_code', $code)
                ->where('tenant_id', $tenant->id)
                ->first();

            if (! $course) {
                throw ValidationException::withMessages([
                    'invite_code' => 'Invalid course invite code for this institution.',
                ]);
            }

            $this->addMembership($tenant, $user->id, 'lecturer');

            $course->update(['lecturer_id' => $user->id]);

            return $this->joined($tenant, 'lecturer', 'Welcome! You joined '.$tenant->name.' and claimed course '.$course->code.' — '.$course->title.'.');
        }

        $this->addMembership($tenant, $user->id, $role);

        return $this->joined($tenant, $role, 'Welcome! You joined '.$tenant->name.' as '.ucfirst($role).'.');
    }

    private function addMembership(Tenant $tenant, int $userId, string $role): void
    {
        TenantUser::create([
            'user_id' => $userId,
            'tenant_id' => $tenant->id,
            'role' => $role,
            'is_active' => true,
            'joined_at' => now(),
        ]);
    }

    private function joined(Tenant $tenant, ?string $role, string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => [
                'tenant' => new TenantResource($tenant),
                'role' => $role,
            ],
        ], $status);
    }

    private function generateSlug(string $name): string
    {
        $stopWords = ['of', 'the', 'and', 'at', 'in', 'for', 'a', 'an', 'de', 'la', 'le', 'di'];

        $words = array_values(array_filter(preg_split('/[\s\-]+/', $name)));

        if (count($words) === 1) {
            return strtolower(substr(preg_replace('/[^a-z0-9]/', '', Str::slug($words[0])), 0, 12));
        }

        $significant = array_filter($words, fn ($w) => ! in_array(strtolower($w), $stopWords));
        $acronym = strtolower(implode('', array_map(fn ($w) => preg_replace('/[^a-z0-9]/i', '', $w[0]), $significant)));

        if (strlen($acronym) >= 2) {
            return $acronym;
        }

        return substr(Str::slug($name), 0, 12);
    }
}
