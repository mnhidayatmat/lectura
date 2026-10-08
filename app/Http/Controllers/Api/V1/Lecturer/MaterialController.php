<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Lecturer;

use App\Http\Controllers\Api\V1\Lecturer\Concerns\AuthorizesLecturerAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\MaterialItemResource;
use App\Models\Course;
use App\Models\CourseFile;
use App\Models\CourseFolder;
use App\Models\CourseMaterialSection;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Weekly course materials for the lecturer — the web's CourseMaterialController, with every
 * section and item checked against the course.
 */
class MaterialController extends Controller
{
    use AuthorizesLecturerAccess;

    /**
     * Every section, hidden and empty ones included (students only see visible sections with items).
     */
    public function index(Course $course): JsonResponse
    {
        $this->authorizeMaterials($course);

        $sections = $course->materialSections()->with('files')->get();

        return response()->json([
            'data' => [
                'course' => ['id' => $course->id, 'code' => $course->code, 'title' => $course->title],
                'sections' => $sections->map(fn (CourseMaterialSection $section) => $this->section($section))->values(),
            ],
        ]);
    }

    public function storeSection(Request $request, Course $course): JsonResponse
    {
        $this->authorizeMaterials($course);

        $validated = $request->validate(['title' => ['required', 'string', 'max:255']]);

        $section = $course->materialSections()->create([
            'title' => $validated['title'],
            'sort_order' => (int) $course->materialSections()->max('sort_order') + 1,
            'is_visible' => true,
        ]);

        return response()->json(['message' => 'Section added.', 'data' => $this->section($section->load('files'))], 201);
    }

    /**
     * Title and visibility. The web has no visibility switch; hidden sections disappear for students.
     */
    public function updateSection(Request $request, Course $course, CourseMaterialSection $section): JsonResponse
    {
        $this->authorizeSection($course, $section);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'is_visible' => ['sometimes', 'boolean'],
        ]);

        $section->update($validated);

        return response()->json(['message' => 'Section updated.', 'data' => $this->section($section->load('files'))]);
    }

    public function destroySection(Course $course, CourseMaterialSection $section): JsonResponse
    {
        $this->authorizeSection($course, $section);

        $count = $section->files->count();

        DB::transaction(function () use ($section) {
            foreach ($section->files as $file) {
                $this->deleteStorage($file);
                $file->forceDelete();
            }
            $section->delete();
        });

        return response()->json([
            'message' => $count > 0 ? "Section and its {$count} ".($count === 1 ? 'item' : 'items').' deleted.' : 'Section deleted.',
            'data' => ['id' => $section->id],
        ]);
    }

    public function moveSection(Request $request, Course $course, CourseMaterialSection $section): JsonResponse
    {
        $this->authorizeSection($course, $section);

        $request->validate(['direction' => ['required', 'in:up,down']]);

        $ids = $course->materialSections()->pluck('id')->all();
        $position = array_search($section->id, $ids, true);
        $target = $request->input('direction') === 'up' ? $position - 1 : $position + 1;

        if ($target >= 0 && $target < count($ids)) {
            [$ids[$position], $ids[$target]] = [$ids[$target], $ids[$position]];

            foreach ($ids as $order => $id) {
                CourseMaterialSection::where('id', $id)->update(['sort_order' => $order]);
            }
        }

        return response()->json(['message' => 'Section moved.', 'data' => ['order' => $ids]]);
    }

    /**
     * One file per request. Stored on Lectura (as the web does when Drive is not connected),
     * never pushed to Drive from the phone.
     */
    public function upload(Request $request, Course $course, CourseMaterialSection $section): JsonResponse
    {
        $this->authorizeSection($course, $section);

        $request->validate([
            'file' => ['required', 'file', 'max:25600'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $upload = $request->file('file');
        $folder = CourseFolder::firstOrCreate(
            ['course_id' => $course->id, 'name' => 'Weekly Materials', 'parent_id' => null],
            ['sort_order' => 2]
        );

        $file = CourseFile::create([
            'course_folder_id' => $folder->id,
            'course_id' => $course->id,
            'uploaded_by' => $request->user()->id,
            'material_type' => 'file',
            'file_name' => $request->filled('title') ? $request->string('title')->toString() : $upload->getClientOriginalName(),
            'file_type' => $upload->getMimeType(),
            'file_size_bytes' => $upload->getSize(),
            'storage_path' => $upload->store("course-files/{$course->id}/{$folder->id}", 'uploads'),
            'description' => $request->input('description'),
            'material_section_id' => $section->id,
            'sort_order' => (int) CourseFile::where('material_section_id', $section->id)->max('sort_order') + 1,
        ]);

        return response()->json(['message' => 'File uploaded.', 'data' => $this->item($file)], 201);
    }

    public function storeLink(Request $request, Course $course, CourseMaterialSection $section): JsonResponse
    {
        $this->authorizeSection($course, $section);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $file = CourseFile::create([
            'course_id' => $course->id,
            'uploaded_by' => $request->user()->id,
            'material_type' => 'link',
            'file_name' => $validated['title'],
            'url' => $validated['url'],
            'description' => $validated['description'] ?? null,
            'material_section_id' => $section->id,
            'sort_order' => (int) CourseFile::where('material_section_id', $section->id)->max('sort_order') + 1,
        ]);

        return response()->json(['message' => 'Link added.', 'data' => $this->item($file)], 201);
    }

    /**
     * Title, description, the URL of a link, and — new on the phone — which section it sits in.
     */
    public function updateItem(Request $request, Course $course, CourseFile $file): JsonResponse
    {
        $this->authorizeItem($course, $file);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'url' => $file->isLink() ? ['sometimes', 'required', 'url', 'max:2048'] : ['prohibited'],
            'material_section_id' => ['sometimes', 'integer'],
        ]);

        if (isset($validated['material_section_id']) && ! $course->materialSections()->whereKey($validated['material_section_id'])->exists()) {
            abort(422, 'That section is not part of this course.');
        }

        $file->update(array_filter([
            'file_name' => $validated['title'] ?? null,
            'url' => $validated['url'] ?? null,
            'material_section_id' => $validated['material_section_id'] ?? null,
        ], fn ($value) => $value !== null) + (array_key_exists('description', $validated) ? ['description' => $validated['description']] : []));

        return response()->json(['message' => 'Material updated.', 'data' => $this->item($file->refresh())]);
    }

    public function destroyItem(Course $course, CourseFile $file): JsonResponse
    {
        $this->authorizeItem($course, $file);

        $this->deleteStorage($file);
        $file->delete();

        return response()->json(['message' => 'Material deleted.', 'data' => ['id' => $file->id]]);
    }

    private function authorizeMaterials(Course $course): void
    {
        $this->ensureLecturer();
        $this->authorizeCourse($course);
    }

    private function authorizeSection(Course $course, CourseMaterialSection $section): void
    {
        $this->authorizeMaterials($course);

        if ($section->course_id !== $course->id) {
            abort(404, 'Section not found.');
        }
    }

    private function authorizeItem(Course $course, CourseFile $file): void
    {
        $this->authorizeMaterials($course);

        // Course files carry no tenant scope of their own; the course check covers it.
        if ($file->course_id !== $course->id || $file->material_section_id === null) {
            abort(404, 'Material not found.');
        }
    }

    private function deleteStorage(CourseFile $file): void
    {
        if ($file->isDriveFile() && $file->drive_file_id) {
            try {
                $uploader = User::find($file->uploaded_by);
                if ($uploader?->isDriveConnected()) {
                    app(GoogleDriveService::class)->deleteFile($uploader, $file->drive_file_id);
                }
            } catch (\Throwable $e) {
                // As on the web: a Drive failure must not keep the material around.
                report($e);
            }
        } elseif ($file->storage_path) {
            Storage::disk('uploads')->delete($file->storage_path);
        }
    }

    private function section(CourseMaterialSection $section): array
    {
        return [
            'id' => $section->id,
            'title' => $section->title,
            'is_visible' => (bool) $section->is_visible,
            'sort_order' => (int) $section->sort_order,
            'items' => $section->files->map(fn (CourseFile $file) => $this->item($file))->values(),
        ];
    }

    private function item(CourseFile $file): array
    {
        return array_merge((new MaterialItemResource($file))->resolve(), [
            'sort_order' => (int) $file->sort_order,
            'material_section_id' => $file->material_section_id,
        ]);
    }
}
