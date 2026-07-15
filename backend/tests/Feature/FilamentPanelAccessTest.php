<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin_panel(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    public function test_non_super_admin_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_super_admin_can_access_admin_panel(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
            'is_super_admin' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }
}
