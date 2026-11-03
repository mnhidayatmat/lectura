<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Concerns\AuthorizesCourseAccess;
use App\Http\Controllers\Concerns\RedirectsToCourseContext;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\PortfolioPhoto;
use App\Services\PortfolioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    use AuthorizesCourseAccess;
    use RedirectsToCourseContext;

    /**
     * Portfolio overview — all courses with photo counts + full teaching portfolio.
     */
    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectToCourseContext('tenant.portfolio.course')) {
            return $redirect;
        }

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
    public function course(string $tenantSlug, Course $course, PortfolioService $portfolio): View
    {
        $this->authorizeCourseAccess($course);

        $photos = $portfolio->photosFor($course, auth()->user());
        $timeline = $portfolio->timeline($photos);

        $sections = $this->lecturerSections($course)->get();
        $categories = PortfolioPhoto::CATEGORIES;

        $photosByCategory = $photos->groupBy('category');
        $photosByWeek = $photos->groupBy('week_number')->sortKeys();

        return view('tenant.portfolio.course', compact('course', 'photos', 'sections', 'categories', 'photosByCategory', 'photosByWeek', 'timeline'));
    }

    /**
     * Store a new portfolio photo.
     */
    public function store(Request $request, string $tenantSlug, Course $course, PortfolioService $portfolio): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
            'category' => ['required', 'string', 'in:'.implode(',', array_keys(PortfolioPhoto::CATEGORIES))],
            'caption' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'week_number' => ['nullable', 'integer', 'min:1', 'max:20'],
            'taken_at' => ['nullable', 'date'],
        ]);

        $portfolio->store($request->file('photo'), $course, $request->user(), $validated);

        return back()->with('success', 'Photo added to portfolio.');
    }

    /**
     * Upload multiple photos at once.
     */
    public function storeBatch(Request $request, string $tenantSlug, Course $course, PortfolioService $portfolio): RedirectResponse
    {
        $this->authorizeCourseAccess($course);

        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:20'],
            'photos.*' => ['image', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
            'category' => ['required', 'string', 'in:'.implode(',', array_keys(PortfolioPhoto::CATEGORIES))],
            'section_id' => ['nullable', 'exists:sections,id'],
            'week_number' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $files = $request->file('photos');
        foreach ($files as $file) {
            $portfolio->store($file, $course, $request->user(), $validated);
        }

        return back()->with('success', count($files).' photo(s) added to portfolio.');
    }

    /**
     * The generated teaching portfolio: every week's photos with captions, as a PDF.
     */
    public function pdf(string $tenantSlug, Course $course, PortfolioService $portfolio): Response
    {
        $this->authorizeCourseAccess($course);

        return $portfolio->pdf($course, auth()->user())->download($portfolio->pdfFilename($course));
    }

    /**
     * Delete a portfolio photo.
     */
    public function destroy(string $tenantSlug, PortfolioPhoto $photo, PortfolioService $portfolio): RedirectResponse
    {
        if ($photo->user_id !== auth()->id()) {
            abort(403);
        }

        $portfolio->delete($photo);

        return back()->with('success', 'Photo removed from portfolio.');
    }
}
