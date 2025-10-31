<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Models\Particular;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProcurementSeeder extends Seeder
{
    /**
     * Generate sample procurement records to demonstrate lifecycle coverage.
     */
    public function run(): void
    {
        $officeIds = Office::query()->pluck('id')->all();
        $particularIds = Particular::query()
            ->where('is_active', true)
            ->pluck('id')
            ->all();
        $activeUserIds = User::query()
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if (empty($officeIds) || empty($particularIds) || empty($activeUserIds)) {
            return;
        }

        $faker = fake();
        $statuses = ['Created', 'In Progress', 'Completed', 'On Hold', 'Cancelled'];
        $procurements = [];

        foreach (range(1, 10) as $index) {
            $createdAt = Carbon::now()->subDays($faker->numberBetween(0, 90));
            $endUserId = Arr::random($officeIds);
            $particularId = Arr::random($particularIds);

            $procurements[] = [
                'end_user_id' => $endUserId,
                'particular_id' => $particularId,
                'purpose' => $faker->sentence(12),
                'abc_amount' => round($faker->randomFloat(2, 5000, 500000), 2),
                'date_of_entry' => $faker->dateTimeBetween('-90 days')->format('Y-m-d'),
                'status' => Arr::random($statuses),
                'created_by_user_id' => Arr::random($activeUserIds),
                'created_at' => $createdAt,
                'updated_at' => (clone $createdAt)->addDays($faker->numberBetween(0, 10)),
                'deleted_at' => null,
            ];
        }

        DB::table('procurements')->insert($procurements);
    }
}
