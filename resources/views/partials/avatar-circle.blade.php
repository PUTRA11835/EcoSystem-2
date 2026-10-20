{{--
    Lingkaran identitas (foto profil SENDIRI, inisial bila belum ada / gagal dimuat). Dipakai kartu sambutan Dashboard dan My Attendance.
    Parameter: $avatarClass (kelas ukuran/warna lingkaran), $avatarInitials. Foto: lihat App\Support\Avatar + rute profile.photo.
--}}
@php $avatarV = \App\Support\Avatar::version((int) (session('user.id') ?? 0)); @endphp
<span class="{{ $avatarClass }} overflow-hidden">
    @if($avatarV)
        <img src="{{ route('profile.photo', ['v' => $avatarV]) }}" alt="" class="h-full w-full object-cover"
             onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">
        <span class="hidden">{{ $avatarInitials }}</span>
    @else
        {{ $avatarInitials }}
    @endif
</span>
