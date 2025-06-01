<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_paginated_active_posts()
    {
        $user = User::factory()->create();

        Post::factory()->count(5)->create([
            'is_draft' => false,
            'published_at' => now()->subDay(),
            'user_id' => $user->id,
        ]);

        Post::factory()->count(3)->create([
            'is_draft' => true,
        ]);

        Post::factory()->count(2)->create([
            'is_draft' => false,
            'published_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->getJson('/posts')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_can_view_single_published_post()
    {
        $user = User::factory()->create();

        $post = Post::factory()->create([
            'is_draft' => false,
            'published_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->getJson("/posts/{$post->id}")
            ->assertOk()
            ->assertJson([
                'id' => $post->id,
                'title' => $post->title,
            ]);
    }

    public function test_cannot_view_draft_post()
    {
        $user = User::factory()->create();

        $post = Post::factory()->create([
            'is_draft' => true,
        ]);

        $this->actingAs($user)
            ->getJson("/posts/{$post->id}")
            ->assertNotFound();
    }

    public function test_cannot_view_scheduled_post()
    {
        $user = User::factory()->create();

        $post = Post::factory()->create([
            'is_draft' => false,
            'published_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->getJson("/posts/{$post->id}")
            ->assertNotFound();
    }

    public function test_authenticated_user_can_create_post()
    {
        $this->withoutMiddleware();

        $user = User::factory()->create();

        $postData = [
            'title' => 'New Post',
            'content' => 'Content of the post',
            'is_draft' => false,
            'published_at' => now()->addDay()->toDateTimeString(),
        ];

        $this->actingAs($user)
            ->postJson('/posts', $postData)
            ->assertStatus(201)
            ->assertJsonFragment([
                'title' => 'New Post',
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'New Post',
            'user_id' => $user->id,
        ]);
    }

    public function test_only_author_can_update_post()
    {
        $this->withoutMiddleware();
        $author = User::factory()->create();
        $otherUser = User::factory()->create();

        $post = Post::factory()->create([
            'user_id' => $author->id,
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated Content',
            'is_draft' => false,
            'published_at' => now()->addDay()->toDateTimeString(),
            'user_id' => $author->id,
        ];
        // Author update — should succeed
        $response = $this->actingAs($author)
            ->putJson("/posts/{$post->id}", $updateData)
            ->assertOk()
            ->assertJsonFragment(['title' => 'Updated Title']);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
            'user_id' => $author->id,
        ]);

        // Other user update — should fail
        $this->actingAs($otherUser)
            ->putJson("/posts/{$post->id}", $updateData)
            ->assertForbidden();
    }

    public function test_only_author_can_delete_post()
    {
        $this->withoutMiddleware();
        $author = User::factory()->create();
        $otherUser = User::factory()->create();

        $post = Post::factory()->create([
            'user_id' => $author->id,
        ]);

        // Other user delete — should fail
        $this->actingAs($otherUser)
            ->deleteJson("/posts/{$post->id}")
            ->assertForbidden();

        // Author delete — should succeed
        $this->actingAs($author)
            ->deleteJson("/posts/{$post->id}")
            ->assertOk();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }
}
