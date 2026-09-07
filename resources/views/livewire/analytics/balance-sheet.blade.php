<div>
    <h4 class="mb-4">{{ __('Laporan Neraca (Balance Sheet)') }}</h4>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="periodId" class="form-label">{{ __('Tahun / Periode RKAP') }}</label>
                    <select wire:model.live="periodId" id="periodId" class="form-select">
                        <option value="">-- {{ __('Pilih Periode') }} --</option>
                        @foreach($availablePeriods as $period)
                            <option value="{{ $period->id }}">{{ $period->year }} - {{ $period->title }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover align-middle mb-0" x-data="balanceSheetTable()">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>{{ __('Akun') }}</th>
                            <th class="text-end">{{ __('Saldo Awal') }}</th>
                            <th class="text-end">{{ __('Anggaran (RKAP)') }}</th>
                            <th class="text-end">{{ __('Realisasi YTD') }}</th>
                            <th class="text-end">{{ __('Proyeksi Akhir') }}</th>
                            <th class="text-center">{{ __('Pencapaian (%)') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bsData as $rgIndex => $rg)
                            <!-- Report Group Row -->
                            <tr class="table-secondary fw-bold" style="cursor: pointer;" @click="toggle('rg_{{ $rg['code'] }}')">
                                <td>
                                    <i class="bx" :class="expanded['rg_{{ $rg['code'] }}'] ? 'bx-chevron-down' : 'bx-chevron-right'"></i>
                                    {{ __($rg['name']) }}
                                </td>
                                <td class="text-end">{{ number_format($rg['opening'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($rg['budget'], 0, ',', '.') }}</td>
                                <td class="text-end text-success">{{ number_format($rg['realization'], 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($rg['projection'], 0, ',', '.') }}</td>
                                <td class="text-center">
                                    {{ $rg['budget'] != 0 ? number_format(($rg['realization'] / $rg['budget']) * 100, 2, ',', '.') . '%' : '-' }}
                                </td>
                            </tr>

                            <!-- COA Groups and COAs -->
                            @foreach($rg['groups'] as $cgIndex => $cg)
                                <template x-if="expanded['rg_{{ $rg['code'] }}']">
                                    <!-- COA Group Row -->
                                    <tr class="table-light fw-semibold" style="cursor: pointer;" @click="toggle('cg_{{ $cg['code'] }}')">
                                        <td class="ps-4">
                                             @if(count($cg['coas']) > 0)
                                                <i class="bx" :class="expanded['cg_{{ $cg['code'] }}'] ? 'bx-chevron-down' : 'bx-chevron-right'"></i>
                                            @else
                                                <i class="bx bx-minus"></i>
                                            @endif
                                            {{ __($cg['name']) }}
                                        </td>
                                        <td class="text-end">{{ number_format($cg['opening'], 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($cg['budget'], 0, ',', '.') }}</td>
                                        <td class="text-end text-success">{{ number_format($cg['realization'], 0, ',', '.') }}</td>
                                        <td class="text-end">{{ number_format($cg['projection'], 0, ',', '.') }}</td>
                                        <td class="text-center">
                                            {{ $cg['budget'] != 0 ? number_format(($cg['realization'] / $cg['budget']) * 100, 2, ',', '.') . '%' : '-' }}
                                        </td>
                                    </tr>
                                </template>

                                @foreach($cg['coas'] as $coa)
                                    <template x-if="expanded['rg_{{ $rg['code'] }}'] && expanded['cg_{{ $cg['code'] }}']">
                                        <!-- COA Row -->
                                        <tr>
                                            <td class="ps-5 text-muted">{{ $coa['code'] }} - {{ $coa['name'] }}</td>
                                            <td class="text-end text-muted">{{ number_format($coa['opening'], 0, ',', '.') }}</td>
                                            <td class="text-end text-muted">{{ number_format($coa['budget'], 0, ',', '.') }}</td>
                                            <td class="text-end text-success">{{ number_format($coa['realization'], 0, ',', '.') }}</td>
                                            <td class="text-end text-muted">{{ number_format($coa['projection'], 0, ',', '.') }}</td>
                                            <td class="text-center text-muted">
                                                {{ $coa['budget'] != 0 ? number_format(($coa['realization'] / $coa['budget']) * 100, 2, ',', '.') . '%' : '-' }}
                                            </td>
                                        </tr>
                                    </template>
                                @endforeach
                            @endforeach
                        @endforeach
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td class="text-end">{{ __('TOTAL ASET') }}</td>
                            <td class="text-end">{{ number_format($totals['aset']['opening'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($totals['aset']['budget'], 0, ',', '.') }}</td>
                            <td class="text-end text-success">{{ number_format($totals['aset']['realization'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($totals['aset']['projection'], 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="text-end">{{ __('TOTAL LIABILITAS & EKUITAS') }}</td>
                            <td class="text-end">{{ number_format($totals['liabilitas_ekuitas']['opening'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($totals['liabilitas_ekuitas']['budget'], 0, ',', '.') }}</td>
                            <td class="text-end text-success">{{ number_format($totals['liabilitas_ekuitas']['realization'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($totals['liabilitas_ekuitas']['projection'], 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                        @php
                            $balOpening = $totals['aset']['opening'] - $totals['liabilitas_ekuitas']['opening'];
                            $balBudget = $totals['aset']['budget'] - $totals['liabilitas_ekuitas']['budget'];
                            $balReal = $totals['aset']['realization'] - $totals['liabilitas_ekuitas']['realization'];
                            $balProj = $totals['aset']['projection'] - $totals['liabilitas_ekuitas']['projection'];
                            $isBalanced = abs($balOpening) < 0.01 && abs($balBudget) < 0.01 && abs($balReal) < 0.01 && abs($balProj) < 0.01;
                        @endphp
                        <tr class="{{ $isBalanced ? 'table-success' : 'table-danger' }}">
                            <td class="text-end">{{ __('CHECK BALANCE (Selisih)') }}</td>
                            <td class="text-end">{{ number_format($balOpening, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($balBudget, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($balReal, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($balProj, 0, ',', '.') }}</td>
                            <td class="text-center">
                                @if($isBalanced)
                                    <i class="bx bx-check-circle fs-4 text-success" title="{{ __('Neraca seimbang') }}"></i>
                                @else
                                    <i class="bx bx-x-circle fs-4 text-danger" title="{{ __('Neraca tidak seimbang') }}"></i>
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <script>
                function balanceSheetTable() {
                    return {
                        expanded: {},
                        toggle(key) {
                            this.expanded[key] = !this.expanded[key];
                        }
                    }
                }
            </script>
        </div>
    </div>
</div>
