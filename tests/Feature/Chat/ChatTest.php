<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use App\Modules\Api\Actions\IssueApiToken;
use App\Modules\Identity\Models\User;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\TenantFixture;
use Tests\TestCase;

/**
 * Chat (Norban's chat module, WhatsApp style): one chat per pair, messages
 * with replies, files, edits, deletes, reactions, pins and forwards; unread
 * counts and the ticks (sent, delivered, read); groups with their admins;
 * and nobody outside a chat — or outside the company — reaching it.
 */
class ChatTest extends TestCase
{
    use RefreshDatabase;

    private Company $delta;

    private User $karim;

    private User $rahim;

    private User $nasrin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->delta = TenantFixture::company('Delta Apparels Ltd', 'DAL');
        TenantFixture::actingAsTenant($this->delta);
        $this->karim = TenantFixture::user($this->delta, 'VIEWER', 'karim@delta.test');
        $this->rahim = TenantFixture::user($this->delta, 'VIEWER', 'rahim@delta.test');
        $this->nasrin = TenantFixture::user($this->delta, 'VIEWER', 'nasrin@delta.test');
    }

    public function test_two_colleagues_talk_with_replies_reactions_edits_deletes_and_ticks(): void
    {
        // One chat per pair, whoever starts it.
        $chat = $this->api($this->karim)->postJson('/api/v1/chat/chats/direct', ['user_id' => $this->rahim->id])->assertOk()->json('data');
        $this->assertFalse($chat['is_group']);
        $this->assertSame($this->rahim->name, $chat['name']);
        $this->assertSame($chat['id'], $this->api($this->rahim)->postJson('/api/v1/chat/chats/direct', ['user_id' => $this->karim->id])->json('data.id'));
        $this->api($this->karim)->postJson('/api/v1/chat/chats/direct', ['user_id' => $this->karim->id])->assertUnprocessable();

        $hello = $this->api($this->karim)->postJson("/api/v1/chat/chats/{$chat['id']}/messages", ['body' => 'Hello Rahim'])->assertCreated()->json('data');
        $this->assertSame('sent', $hello['status']);
        $this->api($this->karim)->postJson("/api/v1/chat/chats/{$chat['id']}/messages", ['body' => '   '])->assertUnprocessable();

        // Rahim's list: one unread; loading the list delivers it (grey ✓✓), opening the chat reads it (blue).
        $list = $this->api($this->rahim)->getJson('/api/v1/chat/chats')->assertOk()->json('data');
        $this->assertSame(1, $list['unread']);
        $this->assertSame('Hello Rahim', $list['chats'][0]['last']['body']);
        $this->assertSame('delivered', $this->api($this->karim)->getJson("/api/v1/chat/chats/{$chat['id']}/messages")->json('data.messages.0.status'));
        $this->api($this->rahim)->getJson("/api/v1/chat/chats/{$chat['id']}/messages")->assertOk();
        $this->assertSame(0, $this->api($this->rahim)->getJson('/api/v1/chat/chats')->json('data.unread'));
        $this->assertSame('read', $this->api($this->karim)->getJson("/api/v1/chat/chats/{$chat['id']}/messages")->json('data.messages.0.status'));

        // A reply quotes; a reaction is one per person; an edit marks it.
        $reply = $this->api($this->rahim)->postJson("/api/v1/chat/chats/{$chat['id']}/messages", ['body' => 'Hi! PO is ready', 'reply_to_id' => $hello['id']])->assertCreated()->json('data');
        $this->assertSame('Hello Rahim', $reply['reply']['body']);
        $this->api($this->karim)->postJson("/api/v1/chat/messages/{$reply['id']}/react", ['emoji' => '👍'])->assertOk();
        $r = $this->api($this->karim)->postJson("/api/v1/chat/messages/{$reply['id']}/react", ['emoji' => '❤️'])->json('data.reactions');
        $this->assertSame([['emoji' => '❤️', 'count' => 1, 'mine' => true, 'users' => [$this->karim->id]]], $r);
        $this->api($this->karim)->patchJson("/api/v1/chat/messages/{$reply['id']}", ['body' => 'mine now?'])->assertUnprocessable();
        $this->assertTrue($this->api($this->rahim)->patchJson("/api/v1/chat/messages/{$reply['id']}", ['body' => 'Hi! PO 94468044 is ready'])->json('data.edited'));

        // Deleted for everyone shows as deleted; deleted for me is gone for me only.
        $this->api($this->karim)->deleteJson("/api/v1/chat/messages/{$reply['id']}?scope=everyone")->assertUnprocessable();
        $this->api($this->rahim)->deleteJson("/api/v1/chat/messages/{$reply['id']}?scope=everyone")->assertOk();
        $this->api($this->karim)->deleteJson("/api/v1/chat/messages/{$hello['id']}?scope=me")->assertOk();
        $karimSees = $this->api($this->karim)->getJson("/api/v1/chat/chats/{$chat['id']}/messages")->json('data.messages');
        $this->assertCount(1, $karimSees);
        $this->assertTrue($karimSees[0]['deleted']);
        $this->assertNull($karimSees[0]['body']);
        $this->assertCount(2, $this->api($this->rahim)->getJson("/api/v1/chat/chats/{$chat['id']}/messages")->json('data.messages'));

        // Someone outside the chat reaches nothing of it.
        $this->api($this->nasrin)->getJson("/api/v1/chat/chats/{$chat['id']}/messages")->assertNotFound();
        $this->api($this->nasrin)->postJson("/api/v1/chat/chats/{$chat['id']}/messages", ['body' => 'hi'])->assertNotFound();
    }

    public function test_a_group_with_admins_files_pins_forwards_and_people_coming_and_going(): void
    {
        $group = $this->api($this->karim)->postJson('/api/v1/chat/chats/group', ['name' => 'C&A Merchandising', 'user_ids' => [$this->rahim->id]])->assertCreated()->json('data');
        $this->assertTrue($group['is_group']);
        $this->assertSame('OWNER', $group['role']);
        $this->assertSame(2, $group['member_count']);

        // A photo travels as an image message with its caption; only the chat's people may open it.
        $photo = $this->api($this->karim)->post("/api/v1/chat/chats/{$group['id']}/messages", ['body' => 'Artwork', 'file' => UploadedFile::fake()->image('artwork.png', 40, 40)], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertSame('IMAGE', $photo['type']);
        $this->assertSame('artwork.png', $photo['file']['name']);
        $this->assertTrue($photo['file']['image']);
        $this->api($this->rahim)->get("/api/v1/chat/messages/{$photo['id']}/file")->assertOk();
        $this->api($this->nasrin)->get("/api/v1/chat/messages/{$photo['id']}/file")->assertNotFound();

        // A member may not run the group; its owner adds Nasrin and makes Rahim an admin.
        $this->api($this->rahim)->postJson("/api/v1/chat/chats/{$group['id']}/members", ['user_ids' => [$this->nasrin->id]])->assertUnprocessable();
        $this->api($this->karim)->postJson("/api/v1/chat/chats/{$group['id']}/members", ['user_ids' => [$this->nasrin->id]])->assertOk()->assertJsonPath('data.added', [$this->nasrin->id]);
        $this->api($this->karim)->patchJson("/api/v1/chat/chats/{$group['id']}/members/{$this->rahim->id}", ['role' => 'ADMIN'])->assertOk();
        $this->api($this->rahim)->patchJson("/api/v1/chat/chats/{$group['id']}", ['name' => 'C&A Team'])->assertOk()->assertJsonPath('data.name', 'C&A Team');
        // Nasrin, added later, sees the group's story from her joining on (not the earlier photo).
        $nasrinSees = collect($this->api($this->nasrin)->getJson("/api/v1/chat/chats/{$group['id']}/messages")->json('data.messages'));
        $this->assertFalse($nasrinSees->contains('id', $photo['id']));
        $this->assertSame(['added', 'admin', 'renamed'], $nasrinSees->where('type', 'SYSTEM')->pluck('meta.event')->values()->all());

        // Pin, and the chat lists it.
        $note = $this->api($this->nasrin)->postJson("/api/v1/chat/chats/{$group['id']}/messages", ['body' => 'Shipment on 21st'])->json('data');
        $this->api($this->karim)->postJson("/api/v1/chat/messages/{$note['id']}/pin")->assertOk()->assertJsonPath('data.pinned', true);
        $this->assertSame([$note['id']], array_column($this->api($this->rahim)->getJson("/api/v1/chat/chats/{$group['id']}")->json('data.pinned_messages'), 'id'));

        // Forward to a one-to-one chat: a copy marked forwarded.
        $direct = $this->api($this->karim)->postJson('/api/v1/chat/chats/direct', ['user_id' => $this->rahim->id])->json('data');
        $this->api($this->karim)->postJson('/api/v1/chat/messages/forward', ['message_id' => $note['id'], 'chat_ids' => [$direct['id']]])->assertOk();
        $fwd = $this->api($this->rahim)->getJson("/api/v1/chat/chats/{$direct['id']}/messages")->json('data.messages.0');
        $this->assertTrue($fwd['forwarded']);
        $this->assertSame('Shipment on 21st', $fwd['body']);

        // The group's message info: who read it.
        $this->api($this->rahim)->getJson("/api/v1/chat/chats/{$group['id']}/messages")->assertOk();
        $this->api($this->karim)->getJson("/api/v1/chat/chats/{$group['id']}/messages")->assertOk();
        $info =$this->api($this->nasrin)->getJson("/api/v1/chat/messages/{$note['id']}/info")->json('data');
        $this->assertEqualsCanonicalizing([$this->karim->id, $this->rahim->id], collect($info['read'])->pluck('id')->all());

        // The owner leaves: the admin becomes owner; the leaver still reads, no longer writes.
        $this->api($this->karim)->deleteJson("/api/v1/chat/chats/{$group['id']}/members/{$this->karim->id}")->assertOk();
        $this->assertSame('OWNER', $this->api($this->rahim)->getJson("/api/v1/chat/chats/{$group['id']}")->json('data.role'));
        $this->api($this->karim)->getJson("/api/v1/chat/chats/{$group['id']}/messages")->assertOk();
        $this->api($this->karim)->postJson("/api/v1/chat/chats/{$group['id']}/messages", ['body' => 'back?'])->assertUnprocessable();
        $this->assertTrue(collect($this->api($this->karim)->getJson('/api/v1/chat/chats')->json('data.chats'))->firstWhere('id', $group['id'])['left']);
    }

    public function test_only_the_companys_people_are_contacts_and_settings_are_ones_own(): void
    {
        $omega = TenantFixture::company('Omega Textiles Ltd', 'OTL');
        $outsider = TenantFixture::user($omega, 'VIEWER', 'out@omega.test');
        TenantFixture::actingAsTenant($this->delta);

        $people = collect($this->api($this->karim)->getJson('/api/v1/chat/contacts')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($people->contains($this->rahim->id));
        $this->assertFalse($people->contains($this->karim->id));
        $this->assertFalse($people->contains($outsider->id));
        $this->api($this->karim)->postJson('/api/v1/chat/chats/direct', ['user_id' => $outsider->id])->assertUnprocessable();

        $chat = $this->api($this->karim)->postJson('/api/v1/chat/chats/direct', ['user_id' => $this->rahim->id])->json('data');
        $row = $this->api($this->karim)->patchJson("/api/v1/chat/chats/{$chat['id']}/settings", ['pinned' => true, 'muted' => true])->assertOk()->json('data');
        $this->assertTrue($row['pinned']);
        $this->assertTrue($row['muted']);
        $this->assertFalse($this->api($this->rahim)->getJson('/api/v1/chat/chats')->json('data.chats.0.pinned'));
        // The minute heartbeat marks one online for the others.
        $this->api($this->rahim)->postJson('/api/v1/chat/ping')->assertOk();
        \App\Modules\Chat\Support\ChatPeople::forget();
        $this->assertTrue(collect($this->api($this->karim)->getJson('/api/v1/chat/contacts')->json('data'))->firstWhere('id', $this->rahim->id)['online']);
    }

    public function test_messages_are_searched_only_where_one_may_still_read_them(): void
    {
        $chat = $this->api($this->karim)->postJson('/api/v1/chat/chats/direct', ['user_id' => $this->rahim->id])->json('data');
        $this->api($this->karim)->postJson("/api/v1/chat/chats/{$chat['id']}/messages", ['body' => 'The PO for 100% cotton is ready'])->assertCreated();
        $this->api($this->rahim)->postJson("/api/v1/chat/chats/{$chat['id']}/messages", ['body' => 'Thanks, sending the po copy'])->assertCreated();
        $group = $this->api($this->karim)->postJson('/api/v1/chat/chats/group', ['name' => 'Merchandising', 'user_ids' => [$this->nasrin->id]])->json('data');
        $this->api($this->nasrin)->post("/api/v1/chat/chats/{$group['id']}/messages", ['file' => UploadedFile::fake()->create('PO-sheet.pdf', 12, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();

        // Everywhere: newest first, file names too, the wildcard typed is a letter.
        $hits = $this->api($this->karim)->getJson('/api/v1/chat/search?q=po')->assertOk()->json('data.results');
        $this->assertSame(['FILE', 'TEXT', 'TEXT'], array_column($hits, 'type'));
        $this->assertSame('PO-sheet.pdf', $hits[0]['file_name']);
        $this->assertSame($this->nasrin->name, $hits[0]['user_name']);
        $this->assertCount(1, $this->api($this->karim)->getJson('/api/v1/chat/search?q='.urlencode('100%'))->json('data.results'));
        $this->assertSame([], $this->api($this->karim)->getJson('/api/v1/chat/search?q=p')->json('data.results'));
        // One chat; and nobody finds what is not theirs.
        $this->assertCount(2, $this->api($this->karim)->getJson("/api/v1/chat/search?q=po&chat={$chat['id']}")->json('data.results'));
        $this->assertCount(1, $this->api($this->nasrin)->getJson('/api/v1/chat/search?q=po')->json('data.results'));
        // A cleared chat finds nothing from before.
        $this->api($this->karim)->postJson("/api/v1/chat/chats/{$chat['id']}/clear")->assertOk();
        $this->assertCount(0, $this->api($this->karim)->getJson("/api/v1/chat/search?q=po&chat={$chat['id']}")->json('data.results'));
        $this->assertCount(2, $this->api($this->rahim)->getJson("/api/v1/chat/search?q=po&chat={$chat['id']}")->json('data.results'));
    }

    public function test_people_and_records_are_mentioned_and_only_readers_see_a_records_card(): void
    {
        \Illuminate\Support\Facades\DB::table('os_orders')->insert([
            'id' => strtolower((string) \Illuminate\Support\Str::ulid()), 'company_id' => $this->delta->id, 'serial_no' => 1, 'created_by' => $this->karim->id,
            'values' => json_encode(['job_no' => ['26NCL00001'], 'buyer' => 'C&A', 'style_number_name' => ['637PK'], 'quantity_pcs' => 1200, 'proposed_ldd_etd' => '2026-12-10']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $chat = $this->api($this->karim)->postJson('/api/v1/chat/chats/direct', ['user_id' => $this->rahim->id])->json('data');

        // The # picker: the kinds one may read, and the matches.
        $found = $this->api($this->karim)->getJson('/api/v1/chat/records?type=os&q=26ncl')->assertOk()->json('data');
        $this->assertSame(['os', 'pb', 'req', 'ds', 'es'], $found['types']);
        $this->assertSame([['os', '26NCL00001', '#26NCL00001']], array_map(fn ($r) => [$r['type'], $r['ref'], $r['token']], $found['results']));
        $this->assertSame([], $this->api($this->rahim)->getJson('/api/v1/chat/records?type=os&q=26ncl')->json('data.results')); // not Rahim's row

        // Only people in the chat whose @name is written, and only real records whose token is, stay on the message.
        $body = "@{$this->rahim->name} please check #26NCL00001";
        $sent = $this->api($this->karim)->postJson("/api/v1/chat/chats/{$chat['id']}/messages", ['body' => $body, 'mentions' => [
            'users' => [['id' => $this->rahim->id], ['id' => $this->nasrin->id]],
            'refs' => [['type' => 'os', 'ref' => '26NCL00001', 'token' => '#26NCL00001'], ['type' => 'os', 'ref' => '99XXX00009', 'token' => '#99XXX00009'], ['type' => 'pb', 'ref' => 'PB-1', 'token' => '#PB-1']],
        ]])->assertCreated()->json('data');
        $this->assertSame(['users' => [['id' => $this->rahim->id, 'name' => $this->rahim->name]], 'refs' => [['type' => 'os', 'ref' => '26NCL00001', 'token' => '#26NCL00001']]], $sent['meta']['mentions']);

        // Rahim's list marks the unread mention; reading it clears the mark.
        $this->assertTrue($this->api($this->rahim)->getJson('/api/v1/chat/chats')->json('data.chats.0.mentioned'));
        $this->api($this->rahim)->getJson("/api/v1/chat/chats/{$chat['id']}/messages")->assertOk();
        $this->assertFalse($this->api($this->rahim)->getJson('/api/v1/chat/chats')->json('data.chats.0.mentioned'));

        // The card: Karim reads the order; Rahim's buyers do not reach it, so his chip stays a plain reference.
        $card = $this->api($this->karim)->getJson('/api/v1/chat/records/cards?keys=os:26NCL00001')->assertOk()->json('data.cards')['os:26NCL00001'];
        $this->assertSame('/order-summary/list?'.http_build_query(['f' => ['job_no' => ['26NCL00001']]]), $card['url']);
        $this->assertSame(['C&A', '637PK', '1,200', '2026-12-10'], array_values(array_map(fn ($f) => $f['value'], array_filter($card['fields'], fn ($f) => $f['label'] !== 'POs'))));
        $this->assertNull($this->api($this->rahim)->getJson('/api/v1/chat/records/cards?keys=os:26NCL00001')->json('data.cards')['os:26NCL00001']);

        // An edit keeps what is still written.
        $edited = $this->api($this->karim)->patchJson("/api/v1/chat/messages/{$sent['id']}", ['body' => 'see #26NCL00001', 'mentions' => $sent['meta']['mentions']])->assertOk()->json('data');
        $this->assertSame(['refs' => [['type' => 'os', 'ref' => '26NCL00001', 'token' => '#26NCL00001']]], $edited['meta']['mentions']);
    }

    public function test_each_hr_department_has_a_group_that_follows_hr(): void
    {
        $db = \Illuminate\Support\Facades\DB::class;
        $now = now();
        $ulid = fn () => strtolower((string) \Illuminate\Support\Str::ulid());
        $dhaka = TenantFixture::factory($this->delta, 'Dhaka Unit 1', 'DHK');
        $gazipur = TenantFixture::factory($this->delta, 'Gazipur Unit 2', 'GZP');
        $gazette = $ulid();
        $db::table('hr_gazettes')->insert(['id' => $gazette, 'company_id' => $this->delta->id, 'name' => 'RMG 2023', 'effective_date' => '2023-12-01', 'created_at' => $now, 'updated_at' => $now]);
        $type = $ulid();
        $db::table('hr_employment_types')->insert(['id' => $type, 'company_id' => $this->delta->id, 'name' => 'Staff', 'name_bn' => 'স্টাফ', 'short_name' => 'STF', 'created_at' => $now, 'updated_at' => $now]);
        $dept = function (string $factory, string $name, string $code) use ($db, $ulid, $now): string {
            $id = $ulid();
            $db::table('departments')->insert(['id' => $id, 'company_id' => $this->delta->id, 'factory_id' => $factory, 'name' => $name, 'code' => $code, 'created_at' => $now, 'updated_at' => $now]);

            return $id;
        };
        // The same department in two factories is one group.
        $merchDhk = $dept($dhaka->id, 'Merchandising', 'MER-D');
        $merchGzp = $dept($gazipur->id, 'Merchandising', 'MER-G');
        $hr = $dept($dhaka->id, 'HR & Admin', 'HRA');
        $n = 0;
        $employ = function (User $user, string $department, string $factory) use ($db, $ulid, $now, $gazette, $type, &$n): void {
            $code = sprintf('EMP-%06d', ++$n);
            $db::table('hr_employees')->insert(['id' => $ulid(), 'company_id' => $this->delta->id, 'employee_code' => $code, 'card_no' => "C{$n}", 'name' => $user->name, 'status' => 'ACTIVE',
                'gazette_id' => $gazette, 'employment_type_id' => $type, 'gross_a' => 1, 'gross_b' => 1, 'factory_id' => $factory, 'department_id' => $department, 'created_at' => $now, 'updated_at' => $now]);
            $db::table('users')->where('id', $user->id)->update(['employee_code' => $code]);
        };
        $employ($this->karim, $merchDhk, $dhaka->id);
        $employ($this->rahim, $merchGzp, $gazipur->id);
        $employ($this->nasrin, $hr, $dhaka->id);

        $sync = fn () => \App\Modules\Chat\Support\ChatDepartmentGroups::sync($this->delta->id);
        $this->assertSame(['groups' => 2, 'joined' => 3, 'left' => 0], $sync());
        $this->assertSame(['groups' => 2, 'joined' => 0, 'left' => 0], $sync()); // nothing twice
        $members = fn (string $name) => \App\Modules\Chat\Models\ChatMember::withoutGlobalScopes()->whereNull('left_at')
            ->whereIn('chat_id', \App\Modules\Chat\Models\Chat::withoutGlobalScopes()->where('name', $name)->pluck('id'))->pluck('user_id')->sort()->values()->all();
        $this->assertSame(collect([$this->karim->id, $this->rahim->id])->sort()->values()->all(), $members('Merchandising'));

        // The list marks it; nobody is its admin (its people follow HR).
        $row = collect($this->api($this->rahim)->getJson('/api/v1/chat/chats')->assertOk()->json('data.chats'))->firstWhere('name', 'Merchandising');
        $this->assertTrue($row['department']);
        $this->assertTrue($row['is_group']);

        // Moved to Merchandising: out of HR & Admin, into Merchandising.
        $db::table('hr_employees')->where('employee_code', $this->nasrin->fresh()->employee_code)->update(['department_id' => $merchDhk]);
        $this->assertSame(['groups' => 1, 'joined' => 1, 'left' => 1], $sync());
        $this->assertCount(3, $members('Merchandising'));
        $this->assertSame([], $members('HR & Admin'));

        // Someone who leaves on their own is not pulled back in.
        $merch = \App\Modules\Chat\Models\Chat::withoutGlobalScopes()->where('name', 'Merchandising')->value('id');
        $this->api($this->karim)->deleteJson("/api/v1/chat/chats/{$merch}/members/{$this->karim->id}")->assertOk();
        $sync();
        $this->assertNotContains($this->karim->id, $members('Merchandising'));

        // The owner (and every admin) sits in each department group as its admin, with no HR record of their own.
        $owner = TenantFixture::user($this->delta, 'COMPANY_OWNER', 'boss@delta.test');
        $sync();
        $this->assertContains($owner->id, $members('Merchandising'));
        $this->assertSame('ADMIN', \App\Modules\Chat\Models\ChatMember::withoutGlobalScopes()->where('chat_id', $merch)->where('user_id', $owner->id)->value('role'));
    }

    public function test_a_browser_subscribes_to_chat_notifications_and_is_moved_on_sign_in(): void
    {
        config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);
        $this->assertNull($this->api($this->karim)->getJson('/api/v1/chat/push/key')->assertOk()->json('data.public_key'));
        config(['services.webpush.public_key' => 'BPublicKey', 'services.webpush.private_key' => 'private']);
        $this->assertSame('BPublicKey', $this->api($this->karim)->getJson('/api/v1/chat/push/key')->json('data.public_key'));

        $sub = ['endpoint' => 'https://push.example.test/abc', 'keys' => ['p256dh' => 'p-key', 'auth' => 'a-key']];
        $this->api($this->karim)->postJson('/api/v1/chat/push/subscribe', ['endpoint' => 'http://insecure.test/x'] + $sub)->assertUnprocessable();
        $this->api($this->karim)->postJson('/api/v1/chat/push/subscribe', $sub)->assertOk();
        $this->api($this->rahim)->postJson('/api/v1/chat/push/subscribe', $sub)->assertOk(); // the same browser, Rahim signed in now
        $rows = \App\Modules\Chat\Models\ChatPushSubscription::query()->get();
        $this->assertCount(1, $rows);
        $this->assertSame($this->rahim->id, $rows[0]->user_id);
        $this->api($this->karim)->postJson('/api/v1/chat/push/unsubscribe', ['endpoint' => $sub['endpoint']])->assertOk(); // not Karim's any more
        $this->assertSame(1, \App\Modules\Chat\Models\ChatPushSubscription::query()->count());
        $this->api($this->rahim)->postJson('/api/v1/chat/push/unsubscribe', ['endpoint' => $sub['endpoint']])->assertOk();
        $this->assertSame(0, \App\Modules\Chat\Models\ChatPushSubscription::query()->count());

        // What a notification says: the sender (and the group), the text or what was sent.
        config(['services.webpush.public_key' => null, 'services.webpush.private_key' => null]);
        $group = $this->api($this->karim)->postJson('/api/v1/chat/chats/group', ['name' => 'Merchandising', 'user_ids' => [$this->rahim->id]])->json('data');
        $id = $this->api($this->karim)->post("/api/v1/chat/chats/{$group['id']}/messages", ['file' => UploadedFile::fake()->create('PO.pdf', 5, 'application/pdf')], ['Accept' => 'application/json'])->json('data.id');
        $m = \App\Modules\Chat\Models\ChatMessage::query()->with('file')->find($id);
        $payload = \App\Modules\Chat\Support\ChatWebPush::payload($m, \App\Modules\Chat\Models\Chat::query()->find($group['id']), $this->karim->name, 'en');
        $this->assertSame(['Merchandising', "{$this->karim->name}: 📄 PO.pdf", 'chat-'.$group['id'], '/chat'], [$payload['title'], $payload['body'], $payload['tag'], $payload['url']]);
    }

    private function api(User $user): static
    {
        $this->flushHeaders();
        \App\Modules\Chat\Support\ChatPeople::forget();
        ['plain' => $plain] = app(IssueApiToken::class)->forUser($user, $this->delta->id, 'Test token');

        return $this->withToken($plain);
    }
}
