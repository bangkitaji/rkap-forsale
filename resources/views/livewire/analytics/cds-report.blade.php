<div>
    <div class="py-3 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="mb-1"><span class="text-muted fw-light">{{ __('Analytics') }} /</span> {{ __('Laporan CDS') }}</h4>
            <p class="text-muted mb-0">{{ __('Akumulasi Nilai Anggaran (Cash Flow), Realisasi, dan Proyeksi berdasarkan CDS Group') }}</p>
        </div>

        <div class="d-flex align-items-center gap-2 bg-white px-3 py-2 rounded shadow-sm border">
            <label for="periodSelect" class="text-muted fw-semibold mb-0 text-nowrap d-flex align-items-center gap-1 rkap-font-09">
                <i class="bx bx-calendar text-primary fs-4"></i>
                <span>{{ __('Periode RKAP:') }}</span>
            </label>
            <select wire:model.live="selectedPeriodId" id="periodSelect"
                class="form-select form-select-sm border-0 fw-semibold text-primary cursor-pointer focus-ring-none rkap-font-09">
                @foreach ($periods as $p)
                <option value="{{ $p->id }}">
                    {{ $p->title }} ({{ $p->year }})
                </option>
                @endforeach
            </select>

            <label for="monthSelect" class="text-muted fw-semibold mb-0 ms-2 text-nowrap d-flex align-items-center gap-1 rkap-font-09">
                <span>{{ __('Bulan:') }}</span>
            </label>
            <select wire:model.live="selectedMonth" id="monthSelect"
                class="form-select form-select-sm border-0 fw-semibold text-primary cursor-pointer focus-ring-none rkap-font-09">
                <option value="">Semua Bulan (YTD)</option>
                @for ($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}">{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                @endfor
            </select>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card h-100 border-start border-primary border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted fw-medium small">{{ __('Total RKAP (Cash Flow)') }}</span>
                        <div class="avatar avatar-sm flex-shrink-0">
                            <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-wallet fs-4"></i></span>
                        </div>
                    </div>
                    <h4 class="mb-0 fw-bold text-primary">{{ $this->formatRp($grandTotalBudget) }}</h4>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card h-100 border-start border-success border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted fw-medium small">{{ __('Total Realisasi') }}</span>
                        <div class="avatar avatar-sm flex-shrink-0">
                            <span class="avatar-initial rounded bg-label-success"><i class="bx bx-check-circle fs-4"></i></span>
                        </div>
                    </div>
                    <h4 class="mb-0 fw-bold text-success">{{ $this->formatRp($grandTotalRealization) }}</h4>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card h-100 border-start border-info border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted fw-medium small">{{ __('Total Proyeksi') }}</span>
                        <div class="avatar avatar-sm flex-shrink-0">
                            <span class="avatar-initial rounded bg-label-info"><i class="bx bx-trending-up fs-4"></i></span>
                        </div>
                    </div>
                    <h4 class="mb-0 fw-bold text-info">{{ $this->formatRp($grandTotalProjection) }}</h4>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card h-100 border-start border-warning border-4 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted fw-medium small">{{ __('Tingkat Penyerapan') }}</span>
                        <div class="avatar avatar-sm flex-shrink-0">
                            <span class="avatar-initial rounded bg-label-warning"><i class="bx bx-pie-chart-alt-2 fs-4"></i></span>
                        </div>
                    </div>
                    <h4 class="mb-0 fw-bold text-warning">{{ $grandAbsorption }}%</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Main CDS Group Table -->
    <div class="card shadow-sm">
        <div class="card-header border-bottom py-3">
            <h5 class="card-title mb-0">{{ __('Rincian Akumulasi per CDS Group') }}</h5>
        </div>

        <div class="table-responsive text-nowrap">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('CDS Group') }}</th>
                        <th class="text-end">{{ __('RKAP (Cash Flow)') }}</th>
                        <th class="text-end">{{ __('Realisasi') }}</th>
                        <th class="text-end">{{ __('Proyeksi') }}</th>
                        <th class="text-center">{{ __('% Penyerapan') }}</th>
                        <th class="text-center">{{ __('% Outlook') }}</th>
                        <th class="text-center">{{ __('Detail Mappings') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reportData as $index => $item)
                    <tr>
                        <td>
                            <span class="badge bg-label-primary me-2">{{ $item['group']->code }}</span>
                            <strong>{{ $item['group']->name }}</strong>
                        </td>
                        <td class="text-end fw-semibold">{{ $this->formatRp($item['budget']) }}</td>
                        <td class="text-end text-success fw-semibold">{{ $this->formatRp($item['realization']) }}</td>
                        <td class="text-end text-info fw-semibold">{{ $this->formatRp($item['projection']) }}</td>
                        <td class="text-center">
                            <span class="badge bg-label-{{ $item['absorption_rate'] >= 80 ? 'success' : ($item['absorption_rate'] >= 50 ? 'warning' : 'danger') }}">
                                {{ $item['absorption_rate'] }}%
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-label-info">{{ $item['outlook_rate'] }}%</span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-icon btn-outline-secondary rounded-pill" type="button"
                                data-bs-toggle="collapse" data-bs-target="#cds-detail-{{ $item['group']->id }}"
                                aria-expanded="false" aria-controls="cds-detail-{{ $item['group']->id }}">
                                <i class="bx bx-chevron-down"></i>
                            </button>
                        </td>
                    </tr>

                    <!-- Collapsible details row -->
                    <tr class="collapse" id="cds-detail-{{ $item['group']->id }}">
                        <td colspan="7" class="bg-light p-3">
                            <div class="px-3 py-2">
                                <h6 class="fw-bold mb-2"><i class="bx bx-git-repo-forked me-1"></i> Termasuk Mappings ke CDS Group ini:</h6>
                                @if(!empty($item['details']))
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered bg-white mb-0">
                                        <thead>
                                            <tr class="table-secondary">
                                                <th>Tipe Group</th>
                                                <th>Kode</th>
                                                <th>Nama Group</th>
                                                <th class="text-end">Kontribusi RKAP</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($item['details'] as $detail)
                                            <tr>
                                                <td><span class="badge bg-label-{{ $detail['type'] === 'COA Group' ? 'info' : 'success' }}">{{ $detail['type'] }}</span></td>
                                                <td><code>{{ $detail['code'] }}</code></td>
                                                <td>{{ $detail['name'] }}</td>
                                                <td class="text-end">{{ $this->formatRp($detail['budget']) }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @else
                                <span class="text-muted small">Belum ada COA Group atau Cash Flow Group yang di-mapping ke CDS Group ini.</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Belum ada data CDS Group.</td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light border-top-2">
                    <tr class="fw-bold">
                        <td>TOTAL KESELURUHAN</td>
                        <td class="text-end text-primary">{{ $this->formatRp($grandTotalBudget) }}</td>
                        <td class="text-end text-success">{{ $this->formatRp($grandTotalRealization) }}</td>
                        <td class="text-end text-info">{{ $this->formatRp($grandTotalProjection) }}</td>
                        <td class="text-center"><span class="badge bg-primary">{{ $grandAbsorption }}%</span></td>
                        <td class="text-center" colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
