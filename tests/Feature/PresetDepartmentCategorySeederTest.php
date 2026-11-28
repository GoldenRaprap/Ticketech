<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use Database\Seeders\PresetDepartmentCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PresetDepartmentCategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_creates_presets_for_each_department_and_can_be_repeated(): void
    {
        $technical = Department::factory()->create(['name' => 'Technical']);
        $existingCategory = Category::factory()->create([
            'name' => 'Learning Platform Issues',
            'description' => 'Existing custom description.',
            'classification_keywords' => 'existing custom keywords',
            'department_id' => $technical->id,
            'is_active' => false,
        ]);

        $seeder = app(PresetDepartmentCategorySeeder::class);
        $seeder->run();

        $this->assertDatabaseHas('departments', ['name' => 'EdTech Helpdesk']);
        $this->assertDatabaseHas('categories', [
            'name' => 'LMS and Course Access',
            'department_id' => Department::query()->where('name', 'EdTech Helpdesk')->value('id'),
        ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Computer and Laptop Repairs',
            'department_id' => $technical->id,
        ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Live Event and Streaming Support',
            'department_id' => Department::query()->where('name', 'Multimedia')->value('id'),
        ]);
        $this->assertDatabaseHas('categories', [
            'name' => 'Cybersecurity and Information Security',
            'department_id' => Department::query()->where('name', 'ICTC')->value('id'),
        ]);

        $this->assertSame('Existing custom description.', $existingCategory->fresh()->description);
        $this->assertSame('existing custom keywords', $existingCategory->fresh()->classification_keywords);
        $this->assertFalse($existingCategory->fresh()->is_active);

        $categoryCount = Category::query()->count();
        $seeder->run();

        $this->assertSame($categoryCount, Category::query()->count());
    }

    public function test_seeding_fails_when_a_preset_category_belongs_to_another_department(): void
    {
        $multimedia = Department::factory()->create(['name' => 'Multimedia']);
        Category::factory()->create([
            'name' => 'LMS and Course Access',
            'department_id' => $multimedia->id,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The preset category [LMS and Course Access] is already assigned to another department.');

        app(PresetDepartmentCategorySeeder::class)->run();
    }
}
