<?php

namespace Tests\Feature;

use App\Models\Tip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTipTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test admin can view tips index (list).
     */
    public function test_admin_can_view_tips_index(): void
    {
        $admin = $this->createAdmin();
        Tip::factory()->count(3)->create(['created_by' => $admin->id]);

        $response = $this->actingAs($admin)->get(route('admin.tips.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.tips.index');
        $response->assertViewHas('tips');
    }

    /**
     * Test admin can view create tip form.
     */
    public function test_admin_can_view_create_tip_form(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get(route('admin.tips.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.tips.create');
    }

    /**
     * Test admin can store a new tip.
     */
    public function test_admin_can_store_tip(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.tips.store'), [
            'title' => 'Test Tip Title',
            'content' => 'Test tip content for users.',
            'type' => 'general',
            'status' => 'published',
            'language' => 'en',
        ]);

        $response->assertRedirect(route('admin.tips.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tips', [
            'title' => 'Test Tip Title',
            'content' => 'Test tip content for users.',
            'type' => 'general',
            'status' => 'published',
            'language' => 'en',
            'created_by' => $admin->id,
        ]);
    }

    /**
     * Test admin cannot store tip with invalid data.
     */
    public function test_admin_cannot_store_tip_with_invalid_data(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post(route('admin.tips.store'), [
            'title' => '',
            'content' => '',
            'type' => 'invalid',
            'status' => 'invalid',
            'language' => 'invalid',
        ]);

        $response->assertSessionHasErrors(['title', 'content', 'type', 'status', 'language']);
        $this->assertDatabaseCount('tips', 0);
    }

    /**
     * Test admin can view single tip (show).
     */
    public function test_admin_can_view_tip_show(): void
    {
        $admin = $this->createAdmin();
        $tip = Tip::factory()->create(['created_by' => $admin->id]);

        $response = $this->actingAs($admin)->get(route('admin.tips.show', $tip));

        $response->assertStatus(200);
        $response->assertViewIs('admin.tips.show');
        $response->assertViewHas('tip', fn ($t) => $t->id === $tip->id);
    }

    /**
     * Test admin can view edit tip form.
     */
    public function test_admin_can_view_edit_tip_form(): void
    {
        $admin = $this->createAdmin();
        $tip = Tip::factory()->create(['created_by' => $admin->id]);

        $response = $this->actingAs($admin)->get(route('admin.tips.edit', $tip));

        $response->assertStatus(200);
        $response->assertViewIs('admin.tips.edit');
        $response->assertViewHas('tip', fn ($t) => $t->id === $tip->id);
    }

    /**
     * Test admin can update a tip.
     */
    public function test_admin_can_update_tip(): void
    {
        $admin = $this->createAdmin();
        $tip = Tip::factory()->create([
            'title' => 'Old Title',
            'content' => 'Old content',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.tips.update', $tip), [
            'title' => 'Updated Tip Title',
            'content' => 'Updated tip content.',
            'type' => 'weekly',
            'status' => 'draft',
            'language' => 'ar',
        ]);

        $response->assertRedirect(route('admin.tips.index'));
        $response->assertSessionHas('success');

        $tip->refresh();
        $this->assertSame('Updated Tip Title', $tip->title);
        $this->assertSame('Updated tip content.', $tip->content);
        $this->assertSame('weekly', $tip->type);
        $this->assertSame('draft', $tip->status);
        $this->assertSame('ar', $tip->language);
    }

    /**
     * Test admin can delete a tip.
     */
    public function test_admin_can_destroy_tip(): void
    {
        $admin = $this->createAdmin();
        $tip = Tip::factory()->create(['created_by' => $admin->id]);
        $tipId = $tip->id;

        $response = $this->actingAs($admin)->delete(route('admin.tips.destroy', $tip));

        $response->assertRedirect(route('admin.tips.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('tips', ['id' => $tipId]);
    }

    /**
     * Test admin can toggle tip status (publish/unpublish).
     */
    public function test_admin_can_toggle_tip_status(): void
    {
        $admin = $this->createAdmin();
        $tip = Tip::factory()->create([
            'status' => 'published',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.tips.toggle-status', $tip));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $tip->refresh();
        $this->assertSame('draft', $tip->status);

        // Toggle again: draft -> published
        $this->actingAs($admin)->post(route('admin.tips.toggle-status', $tip));
        $tip->refresh();
        $this->assertSame('published', $tip->status);
    }

    /**
     * Test unauthenticated user cannot access tips index.
     */
    public function test_guest_cannot_access_tips_index(): void
    {
        $response = $this->get(route('admin.tips.index'));

        $response->assertRedirect(route('login'));
    }

    /**
     * Test non-admin cannot access tips index.
     */
    public function test_non_admin_cannot_access_tips_index(): void
    {
        $client = $this->createCustomer();

        $response = $this->actingAs($client)->get(route('admin.tips.index'));

        $response->assertForbidden();
    }
}
