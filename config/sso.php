<?php

/*
 * Login terpusat lewat AKPSatu (Portal). Nilai PORTAL_* diisi di .env.
 *
 * Aplikasi ini tidak punya akun sendiri: identitas dan level datang dari token Portal.
 * Kode level (kiri) dipakai di routes/web.php: ->middleware('portal.role:admin,pengawas').
 * Jangan ubah kode setelah ada pengguna yang memakainya. Portal membacanya otomatis (Modul > Atur > Deteksi level).
 */
return [
    'portal_url' => env('PORTAL_URL', 'http://localhost:8000'),

    'app_code' => env('PORTAL_APP_CODE'),

    'secret' => env('PORTAL_SSO_SECRET'),

    'leeway' => 5,

    'max_session_minutes' => (int) env('PORTAL_MAX_SESSION_MINUTES', 480),

    // Semua halaman wajib login Portal; hanya /up (cek kesehatan Docker) yang terbuka.
    'protect_all' => true,

    'except' => ['up'],

    'roles' => [
        'admin' => ['Administrator', 'Mengelola unit, checklist, dan semua data P2H.'],
        'pengawas' => ['Pengawas', 'Melihat semua P2H, menindaklanjuti temuan, dan ikut mengisi P2H.'],
        'operator' => ['Operator / Pengemudi', 'Mengisi P2H untuk unit yang dipakainya dan melihat riwayat P2H miliknya.'],
        'viewer' => ['Hanya lihat', 'Melihat dashboard dan semua riwayat P2H tanpa bisa mengubah data.'],
    ],

    'home' => '/p2h',
];
