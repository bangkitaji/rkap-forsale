<div>
    <div class="d-flex justify-content-between align-items-center py-3 mb-4">
        <h4 class="mb-0">
            <span class="text-muted fw-light">RKAP / <a href="{{ route('rkap-submissions') }}" class="text-muted text-decoration-none">Pengajuan</a> /</span>
            Review RKAP
        </h4>
        <div>
            <span class="badge bg-{{ $submission->status_color }} fs-6">{{ $submission->status_label }}</span>
        </div>
    </div>

    @if (session()->has('message'))
    <div class="alert alert-success alert-dismissible" role="alert">
        {{ session('message') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row">
        <!-- Main Content: RKAP Details -->
        <div class="col-xl-9 col-lg-8">
            <!-- Header Info -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="text-muted small">Biro Pengaju</label>
                            <div class="fw-semibold">{{ $submission->bureau->name ?? '-' }}</div>
                            <div class="text-muted small">{{ $submission->bureau->department->directorate->name ?? '-' }}</div>
                        </div>
                        <div class="col-sm-3">
                            <label class="text-muted small">Periode</label>
                            <div class="fw-semibold">{{ $submission->period->title ?? '-' }}</div>
                        </div>
                        <div class="col-sm-3">
                            <label class="text-muted small">Total Anggaran</label>
                            <div class="fw-bold text-primary fs-5">Rp {{ number_format($submission->total_budget, 0, ',', '.') }}</div>
                        </div>
                    </div>
                    @if($submission->notes)
                    <hr class="my-3">
                    <label class="text-muted small">Catatan Pengajuan</label>
                    <p class="mb-0">{{ $submission->notes }}</p>
                    @endif
                </div>
            </div>

            <!-- Work Plans -->
            <h5 class="mb-3">Rincian Program Kerja</h5>
            @foreach($submission->workPlans as $idx => $wp)
            <div class="card mb-3 border-start border-primary border-3">
                <div class="card-header border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1 text-primary">{{ $wp->program_code }} - {{ $wp->program_name }}</h6>
                            @if($wp->description)
                            <p class="text-muted small mb-0">{{ $wp->description }}</p>
                            @endif
                        </div>
                        <div class="text-end">
                            <span class="text-muted small d-block">Subtotal Program</span>
                            <strong class="text-dark">Rp {{ number_format($wp->total_budget, 0, ',', '.') }}</strong>
                        </div>
                    </div>
                    <div class="row mt-2 g-2 small">
                        <div class="col-auto">
                            <span class="text-muted">Target Output:</span> <span class="fw-medium">{{ $wp->output_target ?? '-' }}</span>
                        </div>
                        <div class="col-auto">
                            <span class="text-muted">Volume:</span> <span class="fw-medium">{{ $wp->quantity }} {{ $wp->unit }}</span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Kode Akun</th>
                                    <th>Uraian & Detail Belanja</th>
                                    <th class="text-center">Vol</th>
                                    <th>Satuan</th>
                                    <th class="text-end">Harga Satuan</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($wp->budgetItems->groupBy('account_code') as $accountCode => $items)
                                @php
                                $firstItem = $items->first();
                                @endphp
                                <tr class="table-light fw-semibold">
                                    <td colspan="7" class="text-dark bg-lighter py-2">
                                        <i class="bx bx-subdirectory-right text-primary me-1"></i>
                                        <strong>{{ $accountCode ?? '-' }}</strong> — {{ $firstItem->description }}
                                    </td>
                                </tr>
                                @foreach($items as $bi)
                                @php
                                $allocationModalId = 'allocationDetailModal-' . $bi->id;
                                $monthNames = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
                                @endphp
                                <tr>
                                    <td class="text-center text-muted">
                                        <span class="ps-2">•</span>
                                    </td>
                                    <td>
                                        {{ $bi->remarks ?: $bi->description }}
                                    </td>
                                    <td class="text-center">{{ $bi->quantity }}</td>
                                    <td>{{ $bi->unit }}</td>
                                    <td class="text-end">Rp {{ number_format($bi->unit_price, 0, ',', '.') }}</td>
                                    <td class="text-end">
                                        <div class="fw-semibold text-primary">Rp {{ number_format($bi->total_price, 0, ',', '.') }}</div>
                                    </td>
                                    <td class="text-center">
                                        @if($bi->monthlies->isNotEmpty() || $bi->cashOuts->isNotEmpty())
                                        <div class="d-flex justify-content-center">
                                            <button type="button" class="btn btn-xs btn-outline-primary" data-bs-toggle="modal" data-bs-target="#{{ $allocationModalId }}" title="Detail Alokasi">
                                                <i class="bx bx-info-circle me-1"></i>Detail Alokasi
                                            </button>

                                            <!-- Modal Detail Alokasi (Merged) -->
                                            <div class="modal fade" id="{{ $allocationModalId }}" tabindex="-1" aria-hidden="true" wire:key="allocation-modal-{{ $bi->id }}">
                                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                                    <div class="modal-content text-start">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title d-flex align-items-center">
                                                                <i class="bx bx-info-circle me-2 text-primary fs-4"></i>Detail Alokasi Anggaran
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <!-- Banner Informasi Rincian Belanja -->
                                                            <div class="card bg-lighter shadow-none border mb-4">
                                                                <div class="card-body py-3 px-4">
                                                                    <div class="row g-3 small">
                                                                        <div class="col-md-3 border-end">
                                                                            <span class="text-muted d-block mb-1">Kode Akun</span>
                                                                            <span class="fw-semibold text-dark fs-6">{{ $bi->account_code ?? '-' }}</span>
                                                                        </div>
                                                                        <div class="col-md-5 border-end">
                                                                            <span class="text-muted d-block mb-1">Deskripsi / Detail Belanja</span>
                                                                            <span class="fw-semibold text-dark fs-6 text-wrap">{{ $bi->description }}</span>
                                                                            @if($bi->remarks)
                                                                            <div class="text-muted mt-1 small">Ket: {{ $bi->remarks }}</div>
                                                                            @endif
                                                                        </div>
                                                                        <div class="col-md-2 border-end">
                                                                            <span class="text-muted d-block mb-1">Volume</span>
                                                                            <span class="fw-semibold text-dark fs-6">{{ $bi->quantity }} {{ $bi->unit }}</span>
                                                                        </div>
                                                                        <div class="col-md-2">
                                                                            <span class="text-muted d-block mb-1">Total Anggaran</span>
                                                                            <span class="fw-bold text-primary fs-6">Rp {{ number_format($bi->total_price, 0, ',', '.') }}</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Side-by-side Tables (Only months with values) -->
                                                            <div class="row g-4">
                                                                <!-- Left Column: Distribusi Bulanan -->
                                                                <div class="col-md-6">
                                                                    <div class="border rounded p-3 h-100">
                                                                        <h6 class="fw-semibold mb-3 text-primary d-flex align-items-center">
                                                                            <i class="bx bx-calendar me-2"></i>Distribusi Bulanan
                                                                        </h6>
                                                                        @php
                                                                        $activeMonthlies = $bi->monthlies->filter(fn($m) => (float) $m->amount > 0);
                                                                        @endphp
                                                                        @if($activeMonthlies->isEmpty())
                                                                        <div class="text-center text-muted py-4">
                                                                            <i class="bx bx-info-circle fs-3 mb-2 d-block"></i>
                                                                            <span class="small">Tidak ada data distribusi bulanan</span>
                                                                        </div>
                                                                        @else
                                                                        <div class="table-responsive">
                                                                            <table class="table table-sm table-hover align-middle mb-0">
                                                                                <thead>
                                                                                    <tr>
                                                                                        <th>Bulan</th>
                                                                                        <th class="text-end">Jumlah</th>
                                                                                        <th class="text-center" style="width: 25%">Porsi</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    @foreach($activeMonthlies as $monthlyRecord)
                                                                                    @php
                                                                                    $percentage = $bi->total_price > 0 ? ($monthlyRecord->amount / $bi->total_price) * 100 : 0;
                                                                                    @endphp
                                                                                    <tr class="fw-medium text-primary">
                                                                                        <td>{{ $monthNames[$monthlyRecord->month] }}</td>
                                                                                        <td class="text-end">Rp {{ number_format($monthlyRecord->amount, 0, ',', '.') }}</td>
                                                                                        <td class="text-center">
                                                                                            <span class="badge bg-label-primary">{{ number_format($percentage, 0) }}%</span>
                                                                                        </td>
                                                                                    </tr>
                                                                                    @endforeach
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                        @endif
                                                                    </div>
                                                                </div>

                                                                <!-- Right Column: Rencana Kas Keluar -->
                                                                <div class="col-md-6">
                                                                    <div class="border rounded p-3 h-100">
                                                                        <h6 class="fw-semibold mb-3 text-success d-flex align-items-center">
                                                                            <i class="bx bx-wallet me-2"></i>Rencana Kas Keluar
                                                                        </h6>
                                                                        @php
                                                                        $activeCashOuts = $bi->cashOuts->filter(fn($c) => (float) $c->amount > 0);
                                                                        @endphp
                                                                        @if($activeCashOuts->isEmpty())
                                                                        <div class="text-center text-muted py-4">
                                                                            <i class="bx bx-info-circle fs-3 mb-2 d-block"></i>
                                                                            <span class="small">Tidak ada data rencana kas keluar</span>
                                                                        </div>
                                                                        @else
                                                                        <div class="table-responsive">
                                                                            <table class="table table-sm table-hover align-middle mb-0">
                                                                                <thead>
                                                                                    <tr>
                                                                                        <th>Bulan</th>
                                                                                        <th class="text-end">Jumlah</th>
                                                                                        <th class="text-center" style="width: 25%">Porsi</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    @foreach($activeCashOuts as $cashOutRecord)
                                                                                    @php
                                                                                    $percentage = $bi->total_price > 0 ? ($cashOutRecord->amount / $bi->total_price) * 100 : 0;
                                                                                    @endphp
                                                                                    <tr class="fw-medium text-success">
                                                                                        <td>{{ $monthNames[$cashOutRecord->month] }}</td>
                                                                                        <td class="text-end">Rp {{ number_format($cashOutRecord->amount, 0, ',', '.') }}</td>
                                                                                        <td class="text-center">
                                                                                            <span class="badge bg-label-success">{{ number_format($percentage, 0) }}%</span>
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
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Sidebar: Actions & History -->
        <div class="col-xl-3 col-lg-4">

            <!-- Review Actions (Only if user can review) -->
            @if($this->canApprove())
            <div class="card mb-4 border-primary">
                <div class="card-header bg-label-primary">
                    <h5 class="mb-0 text-primary"><i class="bx bx-check-shield me-2"></i>Aksi Review</h5>
                </div>
                <div class="card-body mt-3">
                    @if($showRevisionForm)
                    <div class="mb-3">
                        <label class="form-label text-danger">Alasan Permintaan Revisi <span class="text-danger">*</span></label>
                        <textarea class="form-control @error('revisionReason') is-invalid @enderror" wire:model="revisionReason" rows="3" placeholder="Sebutkan bagian mana yang perlu diperbaiki..."></textarea>
                        @error('revisionReason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-label-secondary w-50" wire:click="$set('showRevisionForm', false)">Batal</button>
                        <button class="btn btn-danger w-50" wire:click="requestRevision" wire:loading.attr="disabled">Kirim Permintaan</button>
                    </div>
                    @else
                    <div class="mb-3">
                        <label class="form-label">Catatan Review (Opsional)</label>
                        <textarea class="form-control" wire:model="reviewComments" rows="2" placeholder="Tinggalkan catatan untuk persetujuan..."></textarea>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <button class="btn btn-success w-100" wire:click="approve" wire:loading.attr="disabled" wire:confirm="Yakin menyetujui RKAP ini?">
                            <i class="bx bx-check-circle me-1"></i> Setujui RKAP
                        </button>
                        <button class="btn btn-outline-danger w-100" wire:click="$set('showRevisionForm', true)">
                            <i class="bx bx-x-circle me-1"></i> Minta Revisi
                        </button>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Comments / Discussion -->
            <div class="card mb-4">
                <div class="card-header border-bottom">
                    <h5 class="mb-0"><i class="bx bx-message-rounded-dots me-2"></i>Pembahasan</h5>
                </div>
                <div class="card-body mt-3" style="max-height: 400px; overflow-y: auto;">
                    @forelse($submission->comments as $comment)
                    <div class="d-flex mb-3">
                        <div class="avatar avatar-sm me-3 flex-shrink-0">
                            <span class="avatar-initial rounded-circle bg-label-primary">{{ substr($comment->user->name, 0, 2) }}</span>
                        </div>
                        <div class="w-100">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <h6 class="mb-0">{{ $comment->user->name }}</h6>
                                <small class="text-muted">{{ $comment->created_at->diffForHumans() }}</small>
                            </div>
                            <div class="p-2 bg-lighter rounded small">
                                {{ $comment->content }}
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center text-muted my-3">
                        <small>Belum ada pembahasan.</small>
                    </div>
                    @endforelse
                </div>
                <div class="card-footer border-top">
                    <div class="input-group">
                        <input type="text" class="form-control @error('newComment') is-invalid @enderror" wire:model.defer="newComment" placeholder="Ketik pesan..." wire:keydown.enter="addComment">
                        <button class="btn btn-primary" type="button" wire:click="addComment"><i class="bx bx-send"></i></button>
                    </div>
                    @error('newComment') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            <!-- Approval History -->
            <div class="card">
                <div class="card-header border-bottom">
                    <h5 class="mb-0"><i class="bx bx-history me-2"></i>Riwayat Persetujuan</h5>
                </div>
                <div class="card-body mt-3">
                    <ul class="timeline mb-0">
                        @foreach($submission->approvals as $approval)
                        <li class="timeline-item timeline-item-transparent ps-4">
                            <span class="timeline-point timeline-point-{{ $approval->action_color }}"></span>
                            <div class="timeline-event">
                                <div class="timeline-header mb-1">
                                    <h6 class="mb-0">{{ $approval->action_label }}</h6>
                                    <small class="text-muted">{{ $approval->created_at->format('d M Y, H:i') }}</small>
                                </div>
                                <p class="mb-0 small">Oleh: <strong>{{ $approval->user->name }}</strong> ({{ \Illuminate\Support\Str::headline($approval->role) }})</p>
                                @if($approval->comments)
                                <div class="mt-2 p-2 bg-lighter rounded small border-start border-{{ $approval->action_color }} border-3">
                                    <em>"{{ $approval->comments }}"</em>
                                </div>
                                @endif
                            </div>
                        </li>
                        @endforeach
                        <li class="timeline-item timeline-item-transparent ps-4">
                            <span class="timeline-point timeline-point-secondary"></span>
                            <div class="timeline-event pb-0">
                                <div class="timeline-header mb-1">
                                    <h6 class="mb-0">Diajukan</h6>
                                    <small class="text-muted">{{ $submission->created_at->format('d M Y, H:i') }}</small>
                                </div>
                                <p class="mb-0 small">Oleh: <strong>{{ $submission->creator->name ?? '-' }}</strong></p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>