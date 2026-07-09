<div>
    <div class="py-3 mb-4">
        <h4 class="mb-1"><span class="text-muted fw-light">RKAP /</span> Dashboard</h4>
        @if($activePeriod)
        <p class="text-muted mb-0">Menampilkan data untuk periode aktif: <strong>{{ $activePeriod->title }}</strong></p>
        @else
        <div class="alert alert-warning mt-2 mb-0 py-2">
            <i class="bx bx-info-circle me-1"></i> Belum ada periode RKAP yang aktif (Open). <a href="{{ route('rkap-periods') }}" class="alert-link">Kelola Periode</a>.
        </div>
        @endif
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>Total Pengajuan</span>
                            <div class="d-flex align-items-end mt-2">
                                <h4 class="mb-0 me-2">{{ $stats['total'] }}</h4>
                            </div>
                            <small class="text-muted">Dalam periode aktif</small>
                        </div>
                        <span class="badge bg-label-primary rounded p-2">
                            <i class="bx bx-file bx-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>Menunggu Review</span>
                            <div class="d-flex align-items-end mt-2">
                                <h4 class="mb-0 me-2">{{ $stats['pending'] }}</h4>
                            </div>
                            <small class="text-warning">Perlu tindak lanjut</small>
                        </div>
                        <span class="badge bg-label-warning rounded p-2">
                            <i class="bx bx-time-five bx-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span>Disetujui Final</span>
                            <div class="d-flex align-items-end mt-2">
                                <h4 class="mb-0 me-2">{{ $stats['approved'] }}</h4>
                            </div>
                            <small class="text-success">Telah diverifikasi</small>
                        </div>
                        <span class="badge bg-label-success rounded p-2">
                            <i class="bx bx-check-circle bx-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="content-left">
                            <span class="text-white opacity-75">Total Anggaran (Milyar)</span>
                            <div class="d-flex align-items-end mt-2">
                                <h4 class="mb-0 text-white me-2">Rp {{ number_format($stats['total_budget'] / 1000000000, 2, ',', '.') }}</h4>
                            </div>
                            <small class="text-white opacity-75">Estimasi keseluruhan</small>
                        </div>
                        <span class="badge bg-white text-primary rounded p-2">
                            <i class="bx bx-money bx-sm"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php
    $isManagement = auth()->user()->isPresidentDirector() || auth()->user()->isDirekturFinance() || auth()->user()->isVerifikator() || auth()->user()->isAdmin();
    $colClass = $isManagement ? 'col-xl-4 col-lg-4 col-md-6 mb-4' : 'col-xl-6 col-lg-6 col-md-6 mb-4';
    @endphp

    <div class="row mb-4">
        <!-- Pending Actions / Tasks -->
        <div class="{{ $colClass }}">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Tugas Saya</h5>
                    <small class="text-muted">Butuh Perhatian</small>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        @forelse($myActions as $action)
                        @php
                        $isBiro = auth()->user()->isKepalaBiro();
                        $targetRoute = $isBiro
                        ? route('rkap-submissions-edit', $action->id)
                        : route('rkap-submissions-approval-review', $action->id);

                        $badgeText = 'Perlu Review';
                        $badgeColor = 'warning';

                        if ($action->status === 'draft') {
                        $badgeText = 'Draf';
                        $badgeColor = 'secondary';
                        } elseif (in_array($action->status, ['dept_revision', 'dir_revision', 'final_revision', 'pdir_revision'])) {
                        $badgeText = 'Perlu Revisi';
                        $badgeColor = 'danger';
                        }
                        @endphp
                        <li class="mb-3 pb-3 border-bottom">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex flex-column">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge bg-label-{{ $badgeColor }}">{{ $badgeText }}</span>
                                        <small class="text-muted">{{ $action->updated_at->diffForHumans() }}</small>
                                    </div>
                                    <a href="{{ $targetRoute }}" class="h6 mb-0 text-primary">RKAP {{ $action->bureau->name ?? '-' }}</a>
                                    <small class="text-muted">{{ $action->bureau->department->name ?? '-' }}</small>
                                </div>
                                <a href="{{ $targetRoute }}" class="btn btn-sm btn-icon btn-primary rounded-pill">
                                    <i class="bx bx-chevron-right"></i>
                                </a>
                            </div>
                        </li>
                        @empty
                        <li class="text-center text-muted py-4">
                            <i class="bx bx-check-circle bx-lg text-success mb-2 opacity-50"></i>
                            <p class="mb-0">Tidak ada tugas yang menunggu Anda saat ini.</p>
                        </li>
                        @endforelse
                    </ul>
                    @if($myActions->count() > 0)
                    <div class="mt-3 text-center">
                        <a href="{{ route('rkap-submissions') }}" class="btn btn-sm btn-label-secondary w-100">Lihat Semua Pengajuan</a>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Status Pengajuan Departemen -->
        @if($isManagement)
        <div class="{{ $colClass }}">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-1">Status Pengajuan Departemen</h5>
                    <p class="text-muted small">Status verifikasi RKAP per departemen</p>

                    <div class="d-flex align-items-center justify-content-between mt-3 mb-2">
                        <span class="fw-semibold">Terverifikasi</span>
                        <span class="badge bg-label-success">{{ $verifiedDeptCount }} dari {{ $totalDeptCount }} Departemen</span>
                    </div>

                    @php
                    $progressPercent = $totalDeptCount > 0 ? ($verifiedDeptCount / $totalDeptCount) * 100 : 0;
                    @endphp

                    <div class="progress mb-3 rkap-h-12">
                        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" :style="{ width: '{{ $progressPercent }}%' }" aria-valuenow="{{ $progressPercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mt-3 mb-2">
                        <span class="fw-semibold">Presentasi Pengajuan</span>
                        <span class="badge bg-label-primary">Rata-rata {{ number_format($averagePresentation, 2, ',', '.') }}%</span>
                    </div>

                    <div class="progress mb-3 rkap-h-12">
                        <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" role="progressbar" :style="{ width: '{{ $averagePresentation }}%' }" aria-valuenow="{{ $averagePresentation }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>

                    @if($verifiedDeptCount < $totalDeptCount)
                        <div class="alert alert-warning py-2 px-3 mb-0 rkap-font-075">
                        <i class="bx bx-lock-alt me-1"></i>
                        Persetujuan akhir oleh Direktur Utama ditangguhkan hingga seluruh {{ $totalDeptCount }} departemen terverifikasi.
                </div>
                @else
                <div class="alert alert-success py-2 px-3 mb-0 rkap-font-075">
                    <i class="bx bx-check-double me-1"></i>
                    Seluruh departemen telah terverifikasi. Direktur Utama dapat memberikan persetujuan akhir.
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Recent Activity -->
    <div class="{{ $colClass }}">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h5 class="mb-0">Aktivitas Terbaru</h5>
            </div>
            <div class="card-body mt-3 rkap-timeline-scroll">
                <ul class="timeline mb-0">
                    @forelse($recentActivity as $activity)
                    <li class="timeline-item timeline-item-transparent ps-4">
                        <span class="timeline-point timeline-point-{{ $activity->status_color }}"></span>
                        <div class="timeline-event">
                            <div class="timeline-header mb-1">
                                <h6 class="mb-0">Status: {{ $activity->status_label }}</h6>
                                <small class="text-muted">{{ $activity->updated_at->diffForHumans() }}</small>
                            </div>
                            <p class="mb-0 small">Biro: <strong>{{ $activity->bureau->name ?? '-' }}</strong></p>
                            <div class="mt-2">
                                <a href="{{ route('rkap-submissions-review', $activity->id) }}" class="text-body small d-flex align-items-center">
                                    <i class="bx bx-link me-1"></i> Lihat Detail
                                </a>
                            </div>
                        </div>
                    </li>
                    @empty
                    <li class="text-center text-muted py-4 list-unstyled">
                        <p class="mb-0">Belum ada aktivitas.</p>
                    </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Helicopter Progress & Department Tabulation for Management -->
@if($isManagement)
<div class="row mb-4">
    <!-- Tabulation Card -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header p-0">
                <div class="nav-align-top">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#tab-dept-status" aria-controls="tab-dept-status" aria-selected="true">
                                <i class="bx bx-buildings me-1"></i> Status Departemen
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-verified" aria-controls="tab-verified" aria-selected="false">
                                <i class="bx bx-check-shield me-1"></i> Terverifikasi ({{ $submissionsByStatus['verified']->count() }})
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-review" aria-controls="tab-review" aria-selected="false">
                                <i class="bx bx-hourglass me-1"></i> Direview ({{ $submissionsByStatus['review']->count() }})
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-draft" aria-controls="tab-draft" aria-selected="false">
                                <i class="bx bx-edit me-1"></i> Draf/Revisi ({{ $submissionsByStatus['draft']->count() }})
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="tab-content border-0 p-0">
                <!-- Tab 1: Department Statuses -->
                <div class="tab-pane fade show active p-4" id="tab-dept-status" role="tabpanel">
                    <div class="table-responsive text-nowrap">
                        <table class="table table-hover table-striped align-middle mb-0 rkap-font-085">
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Nama Departemen</th>
                                    <th>Presentasi Pengajuan</th>
                                    <th>Status Pengajuan</th>
                                    <th class="text-end">Total Anggaran</th>
                                    <th class="text-start">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($departmentsSubmissions as $ds)
                                @php
                                $statusColor = match($ds['status']) {
                                'Terverifikasi' => 'success',
                                'Sedang Direview' => 'info',
                                'Draf / Revisi' => 'warning',
                                default => 'secondary',
                                };
                                @endphp
                                <tr>
                                    <td><strong>{{ $ds['department']->code }}</strong></td>
                                    <td>{{ $ds['department']->name }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1 rkap-h-12">
                                                <div class="progress-bar bg-primary" role="progressbar" :style="{ width: '{{ $ds['presentation_percent'] }}%' }" aria-valuenow="{{ $ds['presentation_percent'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <span class="fw-semibold text-primary small text-nowrap">{{ number_format($ds['presentation_percent'], 2, ',', '.') }}%</span>
                                        </div>
                                        <div class="text-muted small mt-1">
                                            {{ $ds['submitted_bureaus'] }} dari {{ $ds['total_bureaus'] }} biro aktif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-label-{{ $statusColor }} fw-semibold">{{ $ds['status'] }}</span>
                                    </td>
                                    <td class="text-end fw-semibold text-primary">
                                        Rp {{ number_format($ds['total_budget'], 0, ',', '.') }}
                                    </td>
                                    <td class="text-start">
                                        @if($ds['submissions']->isNotEmpty())
                                        <div class="btn-group">
                                            @foreach($ds['submissions'] as $sub)
                                            <a href="{{ route('rkap-submissions-approval-review', $sub->id) }}" class="btn btn-xs btn-outline-primary" title="Detail RKAP {{ $sub->bureau->name }}">
                                                {{ $sub->bureau->name }} <i class="bx bx-link-external ms-1"></i>
                                            </a>
                                            @endforeach
                                        </div>
                                        @else
                                        <span class="text-muted small">-</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab 2: Verified Submissions -->
                <div class="tab-pane fade p-4" id="tab-verified" role="tabpanel">
                    @if($submissionsByStatus['verified']->isEmpty())
                    <div class="text-center py-4 text-muted">
                        <i class="bx bx-check-shield bx-md opacity-50 mb-2"></i>
                        <p class="mb-0">Belum ada pengajuan yang diverifikasi oleh verifikator.</p>
                    </div>
                    @else
                    <div class="table-responsive text-nowrap">
                        <table class="table table-hover table-striped mb-0 rkap-font-085">
                            <thead>
                                <tr>
                                    <th>Biro / Departemen</th>
                                    <th class="text-center">Versi</th>
                                    <th>Diajukan Oleh</th>
                                    <th class="text-end">Total Anggaran</th>
                                    <th>Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($submissionsByStatus['verified'] as $sub)
                                <tr>
                                    <td>
                                        <strong>{{ $sub->bureau->name }}</strong>
                                        <div class="text-muted small rkap-font-075">{{ $sub->bureau->department->name }}</div>
                                    </td>
                                    <td class="text-center"><span class="badge bg-label-secondary">v{{ $sub->current_version }}</span></td>
                                    <td>{{ $sub->creator->name ?? '-' }}</td>
                                    <td class="text-end fw-semibold text-primary">Rp {{ number_format($sub->total_budget, 0, ',', '.') }}</td>
                                    <td><span class="badge bg-label-{{ $sub->status_color }}">{{ $sub->status_label }}</span></td>
                                    <td class="text-center">
                                        <a href="{{ route('rkap-submissions-approval-review', $sub->id) }}" class="btn btn-xs btn-primary">
                                            <i class="bx bx-search-alt"></i> Buka Review
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>

                <!-- Tab 3: Submissions in review -->
                <div class="tab-pane fade p-4" id="tab-review" role="tabpanel">
                    @if($submissionsByStatus['review']->isEmpty())
                    <div class="text-center py-4 text-muted">
                        <i class="bx bx-hourglass bx-md opacity-50 mb-2"></i>
                        <p class="mb-0">Tidak ada pengajuan yang sedang berada dalam proses review.</p>
                    </div>
                    @else
                    <div class="table-responsive text-nowrap">
                        <table class="table table-hover table-striped mb-0 rkap-font-085">
                            <thead>
                                <tr>
                                    <th>Biro / Departemen</th>
                                    <th class="text-center">Versi</th>
                                    <th>Diajukan Oleh</th>
                                    <th class="text-end">Total Anggaran</th>
                                    <th>Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($submissionsByStatus['review'] as $sub)
                                <tr>
                                    <td>
                                        <strong>{{ $sub->bureau->name }}</strong>
                                        <div class="text-muted small rkap-font-075">{{ $sub->bureau->department->name }}</div>
                                    </td>
                                    <td class="text-center"><span class="badge bg-label-secondary">v{{ $sub->current_version }}</span></td>
                                    <td>{{ $sub->creator->name ?? '-' }}</td>
                                    <td class="text-end fw-semibold text-primary">Rp {{ number_format($sub->total_budget, 0, ',', '.') }}</td>
                                    <td><span class="badge bg-label-{{ $sub->status_color }}">{{ $sub->status_label }}</span></td>
                                    <td class="text-center">
                                        <a href="{{ route('rkap-submissions-approval-review', $sub->id) }}" class="btn btn-xs btn-outline-primary">
                                            Detail <i class="bx bx-chevron-right"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>

                <!-- Tab 4: Submissions in draft/revision -->
                <div class="tab-pane fade p-4" id="tab-draft" role="tabpanel">
                    @if($submissionsByStatus['draft']->isEmpty())
                    <div class="text-center py-4 text-muted">
                        <i class="bx bx-edit bx-md opacity-50 mb-2"></i>
                        <p class="mb-0">Tidak ada pengajuan dengan status draf atau revisi.</p>
                    </div>
                    @else
                    <div class="table-responsive text-nowrap">
                        <table class="table table-hover table-striped mb-0 rkap-font-085">
                            <thead>
                                <tr>
                                    <th>Biro / Departemen</th>
                                    <th class="text-center">Versi</th>
                                    <th>Diajukan Oleh</th>
                                    <th class="text-end">Total Anggaran</th>
                                    <th>Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($submissionsByStatus['draft'] as $sub)
                                <tr>
                                    <td>
                                        <strong>{{ $sub->bureau->name }}</strong>
                                        <div class="text-muted small rkap-font-075">{{ $sub->bureau->department->name }}</div>
                                    </td>
                                    <td class="text-center"><span class="badge bg-label-secondary">v{{ $sub->current_version }}</span></td>
                                    <td>{{ $sub->creator->name ?? '-' }}</td>
                                    <td class="text-end fw-semibold text-primary">Rp {{ number_format($sub->total_budget, 0, ',', '.') }}</td>
                                    <td><span class="badge bg-label-{{ $sub->status_color }}">{{ $sub->status_label }}</span></td>
                                    <td class="text-center">
                                        <a href="{{ route('rkap-submissions-review', $sub->id) }}" class="btn btn-xs btn-outline-secondary">
                                            Detail <i class="bx bx-chevron-right"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Budget Chart for Admins/Verificators -->
@if(auth()->user()->isAdmin() || auth()->user()->isVerifikator())
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                <h5 class="mb-0">Rekapitulasi Anggaran per Direktorat</h5>
            </div>
            <div class="card-body mt-4">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Direktorat</th>
                                <th class="text-end">Total Anggaran (Rp)</th>
                                <th>Proporsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $grandTotal = $budgetByDirectorate->sum('total'); @endphp
                            @forelse($budgetByDirectorate as $budget)
                            @php $percentage = $grandTotal > 0 ? ($budget->total / $grandTotal) * 100 : 0; @endphp
                            <tr>
                                <td class="fw-medium">{{ $budget->directorate }}</td>
                                <td class="text-end fw-semibold text-primary">Rp {{ number_format($budget->total, 0, ',', '.') }}</td>
                                <td class="align-middle w-30p">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="progress w-100 rkap-h-8">
                                            <div class="progress-bar bg-primary" role="progressbar" :style="{ width: '{{ $percentage }}%' }"></div>
                                        </div>
                                        <small>{{ number_format($percentage, 1) }}%</small>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-4">Belum ada data anggaran.</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td>TOTAL KESELURUHAN</td>
                                <td class="text-end text-primary fs-5">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
</div>