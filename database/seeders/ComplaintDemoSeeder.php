<?php

namespace Database\Seeders;

use App\Domain\Complaints\Models\ComplaintCase;
use App\Domain\Complaints\Models\ComplaintMessage;
use App\Domain\Organisations\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class ComplaintDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Complaint demo data may only be seeded locally or in tests.');
        }

        $organisation = Organisation::factory()->create();
        $reporter = User::factory()->create();
        $case = ComplaintCase::factory()->for($organisation)->create([
            'reported_by_user_id' => $reporter->getKey(),
            'subject' => 'Sample service complaint',
        ]);
        ComplaintMessage::factory()->forCase($case)->create([
            'author_user_id' => $reporter->getKey(),
            'visibility' => 'complainant',
            'body' => 'Sample complaint details for local development.',
        ]);
    }
}
