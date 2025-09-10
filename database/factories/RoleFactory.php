<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Role;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => $this->faker->words(3, true),
            'permissions' => config('quickfund.available_permissions')
        ];
    }

    /**
     * For the administrator user
     *
     * @return static
     */
    public function administrator()
    {
        return $this->state(function (array $attributes) {
            return [
                'id' => Role::ADMINISTRATOR,
                'name' => 'administrator',
                'permissions' => ['*']
            ];
        });
    }

    /**
     * For the collector user
     *
     * @return static
     */
    public function collector()
    {
        return $this->state(function (array $attributes) {
            return [
                'name' => 'collector',
                'permissions' => ['collection-cases']
            ];
        });
    }
}
