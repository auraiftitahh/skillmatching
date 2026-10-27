<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SkillRelationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_relation_is_directional_and_can_be_removed_without_removing_skills(): void
    {
        [$a,$b] = Skill::factory()->count(2)->create()->all();
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.skills.relations', $a))->assertOk()->assertSee($a->name);
        $this->post(route('admin.skills.relations.store', $a), ['related_skill_id' => $b->id, 'reason' => 'Kemampuan dasar mendukung kompetensi tujuan.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('skill_relations', ['skill_id' => $a->id, 'related_skill_id' => $b->id]);
        $this->assertDatabaseMissing('skill_relations', ['skill_id' => $b->id, 'related_skill_id' => $a->id]);
        $id = DB::table('skill_relations')->value('id');
        $this->delete(route('admin.skills.relations.destroy', ['skill' => $a, 'relation' => $id]))->assertRedirect();
        $this->assertDatabaseCount('skill_relations', 0);
        $this->assertDatabaseCount('skills', 2);
    }

    public function test_self_duplicate_inactive_and_unexplained_relations_are_rejected(): void
    {
        [$a,$b] = Skill::factory()->count(2)->create()->all();
        $this->actingAs(User::factory()->admin()->create());
        $url = route('admin.skills.relations.store', $a);
        $this->post($url, ['related_skill_id' => $a->id, 'reason' => 'Tidak boleh sama.'])->assertSessionHasErrors('related_skill_id');
        $this->post($url, ['related_skill_id' => $b->id])->assertSessionHasErrors('reason');
        $this->post($url, ['related_skill_id' => $b->id, 'reason' => 'Hubungan relevan.'])->assertSessionHasNoErrors();
        $this->post($url, ['related_skill_id' => $b->id, 'reason' => 'Hubungan relevan.'])->assertSessionHasErrors('related_skill_id');
        $b->update(['is_active' => false]);
        $this->post($url, ['related_skill_id' => $b->id, 'reason' => 'Tidak aktif.'])->assertSessionHasErrors('related_skill_id');
        $this->assertDatabaseCount('skill_relations', 1);
    }

    public function test_only_admin_can_manage_relations_and_deletion_is_scoped(): void
    {
        [$a,$b,$c] = Skill::factory()->count(3)->create()->all();
        $id = DB::table('skill_relations')->insertGetId(['skill_id' => $a->id, 'related_skill_id' => $b->id, 'reason' => 'Dasar relasi']);
        $this->actingAs(User::factory()->create())->get(route('admin.skills.relations', $a))->assertForbidden();
        $this->delete(route('admin.skills.relations.destroy', ['skill' => $a, 'relation' => $id]))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->delete(route('admin.skills.relations.destroy', ['skill' => $c, 'relation' => $id]))->assertNotFound();
        $this->assertDatabaseCount('skill_relations',1);
    }
}
