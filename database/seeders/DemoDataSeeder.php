<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Favorite;
use App\Models\Item;
use App\Models\Label;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Labels ──────────────────────────────────────────────────────
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

        $admin = User::where('email', 'admin@example.com')->first();
        $alice = User::where('email', 'alice@example.com')->first();
        $bob = User::where('email', 'bob@example.com')->first();
        $carol = User::where('email', 'carol@example.com')->first();
        $otherUsers = [$alice, $bob, $carol];

        // ─── Projects ────────────────────────────────────────────────────
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

        $projects = [$project1->id, $project2->id, $project3->id];

        // ── Inbox: 3 записи для admin@example.com ────────────────────────
        foreach ([1,2,3] as $i) {
            $task = Item::factory()->create([
                'title' => "Inbox Task $i",
                'parent_id' => $projects[array_rand($projects)],
                'assignee_id' => $admin->id,
                'reporter_id' => $otherUsers[array_rand($otherUsers)]->id,
            ]);
            $task->labels()->sync([$labelTask->id, $labelTodo->id]);
            $task->to()->sync([$admin->id]);
        }

        // ── Sent: 3 записи для admin@example.com ─────────────────────────
        foreach ([1,2,3] as $i) {
            $task = Item::factory()->create([
                'title' => "Sent Task $i",
                'parent_id' => $projects[array_rand($projects)],
                'assignee_id' => $otherUsers[array_rand($otherUsers)]->id,
                'reporter_id' => $admin->id,
            ]);
            $task->labels()->sync([$labelTask->id, $labelInProgress->id]);
        }

        // ── Favorites: 3 записи для admin@example.com ────────────────────
        $allItems = Item::whereNotNull('parent_id')->get();
        foreach ($allItems->random(min(3, $allItems->count())) as $item) {
            Favorite::firstOrCreate([
                'user_id' => $admin->id,
                'favoritable_type' => Item::class,
                'favoritable_id' => $item->id,
            ], [
                'name' => $item->title,
                'url' => '/i/'.$item->id,
            ]);
        }
    }
}
