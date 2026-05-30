<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['task', 'epic', 'project', 'case']);

        return [
            'type' => $type,
            'title' => $this->titleForType($type),
            'description' => fake()->boolean(60) ? fake()->sentence(10) : null,
            'status' => fake()->randomElement(['todo', 'in_progress', 'review', 'done']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'due_date' => fake()->boolean(40) ? now()->addDays(rand(1, 30))->toDateString() : null,
            'estimated_minutes' => fake()->boolean(50) ? fake()->numberBetween(30, 480) : null,
            'spent_minutes' => 0,
            'position' => fake()->numberBetween(0, 100),
        ];
    }

    public function task(): static
    {
        return $this->state(fn () => ['type' => 'task']);
    }

    public function case(): static
    {
        return $this->state(fn () => ['type' => 'case']);
    }

    public function project(): static
    {
        return $this->state(fn () => ['type' => 'project', 'parent_id' => null]);
    }

    public function epic(): static
    {
        return $this->state(fn () => ['type' => 'epic']);
    }

    public function childOf(Item $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn () => ['assignee_id' => $user->id]);
    }

    private function titleForType(string $type): string
    {
        return match ($type) {
            'task' => fake()->randomElement([
                'Set up CI/CD pipeline',
                'Write unit tests for auth module',
                'Update API documentation',
                'Fix pagination on search results',
                'Add export to CSV feature',
                'Refactor user notification service',
                'Implement dark mode toggle',
                'Optimize database queries',
                'Update dependencies to latest versions',
                'Add rate limiting to API endpoints',
                'Create onboarding wizard',
                'Fix mobile layout issues',
            ]),
            'case' => fake()->randomElement([
                'Login page crashes on Safari',
                'Email notifications not sending',
                'Dashboard charts rendering incorrectly',
                'File upload fails for files > 10MB',
                'Search returns incorrect results',
                'PDF export missing data in footer',
            ]),
            'epic' => fake()->randomElement([
                'Q3 Platform Modernization',
                'Performance Optimization Sprint',
                'Accessibility Compliance',
            ]),
            'project' => fake()->randomElement([
                'AgileTracker Core',
                'Mobile App',
                'API v2',
            ]),
            default => fake()->sentence(4),
        };
    }
}
