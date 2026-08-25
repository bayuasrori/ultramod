# Desain: Seamless App Upgrade (ala Odoo "Upgrade Module")

Status: **fase 1–5 sudah diimplementasikan** (lihat §13). Fase 6 (snapshot/backup
otomatis, upgrade via queue, maintenance mode penuh) belum — rollback saat ini
mengandalkan kompensasi `migrate:rollback`, bukan snapshot.
Target UX: admin buka `/platform`, lihat badge **Upgrade available**, klik **Upgrade**,
semua migrasi + data migration + permission + cache beres tanpa sentuh terminal.

---

## 1. Kondisi sekarang & gap

Yang sudah ada:

- `AppManager::update()` (`app/Platform/Services/AppManager.php:125`) — jalankan migrasi app + sync permission + bump versi.
- CLI `platform:app:update` (`app/Console/Commands/AppUpdateCommand.php`).
- Event `AppUpdated`.

Gap yang menghalangi "tinggal klik":

| # | Gap | Bukti |
|---|---|---|
| G1 | **Tidak ada cara mendeteksi upgrade tersedia.** `discover()` menimpa kolom `version` di registry dengan versi manifest, bahkan untuk app yang sudah terinstal (`AppManager.php:77`). Versi terpasang dan versi tersedia jadi satu kolom, jadi "outdated" tidak pernah bisa dihitung. | `AppManager.php:69,77` |
| G2 | Tidak ada route/tombol Update di UI. Hanya CLI. | `routes/web.php:38-43`, `resources/views/platform/home.blade.php` |
| G3 | **`syncPermissions()` menghapus semua permission app lalu membuat ulang** (`AppManager.php:345`). ID baru → baris di `platform_role_permissions` (FK `platform_app_permission_id`) jadi yatim. **Setiap update menghapus assignment permission ke role.** Ini bug nyata, wajib diperbaiki sebelum tombol upgrade dipakai orang. | `AppManager.php:340-360`, `database/migrations/2026_08_19_000001_create_platform_auth_tables.php:25` |
| G4 | Tidak ada data migration per-versi. Hanya schema migration. Odoo punya `migrations/<version>/pre-*.py` & `post-*.py`; di sini tidak ada padanannya. | — |
| G5 | Constraint `requires` tidak pernah dicek versinya, hanya keberadaan app. `notes-status` yang butuh `notes: ^1.0` tetap lolos saat `notes` naik ke 2.0. | `AppManager.php:103-108,141-147` |
| G6 | Tidak atomic. Kalau migrasi gagal di tengah, registry dan DB tinggal setengah jalan, tanpa rollback dan tanpa jejak error. | `AppManager::update()` |
| G7 | Tidak ada lock. Dua admin klik bersamaan = dua kali migrasi. | — |
| G8 | Cache (config/route/view) dan autoload tidak dibersihkan setelah upgrade. Kelas baru di namespace baru tidak ke-load tanpa `composer dump-autoload`. | `AppScaffolder::registerAutoload()` |
| G9 | Upgrade lama memblokir request HTTP sampai timeout. | — |
| G10 | Tidak ada audit log untuk upgrade meski `AuditLogger` tersedia. | `app/Platform/Services/AuditLogger.php` |

Catatan samping: `composer.json` punya entri autoload rusak `"PlatformApps\\\\{Products}\\"`
— sisa bug scaffolder. Perlu dibersihkan terpisah.

---

## 2. Model data

Migration baru: `add_upgrade_columns_to_platform_apps`.

```php
$table->string('version');                                  // versi TERPASANG (sudah termigrasi)
$table->string('available_version')->nullable();            // versi di platform.json (diisi discover)
$table->string('manifest_hash', 64)->nullable();            // sha256 platform.json — deteksi perubahan saat versi sama (dev)
$table->timestamp('upgraded_at')->nullable();
$table->text('last_upgrade_error')->nullable();
```

Tabel riwayat, sekaligus penjamin idempotensi step:

```php
Schema::create('platform_app_upgrades', function (Blueprint $t) {
    $t->id();
    $t->string('app_id')->index();
    $t->string('from_version');
    $t->string('to_version');
    $t->string('step');                 // FQCN UpgradeStep, atau 'migrations'
    $t->string('phase');                // pre | migrations | post
    $t->string('status');               // running | success | failed | skipped
    $t->unsignedInteger('duration_ms')->nullable();
    $t->text('output')->nullable();
    $t->timestamps();
    $t->unique(['app_id', 'to_version', 'step']);
});
```

Perubahan `discover()`: **jangan pernah menyentuh `version`** untuk app berstatus
`installed`/`enabled`. Hanya isi `name`, `provider`, `available_version`, `manifest_hash`.
Untuk app berstatus `discovered`, `version` = `available_version`.

`PlatformApp::hasUpgrade()`:

```php
return in_array($this->status, [self::STATUS_INSTALLED, self::STATUS_ENABLED])
    && version_compare($this->available_version, $this->version, '>');
```

`PlatformApp::isDirty()` (opsional, mode dev): `manifest_hash` berubah tapi versi sama →
tombol berubah jadi **Reapply**, meniru Odoo yang selalu menyediakan Upgrade.

Backfill saat migrasi: `available_version = version` untuk semua baris lama.

---

## 3. Upgrade script per-versi (padanan `migrations/<version>/` Odoo)

Konvensi direktori di dalam app:

```
apps/notes/
├── platform.json
├── database/migrations/          # perubahan skema (sudah ada)
└── upgrades/
    ├── 1.1.0/
    │   ├── PreUpgrade.php
    │   └── PostUpgrade.php
    └── 2.0.0/
        └── PostUpgrade.php
```

Kontrak:

```php
namespace App\Platform\Contracts;

interface UpgradeStep
{
    public function run(UpgradeContext $context): void;
}
```

File step **mengembalikan** instance-nya, persis seperti file migration Laravel,
sehingga tidak butuh entri autoload dan tidak butuh `composer dump-autoload`:

```php
<?php

return new class implements \App\Platform\Contracts\UpgradeStep
{
    public function run(\App\Platform\Upgrades\UpgradeContext $context): void
    {
        DB::table('notes')->whereNull('slug')->update([...]);
        $context->info('backfilled note slugs');
    }
};
```

```php
final class UpgradeContext
{
    public function __construct(
        public readonly PlatformApp $app,
        public readonly string $fromVersion,
        public readonly string $toVersion,
        public readonly string $stepVersion,
        public readonly bool $dryRun,
    ) {}

    public function info(string $message): void;   // masuk ke output step + log UI
}
```

Aturan eksekusi:

- Kumpulkan folder di `upgrades/` yang `version_compare($dir, $installed, '>')`
  **dan** `version_compare($dir, $target, '<=')`.
- Urutkan naik dengan `version_compare` — **tidak melompat**. 1.0 → 1.3 menjalankan
  1.1, 1.2, 1.3 berurutan, persis seperti Odoo.
- Urutan eksekusi per app: **semua `PreUpgrade`** (versi terlama dulu) → **migrasi
  schema app** → **semua `PostUpgrade`** (versi terlama dulu). Ini urutan Odoo:
  skrip `pre-` melihat skema lama, skrip `post-` melihat skema baru. Migrasi
  Laravel tidak bisa dipetakan ke versi tertentu, jadi seluruh migrasi yang
  tertunda dijalankan sebagai satu blok di antara kedua fase.
- Setiap step dicatat di `platform_app_upgrades` sebelum & sesudah jalan; step yang
  sudah `success` untuk `(app_id, to_version, step)` di-skip. Jadi mengulang upgrade
  yang setengah gagal aman.

`PreUpgrade` untuk hal yang harus terjadi **sebelum** skema berubah (menyimpan data
yang akan hilang, drop index bermasalah). `PostUpgrade` untuk backfill kolom baru,
transformasi data, seed data referensi.

Kenapa bukan cukup migration Laravel biasa: migration tidak tahu versi app dan tidak
punya urutan relatif terhadap batas versi; data migration yang hanya boleh jalan
pada lompatan 1.x → 2.0 tidak bisa diekspresikan di sana.

---

## 4. Tambahan manifest

```json
{
    "id": "notes",
    "version": "1.1.0",
    "upgrade": {
        "maintenance": false,
        "backup": true,
        "queue": "auto",
        "min_upgradable_from": "1.0.0"
    }
}
```

- `maintenance` — aktifkan `php artisan down` selama upgrade (default `false`).
- `backup` — ambil snapshot sebelum jalan (default `true`).
- `queue` — `auto` (antre jika ada >N step atau `maintenance: true`), `true`, `false`.
- `min_upgradable_from` — tolak lompatan yang tidak didukung, minta upgrade bertahap.

`AppManifest` menambah properti `upgrade` (array) + accessor bertipe.

---

## 5. Pipeline `AppUpgrader`

Service baru `app/Platform/Services/AppUpgrader.php`. `AppManager::update()` menjadi
tipis dan mendelegasikan ke sini (nama command lama tetap jalan sebagai alias).

```
plan()      → UpgradePlan (tanpa efek samping, aman dipanggil dari UI)
execute()   → jalankan plan
```

### 5.1 Preflight (semua dicek dulu, gagal = tidak ada yang berubah)

1. Manifest valid & terbaca.
2. `supportsPlatform(config('platform.version'))`.
3. `version_compare(available, installed, '>')` — kalau tidak, hanya boleh lanjut dengan `--force`/Reapply.
4. `min_upgradable_from` terpenuhi.
5. **Dependency ke atas**: setiap `requires` app harus terinstal *dan* versinya memenuhi constraint.
6. **Dependency ke bawah (dependents)**: setiap app terinstal yang `requires[$appId]`
   dicek terhadap versi target. Kalau tidak kompatibel:
   - ada versi baru dependent yang kompatibel → **masukkan ke plan** (cascade),
   - tidak ada → **blokir** dengan pesan jelas: *"Notes Status 1.0 hanya mendukung Notes ^1.0. Upgrade dibatalkan."*
7. Urutkan semua app dalam plan secara topologis (dependency dulu).
8. Tidak ada upgrade lain sedang berjalan (lihat lock).

### 5.2 UpgradePlan (yang ditampilkan sebelum konfirmasi)

```php
final class UpgradePlan
{
    /** @var AppUpgradeSteps[] terurut topologis */
    public array $apps;
    public array $blockers;      // string[] — kalau tidak kosong, tombol Confirm mati
    public array $warnings;
    public bool  $requiresMaintenance;
    public bool  $shouldQueue;
}
```

Per app: `fromVersion`, `toVersion`, daftar versi antara, daftar file migrasi yang
belum jalan, daftar step class, diff permission (`added` / `removed`), perkiraan
apakah butuh backup.

`plan()` murni baca — dipakai untuk modal preview **dan** untuk `--dry-run` di CLI.

### 5.3 Eksekusi

```
lock          Cache::lock("platform:upgrade", 600) — tolak kalau sudah dipegang
status        semua app di plan → status_flag 'upgrading' (kolom status tetap, pakai flag terpisah agar provider tetap teregistrasi)
maintenance   opsional, App::maintenanceMode()->activate(['secret' => ...])
backup        snapshot (§6)
foreach app in plan (urut topologis):
    PreUpgrade  semua versi [v1..vTarget], urut naik  → catat per step
    migrate --path=apps/{id}/database/migrations --force → catat batch id
    PostUpgrade semua versi [v1..vTarget], urut naik  → catat per step
    syncPermissions(diff-based)   ← §7
    bump version, available_version, upgraded_at, clear last_upgrade_error
    Event: AppUpgraded(app, from, to)
    Audit: 'app.upgraded'
finally:
    clear cache: config, route, view, event, compiled
    re-register provider app (kelas baru ikut aktif tanpa restart)
    maintenance off
    release lock
```

Kenapa loop app di luar dan versi di dalam: dependency harus sepenuhnya sampai di
versi target sebelum dependent-nya mulai, sama seperti urutan Odoo.

---

## 6. Rollback

Transaksi DB tunggal **tidak cukup** — MySQL melakukan implicit commit pada DDL.
Karena itu strategi dua lapis:

**Lapis 1 — snapshot (default, `backup: true`) — BELUM DIIMPLEMENTASIKAN**

- SQLite: copy file database ke `storage/app/platform/upgrades/{app}/{ts}.sqlite`.
- MySQL/Postgres: `mysqldump`/`pg_dump` terbatas pada tabel yang dimiliki app
  (didaftarkan manifest lewat `"tables": [...]`, atau seluruh DB kalau tidak ada).
  Kalau tool dump tidak tersedia → warning di plan, bukan error; admin memutuskan.

**Lapis 2 — kompensasi (sudah diimplementasikan)**

- `migrate:rollback --path=... --step=N` untuk batch yang baru dibuat upgrade ini
  (batch id direkam sebelum & sesudah `migrate`).
- Step yang gagal ditandai `failed` di `platform_app_upgrades`, tidak dianggap selesai.
- `version` app **tidak** dibump. `last_upgrade_error` diisi pesan + step yang gagal.

Restore snapshot hanya dilakukan otomatis untuk SQLite (aman, satu file). Untuk
MySQL/Postgres, platform menampilkan perintah restore yang siap disalin — memulihkan
DB produksi secara otomatis terlalu berisiko untuk dilakukan diam-diam.

Setelah gagal: app tetap di versi lama, status kembali normal, banner merah di
`/platform` dengan tombol **Lihat log** dan **Coba lagi** (step sukses tidak diulang).

---

## 7. Permission sync yang tidak merusak (perbaikan G3)

Ganti `delete()` + `create()` dengan diff berbasis nama:

```php
$manifestNames = collect($manifest->permissions);
$existing      = PlatformAppPermission::where('app_id', $appId)->pluck('id', 'name');

// tambah yang baru — ID lama tidak tersentuh, pivot role aman
foreach ($manifestNames->diff($existing->keys()) as $name) {
    PlatformAppPermission::create(['app_id' => $appId, 'name' => $name]);
}

// hapus yang benar-benar hilang dari manifest, sekalian bersihkan pivot
$removedIds = $existing->except($manifestNames->all())->values();
DB::table('platform_role_permissions')->whereIn('platform_app_permission_id', $removedIds)->delete();
PlatformAppPermission::whereIn('id', $removedIds)->delete();
```

Permission `*.view` yang **baru** tetap ditambahkan ke role `member`
(`syncWithoutDetaching`), tapi permission lama tidak pernah di-reset — perubahan
manual admin bertahan melewati upgrade. Diff ini ditampilkan di modal preview.

---

## 8. UI

`resources/views/platform/home.blade.php`:

- Kolom **Version**: `1.0.0 → 1.1.0` dengan badge `text-bg-warning` "Upgrade available".
- Tombol **Upgrade** (`btn-primary`) muncul untuk app `installed`/`enabled` yang punya
  upgrade; kalau tidak ada upgrade, tombol **Reapply** kecil (outline) untuk dev.
- Tombol **Upgrade all** di header saat ada ≥2 app outdated.
- Klik → `GET /platform/apps/{app}/upgrade/plan` (JSON) → modal Bootstrap berisi:
  versi asal→tujuan, app lain yang ikut, jumlah migrasi baru, permission ditambah/dihapus,
  peringatan maintenance/backup, tombol **Confirm upgrade** (mati kalau ada blocker).
- Confirm → `POST /platform/apps/{app}/upgrade`.
  - Sinkron: redirect + flash sukses/gagal.
  - Antre: redirect ke `/platform/apps/{app}/upgrade/{run}` — halaman progress polling
    tiap 2 detik ke endpoint status, menampilkan step demi step dari `platform_app_upgrades`.

Route baru (di dalam `can:platform.manage`):

```php
Route::get('/plan',        [PlatformController::class, 'upgradePlan'])->name('upgrade.plan');
Route::post('/upgrade',    [PlatformController::class, 'upgrade'])->name('upgrade');
Route::get('/upgrade/{run}', [PlatformController::class, 'upgradeStatus'])->name('upgrade.status');
Route::post('/upgrade-all', ...)->name('upgrade.all');   // di luar prefix {app}
```

**Auto-discover.** Supaya admin tidak perlu ingat `platform:app:discover`,
`PlatformController@index` memanggil `discover()` di belakang cache 60 detik
(operasi murah: baca beberapa file JSON). Ini padanan tombol *Update Apps List*
Odoo, tapi tanpa tombol.

---

## 9. CLI

```bash
php artisan platform:app:upgrade notes            # satu app
php artisan platform:app:upgrade --all            # semua yang outdated, urut topologis
php artisan platform:app:upgrade notes --dry-run  # cetak plan, tidak mengubah apa pun
php artisan platform:app:upgrade notes --force    # reapply walau versi sama
php artisan platform:app:upgrade notes --no-backup
```

`platform:app:update` dipertahankan sebagai alias tipis agar skrip lama tidak rusak.
`platform:app:list` menambah kolom `Available` dan penanda `↑`.

---

## 10. Async

Job `App\Platform\Jobs\RunAppUpgrade` (`ShouldBeUnique` berdasarkan `platform:upgrade`).
Dipakai saat `queue: true`, saat `maintenance: true`, atau saat plan berisi >1 app.
Kalau `QUEUE_CONNECTION=sync`, platform mendeteksi dan menjalankan langsung —
plus warning di modal bahwa request akan berjalan lama. Progress tetap terbaca dari
`platform_app_upgrades`, jadi halaman progress sama untuk kedua mode.

---

## 11. Keamanan & audit

- Semua endpoint di belakang `can:platform.manage` (sudah ada).
- Setiap upgrade menulis `AuditLogger::log('app.upgraded', $app, ['from' => ..., 'to' => ..., 'steps' => ...])`,
  dan `app.upgrade_failed` saat gagal.
- Upgrade script adalah kode PHP dari repo sendiri — tidak ada eksekusi kode dari
  sumber eksternal, tidak ada sandbox yang perlu dijanjikan. Ini konsisten dengan
  non-goal "bukan marketplace / plugin sandbox" di README.

---

## 12. Testing

Tambahan `tests/Feature/AppUpgradeTest.php`:

1. `discover()` tidak menimpa `version` app terinstal, hanya `available_version`.
2. `hasUpgrade()` benar untuk kombinasi versi & status.
3. Upgrade menjalankan step 1.1 lalu 1.2 **dalam urutan itu** saat lompat 1.0 → 1.2.
4. Step yang sudah sukses tidak diulang saat upgrade dijalankan ulang.
5. Migrasi baru jalan, migrasi lama tidak jalan dua kali.
6. **Assignment role↔permission bertahan melewati upgrade** (regresi G3).
7. Permission yang dihapus dari manifest hilang beserta pivot-nya.
8. Upgrade `notes` ke 2.0 diblokir saat `notes-status ^1.0` terinstal dan tidak punya versi kompatibel.
9. Cascade: `notes` 2.0 + `notes-status` 2.0 tersedia → keduanya masuk satu plan, urut benar.
10. Step yang melempar exception → versi tidak dibump, `last_upgrade_error` terisi, migrasi ter-rollback.
11. Lock: upgrade kedua yang bersamaan ditolak.
12. Endpoint plan mengembalikan blocker dan tombol confirm mati.

Fixture: app dummy `tests/fixtures/apps/demo` dengan dua versi manifest & folder `upgrades/`.

---

## 13. Rencana implementasi bertahap

Tiap fase berdiri sendiri dan bisa dirilis.

**Fase 1 — deteksi versi (fondasi, wajib duluan) — SELESAI**
Migration kolom baru, `discover()` berhenti menimpa `version`, `hasUpgrade()`,
badge di UI, kolom `Available` di `platform:app:list`. Belum ada tombol.

**Fase 2 — perbaikan permission sync (G3) — SELESAI**
Diff-based. Ini bug yang sudah aktif sekarang, tidak perlu menunggu fitur upgrade.

**Fase 3 — tombol Upgrade sinkron — SELESAI**
`AppUpgrader` + `plan()` + `execute()` tanpa hook script: preflight, migrasi,
permission sync, bump versi, clear cache, audit, lock, error handling. Route + tombol
+ modal preview. Sudah "tinggal klik" untuk mayoritas kasus.

**Fase 4 — upgrade script per-versi — SELESAI**
`UpgradeStep`, `UpgradeContext`, `platform_app_upgrades`, eksekusi bertahap
+ idempotensi.

**Fase 5 — dependency versi & cascade — SELESAI**
`satisfies()` semver di `AppManifest`, blocker dependent, plan multi-app topologis,
tombol **Upgrade all**.

**Fase 6 — backup/rollback & async — BELUM**
Snapshot, kompensasi rollback, job + halaman progress, maintenance mode.

---

## 14. Keputusan yang masih terbuka

1. **Sumber kode versi baru.** Desain ini berasumsi kode app di `apps/` sudah diganti
   (git pull / deploy) sebelum tombol ditekan — versi manifest naik, tombol muncul.
   Kalau nanti ingin "upgrade menarik kode dari remote", itu fitur distribusi yang
   terpisah dan bertabrakan dengan non-goal marketplace di README.
2. **Downgrade.** Tidak didukung. Turun versi = restore snapshot manual.
3. **Multi-tenant.** Kalau nanti ada beberapa database tenant, upgrade harus iterasi
   per koneksi; struktur `AppUpgrader` sudah memungkinkan tapi belum dirancang.
4. **`composer dump-autoload` saat upgrade.** Alternatif tanpa shell: daftarkan prefix
   PSR-4 app langsung ke `ClassLoader` Composer saat boot platform. Lebih aman di
   server dengan composer terkunci, sekalian membereskan entri rusak
   `"PlatformApps\\\\{Products}\\"` di `composer.json`.
