<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Label;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ─── Roles ────────────────────────────────────────────────────────

        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'manager']);
        Role::firstOrCreate(['name' => 'user']);

        // ─── Users ────────────────────────────────────────────────────────

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => Hash::make('password')]
        );
        $admin->assignRole('admin');

        $alice = User::updateOrCreate(
            ['email' => 'alice@example.com'],
            ['name' => 'Alice Johnson', 'password' => Hash::make('password')]
        );
        $alice->assignRole('manager');

        $bob = User::updateOrCreate(
            ['email' => 'bob@example.com'],
            ['name' => 'Bob Williams', 'password' => Hash::make('password')]
        );
        $bob->assignRole('user');

        $carol = User::updateOrCreate(
            ['email' => 'carol@example.com'],
            ['name' => 'Carol Davis', 'password' => Hash::make('password')]
        );
        $carol->assignRole('user');

        // ─── Default Labels ──────────────────────────────────────────────────

        $labels = [
            ['name' => 'Task', 'color' => '#3b82f6'],
            ['name' => 'Bug', 'color' => '#ef4444'],
            ['name' => 'Feature', 'color' => '#8b5cf6'],
            ['name' => 'Epic', 'color' => '#f59e0b'],
            ['name' => 'To Do', 'color' => '#6b7280'],
            ['name' => 'In Progress', 'color' => '#f59e0b'],
            ['name' => 'Review', 'color' => '#3b82f6'],
            ['name' => 'Done', 'color' => '#10b981'],
            ['name' => 'Low', 'color' => '#6b7280'],
            ['name' => 'Medium', 'color' => '#3b82f6'],
            ['name' => 'High', 'color' => '#f59e0b'],
            ['name' => 'Critical', 'color' => '#ef4444'],
        ];

        foreach ($labels as $data) {
            Label::firstOrCreate(['name' => $data['name']], $data);
        }

        $labelTask = Label::where('name', 'Task')->first();
        $labelEpic = Label::where('name', 'Epic')->first();
        $labelInProgress = Label::where('name', 'In Progress')->first();
        $labelTodo = Label::where('name', 'To Do')->first();
        $labelDone = Label::where('name', 'Done')->first();
        $labelHigh = Label::where('name', 'High')->first();
        $labelMedium = Label::where('name', 'Medium')->first();

        // ─── Projects ─────────────────────────────────────────────────────

        $project1 = Item::factory()->create([
            'title' => 'AgileTracker Core',
            'description' => 'Core functionality for the AgileTracker project management tool.',
        ]);
        $project1->labels()->sync([$labelEpic->id, $labelInProgress->id, $labelHigh->id]);

        $project2 = Item::factory()->create([
            'title' => 'Mobile App',
            'description' => 'React Native mobile application for on-the-go task management.',
        ]);
        $project2->labels()->sync([$labelEpic->id, $labelTodo->id, $labelMedium->id]);

        $project3 = Item::factory()->create([
            'title' => 'API v2',
            'description' => 'Public REST API for third-party integrations.',
        ]);
        $project3->labels()->sync([$labelEpic->id, $labelDone->id, $labelHigh->id]);

        // ─── Tasks ────────────────────────────────────────────────────────

        $users = [$admin, $alice, $bob, $carol];
        $projects = [$project1->id, $project2->id, $project3->id];

        $taskTitles = [
            'Set up CI/CD pipeline',
            'Write unit tests for auth module',
            'Fix pagination on search results',
            'Add export to CSV feature',
            'Implement dark mode toggle',
            'Optimize database queries',
            'Create onboarding wizard',
            'Add rate limiting to API',
            'Fix mobile layout issues',
            'Refactor notification service',
            'Update dependencies',
            'Build dashboard analytics',
        ];

        foreach ($taskTitles as $i => $title) {
            $startDate = fake()->boolean(70) ? now()->subDays(rand(1, 30))->toDateString() : null;

            $task = Item::factory()->create([
                'title' => $title,
                'description' => fake()->boolean(50) ? fake()->sentence(8) : null,
                'parent_id' => $projects[array_rand($projects)],
                'assignee_id' => $users[array_rand($users)]->id,
                'reporter_id' => $admin->id,
                'due_date' => fake()->boolean(60) ? now()->addDays(rand(1, 45)) : null,
                'start_date' => $startDate,
                'end_date' => $startDate ? now()->parse($startDate)->addDays(rand(1, 20))->toDateString() : null,
                'etd_date' => fake()->boolean(40) ? now()->subDays(rand(0, 5))->toDateString() : null,
                'eta_date' => fake()->boolean(40) ? now()->addDays(rand(1, 14))->toDateString() : null,
                'position' => $i,
            ]);

            $task->labels()->attach($labelTask);
        }
    }
}
