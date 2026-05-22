<div>
    <div class="py-3 mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <span class="text-muted fw-light">RKAP /
                    <a href="{{ route('rkap-submissions') }}" class="text-muted text-decoration-none">Pengajuan</a> /
                </span>
                {{ $submissionId ? 'Edit RKAP' : 'Buat RKAP' }}
            </h4>
            <div class="text-muted small">
                <i class="bx bx-calendar me-1"></i> {{ $period->title ?? '' }}
            </div>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Grand Total Banner --}}
    <div class="card mb-4 bg-primary text-white">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <div class="small opacity-75">Total Anggaran Keseluruhan</div>
                <div class="fs-3 fw-bold">Rp {{ number_format($this->grandTotal, 0, ',', '.') }}</div>
            </div>
            <i class="bx bx-money bx-lg opacity-50"></i>
        </div>
    </div>

    {{-- Notes --}}
    <div class="card mb-4">
        <div class="card-header"><strong>Catatan Umum</strong></div>
        <div class="card-body">
            <textarea class="form-control" wire:model.live="notes" rows="2" placeholder="Catatan atau keterangan umum untuk pengajuan ini..."></textarea>
        </div>
    </div>

    {{-- Work Plans --}}
    @foreach($workPlans as $wpIdx => $wp)
    <div class="card mb-3 border-start border-primary border-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>
                <i class="bx bx-list-ul me-2 text-primary"></i>
                Program Kerja {{ $wpIdx + 1 }}
                @if($wp['program_name'])
                    — <span class="text-primary">{{ $wp['program_name'] }}</span>
                @endif
            </strong>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small">
                    Subtotal: <strong class="text-dark">Rp {{ number_format(collect($wp['budget_items'])->sum(fn($bi) => ($bi['quantity'] ?? 0) * ($bi['unit_price'] ?? 0)), 0, ',', '.') }}</strong>
                </span>
                @if(count($workPlans) > 1)
                <button type="button" wire:click="removeWorkPlan({{ $wpIdx }})" class="btn btn-sm btn-text-danger rounded-pill" title="Hapus program kerja">
                    <i class="bx bx-trash"></i>
                </button>
                @endif
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-2">
                    <label class="form-label small">Kode Program</label>
                    <input type="text" class="form-control form-control-sm" wire:model.live="workPlans.{{ $wpIdx }}.program_code" placeholder="PGM-01">
                </div>
                <div class="col-md-10">
                    <label class="form-label small">Nama Program Kerja <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm @error("workPlans.$wpIdx.program_name") is-invalid @enderror"
                        wire:model.live="workPlans.{{ $wpIdx }}.program_name" placeholder="Nama program kerja">
                    @error("workPlans.$wpIdx.program_name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-12">
                    <label class="form-label small">Deskripsi / Tujuan</label>
                    <textarea class="form-control form-control-sm" wire:model.live="workPlans.{{ $wpIdx }}.description" rows="2" placeholder="Deskripsi kegiatan..."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Target Output</label>
                    <input type="text" class="form-control form-control-sm" wire:model.live="workPlans.{{ $wpIdx }}.output_target" placeholder="Misal: 1 sistem, 100 user">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Satuan</label>
                    <input type="text" class="form-control form-control-sm" wire:model.live="workPlans.{{ $wpIdx }}.unit" placeholder="Paket, Unit, ...">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Volume</label>
                    <input type="number" class="form-control form-control-sm" wire:model.live="workPlans.{{ $wpIdx }}.quantity" min="1">
                </div>
            </div>

            {{-- Budget Items --}}
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-2">
                    <thead class="table-light">
                        <tr>
                            <th style="width:100px">Kode Akun</th>
                            <th>Uraian Belanja <span class="text-danger">*</span></th>
                            <th style="width:80px">Satuan</th>
                            <th style="width:70px">Vol <span class="text-danger">*</span></th>
                            <th style="width:140px">Harga Satuan (Rp) <span class="text-danger">*</span></th>
                            <th style="width:140px">Total (Rp)</th>
                            <th style="width:100px">Keterangan</th>
                            <th style="width:40px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($wp['budget_items'] as $biIdx => $bi)
                        <tr>
                            <td>
                                <input type="text" class="form-control form-control-sm" wire:model.live="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.account_code" placeholder="0000">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm @error("workPlans.$wpIdx.budget_items.$biIdx.description") is-invalid @enderror"
                                    wire:model.live="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.description" placeholder="Nama belanja">
                                @error("workPlans.$wpIdx.budget_items.$biIdx.description") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </td>
                            <td><input type="text" class="form-control form-control-sm" wire:model.live="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.unit" placeholder="Bh, Paket"></td>
                            <td>
                                <input type="number" class="form-control form-control-sm @error("workPlans.$wpIdx.budget_items.$biIdx.quantity") is-invalid @enderror"
                                    wire:model.live="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.quantity" min="1">
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm @error("workPlans.$wpIdx.budget_items.$biIdx.unit_price") is-invalid @enderror"
                                    wire:model.live="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.unit_price" min="0" step="1000">
                            </td>
                            <td class="text-end text-nowrap fw-semibold text-primary">
                                Rp {{ number_format(($bi['quantity'] ?? 0) * ($bi['unit_price'] ?? 0), 0, ',', '.') }}
                            </td>
                            <td><input type="text" class="form-control form-control-sm" wire:model.live="workPlans.{{ $wpIdx }}.budget_items.{{ $biIdx }}.remarks" placeholder="Ket..."></td>
                            <td>
                                @if(count($wp['budget_items']) > 1)
                                <button type="button" wire:click="removeBudgetItem({{ $wpIdx }}, {{ $biIdx }})" class="btn btn-sm btn-icon btn-text-danger rounded-pill">
                                    <i class="bx bx-minus-circle"></i>
                                </button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" wire:click="addBudgetItem({{ $wpIdx }})" class="btn btn-sm btn-label-secondary">
                <i class="bx bx-plus me-1"></i> Tambah Item Belanja
            </button>
        </div>
    </div>
    @endforeach

    <div class="d-flex gap-2 mb-4">
        <button type="button" wire:click="addWorkPlan()" class="btn btn-label-primary">
            <i class="bx bx-plus me-1"></i> Tambah Program Kerja
        </button>
    </div>

    {{-- Action Buttons --}}
    <div class="card">
        <div class="card-body d-flex justify-content-between align-items-center">
            <a href="{{ route('rkap-submissions') }}" class="btn btn-label-secondary">
                <i class="bx bx-arrow-back me-1"></i> Kembali
            </a>
            <div class="d-flex gap-2">
                <button wire:click="saveDraft()" wire:loading.attr="disabled" class="btn btn-label-primary">
                    <span wire:loading.remove wire:target="saveDraft"><i class="bx bx-save me-1"></i> Simpan Draft</span>
                    <span wire:loading wire:target="saveDraft"><span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...</span>
                </button>
                <button wire:click="submitForReview()" wire:loading.attr="disabled"
                    wire:confirm="Yakin mengajukan RKAP ini untuk review? Pastikan data sudah lengkap."
                    class="btn btn-primary">
                    <span wire:loading.remove wire:target="submitForReview"><i class="bx bx-send me-1"></i> Ajukan untuk Review</span>
                    <span wire:loading wire:target="submitForReview"><span class="spinner-border spinner-border-sm me-1"></span> Mengajukan...</span>
                </button>
            </div>
        </div>
    </div>
</div>
