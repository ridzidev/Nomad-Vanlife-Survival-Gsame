# 🚐 Nomad Vanlife — 3D Camper Van Survival

Game survival eksplorasi dunia terbuka (*open-world*) berbasis **Three.js** dan **LocalStorage**. Kemudikan mobil camper van-mu melintasi dunia prosedural tanpa batas ala Minecraft, kumpulkan perbekalan, kelola kesegaran makanan di dalam kulkas, masak di kompor kabin, dan hias camper van impianmu!

---

## 🚀 Cara Menjalankan

1. Pastikan file `index.html` berada di folder proyekmu.
2. Buka `index.html` langsung di browser modern (**Chrome**, **Edge**, atau **Firefox**) dengan koneksi internet aktif (untuk memuat Three.js r128 dari CDN).
   - *Opsional (disarankan untuk pengembangan):* Jalankan via local server seperti `npx serve .` atau ekstensi **Live Server** di VS Code.

---

## 🎮 Cara Bermain

### 1. Kontrol Dasar

| Tombol | Mode Mobil (Mengemudi) | Mode Jalan Kaki |
| :--- | :--- | :--- |
| **`W A S D` / Panah** | Gas, Mundur, Belok kiri/kanan | Berjalan |
| **`Shift`** | — | Lari cepat (*sprint*, memakai Stamina) |
| **`Space`** | Rem tangan (*handbrake*) | — |
| **`E`** | Keluar dari Camper Van | Masuk kembali ke Camper Van |
| **`F`** | — | Ambil barang / Isi Pom Bensin / Sumur / Tangkap Ikan |
| **`L`** | Nyalakan / Matikan Lampu Utama | — |
| **`H`** | Bunyikan Klakson | — |
| **`C`** | Ubah jarak kamera (Normal / Jauh / Dekat) | Ubah jarak kamera |
| **`Mouse Drag`** | Putar sudut kamera bebas | Putar arah kamera & arah gerak |

### 2. Tombol Menu & Shortcut
- **`Tab` / `I`** — Buka **Tas Ransel, Kulkas Mobil & Kompor Camper**.
- **`B`** — Buka **Bengkel & Hias Mobil** (kamera otomatis mengorbit van secara 3D).
- **`M`** — Buka **Peta Bioma Sekitar** untuk melacak jalan raya, laut, kota, desa, dan posisimu.
- **`Esc`** — Menutup jendela menu yang terbuka.
- **Tombol HUD Atas**: Akses cepat **Tidur** (memajukan waktu +6 jam & memulihkan stamina/HP), **Audio** (bisukan/aktifkan efek suara), **Simpan** (simpan manual ke `localStorage`), dan **Baru** (buat dunia & *seed* acak baru).

---

## 🧭 Mekanik Bertahan Hidup (Survival)

1. **Eksplorasi 6 Bioma Prosedural**:
   Dunia dibuat otomatis secara berkesinambungan per *chunk* (`48x48`) dengan jalan aspal yang menghubungkan berbagai wilayah:
   - 🌲 **Hutan Rindang**: Sumber *Beri Hutan, Jamur Liar,* dan *Kayu Bakar*.
   - 🌵 **Gurun Pasir**: Suhu panas (39°C) membuat rasa haus meningkat cepat dan makanan di tas jauh lebih cepat basi! Terdapat peti perbekalan dan *Scrap*.
   - 🏖️ **Pantai Tropis**: Dekati tepi air laut dan tekan **`F`** untuk mengambil *Air Alam* dan menangkap *Ikan Segar*.
   - 🏔️ **Pegunungan**: Kaya akan *Besi Tua (Scrap)*, *Elektronik*, dan daging buruan.
   - 🏡 **Desa Damai**: Terdapat **Sumur Desa** untuk mengisi air minum bersih gratis dan rumah-rumah kayu dengan peti roti/daging.
   - 🏙️ **Kota Lama**: Terdapat **Pom Bensin** (mendekat dan tekan **`F`** untuk isi tangki bensin penuh + bonus jerigen!) serta minimarket berisi *Makanan Kaleng, Soda,* dan *Elektronik*.

2. **Manajemen Kulkas & Kesegaran Makanan**:
   - Bahan segar (*Daging Mentah, Ikan Segar, Beri, Jamur, Roti, Steak*) memiliki indikator **Kesegaran (%)**. Jika dibiarkan di dalam tas hingga 0%, makanan akan berubah menjadi **Makanan Basi** (mengurangi HP jika dimakan).
   - Pindahkan makanan ke **Kulkas Mobil** (`4°C ❄️`) agar kesegarannya bertahan jauh lebih lama.
   - Minuman seperti **Soda Kaleng** yang didinginkan di kulkas memberikan bonus penghilang dahaga ekstra (`+16 Haus`).
   - Kulkas memakan daya **Baterai Mobil** secara perlahan—matikan kulkas jika baterai kritis, atau pasang **Panel Surya** & terus berkendara (alternator mobil mengisi baterai saat berjalan).

3. **Kompor Camper & Merakit**:
   - Masak bahan mentah menjadi makanan bergizi tinggi:
     - *Daging Mentah + Kayu* ➔ **Steak Panggang**
     - *Ikan Segar + Kayu* ➔ **Ikan Bakar Pantai**
     - *2 Jamur + Air Alam* ➔ **Sup Jamur Hangat**
     - *Air Alam + Kayu* ➔ **Air Minum Bersih** (aman diminum)
     - *Beri + Air Bersih* ➔ **Kopi Camper** (pemulih Stamina)
   - Rakit **Kit Perbaikan** dari *Scrap* untuk memperbaiki bodi mobil, atau suling **Bio-Fuel** darurat dari *Kayu + Beri*.

4. **Hias & Upgrade Mobil (`B`)**:
   - Ubah warna utama bodi, warna aksen/atap, dan corak (*Polos, Garis Retro, Ombak, Gunung*).
   - Rakit aksesori fungsional & estetis: **Panel Surya** (cas baterai otomatis saat siang), **Bumper Off-Road** (mengurangi kerusakan tabrakan), **Lampu Hias Malam**, **Tenda Samping (Awning)**, **Rak Atap**, **Papan Selancar**, dan **Ban Cadangan**.
   - Upgrade **Kapasitas Kulkas** (hingga 16 slot), **Tangki Bensin** (hingga 180%), dan **Baterai** (hingga 200%).

5. **Penyimpanan Otomatis**:
   - Progres tersimpan otomatis setiap 15 detik di `localStorage` browser (`nomad_vanlife_survival_v1`), mencakup posisi mobil, barang di tas & kulkas, tingkat kesegaran, dekorasi van, hingga *loot* yang sudah diambil.

---

## 🛠️ Arah Perkembangan Menjadi Game Sungguhan (Roadmap)

Kode saat ini adalah **Vertical Slice / Prototipe MVP** yang sudah bisa dimainkan penuh. Untuk mengembangkannya menjadi game *indie* komersial (sekelas *Pacific Drive*, *Outbound*, atau *The Long Drive*) di web, itch.io, atau Steam, berikut tahapan pengembangannya:

### Fase 1: Refactoring Arsitektur & Performa
- [ ] **Migrasi ke Vite + TypeScript**: Pecah kode satu file menjadi modul terstruktur (`world/TerrainGen.ts`, `world/ChunkManager.ts`, `entities/CamperVan.ts`, `systems/SurvivalSystem.ts`, `ui/InventoryUI.tsx`).
- [ ] **Web Workers untuk Chunk Generation**: Pindahkan kalkulasi *noise*, vertex terrain, dan penempatan objek ke *Web Worker* agar *framerate* tetap mulus 60 FPS tanpa *stutter* saat mobil melaju kencang melintasi batas chunk.
- [ ] **InstancedMesh & LOD (Level of Detail)**: Gabungkan ratusan pohon, batu, dan kaktus dalam satu *draw call* menggunakan `THREE.InstancedMesh` untuk memangkas beban GPU hingga 70%+.
- [ ] **Spatial Hashing untuk Kolisi**: Ganti loop pengecekan tabrakan linear dengan grid spasial agar tetap cepat meski objek dunia sangat padat.

### Fase 2: Fisika & Mekanik Kendaraan Mendalam
- [ ] **Physics Engine Nyata (Rapier3D / Cannon-es)**: Tambahkan *raycast vehicle suspension* sungguhan agar mobil bisa melompat di gundukan gurun, tergelincir di pasir/lumpur, dan punya bobot muatan (semakin penuh isi kulkas & rak atap, suspensi makin berat).
- [ ] **Interior Camper Van yang Bisa Dimasuki (Walkable Interior)**:
  - Saat pintu belakang/samping van dibuka, pemain bisa langsung berjalan **masuk ke dalam kabin mobil** dalam sudut pandang *First-Person*.
  - Klik langsung objek 3D di dalam kabin: buka pintu kulkas secara fisik, nyalakan kompor gas, rebahan di kasur, atau letakkan pot tanaman/foto di rak dinding sesuka hati (*free placement*).
- [ ] **Perawatan Komponen Mobil**: Pisahkan kondisi **Ban** (bisa bocor di jalan berbatu/off-road), **Oli Mesin**, **Aki**, dan **Radiator** (bisa *overheat* di Gurun!).

### Fase 3: Kedalaman Dunia, Cuaca & Atmosfer
- [ ] **Sistem Cuaca Dinamis**: Hujan deras (mengisi penampung air hujan di atap van, tapi jalanan jadi licin), badai pasir di Gurun (jarak pandang pendek), kabut pagi di Hutan, dan hujan salju di Pegunungan (butuh penghangat kabin agar tidak kedinginan/hipotermia).
- [ ] **Landmark & Point of Interest (POI) Unik**:
  - Terowongan gunung, jembatan kayu melintasi sungai besar, menara pengawas hutan, mercusuar di pantai, dan stasiun pengisian daya EV.
- [ ] **Satwa Liar & Aktivitas Santai**:
  - Memancing ikan dengan *mini-game* di pantai/danau, memotret pemandangan dengan kamera polaroid (disimpan sebagai album foto perjalanan!), serta hewan liar (rusa di hutan, kepiting di pantai, atau serigala di malam hari).
- [ ] **NPC Pengembara & Barter**: Temui *van-lifer* lain di perkemahan acak untuk bertukar barang langka, misi antar paket antar kota, dan cerita.

### Fase 4: Audio, UI/UX & Ekosistem Rilis
- [ ] **Radio Mobil & Musik Lo-Fi Dinamis**: Tambahkan stasiun radio fiktif dengan lagu *ambient/acoustic/synthwave* serta suara lingkungan 3D (ombak pantai, jangkrik malam, rintik hujan memukul atap kaleng van).
- [ ] **Drag-and-Drop Grid Kulkas (Tetris-style)**: Ubah kulkas menjadi grid 2D (misalnya `4x4` kotak) di mana botol air memakan `1x2` kotak dan daging memakan `2x2` kotak agar penataan kulkas lebih taktis dan memuaskan.
- [ ] **Cloud Save & Multi-Slot**: Dukungan ekspor/impor file save `.json` atau integrasi IndexedDB / Supabase untuk menyimpan beberapa profil perjalanan sekaligus.
- [ ] **Packaging Desktop & Mobile**: Bungkus menggunakan **Tauri** atau **Electron** untuk rilis ke **Steam / itch.io** (mendukung *controller/gamepad* & setir kemudi), atau **Capacitor** untuk versi Android/iOS.

---

## 📄 Lisensi
Proyek ini bebas dikembangkan, dimodifikasi, dan dijadikan fondasi game survival milikmu sendiri. Selamat berkarya dan selamat bertualang! 🚐✨