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
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->boolean(60) ? fake()->sentence(10) : null,
            'due_date' => fake()->boolean(40) ? now()->addDays(rand(1, 30))->toDateString() : null,
            'position' => fake()->numberBetween(0, 100),
        ];
    }

    public function childOf(Item $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn () => ['assignee_id' => $user->id]);
    }
}
