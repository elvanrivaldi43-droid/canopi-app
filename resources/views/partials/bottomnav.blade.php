{{-- FILE: resources/views/partials/bottomnav.blade.php --}}
{{-- Auto detect level - pakai file ini di semua view --}}
@if(auth()->user()->level == 1)
    @include('partials.bottomnav-owner')
@else
    @include('partials.bottomnav-karyawan')
@endif