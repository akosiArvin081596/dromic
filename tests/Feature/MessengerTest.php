<?php

use App\Events\MessageSent;
use App\Models\CityMunicipality;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function setupMessengerData(): array
{
    $region = Region::factory()->create();
    $province1 = Province::factory()->create(['region_id' => $region->id]);
    $province2 = Province::factory()->create(['region_id' => $region->id]);
    $lgu1 = CityMunicipality::factory()->create(['province_id' => $province1->id]);
    $lgu2 = CityMunicipality::factory()->create(['province_id' => $province1->id]);

    $admin = User::factory()->admin()->create();
    $regional = User::factory()->regional($region)->create();
    $provincial = User::factory()->provincial($province1)->create();
    $lguUser1 = User::factory()->lgu($lgu1)->create();
    $lguUser2 = User::factory()->lgu($lgu2)->create();

    return compact('admin', 'regional', 'provincial', 'lguUser1', 'lguUser2', 'region', 'province1', 'province2', 'lgu1', 'lgu2');
}

// --- Group Chat Membership ---

test('lgu user sees their province group chat', function () {
    $data = setupMessengerData();

    $response = $this->actingAs($data['lguUser1'])->getJson('/messenger/conversations');
    $response->assertSuccessful();

    $conversations = $response->json();
    expect($conversations)->toHaveCount(1);
    expect($conversations[0]['type'])->toBe('group');
    expect($conversations[0]['province_id'])->toBe($data['province1']->id);
});

test('provincial user sees their province group chat', function () {
    $data = setupMessengerData();

    $response = $this->actingAs($data['provincial'])->getJson('/messenger/conversations');
    $response->assertSuccessful();

    $conversations = $response->json();
    expect($conversations)->toHaveCount(1);
    expect($conversations[0]['type'])->toBe('group');
});

test('regional user sees group chats for all provinces in their region', function () {
    $data = setupMessengerData();

    $response = $this->actingAs($data['regional'])->getJson('/messenger/conversations');
    $response->assertSuccessful();

    $conversations = $response->json();
    expect($conversations)->toHaveCount(2);

    $provinceIds = collect($conversations)->pluck('province_id')->sort()->values()->all();
    expect($provinceIds)->toBe([$data['province1']->id, $data['province2']->id]);
});

test('admin user sees all province group chats', function () {
    $data = setupMessengerData();

    $response = $this->actingAs($data['admin'])->getJson('/messenger/conversations');
    $response->assertSuccessful();

    $conversations = $response->json();
    expect($conversations)->toHaveCount(2);
});

test('province group chat auto-populates all relevant members', function () {
    $data = setupMessengerData();

    $response = $this->actingAs($data['lguUser1'])->getJson('/messenger/conversations');
    $conversations = $response->json();

    // Province1 group should have: lguUser1, lguUser2, provincial, regional, admin = 5
    $province1Group = collect($conversations)->firstWhere('province_id', $data['province1']->id);
    expect($province1Group['participants'])->toHaveCount(5);

    $participantIds = collect($province1Group['participants'])->pluck('id')->sort()->values()->all();
    $expectedIds = collect([
        $data['admin']->id,
        $data['regional']->id,
        $data['provincial']->id,
        $data['lguUser1']->id,
        $data['lguUser2']->id,
    ])->sort()->values()->all();

    expect($participantIds)->toBe($expectedIds);
});

// --- DM Creation ---

test('user can create a DM conversation', function () {
    $data = setupMessengerData();

    $response = $this->actingAs($data['lguUser1'])->postJson('/messenger/conversations/dm', [
        'user_id' => $data['lguUser2']->id,
    ]);
    $response->assertSuccessful();
    expect($response->json('type'))->toBe('dm');
    expect($response->json('participants'))->toHaveCount(2);
});

test('creating DM with same user returns existing conversation', function () {
    $data = setupMessengerData();

    $response1 = $this->actingAs($data['lguUser1'])->postJson('/messenger/conversations/dm', [
        'user_id' => $data['lguUser2']->id,
    ]);

    $response2 = $this->actingAs($data['lguUser1'])->postJson('/messenger/conversations/dm', [
        'user_id' => $data['lguUser2']->id,
    ]);

    expect($response1->json('id'))->toBe($response2->json('id'));
});

test('user cannot create DM with themselves', function () {
    $data = setupMessengerData();

    $response = $this->actingAs($data['lguUser1'])->postJson('/messenger/conversations/dm', [
        'user_id' => $data['lguUser1']->id,
    ]);
    $response->assertStatus(422);
});

// --- Message Sending ---

test('participant can send message to conversation', function () {
    Event::fake([MessageSent::class]);
    $data = setupMessengerData();

    // Create a DM first
    $dmResponse = $this->actingAs($data['lguUser1'])->postJson('/messenger/conversations/dm', [
        'user_id' => $data['lguUser2']->id,
    ]);
    $conversationId = $dmResponse->json('id');

    $response = $this->actingAs($data['lguUser1'])->postJson("/messenger/conversations/{$conversationId}/messages", [
        'body' => 'Hello there!',
    ]);

    $response->assertCreated();
    expect($response->json('body'))->toBe('Hello there!');
    expect($response->json('user_id'))->toBe($data['lguUser1']->id);
});

test('non-participant cannot send message', function () {
    $data = setupMessengerData();

    $conversation = Conversation::factory()->dm($data['lguUser1'], $data['lguUser2'])->create();
    $conversation->participants()->attach([$data['lguUser1']->id, $data['lguUser2']->id]);

    $response = $this->actingAs($data['provincial'])->postJson("/messenger/conversations/{$conversation->id}/messages", [
        'body' => 'Unauthorized',
    ]);
    $response->assertForbidden();
});

test('message body is required', function () {
    $data = setupMessengerData();

    $conversation = Conversation::factory()->dm($data['lguUser1'], $data['lguUser2'])->create();
    $conversation->participants()->attach([$data['lguUser1']->id, $data['lguUser2']->id]);

    $response = $this->actingAs($data['lguUser1'])->postJson("/messenger/conversations/{$conversation->id}/messages", [
        'body' => '',
    ]);
    $response->assertUnprocessable();
});

// --- Message Fetching ---

test('participant can fetch messages for conversation', function () {
    $data = setupMessengerData();

    $conversation = Conversation::factory()->dm($data['lguUser1'], $data['lguUser2'])->create();
    $conversation->participants()->attach([$data['lguUser1']->id, $data['lguUser2']->id]);

    Message::factory()->count(3)->create([
        'conversation_id' => $conversation->id,
        'user_id' => $data['lguUser1']->id,
    ]);

    $response = $this->actingAs($data['lguUser1'])->getJson("/messenger/conversations/{$conversation->id}");
    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(3);
});

test('non-participant cannot fetch messages', function () {
    $data = setupMessengerData();

    $conversation = Conversation::factory()->dm($data['lguUser1'], $data['lguUser2'])->create();
    $conversation->participants()->attach([$data['lguUser1']->id, $data['lguUser2']->id]);

    $response = $this->actingAs($data['provincial'])->getJson("/messenger/conversations/{$conversation->id}");
    $response->assertForbidden();
});

// --- Unread Count ---

test('unread count reflects unread messages', function () {
    $data = setupMessengerData();

    $conversation = Conversation::factory()->dm($data['lguUser1'], $data['lguUser2'])->create();
    $conversation->participants()->attach([$data['lguUser1']->id, $data['lguUser2']->id]);

    Message::factory()->count(3)->create([
        'conversation_id' => $conversation->id,
        'user_id' => $data['lguUser1']->id,
    ]);

    $response = $this->actingAs($data['lguUser2'])->getJson('/messenger/unread-count');
    $response->assertSuccessful();
    expect($response->json('count'))->toBe(3);
});

test('opening conversation resets unread count', function () {
    $data = setupMessengerData();

    $conversation = Conversation::factory()->dm($data['lguUser1'], $data['lguUser2'])->create();
    $conversation->participants()->attach([$data['lguUser1']->id, $data['lguUser2']->id]);

    Message::factory()->count(2)->create([
        'conversation_id' => $conversation->id,
        'user_id' => $data['lguUser1']->id,
    ]);

    // Open the conversation (marks as read)
    $this->actingAs($data['lguUser2'])->getJson("/messenger/conversations/{$conversation->id}");

    $response = $this->actingAs($data['lguUser2'])->getJson('/messenger/unread-count');
    expect($response->json('count'))->toBe(0);
});

// --- User List ---

test('user can search contactable users', function () {
    $data = setupMessengerData();

    $response = $this->actingAs($data['lguUser1'])->getJson('/messenger/users');
    $response->assertSuccessful();

    $userIds = collect($response->json())->pluck('id')->all();
    expect($userIds)->not->toContain($data['lguUser1']->id);
    expect(count($userIds))->toBeGreaterThanOrEqual(4);
});

// --- Broadcast ---

test('MessageSent event is dispatched when message is sent', function () {
    Event::fake([MessageSent::class]);
    $data = setupMessengerData();

    $conversation = Conversation::factory()->dm($data['lguUser1'], $data['lguUser2'])->create();
    $conversation->participants()->attach([$data['lguUser1']->id, $data['lguUser2']->id]);

    $this->actingAs($data['lguUser1'])->postJson("/messenger/conversations/{$conversation->id}/messages", [
        'body' => 'Test broadcast',
    ]);

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($data) {
        return $event->messageData['body'] === 'Test broadcast'
            && $event->recipientUserIds === [$data['lguUser2']->id];
    });
});
