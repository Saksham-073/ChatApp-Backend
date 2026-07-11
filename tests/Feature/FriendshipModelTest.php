<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendshipModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_between_reports_none_with_no_row(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->assertSame(['status' => 'none', 'id' => null], Friendship::statusBetween($alice->id, $bob->id));
    }

    public function test_status_between_reports_pending_sent_and_pending_received(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $friendship = Friendship::create([
            'sender_id' => $alice->id,
            'recipient_id' => $bob->id,
            'status' => 'pending',
        ]);

        $this->assertSame(
            ['status' => 'pending_sent', 'id' => $friendship->id],
            Friendship::statusBetween($alice->id, $bob->id)
        );
        $this->assertSame(
            ['status' => 'pending_received', 'id' => $friendship->id],
            Friendship::statusBetween($bob->id, $alice->id)
        );
    }

    public function test_status_between_reports_friends_regardless_of_direction(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $friendship = Friendship::create([
            'sender_id' => $alice->id,
            'recipient_id' => $bob->id,
            'status' => 'accepted',
        ]);

        $this->assertSame(
            ['status' => 'friends', 'id' => $friendship->id],
            Friendship::statusBetween($alice->id, $bob->id)
        );
        $this->assertSame(
            ['status' => 'friends', 'id' => $friendship->id],
            Friendship::statusBetween($bob->id, $alice->id)
        );
    }

    public function test_unique_constraint_prevents_duplicate_direction(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'pending']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Friendship::create(['sender_id' => $alice->id, 'recipient_id' => $bob->id, 'status' => 'pending']);
    }
}
