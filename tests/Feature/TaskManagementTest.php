<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_can_be_created(): void
    {
        $response = $this->post('/projects', ['name' => 'Roadmap']);
        $project = Project::query()->firstOrFail();

        $response->assertRedirect(route('tasks.index', ['project' => $project->id]));
        $this->assertDatabaseHas('projects', ['name' => 'Roadmap']);
    }

    public function test_tasks_can_be_created_edited_deleted_and_reordered(): void
    {
        $project = Project::create(['name' => 'Launch']);

        $this->post('/tasks', ['name' => 'Write brief', 'project_id' => $project->id])
            ->assertRedirect(route('tasks.index', ['project' => $project->id]));
        $first = Task::query()->firstOrFail();

        $this->post('/tasks', ['name' => 'Review copy', 'project_id' => $project->id]);
        $second = Task::query()->where('name', 'Review copy')->firstOrFail();
        $this->assertSame(2, $second->priority);

        $this->patch("/tasks/{$first->id}", [
            'name' => 'Write final brief',
            'project_id' => $project->id,
        ])->assertRedirect(route('tasks.index', ['project' => $project->id]));

        $this->postJson('/tasks/reorder', [
            'project_id' => $project->id,
            'task_ids' => [$second->id, $first->id],
        ])->assertOk()->assertJson(['saved' => true]);

        $this->assertSame(1, $second->fresh()->priority);
        $this->assertSame(2, $first->fresh()->priority);

        $this->delete("/tasks/{$second->id}")->assertRedirect(route('tasks.index', ['project' => $project->id]));
        $this->assertSame(1, $first->fresh()->priority);
        $this->assertDatabaseHas('tasks', ['id' => $first->id, 'name' => 'Write final brief']);
    }

    public function test_tasks_are_filtered_by_project(): void
    {
        $firstProject = Project::create(['name' => 'Alpha']);
        $secondProject = Project::create(['name' => 'Beta']);
        $firstProject->tasks()->create(['name' => 'Alpha task', 'priority' => 1]);
        $secondProject->tasks()->create(['name' => 'Beta task', 'priority' => 1]);

        $this->get('/?project='.$firstProject->id)
            ->assertOk()
            ->assertSeeText('Alpha task')
            ->assertDontSeeText('Beta task');
    }
}
