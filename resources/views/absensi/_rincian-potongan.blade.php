{{-- Rincian asal potongan_telat — lihat App\Services\RincianPotongan. Butuh $rincian, $total. --}}
<div style="font-size:10px;color:#94a3b8;margin-top:4px;line-height:1.5;text-align:left;">
    @foreach(\App\Services\RincianPotongan::baris($rincian, $total) as $b)
    <div>{{ $b['label'] }}@if($b['ket']) ({{ $b['ket'] }})@endif: Rp{{ number_format($b['n'],0,',','.') }}</div>
    @endforeach
</div>
