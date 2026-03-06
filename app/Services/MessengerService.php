<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\CityMunicipality;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Province;
use App\Models\User;
use Illuminate\Support\Collection;

class MessengerService
{
    public function getOrCreateProvinceGroupChat(Province $province): Conversation
    {
        $conversation = Conversation::firstOrCreate(
            ['type' => 'group', 'province_id' => $province->id],
        );

        $this->syncProvinceGroupMembers($conversation, $province);

        return $conversation;
    }

    public function syncGroupChatMembership(User $user): void
    {
        $provinces = $this->getProvincesForUser($user);

        foreach ($provinces as $province) {
            $this->getOrCreateProvinceGroupChat($province);
        }
    }

    /**
     * Add all LGU users from the province + the provincial user to the group chat.
     */
    private function syncProvinceGroupMembers(Conversation $conversation, Province $province): void
    {
        $cityIds = CityMunicipality::where('province_id', $province->id)->pluck('id');

        $memberIds = User::query()
            ->where(function ($q) use ($province, $cityIds) {
                $q->where(function ($q) use ($cityIds) {
                    $q->where('role', UserRole::Lgu)->whereIn('city_municipality_id', $cityIds);
                })->orWhere(function ($q) use ($province) {
                    $q->where('role', UserRole::Provincial)->where('province_id', $province->id);
                })->orWhere(function ($q) use ($province) {
                    $q->where('role', UserRole::Regional)->where('region_id', $province->region_id);
                })->orWhere('role', UserRole::Admin);
            })
            ->pluck('id');

        $existingIds = $conversation->participants()->pluck('users.id');
        $newIds = $memberIds->diff($existingIds);

        if ($newIds->isNotEmpty()) {
            $pivotData = $newIds->mapWithKeys(fn ($id) => [$id => ['last_read_at' => now()]])->all();
            $conversation->participants()->attach($pivotData);
        }
    }

    public function getOrCreateDm(User $user1, User $user2): Conversation
    {
        $ids = [min($user1->id, $user2->id), max($user1->id, $user2->id)];
        $dmKey = "dm-{$ids[0]}-{$ids[1]}";

        $conversation = Conversation::firstOrCreate(
            ['dm_key' => $dmKey],
            ['type' => 'dm'],
        );

        $conversation->participants()->syncWithoutDetaching([$user1->id, $user2->id]);

        return $conversation;
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getConversationsForUser(User $user): Collection
    {
        $conversations = $user->conversations()
            ->with([
                'participants:id,name,role,city_municipality_id,province_id',
                'participants.cityMunicipality:id,name',
                'participants.province:id,name',
                'latestMessage.user:id,name',
                'province:id,name',
            ])
            ->get();

        $conversations->each(function (Conversation $conversation) use ($user) {
            $lastReadAt = $conversation->pivot->last_read_at;

            $query = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', '!=', $user->id);

            if ($lastReadAt) {
                $query->where('created_at', '>', $lastReadAt);
            }

            $conversation->setAttribute('unread_count', $query->count());
        });

        return $conversations->sortByDesc(fn (Conversation $c) => $c->latestMessage?->created_at ?? $c->created_at)->values();
    }

    /**
     * @return array{data: Collection<int, Message>, has_more: bool}
     */
    public function getMessages(Conversation $conversation, ?int $before = null, int $limit = 30): array
    {
        $query = $conversation->messages()
            ->with('user:id,name,role,city_municipality_id,province_id', 'user.cityMunicipality:id,name', 'user.province:id,name')
            ->when($before, fn ($q) => $q->where('id', '<', $before))
            ->latest()
            ->limit($limit + 1);

        $messages = $query->get();
        $hasMore = $messages->count() > $limit;

        return [
            'data' => $messages->take($limit)->reverse()->values(),
            'has_more' => $hasMore,
        ];
    }

    public function markAsRead(Conversation $conversation, User $user): void
    {
        $conversation->participants()->updateExistingPivot($user->id, [
            'last_read_at' => now(),
        ]);
    }

    public function getUnreadCount(User $user): int
    {
        $total = 0;

        $user->conversations()->each(function (Conversation $conversation) use ($user, &$total) {
            $lastReadAt = $conversation->pivot->last_read_at;

            $query = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', '!=', $user->id);

            if ($lastReadAt) {
                $query->where('created_at', '>', $lastReadAt);
            }

            $total += $query->count();
        });

        return $total;
    }

    /**
     * @return Collection<int, User>
     */
    public function getContactableUsers(User $user, ?string $search = null): Collection
    {
        return User::query()
            ->where('id', '!=', $user->id)
            ->whereNotIn('role', [UserRole::Admin, UserRole::RegionalDirector])
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->select('id', 'name', 'role', 'region_id', 'province_id', 'city_municipality_id')
            ->with('region:id,name', 'province:id,name', 'cityMunicipality:id,name,province_id', 'cityMunicipality.province:id,name')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Province>
     */
    private function getProvincesForUser(User $user): Collection
    {
        if ($user->isAdmin()) {
            return Province::all();
        }

        if ($user->isRegional()) {
            return Province::where('region_id', $user->region_id)->get();
        }

        if ($user->isProvincial()) {
            return Province::where('id', $user->province_id)->get();
        }

        if ($user->isLgu()) {
            $provinceId = $user->cityMunicipality?->province_id;

            return $provinceId ? Province::where('id', $provinceId)->get() : collect();
        }

        return collect();
    }
}
