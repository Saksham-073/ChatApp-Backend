<?php

namespace Tests\Feature;

use App\Events\UserTyping;
use App\Models\ChatRoom;
use App\Models\User;
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
}
