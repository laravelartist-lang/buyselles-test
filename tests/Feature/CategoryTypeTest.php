<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Database\Schema\Blueprint;
use Tests\Concerns\ManagesTestDatabaseSchema;
use Tests\TestCase;

class CategoryTypeTest extends TestCase
{
    use ManagesTestDatabaseSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recreateTable('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->unsignedBigInteger('parent_id')->default(0);
            $table->integer('position')->default(0);
            $table->string('category_type')->default('physical');
            $table->timestamps();
        });

        $this->recreateTable('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->nullable();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $this->app['db']->table('business_settings')->insert([
            'type' => 'language',
            'value' => json_encode([['code' => 'en', 'default' => true, 'direction' => 'ltr']]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recreateTable('translations', function (Blueprint $table): void {
            $table->id();
            $table->string('translationable_type')->nullable();
            $table->unsignedBigInteger('translationable_id')->nullable();
            $table->string('locale')->nullable();
            $table->string('key')->nullable();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function test_category_type_cascades_to_subcategories(): void
    {
        $mainCategory = Category::create([
            'name' => 'Main Category',
            'slug' => 'main-category',
            'parent_id' => 0,
            'position' => 0,
            'category_type' => 'physical',
        ]);

        $subCategory = Category::create([
            'name' => 'Sub Category',
            'slug' => 'sub-category',
            'parent_id' => $mainCategory->id,
            'position' => 1,
            'category_type' => 'physical',
        ]);

        $subSubCategory = Category::create([
            'name' => 'Sub Sub Category',
            'slug' => 'sub-sub-category',
            'parent_id' => $subCategory->id,
            'position' => 2,
            'category_type' => 'physical',
        ]);

        $this->app['db']->table('categories')->where('id', $mainCategory->id)->update(['category_type' => 'digital']);

        $subCategories = Category::where('parent_id', $mainCategory->id)->get();
        foreach ($subCategories as $subCategory) {
            $subCategory->update(['category_type' => 'digital']);
            Category::where('parent_id', $subCategory->id)->update(['category_type' => 'digital']);
        }

        $this->assertEquals('digital', $subCategory->fresh()->category_type);
        $this->assertEquals('digital', $subSubCategory->fresh()->category_type);
    }
}
