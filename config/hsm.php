<?php

return [
    /*
    | Jam kerja & toleransi dikelola lewat tabel `settings` (menu Pengaturan SuperAdmin).
    | Nilai di bawah hanya untuk path utilitas database dan retensi backup.
    */
    'mysqldump_path' => env('HSM_MYSQLDUMP_PATH'),
    'mysql_path' => env('HSM_MYSQL_PATH'),
    'backup_keep_auto' => (int) env('HSM_BACKUP_KEEP_AUTO', 30),
    'backup_max_upload_mb' => (int) env('HSM_BACKUP_MAX_UPLOAD_MB', 200),
    'restore_confirmation_phrase' => 'RESTORE HSM DATABASE',
];
