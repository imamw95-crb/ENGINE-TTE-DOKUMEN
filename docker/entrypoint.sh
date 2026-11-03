#!/bin/sh
# Entrypoint container: siapkan folder storage (volume) lalu jalankan Apache.
set -e

STORE=/var/www/html/storage
mkdir -p "$STORE" "$STORE/master" "$STORE/template" "$STORE/surat" \
         "$STORE/output" "$STORE/tmp" "$STORE/fonts/unifont"

# Folder harus bisa ditulis www-data (uid 33); berkas hasil unggahan manual
# (master PDF, font) cukup 644 sehingga tidak perlu di-chown.
find "$STORE" -type d -exec chown www-data:www-data {} +
find "$STORE" -type d -exec chmod 775 {} +
# app.db dan berkas lain di akar storage bisa saja dibuat oleh root
# (mis. `docker compose exec` tanpa -u), sehingga SQLite jadi read-only.
find "$STORE" -maxdepth 1 -type f -exec chown www-data:www-data {} + -exec chmod 664 {} +

if [ -z "$(ls -A "$STORE/master" 2>/dev/null)" ]; then
    echo "[tte-patrol] PERINGATAN: $STORE/master kosong." >&2
    echo "[tte-patrol] Generate PDF akan gagal sampai lampiran1_bersih.pdf, lampiran1_posisi.json," >&2
    echo "[tte-patrol] halaman_akhir.pdf, dan fonts/unifont/calibri.ttf tersedia (lihat README)." >&2
fi

exec docker-php-entrypoint "$@"
