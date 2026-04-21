<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\PortfolioPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    use AuthorizesCourseAccess;

    /**
     * Portfolio overview — all courses with photo counts + full teaching portfolio.
     */
    public function index(Request $request): View
    {
        $courseIds = $this->accessibleCourseIds();
        $courses = Course::whereIn('id', $courseIds)
            ->withCount(['portfolioPhotos' => fn ($q) => $q->where('user_id', auth()->id())])
            ->get()
            ->sortByDesc('portfolio_photos_count');

        // Full portfolio photos (across all courses)
        $query = PortfolioPhoto::where('user_id', auth()->id())
            ->whereIn('course_id', $courseIds)
            ->with('course', 'section')
            ->latest('taken_at');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $allPhotos = $query->paginate(24);
        $totalPhotos = PortfolioPhoto::where('user_id', auth()->id())->whereIn('course_id', $courseIds)->count();

        $categoryCounts = PortfolioPhoto::where('user_id', auth()->id())
            ->whereIn('course_id', $courseIds)
            ->selectRaw('category, count(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        return view('tenant.portfolio.index', compact('courses', 'allPhotos', 'totalPhotos', 'categoryCounts'));
    }

    /**
     * Photos for a specific course with upload form.
     */
    public function course(string $tenantSlug, Course $course): View
    {
        $this->authorizeCourseAccess($course);

        $photos = PortfolioPhoto::where('course_id', $course->id)
            ->where('user_id', auth()->id())
            ->with('section')
            ->latest('taken_at')
            ->get();

        $sections = $this->lecturerSections($course)->get();
        $categories = PortfolioPhoto::CATEGORIES;

        $photosByCategory = $photos->groupBy('category');
        $photosByWeek = $photos->groupBy('week_number')->sortKeys();

        return view('tenant.portfolio.course', compact('course', 'photos', 'sections', 'categories', 'photosByCategory', 'photosByWeek'));
    }

    /**
     * Store a new portfolio photo.
     */
    public function store(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        $request->validate([
            'photo' => ['required', 'image', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
            'category' => ['required', 'string', 'in:' . implode(',', array_keys(PortfolioPhoto::CATEGORIES))],
            'caption' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'week_number' => ['nullable', 'integer', 'min:1', 'max:20'],
            'taken_at' => ['nullable', 'date'],
        ]);

        $file = $request->file('photo');
        $tenant = app('current_tenant');

        // Store original photo to public disk
        $path = $file->store("portfolio/{$course->id}/" . date('Y-m'), 'public');

        // Generate thumbnail using GD
        $thumbnailPath = $this->generateThumbnail($file, $course->id, $path);

        PortfolioPhoto::create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'section_id' => $request->section_id,
            'user_id' => auth()->id(),
            'category' => $request->category,
            'caption' => $request->caption,
            'description' => $request->description,
            'file_path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size_bytes' => $file->getSize(),
            'week_number' => $request->week_number,
            'taken_at' => $request->taken_at ?? now(),
        ]);

        return back()->with('success', 'Photo added to portfolio.');
    }

    /**
     * Upload multiple photos at once.
     */
    public function storeBatch(Request $request, string $tenantSlug, Course $course): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:20'],
            'photos.*' => ['image', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
            'category' => ['required', 'string', 'in:' . implode(',', array_keys(PortfolioPhoto::CATEGORIES))],
            'section_id' => ['nullable', 'exists:sections,id'],
            'week_number' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $tenant = app('current_tenant');
        $count = 0;

        foreach ($request->file('photos') as $file) {
            $path = $file->store("portfolio/{$course->id}/" . date('Y-m'), 'public');
            $thumbnailPath = $this->generateThumbnail($file, $course->id, $path);

            PortfolioPhoto::create([
                'tenant_id' => $tenant->id,
                'course_id' => $course->id,
                'section_id' => $request->section_id,
                'user_id' => auth()->id(),
                'category' => $request->category,
                'file_path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size_bytes' => $file->getSize(),
                'week_number' => $request->week_number,
                'taken_at' => now(),
            ]);
            $count++;
        }

        return back()->with('success', "{$count} photo(s) added to portfolio.");
    }

    /**
     * Delete a portfolio photo.
     */
    public function destroy(string $tenantSlug, PortfolioPhoto $photo): RedirectResponse
    {
        if ($photo->user_id !== auth()->id()) {
            abort(403);
        }

        if (Storage::disk('public')->exists($photo->file_path)) {
            Storage::disk('public')->delete($photo->file_path);
        }
        if ($photo->thumbnail_path && Storage::disk('public')->exists($photo->thumbnail_path)) {
            Storage::disk('public')->delete($photo->thumbnail_path);
        }

        $photo->delete();

        return back()->with('success', 'Photo removed from portfolio.');
    }

    /**
     * Generate a thumbnail using GD.
     */
    private function generateThumbnail($file, int $courseId, string $originalPath): ?string
    {
        try {
            $mime = $file->getClientMimeType();
            $source = match ($mime) {
                'image/jpeg' => imagecreatefromjpeg($file->getRealPath()),
                'image/png' => imagecreatefrompng($file->getRealPath()),
                'image/webp' => imagecreatefromwebp($file->getRealPath()),
                default => null,
            };

            if (!$source) {
                return null;
            }

            $origW = imagesx($source);
            $origH = imagesy($source);
            $thumbW = 400;
            $thumbH = (int) round($origH * ($thumbW / $origW));

            $thumb = imagecreatetruecolor($thumbW, $thumbH);
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbW, $thumbH, $origW, $origH);

            $thumbDir = "portfolio/{$courseId}/thumbs";
            $thumbName = pathinfo($originalPath, PATHINFO_FILENAME) . '_thumb.jpg';
            $thumbnailPath = "{$thumbDir}/{$thumbName}";

            // Write to temp file then store
            $tempPath = tempnam(sys_get_temp_dir(), 'thumb');
            imagejpeg($thumb, $tempPath, 80);
            imagedestroy($source);
            imagedestroy($thumb);

            Storage::disk('public')->put($thumbnailPath, file_get_contents($tempPath));
            @unlink($tempPath);

            return $thumbnailPath;
        } catch (\Throwable) {
            return null;
        }
    }
}
