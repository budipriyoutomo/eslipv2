# E-Slip v2

Migrasi aplikasi e-slip gaji dari CodeIgniter 3 ke Laravel 13 (PHP 8.4).

- Instruksi kerja: `Migration-Plan.md` (kerjakan per fase, update bagian 9. Progress).
- Keputusan & perbedaan dengan kode lama: `docs/DECISIONS.md`.
- Source lama (sumber kebenaran): `d:/Project/htdocs/eslip/application`.
- `d:/Project/eslip` (Laravel 10, format Songfa) **bukan** bagian dari proyek ini.

## Quality gate (wajib hijau sebelum commit)

```sh
vendor/bin/pint --test
vendor/bin/phpstan analyse
php artisan test
```
