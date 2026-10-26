<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SkillCatalogAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_organize_existing_skill_and_search_its_alias(): void
    {
        $admin = User::factory()->admin()->create();
        $skill = Skill::factory()->create(['name' => 'Operasi Forklift']);
        $this->actingAs($admin)->get('/admin/skill')->assertOk()->assertSee('Operasi Forklift')->assertDontSee('Tambah Skill');
        $this->get(route('admin.skills.edit', $skill))->assertOk();
        $this->patch(route('admin.skills.update', $skill), ['name' => '  Operasi   Forklift ', 'category' => 'Logistik',
            'aliases' => "Pengoperasian Forklift\nOperator Forklift", 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Operasi Forklift', $skill->fresh()->name);
        $this->assertDatabaseHas('skill_names', ['normalized_name' => 'pengoperasian forklift', 'skill_id' => $skill->id]);
        $this->get('/admin/skill?q=OPERATOR%20FORKLIFT')->assertOk()->assertSee('Operasi Forklift');
        $this->patch(route('admin.skills.update', $skill), ['name' => $skill->fresh()->name, 'category' => 'Logistik', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($skill->fresh()->is_active);
        $this->assertDatabaseHas('skills', ['id' => $skill->id]);
        $this->get('/admin/skill?status=active')->assertViewHas('skills', fn ($skills): bool => $skills->isEmpty());
    }

    public function test_names_and_aliases_cannot_collide_and_failed_edits_are_atomic(): void
    {
        $first = Skill::factory()->create(['name' => 'Operasi Forklift']);
        $second = Skill::factory()->create(['name' => 'Pencatatan Persediaan']);
        $this->actingAs(User::factory()->admin()->create());
        $this->patch(route('admin.skills.update', $second), ['name' => $second->name, 'category' => 'Berubah',
            'aliases' => ' OPERASI   FORKLIFT ', 'is_active' => 0])->assertSessionHasErrors('name');
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame('Logistik', $second->fresh()->category);
        $this->assertSame(2, DB::table('skill_names')->count());
        $this->patch(route('admin.skills.update', $first), ['name' => $first->name, 'category' => 'Logistik',
            'aliases' => 'operasi forklift', 'is_active' => 1])->assertSessionHasErrors('aliases');
    }

    public function test_applicant_cannot_access_or_modify_admin_skill_data(): void
    {
        $skill = Skill::factory()->create();
        $this->actingAs(User::factory()->create())->get('/admin/skill')->assertForbidden();
        $this->get(route('admin.skills.edit', $skill))->assertForbidden();
        $this->patch(route('admin.skills.update', $skill), ['name' => 'Dibajak'])->assertForbidden();
        $this->assertNotSame('Dibajak', $skill->fresh()->name);
    }

    public function test_admin_has_no_public_skill_creation_or_deletion_endpoint(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post('/admin/skill', ['name' => 'Baru'])->assertStatus(405);
        $this->assertDatabaseCount('skills', 0);
        $skill = Skill::factory()->create();
        $this->delete('/admin/skill/'.$skill->id)->assertStatus(405);
        $this->assertDatabaseCount('skills', 1);
    }

    public function test_empty_catalog_and_invalid_filters_are_handled(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/skill')->assertOk()->assertSee('Belum ada keterampilan');
        $this->get('/admin/skill?status=unknown')->assertSessionHasErrors('status');
        $this->get('/admin/skill?q=%25')->assertOk();
    }
}
