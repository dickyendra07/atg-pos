@extends('backoffice.layouts.app')

@php($pageTitle = 'Detail Adjustment - ATG POS')

@section('content')
<style>
    .detail{display:grid;gap:18px}.card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:20px}.meta{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.meta div{display:grid;gap:4px}.meta span{font-size:12px;color:#64748b;font-weight:800}.wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:13px;border-bottom:1px solid #e2e8f0;text-align:left}a{color:#ea580c}.up{color:#15803d;font-weight:800}.down{color:#b91c1c;font-weight:800}
    .title-row{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.title-row h1{margin:6px 0}
    .badge{display:inline-block;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:800;letter-spacing:.04em}.badge-completed{background:#dcfce7;color:#166534}.badge-void{background:#fee2e2;color:#991b1b}
    .void-banner{border-color:#fecaca;background:#fef2f2}.void-banner .meta{margin-top:12px}
    .void-box summary{list-style:none;display:inline-flex;cursor:pointer;border-radius:12px;padding:10px 16px;background:#b91c1c;color:#fff;font-weight:800}.void-box summary::-webkit-details-marker{display:none}
    .void-panel{margin-top:16px;display:grid;gap:14px}.void-panel h3{margin:0}.note{color:#64748b;font-size:13px}
    .blockers{border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:12px;padding:12px 16px}.blockers ul{margin:6px 0 0;padding-left:18px}
    .warn{border:1px solid #fde68a;background:#fffbeb;color:#92400e;border-radius:12px;padding:12px 16px;font-size:13px}
    .void-panel textarea{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:10px;min-height:76px;font:inherit}
    .btn-danger{border:0;border-radius:12px;padding:10px 16px;background:#b91c1c;color:#fff;font-weight:800;cursor:pointer}.btn-danger[disabled]{opacity:.45;cursor:not-allowed}
    .field-error{color:#b91c1c;font-size:12px;font-weight:700}tr.row-blocked td{background:#fef2f2}
    @media(max-width:700px){.meta{grid-template-columns:1fr}}
</style>
@php($fmt = fn ($v) => \App\Support\QuantityFormatter::twoDecimals($v))
{{-- Quantities in the VOID preview are integers in hundredths (exact); they are only converted for display. --}}
@php($fmtScaled = fn ($v, $signed = false) => $v === null ? '-' : (($signed && $v > 0 ? '+' : '').\App\Support\QuantityFormatter::twoDecimals($v / 100)))
<div class="detail">
    <div>
        <a href="{{ route('backoffice.stock-adjustments.index') }}">← Adjustment History</a>
        <div class="title-row">
            <h1>Detail Adjustment</h1>
            <span class="badge {{ $adjustment->isVoid() ? 'badge-void' : 'badge-completed' }}">{{ $adjustment->isVoid() ? 'VOID' : 'COMPLETED' }}</span>
        </div>
    </div>

    @if($adjustment->isVoid())
        <div class="card void-banner">
            <strong>Adjustment ini sudah di-VOID.</strong>
            <span class="note">Dampak stoknya sudah dibalik lewat movement pembalik; adjustment, item, dan movement aslinya tetap tersimpan sebagai riwayat.</span>
            <div class="meta">
                <div><span>VOID Oleh</span><strong>{{ $adjustment->voidedBy?->name ?? '-' }}</strong></div>
                <div><span>VOID Pada</span><strong>{{ $adjustment->void_at?->format('Y-m-d H:i') ?? '-' }}</strong></div>
                <div><span>Alasan VOID</span><strong>{{ $adjustment->void_reason ?: '-' }}</strong></div>
            </div>
        </div>
    @endif

    <div class="card meta">
        <div><span>Reference</span><strong>{{ $adjustment->reference }}</strong></div>
        <div><span>Original Date</span><strong>{{ $adjustment->created_at->format('Y-m-d H:i') }}</strong></div>
        <div><span>Outlet / Location</span><strong>{{ $adjustment->locationName() }}</strong></div>
        <div><span>User</span><strong>{{ $adjustment->user?->name ?? '-' }}</strong></div>
        <div><span>Total Items</span><strong>{{ $adjustment->items->count() }}</strong></div>
        <div><span>Note</span><strong>{{ $adjustment->note ?: '-' }}</strong></div>
    </div>

    <div class="card wrap">
        <table>
            <thead><tr><th>Ingredient</th><th>Category</th><th>Unit</th><th>Stock System Before</th><th>Stock Actual</th><th>Difference</th><th>Original Movement</th>@if($adjustment->isVoid())<th>Reversal Movement</th>@endif</tr></thead>
            <tbody>
            @foreach($adjustment->items as $item)
                <tr>
                    <td>{{ $item->ingredient?->name ?? '-' }}</td>
                    <td>{{ $item->ingredient?->category?->name ?? '-' }}</td>
                    <td>{{ $item->unit ?: ($item->ingredient?->unit ?? '-') }}</td>
                    <td>{{ $fmt($item->system_qty) }}</td>
                    <td>{{ $fmt($item->actual_qty) }}</td>
                    <td class="{{ $item->difference > 0 ? 'up' : ($item->difference < 0 ? 'down' : '') }}">{{ $item->difference > 0 ? '+' : '' }}{{ $fmt($item->difference) }}</td>
                    <td>{{ $item->stock_movement_id ? 'MOV-'.$item->stock_movement_id : 'Tidak ada perubahan' }}</td>
                    @if($adjustment->isVoid())
                        <td>
                            @if($item->voidMovement)
                                MOV-{{ $item->voidMovement->id }}
                                ({{ (float) $item->voidMovement->qty_in > 0 ? '+'.$fmt($item->voidMovement->qty_in) : '-'.$fmt($item->voidMovement->qty_out) }})
                            @else
                                Tidak ada perubahan
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @if($canVoid)
        <div class="card void-box">
            <details @if($errors->any()) open @endif>
                <summary>VOID Adjustment</summary>

                <div class="void-panel">
                    <h3>Konfirmasi VOID {{ $adjustment->reference }}</h3>
                    <div class="note">VOID membalik dampak stok adjustment ini (selisihnya), bukan mengembalikan stok ke angka lama. Penjualan atau transfer setelahnya tetap diperhitungkan. Movement asli tidak dihapus; movement pembalik baru dicatat pada waktu VOID.</div>

                    <div class="wrap">
                        <table>
                            <thead><tr><th>Ingredient</th><th>Dampak Asli</th><th>Stok Sekarang</th><th>Pembalikan</th><th>Stok Setelah VOID</th></tr></thead>
                            <tbody>
                            @foreach($voidPreview['rows'] as $row)
                                <tr class="{{ $row['problems'] ? 'row-blocked' : '' }}">
                                    <td>{{ $row['ingredient_name'] }} <span class="note">({{ $row['unit'] }})</span></td>
                                    <td>{{ $fmtScaled($row['original'], true) }}</td>
                                    @if($row['original'] === 0)
                                        <td colspan="3" class="note">Tanpa perubahan stok; hanya status yang berubah.</td>
                                    @else
                                        <td>{{ $fmtScaled($row['current']) }}</td>
                                        <td class="{{ $row['reversal'] > 0 ? 'up' : 'down' }}">{{ $fmtScaled($row['reversal'], true) }}</td>
                                        <td>{{ $fmtScaled($row['resulting']) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($voidPreview['blockers'])
                        <div class="blockers">
                            <strong>VOID tidak bisa dilakukan sekarang:</strong>
                            <ul>@foreach($voidPreview['blockers'] as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>
                        </div>
                    @endif
                    <div class="warn">Pratinjau ini hanya informasi. Saat disubmit, sistem menghitung ulang semuanya dan menolak VOID bila stok atau data sudah berubah. VOID tidak bisa dibatalkan.</div>

                    <form method="POST" action="{{ route('backoffice.stock-adjustments.void', $adjustment) }}">
                        @csrf
                        <div style="display:grid;gap:8px">
                            <label for="void_reason"><strong>Alasan VOID</strong> (wajib)</label>
                            <textarea id="void_reason" name="void_reason" maxlength="1000" required placeholder="Contoh: salah hitung stok fisik">{{ old('void_reason') }}</textarea>
                            @error('void_reason')<div class="field-error">{{ $message }}</div>@enderror

                            <label><input type="checkbox" name="confirm" value="1" required> Saya mengerti VOID ini membalik dampak stok adjustment {{ $adjustment->reference }} dan tidak bisa dibatalkan.</label>
                            @error('confirm')<div class="field-error">{{ $message }}</div>@enderror

                            <div><button type="submit" class="btn-danger" @disabled(! $voidPreview['can_void'])>VOID Adjustment</button></div>
                        </div>
                    </form>
                </div>
            </details>
        </div>
    @endif
</div>
@endsection
