<div>
    <div class="d-flex justify-content-between align-items-center py-3 mb-4">
        <h4 class="mb-0">
            <span class="text-muted fw-light">RKAP / <a href="{{ route('rkap-submissions') }}" class="text-muted text-decoration-none">Pengajuan</a> /</span>
            Riwayat Versi RKAP
        </h4>
        <a href="{{ route('rkap-submissions') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i> Kembali
        </a>
    </div>

    @if (session()->has('info'))
        <div class="alert alert-info alert-dismissible" role="alert">
            {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <!-- Sidebar: Version List -->
        <div class="col-md-4 col-lg-3 mb-4">
            <div class="card h-100">
                <div class="card-header border-bottom">
                    <h5 class="mb-0">Daftar Versi</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @foreach($submission->versions as $version)
                            <button type="button" class="list-group-item list-group-item-action @if($selectedVersionNumber === $version->version_number) active @endif flex-column align-items-start p-3" wire:click="selectVersion({{ $version->version_number }})">
                                <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0 @if($selectedVersionNumber === $version->version_number) text-white @endif">Versi {{ $version->version_number }} @if($version->version_number === $submission->current_version) <span class="badge bg-white text-primary ms-1">Current</span> @endif</h6>
                                    <small class="@if($selectedVersionNumber === $version->version_number) text-white @else text-muted @endif">{{ $version->created_at->format('d/m/Y') }}</small>
                                </div>
                                <p class="mb-1 small @if($selectedVersionNumber === $version->version_number) text-white @else text-muted @endif">{{ $version->change_type_label }}</p>
                                <small class="@if($selectedVersionNumber === $version->version_number) text-white @else text-muted @endif"><i class="bx bx-user me-1"></i> {{ $version->creator->name ?? '-' }}</small>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content: Version Details & Diff -->
        <div class="col-md-8 col-lg-9">
            @if($this->selectedVersion)
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center border-bottom">
                        <div>
                            <h5 class="mb-0">Detail Versi {{ $this->selectedVersion->version_number }}</h5>
                            <small class="text-muted">Disimpan pada {{ $this->selectedVersion->created_at->format('d M Y, H:i') }} oleh {{ $this->selectedVersion->creator->name ?? '-' }}</small>
                        </div>
                        <div class="text-end">
                            <span class="text-muted small d-block">Total Anggaran Versi Ini</span>
                            <h4 class="mb-0 text-primary">Rp {{ number_format($this->selectedVersion->total_budget, 0, ',', '.') }}</h4>
                        </div>
                    </div>
                    <div class="card-body mt-3">
                        @if($this->selectedVersion->change_reason)
                            <div class="alert alert-warning mb-4">
                                <h6 class="alert-heading mb-1"><i class="bx bx-info-circle me-1"></i>Alasan Perubahan:</h6>
                                <p class="mb-0 small">{{ $this->selectedVersion->change_reason }}</p>
                            </div>
                        @endif

                        <div class="d-flex gap-2 mb-4">
                            @if($this->selectedVersion->version_number > 1 && !$showDiff)
                                <button class="btn btn-primary" wire:click="compareToPrevious">
                                    <i class="bx bx-git-compare me-1"></i> Bandingkan dengan Versi {{ $this->selectedVersion->version_number - 1 }}
                                </button>
                            @elseif($showDiff)
                                <button class="btn btn-outline-secondary" wire:click="$set('showDiff', false)">
                                    <i class="bx bx-x me-1"></i> Tutup Perbandingan
                                </button>
                            @endif
                        </div>

                        @if($showDiff)
                            <!-- Diff View -->
                            <h6 class="text-uppercase text-muted fw-bold mb-3">Hasil Perbandingan (Versi {{ $this->selectedVersion->version_number - 1 }} -> Versi {{ $this->selectedVersion->version_number }})</h6>
                            
                            <div class="mb-3 d-flex gap-3 small">
                                <div><span class="badge bg-success rounded-pill p-1 me-1"><i class="bx bx-plus"></i></span> Ditambahkan</div>
                                <div><span class="badge bg-danger rounded-pill p-1 me-1"><i class="bx bx-minus"></i></span> Dihapus</div>
                                <div><span class="badge bg-warning rounded-pill p-1 me-1"><i class="bx bx-pencil"></i></span> Diubah</div>
                            </div>

                            @forelse($this->diff as $diffItem)
                                @php
                                    $statusClass = match($diffItem['status']) {
                                        'added' => 'border-success',
                                        'removed' => 'border-danger',
                                        'modified' => 'border-warning',
                                        default => 'border-secondary'
                                    };
                                    $bgClass = match($diffItem['status']) {
                                        'added' => 'bg-label-success',
                                        'removed' => 'bg-label-danger',
                                        'modified' => 'bg-label-warning',
                                        default => 'bg-lighter'
                                    };
                                    $icon = match($diffItem['status']) {
                                        'added' => 'bx-plus-circle text-success',
                                        'removed' => 'bx-minus-circle text-danger',
                                        'modified' => 'bx-edit text-warning',
                                        default => 'bx-check-circle text-secondary'
                                    };
                                @endphp

                                <div class="card border border-2 {{ $statusClass }} mb-3 shadow-none">
                                    <div class="card-header {{ $bgClass }} p-3 d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <i class="bx {{ $icon }} bx-sm me-2"></i>
                                            <div>
                                                <h6 class="mb-0">{{ $diffItem['item']['program_name'] }}</h6>
                                                <small class="text-muted">{{ $diffItem['item']['program_code'] ?? '-' }}</small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            @if($diffItem['status'] === 'modified')
                                                <small class="text-muted text-decoration-line-through">Rp {{ number_format($diffItem['old_total'], 0, ',', '.') }}</small>
                                                <h6 class="mb-0 text-warning">Rp {{ number_format($diffItem['new_total'], 0, ',', '.') }}</h6>
                                            @else
                                                <h6 class="mb-0">Rp {{ number_format(collect($diffItem['item']['budget_items'] ?? [])->sum(fn($bi) => $bi['quantity'] * $bi['unit_price']), 0, ',', '.') }}</h6>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    @if($diffItem['status'] !== 'removed')
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-sm mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Uraian Belanja</th>
                                                        <th class="text-center">Vol</th>
                                                        <th class="text-end">Harga Satuan</th>
                                                        <th class="text-end">Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($diffItem['item']['budget_items'] ?? [] as $bi)
                                                        <tr>
                                                            <td>{{ $bi['description'] }}</td>
                                                            <td class="text-center">{{ $bi['quantity'] }} {{ $bi['unit'] ?? '' }}</td>
                                                            <td class="text-end">Rp {{ number_format($bi['unit_price'], 0, ',', '.') }}</td>
                                                            <td class="text-end fw-semibold">Rp {{ number_format($bi['quantity'] * $bi['unit_price'], 0, ',', '.') }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            @empty
                                <div class="alert alert-secondary text-center">
                                    Tidak ada perbedaan ditemukan.
                                </div>
                            @endforelse

                        @else
                            <!-- Snapshot View -->
                            <h6 class="text-uppercase text-muted fw-bold mb-3">Snapshot Data</h6>
                            @foreach($this->selectedVersion->snapshot_data as $idx => $wp)
                                <div class="card border shadow-none mb-3">
                                    <div class="card-header bg-lighter p-3 d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0 text-primary">{{ $wp['program_code'] ?? '-' }} - {{ $wp['program_name'] }}</h6>
                                        </div>
                                        <div class="text-end">
                                            <strong class="text-dark">Rp {{ number_format(collect($wp['budget_items'] ?? [])->sum(fn($bi) => ($bi['quantity'] ?? 0) * ($bi['unit_price'] ?? 0)), 0, ',', '.') }}</strong>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-sm mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Uraian Belanja</th>
                                                        <th class="text-center">Vol</th>
                                                        <th>Satuan</th>
                                                        <th class="text-end">Harga Satuan</th>
                                                        <th class="text-end">Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($wp['budget_items'] ?? [] as $bi)
                                                        <tr>
                                                            <td>{{ $bi['description'] }}</td>
                                                            <td class="text-center">{{ $bi['quantity'] ?? 0 }}</td>
                                                            <td>{{ $bi['unit'] ?? '-' }}</td>
                                                            <td class="text-end">Rp {{ number_format($bi['unit_price'] ?? 0, 0, ',', '.') }}</td>
                                                            <td class="text-end fw-semibold">Rp {{ number_format(($bi['quantity'] ?? 0) * ($bi['unit_price'] ?? 0), 0, ',', '.') }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body text-center py-5 text-muted">
                        <i class="bx bx-history bx-lg d-block mb-3"></i>
                        <p>Pilih versi dari daftar di sebelah kiri untuk melihat detail.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
