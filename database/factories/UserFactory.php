<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'remember_token' => Str::random(10),
            'profile_photo_path' => null,
            'current_team_id' => null,
            
            // --- NUEVOS CAMPOS DEL ERP ---
            'code' => fake()->unique()->numerify('EMP-####'),
            'phone' => fake()->numerify('##########'),
            'birthdate' => fake()->date('Y-m-d', '-20 years'), // Al menos 20 años de edad
            'civil_state' => fake()->randomElement(['Soltero(a)', 'Casado(a)', 'Unión libre']),
            'address' => fake()->address(),
            'rfc' => strtoupper(fake()->bothify('????######???')),
            'curp' => strtoupper(fake()->bothify('????######??????##')),
            'ssn' => fake()->numerify('###########'),
            'is_active' => true,
            'home_office' => false,
            'employees_in_charge' => [],
            'org_props' => [
                'entry_date' => fake()->date('Y-m-d', '-2 years'),
                'position' => fake()->jobTitle(),
                'department' => 'Operaciones', // Un departamento estándar
                'work_shift' => 'Diurno', // Turno por defecto
                'email' => fake()->unique()->companyEmail(),
                'phone' => fake()->numerify('##########'),
                'net_salary' => fake()->randomFloat(2, 8000, 30000),
                'biweekly_complement' => 0,
                'month_complement' => 0,
                'vacations' => 12, // Días por defecto
                'updated_date_vacations' => now()->toDateString(),
            ],
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user should have a personal team.
     */
    public function withPersonalTeam(?callable $callback = null): static
    {
        if (! Features::hasTeamFeatures()) {
            return $this->state([]);
        }

        return $this->has(
            Team::factory()
                ->state(fn (array $attributes, User $user) => [
                    'name' => $user->name.'\'s Team',
                    'user_id' => $user->id,
                    'personal_team' => true,
                ])
                ->when(is_callable($callback), $callback),
            'ownedTeams'
        );
    }
}