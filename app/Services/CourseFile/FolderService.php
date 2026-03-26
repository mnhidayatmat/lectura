<?php

declare(strict_types=1);

namespace App\Services\CourseFile;

use App\Models\Course;
use App\Models\CourseFolder;

class FolderService
{
    /**
     * Create default folder structure for a course.
     */
    public function createDefaultFolders(Course $course): void
    {
        $folders = config('lectura.default_folders', []);

        foreach ($folders as $i => $name) {
            CourseFolder::firstOrCreate(
                ['course_id' => $course->id, 'parent_id' => null, 'name' => $name],
                ['sort_order' => $i]
            );
        }
    }

    /**
     * Get folder tree for a course.
     */
    public function getFolderTree(Course $course): array
    {
        $folders = CourseFolder::where('course_id', $course->id)
            ->withCount('files')
            ->orderBy('sort_order')
            ->get();

        return $this->buildTree($folders);
    }

    protected function buildTree($folders, ?int $parentId = null): array
    {
        $tree = [];

        foreach ($folders->where('parent_id', $parentId) as $folder) {
            $tree[] = [
                'id' => $folder->id,
                'name' => $folder->name,
                'sort_order' => $folder->sort_order,
                'files_count' => $folder->files_count,
                'children' => $this->buildTree($folders, $folder->id),
            ];
        }

        return $tree;
    }
}
