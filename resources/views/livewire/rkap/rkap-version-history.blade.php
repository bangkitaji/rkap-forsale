<div>
    <div class="d-flex justify-content-between align-items-center py-3 mb-4">
        <h4 class="mb-0">
            <span class="text-muted fw-light">RKAP / <a href="{{ route('rkap-submissions') }}" class="text-muted text-decoration-none">Pengajuan</a> /</span>
            Riwayat Versi RKAP
        </h4>
        <a href="{{ route('rkap-submissions') }}" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i> {{ __('Kembali') }}
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
                    <h5 class="mb-0">{{ __('Daftar Versi') }}</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @foreach($submission->versions as $version)
                        <button type="button" class="list-group-item list-group-item-action @if($selectedVersionNumber === $version->version_number) active @endif flex-column align-items-start p-3" wire:click="selectVersion({{ $version->version_number }})">
                            <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                <h6 class="mb-0 @if($selectedVersionNumber === $version->version_number) text-white @endif">Versi {{ $version->version_number }} @if($version->version_number === $submission->current_version) <span class="badge bg-white text-primary ms-1">{{ __('Current') }}</span> @endif</h6>
                                <small class="@if($selectedVersionNumber === $version->version_number) text-white @else text-muted @endif">{{ $version->created_at->timezone('Asia/Jakarta')->format('d/m/Y') }}</small>
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
                        <small class="text-muted">{{ __('Disimpan pada') }} {{ $this->selectedVersion->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} {{ __('oleh') }} {{ $this->selectedVersion->creator->name ?? '-' }}</small>
                    </div>
                    <div class="text-end">
                        <span class="text-muted small d-block">{{ __('Total Anggaran Versi Ini') }}</span>
                        <h4 class="mb-0 text-primary">Rp {{ number_format($this->selectedVersion->total_budget, 0, ',', '.') }}</h4>
                    </div>
                </div>
                <div class="card-body mt-3">
                    @if($this->selectedVersion->change_reason)
                    <div class="alert alert-warning mb-4">
                        <h6 class="alert-heading mb-1"><i class="bx bx-info-circle me-1"></i>{{ __('Alasan Perubahan:') }}</h6>
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
                                <h6 class="mb-0">Rp {{ number_format(collect($diffItem['item']['budget_items'] ?? [])->sum(fn($bi) => (float)($bi['quantity'] ?? 0) * (float)($bi['unit_price'] ?? 0)), 0, ',', '.') }}</h6>
                                @endif
                            </div>
                        </div>

                        @if($diffItem['status'] !== 'removed')
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>{{ __('Uraian Belanja') }}</th>
                                            <th class="text-center">Vol</th>
                                            <th class="text-end">{{ __('Harga Satuan') }}</th>
                                            <th class="text-end">{{ __('Total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if($diffItem['status'] === 'modified')
                                        @foreach($diffItem['budget_items_diff'] ?? [] as $biDiff)
                                        @php
                                        $rowClass = match($biDiff['status']) {
                                        'added' => 'table-success',
                                        'removed' => 'table-danger text-muted text-decoration-line-through',
                                        'modified' => 'table-warning',
                                        default => ''
                                        };
                                        $bi = $biDiff['item'];
                                        @endphp
                                        <tr class="{{ $rowClass }}">
                                            <td>
                                                @if($biDiff['status'] === 'added')
                                                <span class="badge bg-success me-1">+</span>
                                                @elseif($biDiff['status'] === 'removed')
                                                <span class="badge bg-danger me-1">-</span>
                                                @elseif($biDiff['status'] === 'modified')
                                                <span class="badge bg-warning me-1">Δ</span>
                                                @endif
                                                {{ $bi['description'] }}
                                                @if(!empty($bi['account_code']))
                                                <small class="text-muted d-block">{{ $bi['account_code'] }}</small>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($biDiff['status'] === 'modified' && ($biDiff['old']['quantity'] != $bi['quantity'] || ($biDiff['old']['unit'] ?? '') != ($bi['unit'] ?? '')))
                                                <span class="text-muted text-decoration-line-through">{{ $biDiff['old']['quantity'] }} {{ $biDiff['old']['unit'] ?? '' }}</span>
                                                <i class="bx bx-right-arrow-alt mx-1"></i>
                                                @endif
                                                {{ $bi['quantity'] }} {{ $bi['unit'] ?? '' }}
                                            </td>
                                            <td class="text-end">
                                                @if($biDiff['status'] === 'modified' && $biDiff['old']['unit_price'] != $bi['unit_price'])
                                                <span class="text-muted text-decoration-line-through">Rp {{ number_format($biDiff['old']['unit_price'], 0, ',', '.') }}</span>
                                                <i class="bx bx-right-arrow-alt mx-1"></i>
                                                @endif
                                                Rp {{ number_format($bi['unit_price'], 0, ',', '.') }}
                                            </td>
                                            <td class="text-end fw-semibold">
                                                @if($biDiff['status'] === 'modified')
                                                @php
                                                $oldTotal = (float)($biDiff['old']['quantity'] ?? 0) * (float)($biDiff['old']['unit_price'] ?? 0);
                                                $newTotal = (float)($bi['quantity'] ?? 0) * (float)($bi['unit_price'] ?? 0);
                                                @endphp
                                                @if($oldTotal != $newTotal)
                                                <span class="text-muted text-decoration-line-through fw-normal">Rp {{ number_format($oldTotal, 0, ',', '.') }}</span>
                                                <i class="bx bx-right-arrow-alt mx-1"></i>
                                                @endif
                                                Rp {{ number_format($newTotal, 0, ',', '.') }}
                                                @else
                                                Rp {{ number_format((float)($bi['quantity'] ?? 0) * (float)($bi['unit_price'] ?? 0), 0, ',', '.') }}
                                                @endif
                                            </td>
                                        </tr>
                                        @if(!empty($bi['monthly_distribution']))
                                        <tr class="{{ $rowClass }}">
                                            <td colspan="4" class="p-0 border-top-0">
                                                <div class="px-3 py-1">
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @php $mNames = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des']; @endphp
                                                        @foreach($bi['monthly_distribution'] as $mo => $amt)
                                                        <span class="badge bg-label-secondary rounded-pill rkap-font-065">{{ $mNames[(int)$mo] ?? $mo }}: Rp {{ number_format($amt, 0, ',', '.') }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        @endif
                                        @if(!empty($bi['cash_out_distribution']))
                                        <tr class="{{ $rowClass }}">
                                            <td colspan="4" class="p-0 border-top-0">
                                                <div class="px-3 py-1 border-top">
                                                    <div class="d-flex flex-wrap gap-1 align-items-center">
                                                        <span class="text-muted fw-semibold rkap-font-065">{{ __('Kas Keluar:') }}</span>
                                                        @php $mNames = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des']; @endphp
                                                        @foreach($bi['cash_out_distribution'] as $mo => $amt)
                                                        <span class="badge bg-label-primary rounded-pill rkap-font-065">{{ $mNames[(int)$mo] ?? $mo }}: Rp {{ number_format($amt, 0, ',', '.') }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        @endif
                                        @endforeach
                                        @else
                                        @foreach($diffItem['item']['budget_items'] ?? [] as $bi)
                                        <tr>
                                            <td>
                                                {{ $bi['description'] }}
                                                @if(!empty($bi['account_code']))
                                                <small class="text-muted d-block">{{ $bi['account_code'] }}</small>
                                                @endif
                                            </td>
                                            <td class="text-center">{{ $bi['quantity'] }} {{ $bi['unit'] ?? '' }}</td>
                                            <td class="text-end">Rp {{ number_format($bi['unit_price'], 0, ',', '.') }}</td>
                                            <td class="text-end fw-semibold">Rp {{ number_format((float)($bi['quantity'] ?? 0) * (float)($bi['unit_price'] ?? 0), 0, ',', '.') }}</td>
                                        </tr>
                                        @if(!empty($bi['monthly_distribution']))
                                        <tr>
                                            <td colspan="4" class="p-0 border-top-0">
                                                <div class="px-3 py-1">
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @php $mNames = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des']; @endphp
                                                        @foreach($bi['monthly_distribution'] as $mo => $amt)
                                                        <span class="badge bg-label-secondary rounded-pill rkap-font-065">{{ $mNames[(int)$mo] ?? $mo }}: Rp {{ number_format($amt, 0, ',', '.') }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        @endif
                                        @if(!empty($bi['cash_out_distribution']))
                                        <tr>
                                            <td colspan="4" class="p-0 border-top-0">
                                                <div class="px-3 py-1 border-top">
                                                    <div class="d-flex flex-wrap gap-1 align-items-center">
                                                        <span class="text-muted fw-semibold rkap-font-065">{{ __('Kas Keluar:') }}</span>
                                                        @php $mNames = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des']; @endphp
                                                        @foreach($bi['cash_out_distribution'] as $mo => $amt)
                                                        <span class="badge bg-label-primary rounded-pill rkap-font-065">{{ $mNames[(int)$mo] ?? $mo }}: Rp {{ number_format($amt, 0, ',', '.') }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        @endif
                                        @endforeach
                                        @endif
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
                    <h6 class="text-uppercase text-muted fw-bold mb-3">{{ __('Snapshot Data') }}</h6>
                    @foreach($this->selectedVersion->snapshot_data as $idx => $wp)
                    <div class="card border shadow-none mb-3">
                        <div class="card-header bg-lighter p-3 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 text-primary">{{ $wp['program_code'] ?? '-' }} - {{ $wp['program_name'] }}</h6>
                            </div>
                            <div class="text-end">
                                <strong class="text-dark">Rp {{ number_format(collect($wp['budget_items'] ?? [])->sum(fn($bi) => (float)($bi['quantity'] ?? 0) * (float)($bi['unit_price'] ?? 0)), 0, ',', '.') }}</strong>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>{{ __('Uraian Belanja') }}</th>
                                            <th class="text-center">Vol</th>
                                            <th>{{ __('Satuan') }}</th>
                                            <th class="text-end">{{ __('Harga Satuan') }}</th>
                                            <th class="text-end">{{ __('Total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($wp['budget_items'] ?? [] as $bi)
                                        <tr>
                                            <td>{{ $bi['description'] }}</td>
                                            <td class="text-center">{{ $bi['quantity'] ?? 0 }}</td>
                                            <td>{{ $bi['unit'] ?? '-' }}</td>
                                            <td class="text-end">Rp {{ number_format($bi['unit_price'] ?? 0, 0, ',', '.') }}</td>
                                            <td class="text-end fw-semibold">Rp {{ number_format((float)($bi['quantity'] ?? 0) * (float)($bi['unit_price'] ?? 0), 0, ',', '.') }}</td>
                                        </tr>
                                        @if(!empty($bi['monthly_distribution']))
                                        <tr>
                                            <td colspan="5" class="p-0 border-top-0">
                                                <div class="bg-light px-3 py-2" x-data="{ show: false }">
                                                    <div class="d-flex align-items-center gap-2 cursor-pointer" @click="show = !show">
                                                        <i class="bx bx-calendar text-primary rkap-font-085"></i>
                                                        <span class="small fw-semibold text-primary">{{ __('Distribusi Bulanan') }}</span>
                                                        <span class="badge bg-label-primary rounded-pill small">{{ count($bi['monthly_distribution']) }} bulan</span>
                                                        <i class="bx ms-auto rkap-font-085" :class="show ? 'bx-chevron-up' : 'bx-chevron-down'"></i>
                                                    </div>
                                                    <div x-show="show" x-collapse class="mt-2">
                                                        <div class="d-flex flex-wrap gap-2">
                                                            @php
                                                            $monthNames = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
                                                            @endphp
                                                            @foreach($bi['monthly_distribution'] as $month => $amount)
                                                            <div class="border rounded px-2 py-1 bg-white text-center rkap-min-w-80">
                                                                <div class="text-muted small rkap-font-07">{{ $monthNames[(int)$month] ?? $month }}</div>
                                                                <div class="fw-semibold small text-dark">Rp {{ number_format($amount, 0, ',', '.') }}</div>
                                                            </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        @endif
                                        @if(!empty($bi['cash_out_distribution']))
                                        <tr>
                                            <td colspan="5" class="p-0 border-top-0">
                                                <div class="bg-light px-3 py-2 border-top" x-data="{ show: false }">
                                                    <div class="d-flex align-items-center gap-2 cursor-pointer" @click="show = !show">
                                                        <i class="bx bx-wallet text-primary rkap-font-085"></i>
                                                        <span class="small fw-semibold text-primary">{{ __('Rencana Pendanaan') }}</span>
                                                        <span class="badge bg-label-primary rounded-pill small">{{ count($bi['cash_out_distribution']) }} bulan</span>
                                                        <i class="bx ms-auto rkap-font-085" :class="show ? 'bx-chevron-up' : 'bx-chevron-down'"></i>
                                                    </div>
                                                    <div x-show="show" x-collapse class="mt-2">
                                                        <div class="d-flex flex-wrap gap-2">
                                                            @php
                                                            $monthNames = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
                                                            @endphp
                                                            @foreach($bi['cash_out_distribution'] as $month => $amount)
                                                            <div class="border rounded px-2 py-1 bg-white text-center rkap-min-w-80">
                                                                <div class="text-muted small rkap-font-07">{{ $monthNames[(int)$month] ?? $month }}</div>
                                                                <div class="fw-semibold small text-dark">Rp {{ number_format($amount, 0, ',', '.') }}</div>
                                                            </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        @endif
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
                    <p>{{ __('Pilih versi dari daftar di sebelah kiri untuk melihat detail.') }}</p>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
