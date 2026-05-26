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
        <div class="col-xl-8 col-lg-7">
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
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($wp->budgetItems as $bi)
                                        <tr>
                                            <td>{{ $bi->account_code ?? '-' }}</td>
                                            <td>
                                                {{ $bi->description }}
                                                @if($bi->remarks)
                                                    <div class="text-muted small mt-1">{{ $bi->remarks }}</div>
                                                @endif
                                            </td>
                                            <td class="text-center">{{ $bi->quantity }}</td>
                                            <td>{{ $bi->unit }}</td>
                                            <td class="text-end">Rp {{ number_format($bi->unit_price, 0, ',', '.') }}</td>
                                            <td class="text-end fw-semibold text-primary">Rp {{ number_format($bi->total_price, 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Sidebar: Actions & History -->
        <div class="col-xl-4 col-lg-5">

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
