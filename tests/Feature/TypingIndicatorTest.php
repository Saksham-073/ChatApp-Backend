<?php

namespace Tests\Feature;

use App\Events\UserTyping;
use App\Events\DirectUserTyping;
use App\Models\ChatRoom;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Friendship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TypingIndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_broadcast_room_typing(): void
    {
        Event::fake([UserTyping::class]);

        $user = User::factory()->create();
        $room = ChatRoom::create(['name' => 'general']);
        Sanctum::actingAs($user);

        $this->postJson("/api/chat/room/{$room->id}/typing")
            ->assertStatus(204);

        Event::assertDispatched(UserTyping::class, function (UserTyping $event) use ($room, $user) {
            return $event->roomId === $room->id && $event->user->id === $user->id;
        });
    }

    public function test_friend_can_broadcast_dm_typing(): void
    {
        Event::fake([DirectUserTyping::class]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'accepted']);
        [$a, $b] = $alice->id < $bob->id ? [$alice->id, $bob->id] : [$bob->id, $alice->id];
        $conversation = Conversation::create(['user_one_id' => $a, 'user_two_id' => $b]);

        Sanctum::actingAs($alice);

        $this->postJson("/api/conversations/{$conversation->id}/typing")
            ->assertStatus(204);

        Event::assertDispatched(DirectUserTyping::class, function (DirectUserTyping $event) use ($conversation, $alice) {
            return $event->conversationId === $conversation->id && $event->user->id === $alice->id;
        });
    }

    public function test_non_friend_cannot_broadcast_dm_typing(): void
    {
        Event::fake([DirectUserTyping::class]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        [$a, $b] = $alice->id < $bob->id ? [$alice->id, $bob->id] : [$bob->id, $alice->id];
        $conversation = Conversation::create(['user_one_id' => $a, 'user_two_id' => $b]);

        Sanctum::actingAs($alice);

        $this->postJson("/api/conversations/{$conversation->id}/typing")
            ->assertStatus(403);

        Event::assertNotDispatched(DirectUserTyping::class);
    }
}
