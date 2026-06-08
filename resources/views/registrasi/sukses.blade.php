{{-- FILE: resources/views/registrasi/sukses.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registrasi Berhasil</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center px-4">
<div class="max-w-md w-full text-center">
  <div class="bg-slate-800 rounded-2xl p-8 border border-slate-700">
    <div class="text-6xl mb-4">🎉</div>
    <h1 class="text-2xl font-bold text-white mb-2">Registrasi Berhasil!</h1>
    <p class="text-slate-400 mb-6">Halo <strong class="text-amber-400">{{ $karyawan->name }}</strong>, akun kamu sudah aktif. Silakan login ke sistem.</p>
    <a href="/login" class="inline-block bg-amber-400 hover:bg-amber-300 text-slate-900 font-bold py-3 px-8 rounded-xl transition-colors">
      Masuk ke Sistem →
    </a>
  </div>
</div>
</body>
</html>
