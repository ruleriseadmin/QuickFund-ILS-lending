<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\{User, Role};

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function unverified()
    {
        return $this->state(function (array $attributes) {
            return [
                'email_verified_at' => null,
            ];
        });
    }

    /**
     * For an administrator user
     *
     * @return static
     */
    public function administrators()
    {
        return $this->state(function (array $attributes) {
            return [
                'role_id' => Role::ADMINISTRATOR
            ];
        });
    }

    /**
     * For the application user
     *
     * @return static
     */
    public function application()
    {
        return $this->state(function (array $attributes) {
            return [
                'id' => User::APPLICATION_ID
            ];
        });
    }

    /**
     * For the interswitch user
     *
     * @return static
     */
    public function interswitch()
    {
        return $this->state(function (array $attributes) {
            return [
                'id' => User::INTERSWITCH_ID,
                'name' => 'Interswitch',
                'email' => 'lending_quick@quickfundmfb.com',
                'password' => Hash::make('quick@lending_2018')
            ];
        });
    }
}
