<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DifferenceGroup;
use App\Models\Coa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DifferenceCoaMappingSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    // Reset existing mappings
    DB::table('difference_group_coa')->delete();

    // Use COA master JSON as the source of valid COA codes for mapping.
    $allowedCoaCodes = null;
    $coaFilePath = database_path('seeders/data/coas.json');
    if (File::exists($coaFilePath)) {
      $coaData = json_decode(File::get($coaFilePath), true);
      if (is_array($coaData)) {
        $allowedCoaCodes = array_flip(
          array_values(
            array_filter(array_map(
              static fn($item) => isset($item['code']) ? trim((string) $item['code']) : null,
              $coaData
            ))
          )
        );
      }
    }

    $mappings = [
      '1001C' => [
        '110205',
        '110301',
        '110302',
      ],
      '1007' => [
        '121101',
        '122101',
        '122199',
        '123101',
        '123199',
        '124101',
        '124199',
        '125101',
        '125199',
      ],
      '1009' => [
        '126101',
        '126199',
      ],
      '1010' => [
        '127101',
        '127199',
      ],
      '1006' => [
        '128101',
      ],
      '2000A' => [
        '212101',
      ],
      '2000B' => [
        '212104',
      ],
      '2000C' => [
        '212102',
        '212103',
      ],
      '2001' => [
        '214101',
        '214102',
      ],
      '2003' => [
        '211101',
        '211102',
      ],
      '2004' => [
        '213101',
        '213102',
        '213103',
        '213104',
        '213201',
      ],
      '2007' => [
        '215101',
        '221101',
        '222101',
        '223101',
      ],
      '7003' => [
        '710101',
        '710102',
      ],
    ];

    $records = [];
    foreach ($mappings as $dgCode => $coaCodes) {
      if (is_array($allowedCoaCodes)) {
        $coaCodes = array_values(array_filter(
          $coaCodes,
          static fn($code) => isset($allowedCoaCodes[$code])
        ));
      }

      if (empty($coaCodes)) {
        continue;
      }

      $group = DifferenceGroup::where('code', $dgCode)->first();
      if (!$group) {
        continue;
      }

      $coas = Coa::whereIn('code', $coaCodes)->pluck('id');
      foreach ($coas as $coaId) {
        $records[] = [
          'difference_group_id' => $group->id,
          'coa_id' => $coaId,
          'created_at' => now(),
          'updated_at' => now(),
        ];
      }
    }

    if (!empty($records)) {
      DB::table('difference_group_coa')->insert($records);
    }

    $this->command->info('Mapped COAs to Difference Groups. Total links: ' . count($records));
  }
}
