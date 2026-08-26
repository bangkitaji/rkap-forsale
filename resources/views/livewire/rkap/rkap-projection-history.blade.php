<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold py-3 mb-0">
            <span class="text-muted fw-light">{{ __('RKAP') }} / {{ __('Proyeksi') }} /</span> {{ __('Riwayat') }}
        </h4>
        <a href="{{ route('rkap-projections') }}" class="btn btn-outline-secondary d-flex align-items-center gap-1">
            <i class="bx bx-arrow-back"></i> {{ __('Kembali ke Proyeksi') }}
        </a>
    </div>

    <div class="card">
        <div class="card-header border-bottom bg-light">
            <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                <i class="bx bx-history text-primary fs-4"></i>
                <span>{{ __('Riwayat Perubahan Proyeksi') }}</span>
            </h5>
        </div>
        <div class="card-body pt-4">
            @if($historyItemName)
            <div class="alert alert-primary d-flex align-items-center mb-4">
                <i class="bx bx-info-circle fs-4 me-2"></i>
                <div>
                    <strong>{{ __('Item Anggaran') }}:</strong> {{ $historyItemName }}
                </div>
            </div>
            @endif

            @if(empty($historyLogs))
            <div class="text-center py-5 text-muted">
                <i class="bx bx-history fs-1 mb-2 d-block"></i>
                <p class="mb-0">{{ __('Belum ada riwayat perubahan proyeksi untuk item ini.') }}</p>
            </div>
            @else
            <div class="timeline p-2">
                @foreach($historyLogs as $index => $log)
                <div class="card mb-3 border shadow-none" x-data="{ expanded: false }">
                    <div class="card-header bg-light py-2 px-3 d-flex justify-content-between align-items-center cursor-pointer" @click="expanded = !expanded">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge {{ $log['source'] === 'Upload Massal Excel' ? 'bg-info' : ($log['source'] === 'Sinkronisasi Realisasi' ? 'bg-warning' : 'bg-primary') }}">
                                {{ $log['source'] }}
                            </span>
                            <span class="fw-semibold text-dark">{{ $log['created_at'] }}</span>
                            <span class="text-muted small">oleh <strong>{{ $log['user_name'] }}</strong></span>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="text-end">
                                <span class="text-muted small me-1">Total:</span>
                                <span class="text-decoration-line-through text-muted me-1">Rp {{ number_format($log['old_total'], 0, ',', '.') }}</span>
                                <i class="bx bx-right-arrow-alt text-primary"></i>
                                <span class="fw-bold text-primary">Rp {{ number_format($log['new_total'], 0, ',', '.') }}</span>
                            </div>
                            <button type="button" class="btn btn-xs btn-icon btn-outline-secondary">
                                <i class="bx" :class="expanded ? 'bx-chevron-up' : 'bx-chevron-down'"></i>
                            </button>
                        </div>
                    </div>
                    @if($log['notes'])
                    <div class="px-3 py-2 bg-light border-top text-muted small">
                        <i class="bx bx-comment-detail me-1 text-primary"></i>
                        <strong>{{ __('Catatan') }}:</strong> {{ $log['notes'] }}
                    </div>
                    @endif
                    <div class="card-body p-2" x-show="expanded" x-transition>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('Bulan / Perincian') }}</th>
                                        <th class="text-end">{{ __('Sebelum Edit') }}</th>
                                        <th class="text-end">{{ __('Sesudah Edit') }}</th>
                                        <th class="text-end">{{ __('Selisih / Perubahan') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $monthFullNames = [
                                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                                        ];
                                        $changedMonths = [];
                                        for ($m = 1; $m <= 12; $m++) {
                                            $oldV = (float)($log['old_monthly'][$m] ?? 0);
                                            $newV = (float)($log['new_monthly'][$m] ?? 0);
                                            if (abs($oldV - $newV) > 0.01) {
                                                $changedMonths[$m] = [
                                                    'name' => $monthFullNames[$m],
                                                    'old'  => $oldV,
                                                    'new'  => $newV,
                                                    'diff' => $newV - $oldV,
                                                ];
                                            }
                                        }
                                        $totalDiff = (float)$log['new_total'] - (float)$log['old_total'];
                                    @endphp

                                    @if(!empty($changedMonths))
                                        @foreach($changedMonths as $mInfo)
                                        <tr>
                                            <td class="fw-semibold text-dark">{{ $mInfo['name'] }}</td>
                                            <td class="text-end text-muted">Rp {{ number_format($mInfo['old'], 0, ',', '.') }}</td>
                                            <td class="text-end fw-bold text-primary">Rp {{ number_format($mInfo['new'], 0, ',', '.') }}</td>
                                            <td class="text-end fw-bold {{ $mInfo['diff'] > 0 ? 'text-success' : ($mInfo['diff'] < 0 ? 'text-danger' : 'text-muted') }}">
                                                {{ $mInfo['diff'] > 0 ? '+' : '' }}Rp {{ number_format($mInfo['diff'], 0, ',', '.') }}
                                            </td>
                                        </tr>
                                        @endforeach
                                    @elseif($log['input_mode'] === 'yearly' || abs($totalDiff) > 0.01)
                                        <tr>
                                            <td class="fw-semibold text-dark">{{ __('Proyeksi Tahunan') }}</td>
                                            <td class="text-end text-muted">Rp {{ number_format($log['old_total'], 0, ',', '.') }}</td>
                                            <td class="text-end fw-bold text-primary">Rp {{ number_format($log['new_total'], 0, ',', '.') }}</td>
                                            <td class="text-end fw-bold {{ $totalDiff > 0 ? 'text-success' : ($totalDiff < 0 ? 'text-danger' : 'text-muted') }}">
                                                {{ $totalDiff > 0 ? '+' : '' }}Rp {{ number_format($totalDiff, 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td colspan="4" class="text-center text-muted small py-2">
                                                <i class="bx bx-info-circle me-1 text-warning"></i>
                                                {{ __('Tidak ada rincian bulan yang mengalami perubahan nilai.') }}
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                                <tfoot class="table-light fw-bold">
                                    <tr>
                                        <td>{{ __('Total Akumulasi Proyeksi') }}</td>
                                        <td class="text-end text-muted">Rp {{ number_format($log['old_total'], 0, ',', '.') }}</td>
                                        <td class="text-end text-primary">Rp {{ number_format($log['new_total'], 0, ',', '.') }}</td>
                                        <td class="text-end {{ $totalDiff > 0 ? 'text-success' : ($totalDiff < 0 ? 'text-danger' : 'text-muted') }}">
                                            {{ $totalDiff > 0 ? '+' : '' }}Rp {{ number_format($totalDiff, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
