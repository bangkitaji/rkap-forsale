<div>
    <h4 class="mb-4">{{ __('Form Input Saldo Awal Neraca') }}</h4>

    <div class="card mb-4">
        <div class="card-body">
            @if (session()->has('message'))
                <div class="alert alert-success alert-dismissible" role="alert">
                    {{ session('message') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <form wire:submit.prevent="save">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="periodId" class="form-label">{{ __('Tahun / Periode RKAP') }}</label>
                        <select wire:model.live="periodId" id="periodId" class="form-select">
                            <option value="">-- {{ __('Pilih Periode') }} --</option>
                            @foreach($availablePeriods as $period)
                                <option value="{{ $period->id }}">{{ $period->year }} - {{ $period->title }}</option>
                            @endforeach
                        </select>
                        @error('periodId') <span class="text-danger small">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-md-8 d-flex align-items-end justify-content-end">
                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">{{ __('Simpan Saldo Awal') }}</span>
                            <span wire:loading wire:target="save">{{ __('Menyimpan...') }}</span>
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Report Group') }}</th>
                                <th>{{ __('COA Group') }}</th>
                                <th>{{ __('Kode COA') }}</th>
                                <th>{{ __('Nama Akun') }}</th>
                                <th style="width: 250px;">{{ __('Saldo Awal (Rp)') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bsReportGroups as $rg)
                                <tr class="table-secondary fw-bold">
                                    <td colspan="5">{{ __($rg->name) }} ({{ $rg->code }})</td>
                                </tr>
                                @foreach($rg->coaGroups as $cg)
                                    <tr class="table-light fw-semibold">
                                        <td></td>
                                        <td colspan="4">{{ __($cg->name) }} ({{ $cg->code }})</td>
                                    </tr>
                                    @foreach($cg->coas as $coa)
                                        <tr>
                                            <td></td>
                                            <td></td>
                                            <td>{{ $coa->code }}</td>
                                            <td>{{ $coa->title }}</td>
                                            <td>
                                                <input type="number" step="0.01" class="form-control form-control-sm text-end" 
                                                       wire:model="openingBalances.{{ $coa->id }}">
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
</div>
