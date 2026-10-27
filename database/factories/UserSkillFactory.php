<?php

namespace Database\Factories;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserSkillFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'skill_id' => Skill::factory(), 'self_reported_level' => 2, 'level' => 2, 'verification_status' => 'self_reported'];
    }
}
