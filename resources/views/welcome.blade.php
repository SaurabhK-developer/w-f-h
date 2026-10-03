<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Taskline') }} · Task manager</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="app-shell">
        <header class="topbar">
            <a class="brand" href="{{ route('tasks.index') }}">
                <span class="brand-mark">T</span>
                <span>{{ config('app.name', 'Taskline') }}</span>
            </a>
            <div class="topbar-meta"><span class="status-dot"></span> WORKSPACE ONLINE</div>
        </header>

        <main class="workspace">
            <section class="page-heading">
                <div>
                    <p class="eyebrow">TASK SPACE / {{ now()->format('M Y') }}</p>
                    <h1>Tasks<span>.</span></h1>
                    <p class="heading-copy">A clear place for the work ahead.</p>
                </div>
                <div class="heading-stat">
                    <strong>{{ $tasks->count() }}</strong>
                    <span>{{ Str::plural('task', $tasks->count()) }} in view</span>
                </div>
            </section>

            @if (session('status'))
                <div class="notice" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="errors" role="alert">{{ $errors->first() }}</div>
            @endif

            <section class="toolbar">
                <form class="filter-form" method="GET" action="{{ route('tasks.index') }}">
                    <label class="field-label" for="project-filter">Viewing</label>
                    <span class="select-wrap">
                        <select class="filter-select" id="project-filter" name="project" data-auto-submit>
                            <option value="">All projects</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected($selectedProject?->id === $project->id)>
                                    {{ $project->name }} · {{ $project->tasks_count }}
                                </option>
                            @endforeach
                        </select>
                    </span>
                </form>
                <button class="button button-quiet" type="button" data-dialog-open="project-dialog">
                    <span aria-hidden="true">+</span> New project
                </button>
            </section>

            <div class="workspace-grid">
                <section class="main-column list-panel" aria-labelledby="task-list-title">
                    <header class="list-heading">
                        <div>
                            <h2 id="task-list-title">{{ $selectedProject?->name ?? 'All tasks' }}</h2>
                            <p>{{ $selectedProject ? 'Project task list' : 'Browse tasks across your projects' }}</p>
                        </div>
                        <span class="task-count">{{ str_pad((string) $tasks->count(), 2, '0', STR_PAD_LEFT) }} ITEMS</span>
                    </header>

                    @if ($projects->isNotEmpty())
                        <form class="quick-add" method="POST" action="{{ route('tasks.store') }}">
                            @csrf
                            <label for="task-name">Add a task</label>
                            <div class="quick-add-row">
                                <input class="text-input" id="task-name" name="name" type="text" maxlength="255" placeholder="What needs doing?" value="{{ old('name') }}" required>
                                <span class="select-wrap">
                                    <select name="project_id" aria-label="Task project" required>
                                        @foreach ($projects as $project)
                                            <option value="{{ $project->id }}" @selected((string) old('project_id', $selectedProject?->id ?? $projects->first()->id) === (string) $project->id)>
                                                {{ $project->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </span>
                                <button class="button button-accent" type="submit"><span aria-hidden="true">+</span> Add task</button>
                            </div>
                        </form>
                    @endif

                    @if ($tasks->isNotEmpty())
                        <ul class="task-list"
                            @if ($selectedProject)
                                data-sortable
                                data-project-id="{{ $selectedProject->id }}"
                                data-reorder-url="{{ route('tasks.reorder') }}"
                            @endif
                        >
                            @foreach ($tasks as $task)
                                <li class="task-row" data-task-row data-task-id="{{ $task->id }}">
                                    <button class="drag-handle" type="button" aria-label="Drag to reorder task" title="{{ $selectedProject ? 'Drag to reorder' : 'Choose a project to reorder' }}" @disabled(!$selectedProject) @if ($selectedProject) draggable="true" data-drag-handle @endif>::</button>
                                    <span class="priority-chip">#<span data-priority-value>{{ $task->priority }}</span></span>
                                    <div class="task-copy">
                                        <p class="task-name">{{ $task->name }}</p>
                                        <div class="task-meta">
                                            @if (!$selectedProject)
                                                <span class="task-project">{{ $task->project->name }}</span>
                                                <span aria-hidden="true">·</span>
                                            @endif
                                            <span>Added {{ $task->created_at->format('M j') }}</span>
                                        </div>
                                    </div>
                                    <div class="task-actions">
                                        <button class="text-action" type="button" data-dialog-open="edit-task-{{ $task->id }}">Edit</button>
                                        <form method="POST" action="{{ route('tasks.destroy', $task) }}" data-confirm-delete>
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-action delete" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @elseif ($projects->isEmpty())
                        <div class="empty-state">
                            <div class="empty-mark" aria-hidden="true">+</div>
                            <h3>Start with a project</h3>
                            <p>Create a project to organize your tasks.</p>
                            <button class="button button-primary empty-action" type="button" data-dialog-open="project-dialog">Create a project</button>
                        </div>
                    @else
                        <div class="empty-state">
                            <div class="empty-mark" aria-hidden="true">0</div>
                            <h3>No tasks here yet</h3>
                            <p>Add a task above to get started.</p>
                        </div>
                    @endif
                </section>

                <aside class="side-column">
                    <section class="side-panel" aria-labelledby="projects-title">
                        <header class="side-panel-header">
                            <h2 class="side-title" id="projects-title">Projects</h2>
                            <span class="side-total">{{ str_pad((string) $projects->count(), 2, '0', STR_PAD_LEFT) }}</span>
                        </header>
                        <nav class="project-nav" aria-label="Projects">
                            <a class="project-link {{ !$selectedProject ? 'active' : '' }}" href="{{ route('tasks.index') }}" @if (!$selectedProject) aria-current="page" @endif>
                                <span class="project-name"><span class="project-dot"></span>All projects</span>
                                <span class="project-count">{{ $projects->sum('tasks_count') }}</span>
                            </a>
                            @foreach ($projects as $project)
                                <a class="project-link {{ $selectedProject?->id === $project->id ? 'active' : '' }}" href="{{ route('tasks.index', ['project' => $project->id]) }}" @if ($selectedProject?->id === $project->id) aria-current="page" @endif>
                                    <span class="project-name"><span class="project-dot"></span>{{ $project->name }}</span>
                                    <span class="project-count">{{ $project->tasks_count }}</span>
                                </a>
                            @endforeach
                        </nav>
                        <button class="button button-quiet project-create" type="button" data-dialog-open="project-dialog">+ Add project</button>
                    </section>
                </aside>
            </div>
        </main>
    </div>

    <dialog class="modal" id="project-dialog">
        <form class="modal-form" method="POST" action="{{ route('projects.store') }}">
            @csrf
            <header class="modal-header">
                <div>
                    <h2>New project</h2>
                    <p>Give this project a name.</p>
                </div>
                <button class="modal-close" type="button" data-dialog-close aria-label="Close">×</button>
            </header>
            <div class="modal-field">
                <label for="project-name">Project name</label>
                <input class="text-input" id="project-name" name="name" type="text" maxlength="120" value="{{ old('name') }}" required autofocus>
            </div>
            <div class="modal-actions">
                <button class="button button-quiet" type="button" data-dialog-close>Cancel</button>
                <button class="button button-primary" type="submit">Create project</button>
            </div>
        </form>
    </dialog>

    @foreach ($tasks as $task)
        <dialog class="modal" id="edit-task-{{ $task->id }}">
            <form class="modal-form" method="POST" action="{{ route('tasks.update', $task) }}">
                @csrf
                @method('PATCH')
                <header class="modal-header">
                    <div>
                        <h2>Edit task</h2>
                        <p>Update the task or move it to another project.</p>
                    </div>
                    <button class="modal-close" type="button" data-dialog-close aria-label="Close">×</button>
                </header>
                <div class="modal-field">
                    <label for="task-name-{{ $task->id }}">Task name</label>
                    <input class="text-input" id="task-name-{{ $task->id }}" name="name" type="text" maxlength="255" value="{{ $task->name }}" required>
                </div>
                <div class="modal-field">
                    <label for="task-project-{{ $task->id }}">Project</label>
                    <span class="select-wrap">
                        <select id="task-project-{{ $task->id }}" name="project_id" required>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected($task->project_id === $project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </span>
                </div>
                <div class="modal-actions">
                    <button class="button button-quiet" type="button" data-dialog-close>Cancel</button>
                    <button class="button button-primary" type="submit">Save changes</button>
                </div>
            </form>
        </dialog>
    @endforeach
</body>
</html>
