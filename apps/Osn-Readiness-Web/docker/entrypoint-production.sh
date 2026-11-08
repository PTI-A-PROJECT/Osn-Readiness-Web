#!/bin/sh
set -e

cd /var/www/html

# Env produksi diinjeksi saat container start, jadi cache bootstrap dibuat di
# sini, bukan saat build. Route di-cache karena routes/web.php sudah bebas
# closure action. Migrasi sengaja tidak dijalankan dari entrypoint.
php artisan config:cache
php artisan event:cache
php artisan view:cache
php artisan route:cache

# queue-worker memberi perintah sendiri; tanpa argumen jalankan Apache.
if [ "$#" -gt 0 ]; then
    exec "$@"
fi

exec apache2-foreground
