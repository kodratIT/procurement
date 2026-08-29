<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use App\Models\UserAssignment;
use App\Services\AssignmentBulkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class AssignmentBulkServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_many_assignments_for_one_user_in_one_call(): void
    {
        $user = User::factory()->create();
        $jakarta = Office::factory()->create(['code' => 'JKT', 'name' => 'Jakarta']);
        $jambi = Office::factory()->create(['code' => 'JBI', 'name' => 'Jambi']);

        $created = app(AssignmentBulkService::class)->createMany($user, [
            [
                'office_id' => $jakarta->id,
                'role' => 'Manager',
                'valid_from' => '2026-01-01',
                'valid_until' => '2026-12-31',
                'is_primary' => true,
            ],
            [
                'office_id' => $jambi->id,
                'role' => 'Operasional',
                'valid_from' => '2026-03-01',
            ],
        ]);

        $this->assertCount(2, $created);
        $this->assertSame(2, $user->assignments()->count());

        $jakartaAssignment = $user->assignments()->where('office_id', $jakarta->id)->firstOrFail();
        $this->assertSame('Manager', $jakartaAssignment->role);
        $this->assertSame('2026-01-01', $jakartaAssignment->valid_from->toDateString());
        $this->assertSame('2026-12-31', $jakartaAssignment->valid_until->toDateString());
        $this->assertTrue($jakartaAssignment->is_primary);

        $jambiAssignment = $user->assignments()->where('office_id', $jambi->id)->firstOrFail();
        $this->assertSame('Operasional', $jambiAssignment->role);
        $this->assertSame('2026-03-01', $jambiAssignment->valid_from->toDateString());
        $this->assertNull($jambiAssignment->valid_until);
        $this->assertFalse($jambiAssignment->is_primary);
    }

    public function test_default_role_is_applied_when_role_missing(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();

        $created = app(AssignmentBulkService::class)->createMany($user, [
            ['office_id' => $office->id, 'valid_from' => '2026-01-01'],
        ]);

        $this->assertSame(UserAssignment::DEFAULT_ROLE, $created->first()->role);
    }

    public function test_rejects_empty_row_set(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one assignment row is required.');

        app(AssignmentBulkService::class)->createMany(User::factory()->create(), []);
    }

    public function test_rejects_invalid_period(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/valid_until|earlier/i');

        app(AssignmentBulkService::class)->createMany($user, [
            ['office_id' => $office->id, 'valid_from' => '2026-02-01', 'valid_until' => '2026-01-31'],
        ]);
    }

    public function test_primary_is_unique_per_user(): void
    {
        $user = User::factory()->create();
        $jakarta = Office::factory()->create(['code' => 'JKT']);
        $jambi = Office::factory()->create(['code' => 'JBI']);

        // Existing primary on another office.
        UserAssignment::factory()->create([
            'user_id' => $user->id, 'office_id' => $jakarta->id,
            'is_primary' => true, 'valid_from' => Carbon::yesterday(),
        ]);

        app(AssignmentBulkService::class)->createMany($user, [
            ['office_id' => $jambi->id, 'valid_from' => '2026-01-01', 'is_primary' => true],
        ]);

        $this->assertSame(
            1,
            $user->assignments()->where('is_primary', true)->count(),
            'Exactly one assignment must remain primary.',
        );
        $this->assertTrue(
            $user->assignments()->where('office_id', $jambi->id)->where('is_primary', true)->exists(),
            'The newly created primary assignment must win.',
        );
    }

    public function test_extend_validity_updates_only_changed_fields(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $assignment = UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-06-30',
        ]);

        $count = app(AssignmentBulkService::class)->extendValidity(
            collect([$assignment]),
            validFrom: '2026-02-01',
            validUntil: '2027-06-30',
        );

        $this->assertSame(1, $count);
        $assignment->refresh();
        $this->assertSame('2026-02-01', $assignment->valid_from->toDateString());
        $this->assertSame('2027-06-30', $assignment->valid_until->toDateString());
    }

    public function test_extend_validity_rejects_shortened_period(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();
        $assignment = UserAssignment::factory()->create([
            'user_id' => $user->id,
            'office_id' => $office->id,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/valid_until|earlier/i');

        app(AssignmentBulkService::class)->extendValidity(
            collect([$assignment]),
            validFrom: '2026-06-01',
            validUntil: '2026-05-01',
        );
    }

    public function test_bulk_create_rolls_back_on_failure(): void
    {
        $user = User::factory()->create();
        $office = Office::factory()->create();

        try {
            app(AssignmentBulkService::class)->createMany($user, [
                ['office_id' => $office->id, 'valid_from' => '2026-01-01'],
                ['office_id' => $office->id, 'valid_from' => '2026-02-01', 'valid_until' => '2026-01-31'],
            ]);
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->assertSame(0, $user->assignments()->count(), 'Transaction must roll back on failure.');
    }
}
