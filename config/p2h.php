<?php

return [
    // Level BBM yang dipilih pemeriksa.
    'fuel_levels' => ['Penuh', '3/4', '1/2', '1/4', 'Hampir habis'],

    // Shift: jam mulai shift siang (D) dan malam (N) menurut waktu site (APP_TIMEZONE).
    'day_shift_start_hour' => 6,
    'night_shift_start_hour' => 18,

    // Shift yang wajib diperiksa tiap unit aktif setiap hari (target kepatuhan).
    'required_shifts' => ['D', 'N'],

    // Odometer: tidak boleh lebih kecil dari P2H sebelumnya; lonjakan lebih dari ini harus dikonfirmasi pemeriksa.
    'odometer_strict' => true,
    'odometer_max_jump_km' => 1500,

    // Foto bukti kerusakan (opsional): batas ukuran unggahan (KB) dan sisi terpanjang setelah dikompres (px).
    'photo_max_kb' => 6144,
    'photo_max_px' => 1280,
    'photo_disk' => 'local',

    // Pengingat servis: tampil di dashboard saat sisa km kurang dari angka ini.
    'service_warning_km' => 500,

    // Unit dianggap "lama tidak diperiksa" bila P2H terakhirnya lebih tua dari ini (jam).
    'stale_after_hours' => 24,
];
