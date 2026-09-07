<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CashflowGroup;
use App\Models\Coa;
use Illuminate\Support\Facades\File;

class CashflowCoaMappingSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    // Clear existing mappings
    Coa::query()->update(['cashflow_group_id' => null]);

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
      'CF0A1B' => [
        '410101',
        '410102',
        '410103',
        '410201',
        '410202',
        '440101',
        '440102',
      ],
      'CF0A3' => [
        '420101',
        '420201',
        '420301',
        '429901',
        '430101',
      ],
      'CF0B10' => [
        '710101',
        '710102',
        '710103',
        '710104',
      ],
      'CF0B3' => [
        '560101',
        '560102',
        '560103',
        '640101',
        '640102',
        '640103',
        '640104',
        '640105',
        '660101',
        '660102',
      ],
      'CF0B5' => [
        '760101',
        '760102',
        '760103',
        '760901',
        '760301',
        '7603000001',
      ],
      'CF0B2A' => [
        '121101',
        '122101',
        '123101',
        '124101',
        '125101',
        '126101',
        '127101',
      ],
      'CF0B2' => [
        // Direct Costs (HPP)
        '510101',
        '510102',
        '510103',
        '520101',
        '520102',
        '530101',
        '530102',
        '550101',
        '570101',
        '570102',
        '580101',
        '580102',
        // OPEX
        '610101',
        '610102',
        '610103',
        '610104',
        '610105',
        '620101',
        '620102',
        '620103',
        '620104',
        '630101',
        '630102',
        '630103',
        '630104',
        '650101',
        '650102',
        '650103',
        '650104',
        '670101',
        '670102',
        '670103',
        '670104',
        '680101',
        '680102',
        '680103',
        '680104',
        '790101',
        '790102',
      ],
      'CF0E1' => [
        '215101',
        '221101',
        '222101',
      ],
      'CF0E11' => [
        '110205',
        '110301',
        '110302',
      ],
      'CF0F8' => [
        '110101',
        '110102',
        '110201',
        '110202',
        '110203',
        '110204',
      ],
    ];

    foreach ($mappings as $cfCode => $coaCodes) {
      if (is_array($allowedCoaCodes)) {
        $coaCodes = array_values(array_filter(
          $coaCodes,
          static fn($code) => isset($allowedCoaCodes[$code])
        ));
      }

      if (empty($coaCodes)) {
        continue;
      }

      $group = CashflowGroup::where('code', $cfCode)->first();
      if ($group) {
        Coa::whereIn('code', $coaCodes)->update([
          'cashflow_group_id' => $group->id,
        ]);
      }
    }

    $this->command->info('Mapped COAs to Cashflow Groups.');
  }
}
