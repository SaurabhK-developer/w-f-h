<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $projects = Project::query()->withCount('tasks')->orderBy('name')->get();
        $selectedProject = $request->filled('project')
            ? Project::findOrFail($request->integer('project'))
            : null;

        $tasks = Task::query()
            ->with('project')
            ->when($selectedProject, fn ($query) => $query->where('project_id', $selectedProject->id))
            ->when(! $selectedProject, fn ($query) => $query->join('projects', 'tasks.project_id', '=', 'projects.id')
                ->orderBy('projects.name'))
            ->orderBy('tasks.priority')
            ->orderBy('tasks.id')
            ->select('tasks.*')
            ->get();

        return view('welcome', [
            'projects' => $projects,
            'selectedProject' => $selectedProject,
            'tasks' => $tasks,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $priority = ($project->tasks()->max('priority') ?? 0) + 1;

        $project->tasks()->create([
            'name' => $validated['name'],
            'priority' => $priority,
        ]);

        return redirect()->route('tasks.index', ['project' => $project->id]);
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'project_id' => ['required', 'integer', 'exists:projects,id'],
        ]);

        $redirectProjectId = (int) $validated['project_id'];

        DB::transaction(function () use ($task, $validated, &$redirectProjectId): void {
            $task = Task::query()->lockForUpdate()->findOrFail($task->id);
            $oldProjectId = $task->project_id;
            $newProjectId = (int) $validated['project_id'];

            if ($oldProjectId !== $newProjectId) {
                $task->project_id = $newProjectId;
                $task->priority = (Project::findOrFail($newProjectId)->tasks()->max('priority') ?? 0) + 1;
            }

            $task->name = $validated['name'];
            $task->save();

            if ($oldProjectId !== $newProjectId) {
                $this->normalizePriorities($oldProjectId);
            }

            $redirectProjectId = $newProjectId;
        });

        return redirect()->route('tasks.index', ['project' => $redirectProjectId]);
    }

    public function destroy(Task $task): RedirectResponse
    {
        $projectId = $task->project_id;

        DB::transaction(function () use ($task, $projectId): void {
            Task::query()->lockForUpdate()->findOrFail($task->id)->delete();
            $this->normalizePriorities($projectId);
        });

        return redirect()->route('tasks.index', ['project' => $projectId]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'task_ids' => ['required', 'array'],
            'task_ids.*' => ['required', 'integer', 'distinct', 'exists:tasks,id'],
        ]);

        $projectId = (int) $validated['project_id'];
        $taskIds = array_map('intval', $validated['task_ids']);

        DB::transaction(function () use ($projectId, $taskIds): void {
            $expectedIds = Task::query()
                ->where('project_id', $projectId)
                ->orderBy('priority')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $sortedTaskIds = $taskIds;
            sort($sortedTaskIds);
            $sortedExpectedIds = $expectedIds;
            sort($sortedExpectedIds);

            abort_unless($sortedTaskIds === $sortedExpectedIds, 422);

            foreach ($taskIds as $index => $taskId) {
                Task::query()
                    ->where('project_id', $projectId)
                    ->whereKey($taskId)
                    ->where('priority', '!=', $index + 1)
                    ->update([
                        'priority' => $index + 1,
                        'updated_at' => now(),
                    ]);
            }
        });

        return response()->json(['saved' => true]);
    }

    private function normalizePriorities(int $projectId): void
    {
        Task::query()
            ->where('project_id', $projectId)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->each(function (Task $task, int $index): void {
                $priority = $index + 1;

                if ($task->priority !== $priority) {
                    $task->update([
                        'priority' => $priority,
                    ]);
                }
            });
    }
}
