<?php
/**
 * Patcher: Modularisasi Kode + Sistem Turun/Gerak Karakter 3D
 * Tanggal: 2026-11-24 03:11 WIB
 */

$isCli = (php_sapi_name() === 'cli');
$logs = [];

function logMsg($msg, $type = 'info') {
    global $logs, $isCli;
    $logs[] = ['msg' => $msg, 'type' => $type];
    if ($isCli) {
        $prefix = $type === 'error' ? '[ERROR] ' : ($type === 'ok' ? '[OK] ' : '[INFO] ');
        echo $prefix . $msg . PHP_EOL;
    }
}

$baseDir = __DIR__;
$indexFile = $baseDir . '/index.html';

if (!file_exists($indexFile)) {
    logMsg('File index.html tidak ditemukan di folder: ' . $baseDir, 'error');
} else {
    // 1. Backup index.html
    $backupFile = $baseDir . '/index.html.bak_20261124_0311';
    if (!file_exists($backupFile)) {
        copy($indexFile, $backupFile);
        logMsg('Backup dibuat: index.html.bak_20261124_0311', 'ok');
    } else {
        logMsg('Backup sudah ada sebelumnya, dilewati.', 'info');
    }

    // 2. Pastikan folder css/ dan js/ tersedia
    foreach (['css', 'js'] as $dir) {
        $path = $baseDir . '/' . $dir;
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
            logMsg("Folder dibuat: {$dir}/", 'ok');
        }
    }

    // 3. Kumpulan file modular baru
    $files = [];

    // -----------------------------------------------------------------
    // css/style.css
    // -----------------------------------------------------------------
    $files['css/style.css'] = <<<'NOMAD_END'
:root {
  --bg-glass: rgba(15, 22, 32, 0.78);
  --bg-card: rgba(25, 35, 50, 0.9);
  --border: rgba(255, 255, 255, 0.14);
  --accent: #f59e0b;
  --accent-2: #10b981;
  --danger: #ef4444;
  --info: #38bdf8;
  --text: #f8fafc;
  --muted: #94a3b8;
}

* { box-sizing: border-box; user-select: none; }
html, body {
  margin: 0; padding: 0; width: 100%; height: 100%;
  overflow: hidden; background: #0b1120;
  font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
  color: var(--text);
}

#game-canvas {
  display: block; width: 100%; height: 100%;
  cursor: grab;
}
#game-canvas:active { cursor: grabbing; }

/* HUD */
#hud {
  position: fixed; inset: 0; pointer-events: none;
  display: flex; flex-direction: column; justify-content: space-between;
  padding: 14px; z-index: 10;
}

.hud-row {
  display: flex; justify-content: space-between; align-items: flex-start; gap: 12px;
}

.panel {
  background: var(--bg-glass);
  backdrop-filter: blur(10px);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 10px 14px;
  pointer-events: auto;
  box-shadow: 0 8px 28px rgba(0,0,0,0.35);
}

/* Bars */
.stats-grid {
  display: grid; grid-template-columns: repeat(2, 145px); gap: 8px 12px;
}
.stat-item { display: flex; flex-direction: column; gap: 3px; }
.stat-label {
  display: flex; justify-content: space-between; font-size: 11px; font-weight: 600; color: #e2e8f0;
}
.bar-track {
  width: 100%; height: 7px; background: rgba(255,255,255,0.12);
  border-radius: 99px; overflow: hidden;
}
.bar-fill {
  height: 100%; width: 100%; border-radius: 99px; transition: width 0.2s ease;
}

/* World Info */
.world-badge {
  display: flex; flex-direction: column; align-items: center; text-align: center; gap: 4px;
  min-width: 210px;
}
.biome-pill {
  font-size: 13px; font-weight: 700; letter-spacing: 0.4px;
  padding: 3px 10px; border-radius: 99px; background: rgba(255,255,255,0.1);
}
.time-row { font-size: 12px; color: var(--muted); display: flex; gap: 10px; align-items: center; }

/* Top Right Buttons */
.quick-actions { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; max-width: 360px; }
.btn {
  background: rgba(255,255,255,0.1); color: var(--text);
  border: 1px solid var(--border); border-radius: 10px;
  padding: 7px 11px; font-size: 12px; font-weight: 600;
  cursor: pointer; transition: 0.15s; display: inline-flex; align-items: center; gap: 5px;
}
.btn:hover { background: rgba(255,255,255,0.2); transform: translateY(-1px); }
.btn.primary { background: var(--accent); color: #111827; border-color: #fbbf24; }
.btn.primary:hover { background: #fbbf24; }
.btn.success { background: var(--accent-2); color: #052e16; border-color: #34d399; }
.btn.danger { background: rgba(239,68,68,0.25); border-color: rgba(239,68,68,0.5); color: #fecaca; }
.btn:disabled { opacity: 0.45; cursor: not-allowed; transform: none; }

/* Bottom HUD */
.bottom-hud { align-items: flex-end; }
.vehicle-dash { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; }
.speedo {
  text-align: center; min-width: 82px; padding-right: 12px;
  border-right: 1px solid var(--border);
}
.speedo-val { font-size: 26px; font-weight: 800; line-height: 1; color: var(--accent); }
.speedo-unit { font-size: 10px; color: var(--muted); text-transform: uppercase; margin-top: 2px; }

.controls-hint {
  font-size: 11px; color: #cbd5e1; line-height: 1.5; max-width: 325px;
}
kbd {
  background: rgba(255,255,255,0.16); padding: 1px 5px; border-radius: 4px;
  font-family: monospace; font-size: 10px; border: 1px solid rgba(255,255,255,0.2);
}

/* Prompt */
#interact-prompt {
  position: fixed; bottom: 118px; left: 50%; transform: translateX(-50%);
  background: rgba(15, 23, 42, 0.9); border: 1px solid var(--accent);
  color: #fef3c7; padding: 8px 16px; border-radius: 99px;
  font-size: 13px; font-weight: 600; pointer-events: auto; cursor: pointer;
  display: none; z-index: 15; box-shadow: 0 4px 20px rgba(245, 158, 11, 0.25);
  animation: pulsePrompt 1.5s infinite;
}
@keyframes pulsePrompt {
  0%, 100% { transform: translateX(-50%) scale(1); }
  50% { transform: translateX(-50%) scale(1.03); }
}

/* Toast Notifications */
#toast-container {
  position: fixed; top: 88px; left: 50%; transform: translateX(-50%);
  display: flex; flex-direction: column; gap: 6px; z-index: 40; pointer-events: none;
}
.toast {
  background: rgba(15, 23, 42, 0.92); border: 1px solid var(--border);
  padding: 8px 14px; border-radius: 10px; font-size: 13px; font-weight: 500;
  box-shadow: 0 6px 20px rgba(0,0,0,0.4); animation: fadeSlide 0.25s ease;
}
@keyframes fadeSlide {
  from { opacity: 0; transform: translateY(-8px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Modals */
.modal-backdrop {
  position: fixed; inset: 0; background: rgba(5, 8, 15, 0.65);
  backdrop-filter: blur(5px); display: none; justify-content: center; align-items: center;
  z-index: 30; padding: 16px;
}
.modal-backdrop.open { display: flex; }

#modal-customize {
  background: transparent; backdrop-filter: none; justify-content: flex-end; pointer-events: none;
}
#modal-customize .modal-box {
  pointer-events: auto; width: 390px; max-height: 90vh;
}

.modal-box {
  background: #111827; border: 1px solid rgba(255,255,255,0.16);
  border-radius: 18px; width: min(1040px, 96vw); max-height: 88vh;
  display: flex; flex-direction: column; overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,0.6);
}
.modal-header {
  padding: 14px 18px; border-bottom: 1px solid var(--border);
  display: flex; justify-content: space-between; align-items: center;
  background: rgba(255,255,255,0.03);
}
.modal-header h2 { margin: 0; font-size: 17px; display: flex; align-items: center; gap: 8px; }
.modal-body { padding: 16px; overflow-y: auto; flex: 1; }

.inv-layout {
  display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px;
}
@media (max-width: 860px) { .inv-layout { grid-template-columns: 1fr; } }

.col-box {
  background: var(--bg-card); border: 1px solid var(--border);
  border-radius: 14px; padding: 12px; display: flex; flex-direction: column; gap: 10px;
}
.col-title {
  display: flex; justify-content: space-between; align-items: center;
  font-size: 14px; font-weight: 700; padding-bottom: 8px; border-bottom: 1px solid var(--border);
}

.item-list { display: flex; flex-direction: column; gap: 7px; max-height: 410px; overflow-y: auto; }
.item-card {
  background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);
  border-radius: 10px; padding: 8px 10px; display: flex; flex-direction: column; gap: 5px;
}
.item-top { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.item-name { font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
.item-desc { font-size: 11px; color: var(--muted); }
.item-actions { display: flex; gap: 5px; flex-wrap: wrap; }
.btn-xs { padding: 4px 8px; font-size: 11px; border-radius: 7px; }

.fresh-bar { width: 100%; height: 4px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden; }
.fresh-fill { height: 100%; background: #22c55e; }

.color-row { display: flex; gap: 7px; flex-wrap: wrap; margin-top: 6px; }
.swatch {
  width: 28px; height: 28px; border-radius: 50%; border: 2px solid transparent;
  cursor: pointer; transition: transform 0.12s;
}
.swatch.active { border-color: #fff; transform: scale(1.14); }
.section-label { font-size: 12px; font-weight: 700; color: #cbd5e1; margin-top: 12px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }

.decor-grid { display: flex; flex-direction: column; gap: 8px; }
.decor-card {
  background: rgba(255,255,255,0.05); border: 1px solid var(--border);
  border-radius: 10px; padding: 9px 11px; display: flex; justify-content: space-between; align-items: center; gap: 10px;
}

#map-canvas {
  width: 100%; max-width: 460px; aspect-ratio: 1; border-radius: 12px;
  border: 1px solid var(--border); display: block; margin: 0 auto; background: #0f172a;
}
.map-legend {
  display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 12px; font-size: 12px;
}
.legend-dot { width: 12px; height: 12px; border-radius: 3px; display: inline-block; vertical-align: middle; margin-right: 4px; }

#gameover-screen {
  position: fixed; inset: 0; background: rgba(10, 10, 18, 0.88);
  display: none; flex-direction: column; justify-content: center; align-items: center;
  z-index: 50; text-align: center; gap: 14px; padding: 20px;
}
#gameover-screen.open { display: flex; }

#mobile-controls {
  display: none; position: fixed; bottom: 100px; left: 16px; right: 16px;
  justify-content: space-between; pointer-events: none; z-index: 12;
}
@media (hover: none) and (pointer: coarse) {
  #mobile-controls { display: flex; }
  .controls-hint { display: none; }
}
.touch-pad { display: grid; grid-template-columns: repeat(3, 48px); gap: 6px; pointer-events: auto; }
.touch-btn {
  width: 48px; height: 48px; border-radius: 12px; background: rgba(15,23,42,0.75);
  border: 1px solid var(--border); color: #fff; font-size: 18px; font-weight: bold;
}
NOMAD_END;

    // -----------------------------------------------------------------
    // js/data.js
    // -----------------------------------------------------------------
    $files['js/data.js'] = <<<'NOMAD_END'
// =========================================================================
// DATABASE ITEM, RESEP, DEKORASI, UPGRADE & BIOMA
// =========================================================================
const ITEMS = {
  berry:        { name: 'Beri Hutan', icon: '🫐', type: 'food', hunger: 12, thirst: 6, hp: 2, perishable: true, decayRate: 1.3, desc: 'Segar dari semak hutan.' },
  mushroom:     { name: 'Jamur Liar', icon: '🍄', type: 'food', hunger: 14, thirst: 0, hp: 0, perishable: true, decayRate: 1.2, desc: 'Bisa dimakan atau dimasak jadi sup.' },
  raw_meat:     { name: 'Daging Mentah', icon: '🥩', type: 'food', hunger: 10, thirst: 0, hp: -8, perishable: true, decayRate: 2.0, desc: 'Masak dulu di kompor agar sehat!' },
  raw_fish:     { name: 'Ikan Segar', icon: '🐟', type: 'food', hunger: 10, thirst: 0, hp: -4, perishable: true, decayRate: 2.1, desc: 'Didapat dari pantai. Enak dibakar!' },
  canned_food:  { name: 'Makanan Kaleng', icon: '🥫', type: 'food', hunger: 38, thirst: 5, hp: 5, perishable: false, desc: 'Awet sangat lama, temuan dari kota.' },
  bread:        { name: 'Roti Hangat', icon: '🥖', type: 'food', hunger: 26, thirst: 0, hp: 4, perishable: true, decayRate: 0.9, desc: 'Bekal nyaman di perjalanan.' },
  steak:        { name: 'Steak Panggang', icon: '🍖', type: 'food', hunger: 58, thirst: 0, hp: 18, perishable: true, decayRate: 0.85, desc: 'Hasil masakan penuh energi!' },
  grilled_fish: { name: 'Ikan Bakar Pantai', icon: '🐠', type: 'food', hunger: 52, thirst: 4, hp: 14, perishable: true, decayRate: 0.9, desc: 'Gurih dan menyehatkan.' },
  soup:         { name: 'Sup Jamur Hangat', icon: '🍲', type: 'food', hunger: 44, thirst: 24, hp: 15, perishable: true, decayRate: 0.95, desc: 'Mengenyangkan sekaligus melepas dahaga.' },
  spoiled:      { name: 'Makanan Basi', icon: '🤢', type: 'trash', hunger: 4, thirst: 0, hp: -20, perishable: false, desc: 'Sudah basi karena lupa masuk kulkas!' },

  dirty_water:  { name: 'Air Alam', icon: '🫙', type: 'drink', hunger: 0, thirst: 22, hp: -5, perishable: false, desc: 'Rebus di kompor agar jadi air bersih.' },
  water:        { name: 'Air Minum Bersih', icon: '💧', type: 'drink', hunger: 0, thirst: 45, hp: 4, perishable: false, desc: 'Air murni menyegarkan.' },
  soda:         { name: 'Soda Kaleng', icon: '🥤', type: 'drink', hunger: 4, thirst: 36, hp: 0, coldBonus: 16, perishable: false, desc: 'Dinginkan di kulkas untuk bonus +16 Haus!' },
  coffee:       { name: 'Kopi Camper', icon: '☕', type: 'drink', hunger: 2, thirst: 28, hp: 4, stamina: 45, perishable: false, desc: 'Memulihkan stamina pengemudi.' },

  fuel_can:     { name: 'Jerigen Bensin', icon: '⛽', type: 'fuel', amount: 35, desc: 'Isi tangki camper van +35%.' },
  wood:         { name: 'Kayu Bakar', icon: '🪵', type: 'mat', desc: 'Bahan bakar kompor & aksesori kayu.' },
  scrap:        { name: 'Besi Tua (Scrap)', icon: '⚙️', type: 'mat', desc: 'Material untuk menghias & upgrade mobil.' },
  electronics:  { name: 'Elektronik', icon: '🔌', type: 'mat', desc: 'Komponen untuk Panel Surya, Kulkas & Lampu.' },
  repair_kit:   { name: 'Kit Perbaikan', icon: '🧰', type: 'repair', amount: 45, desc: 'Perbaiki bodi & mesin mobil +45%.' },
  medkit:       { name: 'Kotak P3K', icon: '🩹', type: 'med', amount: 55, desc: 'Pulihkan kesehatan +55%.' }
};

const RECIPES = [
  { id: 'cook_steak', name: 'Steak Panggang', icon: '🍖', req: { raw_meat: 1, wood: 1 }, out: { id: 'steak', qty: 1 }, desc: '+58 Lapar, +18 HP' },
  { id: 'cook_fish',  name: 'Ikan Bakar', icon: '🐠', req: { raw_fish: 1, wood: 1 }, out: { id: 'grilled_fish', qty: 1 }, desc: '+52 Lapar, +14 HP' },
  { id: 'cook_soup',  name: 'Sup Jamur', icon: '🍲', req: { mushroom: 2, dirty_water: 1 }, out: { id: 'soup', qty: 1 }, desc: '+44 Lapar, +24 Haus' },
  { id: 'boil_water', name: 'Rebus Air Bersih', icon: '💧', req: { dirty_water: 1, wood: 1 }, out: { id: 'water', qty: 1 }, desc: 'Air aman & segar' },
  { id: 'brew_coffee',name: 'Seduh Kopi', icon: '☕', req: { berry: 1, water: 1 }, out: { id: 'coffee', qty: 1 }, desc: '+45 Stamina' },
  { id: 'craft_rep',  name: 'Rakit Kit Perbaikan', icon: '🧰', req: { scrap: 2 }, out: { id: 'repair_kit', qty: 1 }, desc: 'Perbaiki mobil +45%' },
  { id: 'craft_fuel', name: 'Suling Bio-Fuel', icon: '⛽', req: { wood: 3, berry: 2 }, out: { id: 'fuel_can', qty: 1 }, desc: 'Bensin darurat +35%' }
];

const DECORS = [
  { id: 'fairyLights', name: 'Lampu Hias Malam', icon: '✨', cost: { electronics: 1 }, unlockedDefault: true, desc: 'Lampu LED hangat yang menyala cantik di malam hari.' },
  { id: 'cabinPlant',  name: 'Tanaman & Lentera Kabin', icon: '🪴', cost: {}, unlockedDefault: true, desc: 'Hiasan kaktus & lentera kecil di dashboard.' },
  { id: 'roofRack',    name: 'Rak Atap Petualang', icon: '🧳', cost: { wood: 2, scrap: 2 }, desc: 'Rak kokoh di atap lengkap dengan kotak barang.' },
  { id: 'solarPanel',  name: 'Panel Surya Atap', icon: '☀️', cost: { electronics: 2, scrap: 2 }, desc: 'Mengisi baterai mobil otomatis saat siang hari!' },
  { id: 'awning',      name: 'Tenda Samping (Awning)', icon: '⛱️', cost: { wood: 2, scrap: 1 }, desc: 'Kanopi berkemah bergaris di sisi kanan mobil.' },
  { id: 'surfboard',   name: 'Papan Selancar Atap', icon: '🏄', cost: { wood: 3 }, desc: 'Gaya anak pantai di atas atap camper van.' },
  { id: 'bullbar',     name: 'Bumper Off-Road + Lampu Kabut', icon: '🛡️', cost: { scrap: 3, electronics: 1 }, desc: 'Mengurangi kerusakan tabrakan hingga 50%.' },
  { id: 'spareTire',   name: 'Ban Cadangan & Tangga Belakang', icon: '🛞', cost: { scrap: 2 }, desc: 'Tampilan ekspedisi tangguh di pintu belakang.' }
];

const UPGRADES = [
  { id: 'fridgeLevel', name: 'Kapasitas Kulkas', icon: '🧊', max: 3, costs: [null, { scrap: 2, electronics: 1 }, { scrap: 3, electronics: 2 }], desc: lv => `Slot saat ini: ${4 + lv * 4} slot (Maks Lv 3)` },
  { id: 'tankLevel',   name: 'Kapasitas Tangki Bensin', icon: '⛽', max: 3, costs: [null, { scrap: 3 }, { scrap: 4, electronics: 1 }], desc: lv => `Kapasitas: ${100 + (lv - 1) * 40}%` },
  { id: 'batLevel',    name: 'Kapasitas Baterai Mobil', icon: '🔋', max: 3, costs: [null, { electronics: 2, scrap: 1 }, { electronics: 3, scrap: 2 }], desc: lv => `Kapasitas: ${100 + (lv - 1) * 50}%` }
];

const PRIMARY_SWATCHES = ['#e85d3f', '#2563eb', '#16a34a', '#eab308', '#f8fafc', '#1e293b', '#8b5cf6', '#0d9488', '#db2777'];
const ACCENT_SWATCHES  = ['#f8fafc', '#fef3c7', '#1e293b', '#f59e0b', '#38bdf8', '#ef4444', '#10b981'];
const LIVERIES = [
  { id: 'none', name: 'Polos' },
  { id: 'stripe', name: 'Garis Retro' },
  { id: 'wave', name: 'Ombak' },
  { id: 'mountain', name: 'Gunung' }
];

const BIOME_META = {
  hutan:  { label: '🌲 Hutan Rindang', temp: 24, fog: 0x9ec5ab },
  gurun:  { label: '🌵 Gurun Pasir', temp: 39, fog: 0xe8cfa0 },
  pantai: { label: '🏖️ Pantai Tropis', temp: 30, fog: 0xaad9ea },
  gunung: { label: '🏔️ Pegunungan', temp: 13, fog: 0xbac7d4 },
  desa:   { label: '🏡 Desa Damai', temp: 25, fog: 0xa5cca8 },
  kota:   { label: '🏙️ Kota Lama', temp: 28, fog: 0x9fb0c0 }
};
NOMAD_END;

    // -----------------------------------------------------------------
    // js/audio.js
    // -----------------------------------------------------------------
    $files['js/audio.js'] = <<<'NOMAD_END'
// =========================================================================
// AUDIO SYNTHESIZER (Web Audio API)
// =========================================================================
const AudioSys = {
  ctx: null, enabled: true, engineOsc: null, engineGain: null,
  init() {
    if (this.ctx) return;
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return;
    this.ctx = new AC();
    this.engineOsc = this.ctx.createOscillator();
    this.engineGain = this.ctx.createGain();
    const filter = this.ctx.createBiquadFilter();
    filter.type = 'lowpass';
    filter.frequency.value = 180;
    this.engineOsc.type = 'sawtooth';
    this.engineOsc.frequency.value = 45;
    this.engineGain.gain.value = 0;
    this.engineOsc.connect(filter);
    filter.connect(this.engineGain);
    this.engineGain.connect(this.ctx.destination);
    this.engineOsc.start();
  },
  resume() {
    if (!this.ctx) this.init();
    if (this.ctx && this.ctx.state === 'suspended') this.ctx.resume();
  },
  updateEngine(speedAbs, driving) {
    if (!this.ctx || !this.engineGain) return;
    if (!this.enabled || !driving) {
      this.engineGain.gain.setTargetAtTime(0, this.ctx.currentTime, 0.1);
      return;
    }
    const freq = 42 + Math.min(speedAbs * 3.2, 110);
    this.engineOsc.frequency.setTargetAtTime(freq, this.ctx.currentTime, 0.08);
    this.engineGain.gain.setTargetAtTime(0.035 + Math.min(speedAbs * 0.002, 0.04), this.ctx.currentTime, 0.1);
  },
  playTone(freq, dur = 0.12, type = 'sine', vol = 0.12) {
    if (!this.enabled) return;
    this.resume();
    if (!this.ctx) return;
    const o = this.ctx.createOscillator();
    const g = this.ctx.createGain();
    o.type = type; o.frequency.value = freq;
    g.gain.setValueAtTime(vol, this.ctx.currentTime);
    g.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + dur);
    o.connect(g); g.connect(this.ctx.destination);
    o.start(); o.stop(this.ctx.currentTime + dur);
  },
  horn() {
    if (!this.enabled) return;
    this.playTone(340, 0.18, 'sawtooth', 0.1);
    setTimeout(() => this.playTone(420, 0.25, 'sawtooth', 0.1), 90);
  },
  footstep() { this.playTone(110 + Math.random() * 25, 0.045, 'triangle', 0.04); },
  pickup()   { this.playTone(520, 0.08, 'triangle', 0.1); setTimeout(() => this.playTone(760, 0.12, 'triangle', 0.1), 60); },
  cook()     { this.playTone(300, 0.1, 'sine', 0.1); setTimeout(() => this.playTone(450, 0.1, 'sine', 0.1), 80); setTimeout(() => this.playTone(620, 0.15, 'triangle', 0.1), 160); },
  bump()     { this.playTone(85, 0.18, 'square', 0.14); }
};

function toggleAudio() {
  AudioSys.enabled = !AudioSys.enabled;
  document.getElementById('btn-audio').textContent = AudioSys.enabled ? '🔊 Audio' : '🔇 Bisukan';
}
NOMAD_END;

    // -----------------------------------------------------------------
    // js/state.js
    // -----------------------------------------------------------------
    $files['js/state.js'] = <<<'NOMAD_END'
// =========================================================================
// GAME STATE, LOCALSTORAGE & HELPER INVENTORI
// =========================================================================
const SAVE_KEY = 'nomad_vanlife_survival_v1';

function createDefaultState() {
  return {
    worldSeed: Math.floor(Math.random() * 999999) + 1,
    day: 1,
    timeOfDay: 0.34,
    player: {
      hp: 100, hunger: 92, thirst: 90, stamina: 100,
      x: 6, y: 3, z: 0, yaw: 0,
      inVan: true
    },
    van: {
      x: 0, y: 3, z: 0, yaw: 0, speed: 0,
      fuel: 88, battery: 95, hp: 100,
      headlights: false,
      primaryColor: '#e85d3f',
      accentColor: '#fef3c7',
      livery: 'stripe',
      unlockedDecor: { fairyLights: true, cabinPlant: true },
      activeDecor: {
        fairyLights: true, cabinPlant: true,
        roofRack: false, solarPanel: false, awning: false,
        surfboard: false, bullbar: false, spareTire: false
      },
      upgrades: { fridgeLevel: 1, tankLevel: 1, batLevel: 1 }
    },
    fridge: {
      on: true,
      items: [
        { id: 'water', qty: 2, fresh: 100 },
        { id: 'soda', qty: 2, fresh: 100 },
        { id: 'bread', qty: 1, fresh: 95 },
        { id: 'raw_meat', qty: 1, fresh: 95 }
      ]
    },
    inventory: [
      { id: 'canned_food', qty: 2, fresh: 100 },
      { id: 'fuel_can', qty: 1, fresh: 100 },
      { id: 'wood', qty: 3, fresh: 100 },
      { id: 'scrap', qty: 2, fresh: 100 },
      { id: 'electronics', qty: 1, fresh: 100 },
      { id: 'berry', qty: 2, fresh: 100 }
    ],
    lootedIds: {},
    distanceDriven: 0
  };
}

let state = loadGameState();

function loadGameState() {
  try {
    const raw = localStorage.getItem(SAVE_KEY);
    if (raw) {
      const parsed = JSON.parse(raw);
      if (parsed && parsed.van && parsed.player) return parsed;
    }
  } catch (e) { console.warn('Gagal memuat save:', e); }
  return createDefaultState();
}

function saveGameState(silent = true) {
  try {
    localStorage.setItem(SAVE_KEY, JSON.stringify(state));
    if (!silent) showToast('💾 Permainan tersimpan di LocalStorage!');
  } catch (e) {
    if (!silent) showToast('⚠️ Gagal menyimpan ke browser.');
  }
}

function manualSave() { saveGameState(false); AudioSys.pickup(); }
function confirmResetWorld() {
  if (confirm('Mulai dunia baru dengan terrain & seed acak? Progress saat ini akan diganti.')) {
    resetWorldNow();
  }
}
function resetWorldNow() {
  localStorage.removeItem(SAVE_KEY);
  location.reload();
}

function getMaxFuel() { return 100 + (state.van.upgrades.tankLevel - 1) * 40; }
function getMaxBat()  { return 100 + (state.van.upgrades.batLevel - 1) * 50; }
function getMaxFridgeSlots() { return 4 + state.van.upgrades.fridgeLevel * 4; }

function addItemToList(list, id, qty, fresh = 100) {
  const existing = list.find(s => s.id === id && Math.abs(s.fresh - fresh) < 12);
  if (existing) existing.qty += qty;
  else list.push({ id, qty, fresh });
}

function countItem(list, id) {
  return list.filter(s => s.id === id).reduce((a, b) => a + b.qty, 0);
}

function consumeItem(list, id, qty) {
  let rem = qty;
  for (let i = list.length - 1; i >= 0 && rem > 0; i--) {
    if (list[i].id === id) {
      const take = Math.min(list[i].qty, rem);
      list[i].qty -= take;
      rem -= take;
      if (list[i].qty <= 0) list.splice(i, 1);
    }
  }
}
NOMAD_END;

    // -----------------------------------------------------------------
    // js/scene.js
    // -----------------------------------------------------------------
    $files['js/scene.js'] = <<<'NOMAD_END'
// =========================================================================
// THREE.JS SCENE, KAMERA, CAHAYA & GLOBAL INPUT STATE
// =========================================================================
const keys = {};
let camAngle = Math.PI;
let camPitch = 0.32;
let camDistMode = 0;
let isDragging = false;
let activeModal = null;

const canvas = document.getElementById('game-canvas');
const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, powerPreference: 'high-performance' });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.setSize(window.innerWidth, window.innerHeight);
renderer.shadowMap.enabled = true;
renderer.shadowMap.type = THREE.PCFSoftShadowMap;

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x87ceeb);
scene.fog = new THREE.FogExp2(0x9ec5ab, 0.0068);

const camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.2, 420);

const hemiLight = new THREE.HemisphereLight(0xdbeafe, 0x3f4d36, 0.75);
scene.add(hemiLight);

const sunLight = new THREE.DirectionalLight(0xfff5e0, 1.1);
sunLight.castShadow = true;
sunLight.shadow.mapSize.width = 1024;
sunLight.shadow.mapSize.height = 1024;
sunLight.shadow.camera.near = 5;
sunLight.shadow.camera.far = 160;
const dCam = 48;
sunLight.shadow.camera.left = -dCam;
sunLight.shadow.camera.right = dCam;
sunLight.shadow.camera.top = dCam;
sunLight.shadow.camera.bottom = -dCam;
scene.add(sunLight);
scene.add(sunLight.target);

// Shared Materials
const MAT_WOOD   = new THREE.MeshStandardMaterial({ color: 0x7c4a2d, roughness: 0.9 });
const MAT_LEAF   = new THREE.MeshStandardMaterial({ color: 0x2e7d32, roughness: 0.85 });
const MAT_PINE   = new THREE.MeshStandardMaterial({ color: 0x1b5e20, roughness: 0.85 });
const MAT_CACTUS = new THREE.MeshStandardMaterial({ color: 0x3f8f29, roughness: 0.8 });
const MAT_ROCK   = new THREE.MeshStandardMaterial({ color: 0x7c8594, roughness: 0.92, flatShading: true });
const MAT_WALL   = new THREE.MeshStandardMaterial({ color: 0xd6d3d1, roughness: 0.85 });
const MAT_CITY   = new THREE.MeshStandardMaterial({ color: 0x64748b, roughness: 0.8 });
const MAT_ROOF   = new THREE.MeshStandardMaterial({ color: 0x9a3412, roughness: 0.8 });
const MAT_WATER  = new THREE.MeshStandardMaterial({ color: 0x0ea5e9, transparent: true, opacity: 0.72, roughness: 0.15, metalness: 0.2 });
NOMAD_END;

    // -----------------------------------------------------------------
    // js/world.js
    // -----------------------------------------------------------------
    $files['js/world.js'] = <<<'NOMAD_END'
// =========================================================================
// GENERATOR TERRAIN PROSEDURAL & CHUNK STREAMING (ALA MINECRAFT)
// =========================================================================
const CHUNK_SIZE = 48;
const CHUNK_SEG = 16;
const RENDER_RADIUS = 3;
const WATER_LEVEL = 0.0;
const SECTOR_SIZE = 144;

const WorldGen = {
  hash2(ix, iz, salt = 0) {
    let n = Math.imul(ix, 374761393) + Math.imul(iz, 668265263) + Math.imul(state.worldSeed + salt, 1442695041);
    n = (n ^ (n >>> 13)) * 1274126177;
    return ((n ^ (n >>> 16)) >>> 0) / 4294967296;
  },
  smoothstep(t) { return t * t * (3 - 2 * t); },
  lerp(a, b, t) { return a + (b - a) * t; },
  noise2(x, z, salt = 0) {
    const ix = Math.floor(x), iz = Math.floor(z);
    const fx = this.smoothstep(x - ix), fz = this.smoothstep(z - iz);
    const v00 = this.hash2(ix, iz, salt);
    const v10 = this.hash2(ix + 1, iz, salt);
    const v01 = this.hash2(ix, iz + 1, salt);
    const v11 = this.hash2(ix + 1, iz + 1, salt);
    return this.lerp(this.lerp(v00, v10, fx), this.lerp(v01, v11, fx), fz);
  },
  fbm(x, z, octaves = 4, salt = 0) {
    let val = 0, amp = 0.5, freq = 1, maxAmp = 0;
    for (let i = 0; i < octaves; i++) {
      val += this.noise2(x * freq, z * freq, salt + i * 31) * amp;
      maxAmp += amp;
      amp *= 0.5;
      freq *= 2.05;
    }
    return val / maxAmp;
  },
  getRoadX(z) {
    return Math.sin(z * 0.0085 + state.worldSeed * 0.01) * 42 + Math.cos(z * 0.0032) * 24;
  },
  getSectorInfo(x, z) {
    const sx = Math.floor((x + SECTOR_SIZE * 0.5) / SECTOR_SIZE);
    const sz = Math.floor((z + SECTOR_SIZE * 0.5) / SECTOR_SIZE);
    if (sx === 0 && sz === 0) return { type: 'none', cx: 0, cz: 0 };
    const cx = sx * SECTOR_SIZE;
    const cz = sz * SECTOR_SIZE;
    const r = this.hash2(sx, sz, 999);
    const baseH = this.getRawHeight(cx, cz);
    if (baseH < 2.3 || baseH > 14) return { type: 'none', cx, cz };
    if (r < 0.22) return { type: 'kota', cx, cz, sx, sz, baseH };
    if (r < 0.45) return { type: 'desa', cx, cz, sx, sz, baseH };
    return { type: 'none', cx, cz };
  },
  getRawHeight(x, z) {
    const cont = this.fbm(x * 0.0038, z * 0.0038, 4, 11);
    const detail = this.fbm(x * 0.018, z * 0.018, 3, 55);
    const ridgeRaw = Math.abs(this.noise2(x * 0.0075, z * 0.0075, 101) * 2 - 1);
    const mountainMask = Math.max(0, (cont - 0.52) * 2.2);
    return (cont - 0.36) * 22 + (detail - 0.5) * 4.5 + (1 - ridgeRaw) * mountainMask * 16;
  },
  getTerrainHeight(x, z) {
    let h = this.getRawHeight(x, z);

    const sec = this.getSectorInfo(x, z);
    if (sec.type !== 'none') {
      const d = Math.hypot(x - sec.cx, z - sec.cz);
      if (d < 44) {
        const blend = this.smoothstep(Math.max(0, Math.min(1, (44 - d) / 14)));
        h = this.lerp(h, sec.baseH, blend);
      }
    }

    const rx = this.getRoadX(z);
    const dr = Math.abs(x - rx);
    if (dr < 12) {
      const roadBase = Math.max(1.3, this.getRawHeight(rx, z));
      const rPrev = Math.max(1.3, this.getRawHeight(this.getRoadX(z - 12), z - 12));
      const rNext = Math.max(1.3, this.getRawHeight(this.getRoadX(z + 12), z + 12));
      const smoothRoadH = (roadBase * 2 + rPrev + rNext) * 0.25;
      const blend = this.smoothstep(Math.max(0, Math.min(1, (12 - dr) / 7)));
      h = this.lerp(h, smoothRoadH, blend);
    }
    return h;
  },
  getBiomeAt(x, z) {
    const dr = Math.abs(x - this.getRoadX(z));
    const sec = this.getSectorInfo(x, z);
    if (sec.type !== 'none' && Math.hypot(x - sec.cx, z - sec.cz) < 36) return sec.type;
    const h = this.getTerrainHeight(x, z);
    if (h < 1.85 && dr > 5) return 'pantai';
    if (h > 13.5) return 'gunung';
    const temp = this.fbm(x * 0.0042 + 120, z * 0.0042 - 80, 2, 202);
    const moist = this.fbm(x * 0.0042 - 40, z * 0.0042 + 90, 2, 303);
    if (temp > 0.53 && moist < 0.54) return 'gurun';
    return 'hutan';
  }
};

const activeChunks = new Map();
let worldInteractables = [];

function makePropMesh(kind) {
  const g = new THREE.Group();
  if (kind === 'pine') {
    const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.32, 2.2, 6), MAT_WOOD);
    trunk.position.y = 1.1; trunk.castShadow = true;
    const c1 = new THREE.Mesh(new THREE.ConeGeometry(1.7, 3.2, 7), MAT_PINE);
    c1.position.y = 3.1; c1.castShadow = true;
    const c2 = new THREE.Mesh(new THREE.ConeGeometry(1.2, 2.4, 7), MAT_PINE);
    c2.position.y = 4.6; c2.castShadow = true;
    g.add(trunk, c1, c2);
  } else if (kind === 'oak') {
    const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.38, 2.5, 6), MAT_WOOD);
    trunk.position.y = 1.25; trunk.castShadow = true;
    const top = new THREE.Mesh(new THREE.DodecahedronGeometry(1.75), MAT_LEAF);
    top.position.y = 3.2; top.castShadow = true;
    g.add(trunk, top);
  } else if (kind === 'palm') {
    const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.3, 4.2, 6), MAT_WOOD);
    trunk.position.y = 2.1; trunk.rotation.z = 0.1; trunk.castShadow = true;
    g.add(trunk);
    for (let i = 0; i < 5; i++) {
      const frond = new THREE.Mesh(new THREE.BoxGeometry(0.45, 0.08, 1.9), MAT_LEAF);
      frond.position.set(-0.2, 4.2, 0);
      frond.rotation.y = (i / 5) * Math.PI * 2;
      frond.rotation.x = 0.35;
      g.add(frond);
    }
  } else if (kind === 'cactus') {
    const stem = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.26, 2.4, 7), MAT_CACTUS);
    stem.position.y = 1.2; stem.castShadow = true;
    const arm = new THREE.Mesh(new THREE.CylinderGeometry(0.15, 0.15, 0.9, 6), MAT_CACTUS);
    arm.position.set(0.38, 1.5, 0); arm.rotation.z = -0.5;
    g.add(stem, arm);
  } else if (kind === 'rock') {
    const r = new THREE.Mesh(new THREE.DodecahedronGeometry(1.1), MAT_ROCK);
    r.scale.set(1.2, 0.75, 1.0); r.position.y = 0.55; r.castShadow = true;
    g.add(r);
  } else if (kind === 'cottage') {
    const body = new THREE.Mesh(new THREE.BoxGeometry(5.5, 3.2, 4.8), MAT_WALL);
    body.position.y = 1.6; body.castShadow = true; body.receiveShadow = true;
    const roof = new THREE.Mesh(new THREE.ConeGeometry(4.4, 2.4, 4), MAT_ROOF);
    roof.position.y = 4.4; roof.rotation.y = Math.PI / 4; roof.castShadow = true;
    const door = new THREE.Mesh(new THREE.BoxGeometry(1.1, 2.0, 0.15), MAT_WOOD);
    door.position.set(0, 1.0, 2.42);
    g.add(body, roof, door);
  } else if (kind === 'building') {
    const h = 7 + Math.random() * 6;
    const body = new THREE.Mesh(new THREE.BoxGeometry(7.2, h, 7.2), MAT_CITY);
    body.position.y = h / 2; body.castShadow = true; body.receiveShadow = true;
    g.add(body);
    for (let wy = 2; wy < h - 1; wy += 2.2) {
      const win = new THREE.Mesh(new THREE.BoxGeometry(7.3, 0.9, 5.2), new THREE.MeshStandardMaterial({ color: 0xfef08a, emissive: 0xf59e0b, emissiveIntensity: 0.2 }));
      win.position.y = wy;
      g.add(win);
    }
  } else if (kind === 'gas_station') {
    const canopy = new THREE.Mesh(new THREE.BoxGeometry(8, 0.45, 5.5), new THREE.MeshStandardMaterial({ color: 0xef4444 }));
    canopy.position.y = 4.2; canopy.castShadow = true;
    const p1 = new THREE.Mesh(new THREE.CylinderGeometry(0.2, 0.2, 4.2), MAT_WALL);
    p1.position.set(-3.2, 2.1, 0);
    const p2 = p1.clone(); p2.position.set(3.2, 2.1, 0);
    const pump = new THREE.Mesh(new THREE.BoxGeometry(1.1, 1.8, 0.7), new THREE.MeshStandardMaterial({ color: 0xf59e0b }));
    pump.position.set(0, 0.9, 0);
    g.add(canopy, p1, p2, pump);
  } else if (kind === 'well') {
    const base = new THREE.Mesh(new THREE.CylinderGeometry(1.1, 1.2, 1.0, 10), MAT_ROCK);
    base.position.y = 0.5;
    const water = new THREE.Mesh(new THREE.CylinderGeometry(0.85, 0.85, 0.9, 10), MAT_WATER);
    water.position.y = 0.52;
    const roof = new THREE.Mesh(new THREE.ConeGeometry(1.5, 1.1, 4), MAT_WOOD);
    roof.position.y = 2.6; roof.rotation.y = Math.PI / 4;
    g.add(base, water, roof);
  }
  return g;
}

function makeLootMesh(lootType) {
  const g = new THREE.Group();
  if (lootType === 'berry') {
    const bush = new THREE.Mesh(new THREE.DodecahedronGeometry(0.65), MAT_LEAF);
    bush.position.y = 0.45;
    const b1 = new THREE.Mesh(new THREE.SphereGeometry(0.12), new THREE.MeshStandardMaterial({ color: 0x6366f1 }));
    b1.position.set(0.35, 0.65, 0.3);
    const b2 = b1.clone(); b2.position.set(-0.3, 0.55, 0.35);
    g.add(bush, b1, b2);
  } else if (lootType === 'mushroom') {
    const stem = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.14, 0.35, 6), new THREE.MeshStandardMaterial({ color: 0xfef3c7 }));
    stem.position.y = 0.18;
    const cap = new THREE.Mesh(new THREE.ConeGeometry(0.38, 0.28, 7), new THREE.MeshStandardMaterial({ color: 0xdc2626 }));
    cap.position.y = 0.42;
    g.add(stem, cap);
  } else if (lootType === 'fuel_can') {
    const can = new THREE.Mesh(new THREE.BoxGeometry(0.45, 0.62, 0.28), new THREE.MeshStandardMaterial({ color: 0xef4444 }));
    can.position.y = 0.31;
    g.add(can);
  } else if (lootType === 'wood') {
    const log = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.18, 1.1, 6), MAT_WOOD);
    log.rotation.z = Math.PI / 2; log.position.y = 0.18;
    g.add(log);
  } else if (lootType === 'crate' || lootType === 'city_crate') {
    const col = lootType === 'city_crate' ? 0x38bdf8 : 0xb45309;
    const box = new THREE.Mesh(new THREE.BoxGeometry(0.75, 0.65, 0.75), new THREE.MeshStandardMaterial({ color: col, roughness: 0.7 }));
    box.position.y = 0.33; box.castShadow = true;
    g.add(box);
  } else if (lootType === 'scrap') {
    const m = new THREE.Mesh(new THREE.OctahedronGeometry(0.42), new THREE.MeshStandardMaterial({ color: 0x94a3b8, metalness: 0.7, roughness: 0.3 }));
    m.position.y = 0.35;
    g.add(m);
  }
  return g;
}

function createChunk(cx, cz) {
  const key = `${cx},${cz}`;
  const group = new THREE.Group();
  const ox = cx * CHUNK_SIZE;
  const oz = cz * CHUNK_SIZE;

  const geo = new THREE.PlaneGeometry(CHUNK_SIZE, CHUNK_SIZE, CHUNK_SEG, CHUNK_SEG);
  geo.rotateX(-Math.PI / 2);
  const pos = geo.attributes.position;
  const colors = new Float32Array(pos.count * 3);
  let hasWater = false;

  const cGrass = new THREE.Color(0x3f8f45);
  const cSand = new THREE.Color(0xe5c16c);
  const cBeach = new THREE.Color(0xf1d99a);
  const cRock = new THREE.Color(0x7c8594);
  const cSnow = new THREE.Color(0xf1f5f9);
  const cRoad = new THREE.Color(0x3d4148);
  const cRoadLine = new THREE.Color(0xfbbf24);
  const cCityGround = new THREE.Color(0x525866);
  const tmpCol = new THREE.Color();

  for (let i = 0; i < pos.count; i++) {
    const lx = pos.getX(i), lz = pos.getZ(i);
    const wx = ox + lx, wz = oz + lz;
    const y = WorldGen.getTerrainHeight(wx, wz);
    pos.setY(i, y);
    if (y < WATER_LEVEL + 0.2) hasWater = true;

    const biome = WorldGen.getBiomeAt(wx, wz);
    const dr = Math.abs(wx - WorldGen.getRoadX(wz));

    if (dr < 3.6) tmpCol.copy(dr < 0.35 && Math.abs(wz % 10) < 4.5 ? cRoadLine : cRoad);
    else if (biome === 'kota') tmpCol.copy(cCityGround);
    else if (biome === 'gurun') tmpCol.copy(cSand);
    else if (biome === 'pantai') tmpCol.copy(cBeach);
    else if (biome === 'gunung') tmpCol.copy(y > 19.5 ? cSnow : cRock);
    else {
      tmpCol.copy(cGrass);
      const n = WorldGen.noise2(wx * 0.1, wz * 0.1, 77);
      tmpCol.offsetHSL(0, 0, (n - 0.5) * 0.06);
    }

    colors[i * 3] = tmpCol.r;
    colors[i * 3 + 1] = tmpCol.g;
    colors[i * 3 + 2] = tmpCol.b;
  }

  geo.setAttribute('color', new THREE.BufferAttribute(colors, 3));
  geo.computeVertexNormals();

  const terrainMat = new THREE.MeshStandardMaterial({ vertexColors: true, roughness: 0.92, metalness: 0.02, flatShading: true });
  const terrainMesh = new THREE.Mesh(geo, terrainMat);
  terrainMesh.position.set(ox, 0, oz);
  terrainMesh.receiveShadow = true;
  group.add(terrainMesh);

  if (hasWater) {
    const wGeo = new THREE.PlaneGeometry(CHUNK_SIZE, CHUNK_SIZE);
    wGeo.rotateX(-Math.PI / 2);
    const wMesh = new THREE.Mesh(wGeo, MAT_WATER);
    wMesh.position.set(ox, WATER_LEVEL, oz);
    group.add(wMesh);
  }

  const colliders = [];
  const interactables = [];

  const sec = WorldGen.getSectorInfo(ox, oz);
  if (sec.type !== 'none' && Math.abs(sec.cx - ox) <= CHUNK_SIZE * 0.5 && Math.abs(sec.cz - oz) <= CHUNK_SIZE * 0.5) {
    if (sec.type === 'kota') {
      const gsOffset = sec.cx < WorldGen.getRoadX(sec.cz) ? 10 : -10;
      const gx = sec.cx + gsOffset, gz = sec.cz - 8;
      const gy = WorldGen.getTerrainHeight(gx, gz);
      const gs = makePropMesh('gas_station');
      gs.position.set(gx, gy, gz);
      group.add(gs);
      interactables.push({
        uid: `pump_${sec.sx}_${sec.sz}`, kind: 'gas_pump', label: '⛽ Pom Bensin (Isi Penuh Gratis / Cadangan)',
        x: gx, y: gy, z: gz, radius: 5.5
      });

      [[-14, 12], [14, 12], [-15, -14], [16, -15]].forEach(([dx, dz]) => {
        const bx = sec.cx + dx, bz = sec.cz + dz;
        if (Math.abs(bx - WorldGen.getRoadX(bz)) < 6) return;
        const by = WorldGen.getTerrainHeight(bx, bz);
        const bld = makePropMesh('building');
        bld.position.set(bx, by, bz);
        group.add(bld);
        colliders.push({ x: bx, z: bz, r: 4.0 });
      });
    } else if (sec.type === 'desa') {
      const wx = sec.cx + 8, wz = sec.cz + 4;
      const wy = WorldGen.getTerrainHeight(wx, wz);
      const well = makePropMesh('well');
      well.position.set(wx, wy, wz);
      group.add(well);
      colliders.push({ x: wx, z: wz, r: 1.3 });
      interactables.push({
        uid: `well_${sec.sx}_${sec.sz}`, kind: 'well', label: '💧 Sumur Desa (Ambil Air Bersih)',
        x: wx, y: wy, z: wz, radius: 4.0
      });

      [[-12, -8], [13, -10], [-12, 12]].forEach(([dx, dz]) => {
        const hx = sec.cx + dx, hz = sec.cz + dz;
        if (Math.abs(hx - WorldGen.getRoadX(hz)) < 6) return;
        const hy = WorldGen.getTerrainHeight(hx, hz);
        const house = makePropMesh('cottage');
        house.position.set(hx, hy, hz);
        house.rotation.y = Math.atan2(-dx, -dz);
        group.add(house);
        colliders.push({ x: hx, z: hz, r: 3.2 });
      });
    }
  }

  for (let i = 0; i < 16; i++) {
    const rx = ox + (WorldGen.hash2(cx, cz, i * 7 + 1) - 0.5) * (CHUNK_SIZE - 4);
    const rz = oz + (WorldGen.hash2(cx, cz, i * 7 + 2) - 0.5) * (CHUNK_SIZE - 4);
    if (Math.abs(rx - WorldGen.getRoadX(rz)) < 5.2) continue;
    const ry = WorldGen.getTerrainHeight(rx, rz);
    if (ry < WATER_LEVEL + 0.15) continue;

    const biome = WorldGen.getBiomeAt(rx, rz);
    const rKind = WorldGen.hash2(cx, cz, i * 7 + 3);
    const uid = `${key}_${i}`;

    if (i < 10) {
      if (biome === 'kota' || biome === 'desa') continue;
      let propKind = null;
      if (biome === 'hutan') propKind = rKind < 0.55 ? 'pine' : (rKind < 0.9 ? 'oak' : 'rock');
      else if (biome === 'gurun') propKind = rKind < 0.6 ? 'cactus' : 'rock';
      else if (biome === 'pantai') propKind = rKind < 0.55 ? 'palm' : 'rock';
      else if (biome === 'gunung') propKind = rKind < 0.45 ? 'pine' : 'rock';

      if (propKind) {
        const m = makePropMesh(propKind);
        m.position.set(rx, ry, rz);
        m.rotation.y = rKind * Math.PI * 2;
        group.add(m);
        colliders.push({ x: rx, z: rz, r: propKind === 'rock' ? 1.1 : 0.7 });
      }
    } else if (!state.lootedIds[uid]) {
      let lootType = null;
      let dropPool = [];
      if (biome === 'hutan') {
        lootType = rKind < 0.4 ? 'berry' : (rKind < 0.7 ? 'mushroom' : 'wood');
        dropPool = lootType === 'berry' ? [{ id: 'berry', qty: 2 }] : (lootType === 'mushroom' ? [{ id: 'mushroom', qty: 2 }] : [{ id: 'wood', qty: 2 }]);
      } else if (biome === 'gurun') {
        lootType = rKind < 0.45 ? 'crate' : (rKind < 0.75 ? 'fuel_can' : 'scrap');
        dropPool = lootType === 'fuel_can' ? [{ id: 'fuel_can', qty: 1 }] : (lootType === 'scrap' ? [{ id: 'scrap', qty: 2 }] : [{ id: 'canned_food', qty: 1 }, { id: 'water', qty: 1 }]);
      } else if (biome === 'pantai') {
        lootType = rKind < 0.5 ? 'crate' : 'wood';
        dropPool = lootType === 'wood' ? [{ id: 'wood', qty: 2 }] : [{ id: 'raw_fish', qty: 2 }, { id: 'soda', qty: 1 }];
      } else if (biome === 'gunung') {
        lootType = rKind < 0.5 ? 'scrap' : 'crate';
        dropPool = lootType === 'scrap' ? [{ id: 'scrap', qty: 2 }, { id: 'electronics', qty: 1 }] : [{ id: 'raw_meat', qty: 1 }, { id: 'wood', qty: 2 }];
      } else if (biome === 'desa') {
        lootType = 'crate';
        dropPool = [{ id: 'bread', qty: 1 }, { id: 'raw_meat', qty: 1 }, { id: 'wood', qty: 1 }];
      } else if (biome === 'kota') {
        lootType = rKind < 0.35 ? 'fuel_can' : 'city_crate';
        dropPool = lootType === 'fuel_can' ? [{ id: 'fuel_can', qty: 1 }] : [{ id: 'canned_food', qty: 1 }, { id: 'soda', qty: 1 }, { id: 'electronics', qty: 1 }];
      }

      if (lootType) {
        const lm = makeLootMesh(lootType);
        lm.position.set(rx, ry, rz);
        group.add(lm);
        const names = { berry: 'Semak Beri', mushroom: 'Jamur Liar', wood: 'Kayu Bakar', fuel_can: 'Jerigen Bensin', scrap: 'Besi Tua', crate: 'Peti Bekal', city_crate: 'Kotak Minimarket' };
        interactables.push({
          uid, kind: 'loot', label: `✋ Ambil ${names[lootType] || 'Barang'}`,
          x: rx, y: ry, z: rz, radius: 2.8, mesh: lm, drops: dropPool
        });
      }
    }
  }

  scene.add(group);
  activeChunks.set(key, { group, colliders, interactables });
}

function updateChunks() {
  const px = state.player.inVan ? state.van.x : state.player.x;
  const pz = state.player.inVan ? state.van.z : state.player.z;
  const curCX = Math.round(px / CHUNK_SIZE);
  const curCZ = Math.round(pz / CHUNK_SIZE);

  const needed = new Set();
  for (let dx = -RENDER_RADIUS; dx <= RENDER_RADIUS; dx++) {
    for (let dz = -RENDER_RADIUS; dz <= RENDER_RADIUS; dz++) {
      const key = `${curCX + dx},${curCZ + dz}`;
      needed.add(key);
      if (!activeChunks.has(key)) createChunk(curCX + dx, curCZ + dz);
    }
  }

  for (const [key, ch] of activeChunks.entries()) {
    if (!needed.has(key)) {
      scene.remove(ch.group);
      ch.group.traverse(obj => { if (obj.geometry && obj.geometry.dispose) obj.geometry.dispose(); });
      activeChunks.delete(key);
    }
  }

  worldInteractables = [];
  for (const ch of activeChunks.values()) {
    for (const it of ch.interactables) worldInteractables.push(it);
  }
}

function resolveCollisions(x, z, radius) {
  let rx = x, rz = z, hit = false;
  for (const ch of activeChunks.values()) {
    for (const c of ch.colliders) {
      const dx = rx - c.x, dz = rz - c.z;
      const distSq = dx * dx + dz * dz;
      const minD = radius + c.r;
      if (distSq < minD * minD && distSq > 0.0001) {
        const dist = Math.sqrt(distSq);
        const push = minD - dist;
        rx += (dx / dist) * push;
        rz += (dz / dist) * push;
        hit = true;
      }
    }
  }
  return { x: rx, z: rz, hit };
}
NOMAD_END;

    // -----------------------------------------------------------------
    // js/van.js
    // -----------------------------------------------------------------
    $files['js/van.js'] = <<<'NOMAD_END'
// =========================================================================
// CAMPER VAN 3D MODEL, DEKORASI & FISIKA BERKENDARA
// =========================================================================
const vanGroup = new THREE.Group();
const vanBodyTilt = new THREE.Group();
vanGroup.add(vanBodyTilt);
scene.add(vanGroup);

const vanMaterials = {
  primary:   new THREE.MeshStandardMaterial({ color: state.van.primaryColor, roughness: 0.4, metalness: 0.15 }),
  accent:    new THREE.MeshStandardMaterial({ color: state.van.accentColor, roughness: 0.45 }),
  glass:     new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.1, metalness: 0.5, transparent: true, opacity: 0.72 }),
  chrome:    new THREE.MeshStandardMaterial({ color: 0xcbd5e1, roughness: 0.25, metalness: 0.8 }),
  tire:      new THREE.MeshStandardMaterial({ color: 0x18181b, roughness: 0.9 }),
  headlight: new THREE.MeshStandardMaterial({ color: 0xfef08a, emissive: 0xfef08a, emissiveIntensity: 0.1 }),
  taillight: new THREE.MeshStandardMaterial({ color: 0xdc2626, emissive: 0xdc2626, emissiveIntensity: 0.3 }),
  fairy:     new THREE.MeshStandardMaterial({ color: 0xfde047, emissive: 0xfacc15, emissiveIntensity: 1.5 })
};

let vanWheels = [];
let vanDecorMeshes = {};
let vanHeadLightObj, vanInteriorLight, vanDriverMesh;
let liveryMeshLeft, liveryMeshRight;
let steerVis = 0;

function buildCamperVan() {
  while (vanBodyTilt.children.length) vanBodyTilt.remove(vanBodyTilt.children[0]);
  vanWheels = [];

  // Bodi Bawah & Atas
  const lowerBody = new THREE.Mesh(new THREE.BoxGeometry(2.2, 1.05, 4.7), vanMaterials.primary);
  lowerBody.position.set(0, 0.95, 0);
  lowerBody.castShadow = true; lowerBody.receiveShadow = true;
  vanBodyTilt.add(lowerBody);

  const upperBody = new THREE.Mesh(new THREE.BoxGeometry(2.12, 0.88, 4.3), vanMaterials.accent);
  upperBody.position.set(0, 1.88, -0.1);
  upperBody.castShadow = true; upperBody.receiveShadow = true;
  vanBodyTilt.add(upperBody);

  // Siluet Pengemudi di dalam Kabin (Hilang saat orangnya turun!)
  vanDriverMesh = new THREE.Group();
  const drvTorso = new THREE.Mesh(new THREE.BoxGeometry(0.45, 0.42, 0.28), new THREE.MeshStandardMaterial({ color: 0xd97706 }));
  drvTorso.position.set(0, 0, 0);
  const drvHead = new THREE.Mesh(new THREE.BoxGeometry(0.32, 0.32, 0.32), new THREE.MeshStandardMaterial({ color: 0xfdba74 }));
  drvHead.position.set(0, 0.36, 0);
  const drvHat = new THREE.Mesh(new THREE.BoxGeometry(0.38, 0.1, 0.4), new THREE.MeshStandardMaterial({ color: 0x78350f }));
  drvHat.position.set(0, 0.54, 0.02);
  vanDriverMesh.add(drvTorso, drvHead, drvHat);
  vanDriverMesh.position.set(0.45, 1.58, 1.35);
  vanBodyTilt.add(vanDriverMesh);

  // Kaca Depan & Samping
  const windshield = new THREE.Mesh(new THREE.BoxGeometry(1.94, 0.65, 0.12), vanMaterials.glass);
  windshield.position.set(0, 1.82, 2.04);
  windshield.rotation.x = -0.18;
  vanBodyTilt.add(windshield);

  const frontSideWin = new THREE.Mesh(new THREE.BoxGeometry(2.16, 0.5, 1.25), vanMaterials.glass);
  frontSideWin.position.set(0, 1.84, 1.2);
  vanBodyTilt.add(frontSideWin);

  const midSideWin = new THREE.Mesh(new THREE.BoxGeometry(2.16, 0.46, 1.5), vanMaterials.glass);
  midSideWin.position.set(0, 1.84, -0.55);
  vanBodyTilt.add(midSideWin);

  // Livery Samping
  const livMat = new THREE.MeshStandardMaterial({ transparent: true, roughness: 0.4 });
  const livGeo = new THREE.PlaneGeometry(4.4, 0.85);
  liveryMeshRight = new THREE.Mesh(livGeo, livMat);
  liveryMeshRight.position.set(1.11, 0.98, 0);
  liveryMeshRight.rotation.y = Math.PI / 2;
  vanBodyTilt.add(liveryMeshRight);

  liveryMeshLeft = new THREE.Mesh(livGeo, livMat);
  liveryMeshLeft.position.set(-1.11, 0.98, 0);
  liveryMeshLeft.rotation.y = -Math.PI / 2;
  vanBodyTilt.add(liveryMeshLeft);
  updateVanLiveryTexture();

  // Bumper & Grille
  const fBumper = new THREE.Mesh(new THREE.BoxGeometry(2.3, 0.24, 0.25), vanMaterials.chrome);
  fBumper.position.set(0, 0.48, 2.38);
  const rBumper = new THREE.Mesh(new THREE.BoxGeometry(2.3, 0.24, 0.25), vanMaterials.chrome);
  rBumper.position.set(0, 0.48, -2.38);
  const grille = new THREE.Mesh(new THREE.BoxGeometry(1.1, 0.32, 0.08), vanMaterials.chrome);
  grille.position.set(0, 0.92, 2.35);
  vanBodyTilt.add(fBumper, rBumper, grille);

  // Lampu Depan & Belakang
  [-0.78, 0.78].forEach(x => {
    const hl = new THREE.Mesh(new THREE.CylinderGeometry(0.16, 0.16, 0.1, 12), vanMaterials.headlight);
    hl.rotation.x = Math.PI / 2;
    hl.position.set(x, 0.95, 2.35);
    vanBodyTilt.add(hl);

    const tl = new THREE.Mesh(new THREE.BoxGeometry(0.28, 0.22, 0.08), vanMaterials.taillight);
    tl.position.set(x, 0.98, -2.35);
    vanBodyTilt.add(tl);
  });

  vanHeadLightObj = new THREE.SpotLight(0xfffbeb, 0, 48, Math.PI / 4.5, 0.45, 1.2);
  vanHeadLightObj.position.set(0, 1.15, 2.35);
  const hlTarget = new THREE.Object3D();
  hlTarget.position.set(0, 0.3, 18);
  vanBodyTilt.add(hlTarget);
  vanHeadLightObj.target = hlTarget;
  vanBodyTilt.add(vanHeadLightObj);

  vanInteriorLight = new THREE.PointLight(0xfbbf24, 0.4, 7);
  vanInteriorLight.position.set(0, 1.8, 0.2);
  vanBodyTilt.add(vanInteriorLight);

  // 4 Roda
  const wheelGeo = new THREE.CylinderGeometry(0.45, 0.45, 0.34, 16);
  wheelGeo.rotateZ(Math.PI / 2);
  const hubGeo = new THREE.CylinderGeometry(0.22, 0.22, 0.36, 10);
  hubGeo.rotateZ(Math.PI / 2);

  [
    { x: -1.06, z: 1.45, front: true },
    { x:  1.06, z: 1.45, front: true },
    { x: -1.06, z: -1.45, front: false },
    { x:  1.06, z: -1.45, front: false }
  ].forEach(wp => {
    const steerPivot = new THREE.Group();
    steerPivot.position.set(wp.x, 0.45, wp.z);
    const wMesh = new THREE.Mesh(wheelGeo, vanMaterials.tire);
    wMesh.castShadow = true;
    wMesh.add(new THREE.Mesh(hubGeo, vanMaterials.chrome));
    steerPivot.add(wMesh);
    vanBodyTilt.add(steerPivot);
    vanWheels.push({ pivot: steerPivot, mesh: wMesh, front: wp.front });
  });

  // Dekorasi Opsional
  vanDecorMeshes = {};

  const rackG = new THREE.Group();
  const rackFrame = new THREE.Mesh(new THREE.BoxGeometry(1.9, 0.08, 3.2), vanMaterials.chrome);
  rackFrame.position.set(0, 2.38, -0.2);
  const crate1 = new THREE.Mesh(new THREE.BoxGeometry(0.95, 0.34, 1.1), new THREE.MeshStandardMaterial({ color: 0xb45309, roughness: 0.7 }));
  crate1.position.set(-0.35, 2.55, -0.8);
  const crate2 = new THREE.Mesh(new THREE.BoxGeometry(0.7, 0.28, 0.8), new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.7 }));
  crate2.position.set(0.42, 2.52, -0.9);
  rackG.add(rackFrame, crate1, crate2);
  vanBodyTilt.add(rackG);
  vanDecorMeshes.roofRack = rackG;

  const solarG = new THREE.Group();
  const spBase = new THREE.Mesh(new THREE.BoxGeometry(1.5, 0.07, 1.3), new THREE.MeshStandardMaterial({ color: 0x1e3a8a, metalness: 0.5, roughness: 0.2 }));
  spBase.position.set(0, 2.42, 0.8);
  solarG.add(spBase);
  vanBodyTilt.add(solarG);
  vanDecorMeshes.solarPanel = solarG;

  const awningG = new THREE.Group();
  const awnPole = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.16, 3.3), vanMaterials.chrome);
  awnPole.position.set(1.14, 2.22, -0.2);
  const awnCloth = new THREE.Mesh(new THREE.BoxGeometry(1.75, 0.06, 3.2), new THREE.MeshStandardMaterial({ color: 0xf59e0b, roughness: 0.8 }));
  awnCloth.position.set(1.98, 2.12, -0.2);
  awnCloth.rotation.z = -0.14;
  const leg1 = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 1.95), vanMaterials.chrome);
  leg1.position.set(2.8, 1.0, 1.3);
  const leg2 = leg1.clone(); leg2.position.set(2.8, 1.0, -1.7);
  awningG.add(awnPole, awnCloth, leg1, leg2);
  vanBodyTilt.add(awningG);
  vanDecorMeshes.awning = awningG;

  const fairyG = new THREE.Group();
  for (let z = -1.9; z <= 1.7; z += 0.45) {
    const b1 = new THREE.Mesh(new THREE.SphereGeometry(0.055, 6, 6), vanMaterials.fairy);
    b1.position.set(1.1, 2.3, z);
    const b2 = b1.clone(); b2.position.set(-1.1, 2.3, z);
    fairyG.add(b1, b2);
  }
  vanBodyTilt.add(fairyG);
  vanDecorMeshes.fairyLights = fairyG;

  const surfG = new THREE.Group();
  const board = new THREE.Mesh(new THREE.BoxGeometry(0.65, 0.09, 2.9), new THREE.MeshStandardMaterial({ color: 0x06b6d4, roughness: 0.3 }));
  board.position.set(0.55, 2.48, -0.1);
  const fin = new THREE.Mesh(new THREE.BoxGeometry(0.04, 0.18, 0.25), new THREE.MeshStandardMaterial({ color: 0xf43f5e }));
  fin.position.set(0.55, 2.58, -1.3);
  surfG.add(board, fin);
  vanBodyTilt.add(surfG);
  vanDecorMeshes.surfboard = surfG;

  const bullG = new THREE.Group();
  const bar = new THREE.Mesh(new THREE.BoxGeometry(1.9, 0.42, 0.16), vanMaterials.chrome);
  bar.position.set(0, 0.72, 2.52);
  const fog1 = new THREE.Mesh(new THREE.CylinderGeometry(0.11, 0.11, 0.08, 10), vanMaterials.fairy);
  fog1.rotation.x = Math.PI / 2; fog1.position.set(-0.42, 0.96, 2.52);
  const fog2 = fog1.clone(); fog2.position.set(0.42, 0.96, 2.52);
  bullG.add(bar, fog1, fog2);
  vanBodyTilt.add(bullG);
  vanDecorMeshes.bullbar = bullG;

  const spareG = new THREE.Group();
  const tire = new THREE.Mesh(new THREE.CylinderGeometry(0.42, 0.42, 0.28, 14), vanMaterials.tire);
  tire.rotation.x = Math.PI / 2;
  tire.position.set(-0.45, 1.2, -2.5);
  const ladder = new THREE.Mesh(new THREE.BoxGeometry(0.45, 1.5, 0.08), vanMaterials.chrome);
  ladder.position.set(0.6, 1.45, -2.42);
  spareG.add(tire, ladder);
  vanBodyTilt.add(spareG);
  vanDecorMeshes.spareTire = spareG;

  const plantG = new THREE.Group();
  const pot = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.09, 0.16, 8), new THREE.MeshStandardMaterial({ color: 0xb45309 }));
  pot.position.set(-0.55, 1.48, 1.75);
  const leaf = new THREE.Mesh(new THREE.DodecahedronGeometry(0.16), MAT_LEAF);
  leaf.position.set(-0.55, 1.66, 1.75);
  const lantern = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.08, 0.18, 8), vanMaterials.fairy);
  lantern.position.set(0.55, 1.52, 1.75);
  plantG.add(pot, leaf, lantern);
  vanBodyTilt.add(plantG);
  vanDecorMeshes.cabinPlant = plantG;

  syncVanVisuals();
}

function updateVanLiveryTexture() {
  if (!liveryMeshRight) return;
  const c = document.createElement('canvas');
  c.width = 512; c.height = 128;
  const ctx = c.getContext('2d');
  ctx.clearRect(0, 0, 512, 128);

  const liv = state.van.livery;
  if (liv === 'stripe') {
    ctx.fillStyle = state.van.accentColor;
    ctx.fillRect(0, 36, 512, 24);
    ctx.fillStyle = '#f59e0b';
    ctx.fillRect(0, 66, 512, 14);
  } else if (liv === 'wave') {
    ctx.fillStyle = '#38bdf8';
    ctx.beginPath(); ctx.moveTo(0, 128);
    for (let x = 0; x <= 512; x += 16) ctx.lineTo(x, 68 + Math.sin(x * 0.04) * 24);
    ctx.lineTo(512, 128); ctx.closePath(); ctx.fill();
  } else if (liv === 'mountain') {
    ctx.fillStyle = state.van.accentColor;
    ctx.beginPath();
    ctx.moveTo(40, 128); ctx.lineTo(130, 30); ctx.lineTo(220, 128);
    ctx.lineTo(310, 20); ctx.lineTo(420, 128);
    ctx.fill();
  }
  const tex = new THREE.CanvasTexture(c);
  liveryMeshRight.material.map = tex;
  liveryMeshRight.material.needsUpdate = true;
}

function syncVanVisuals() {
  vanMaterials.primary.color.set(state.van.primaryColor);
  vanMaterials.accent.color.set(state.van.accentColor);
  updateVanLiveryTexture();
  for (const [k, mesh] of Object.entries(vanDecorMeshes)) {
    mesh.visible = !!state.van.activeDecor[k];
  }
  const hlOn = state.van.headlights && state.van.battery > 0;
  vanHeadLightObj.intensity = hlOn ? 2.4 : 0;
  vanMaterials.headlight.emissiveIntensity = hlOn ? 2.0 : 0.1;
}

// Dust particles
const dustParticles = [];
const dustGeo = new THREE.SphereGeometry(0.22, 5, 5);
const dustMat = new THREE.MeshBasicMaterial({ color: 0xd6c7a9, transparent: true, opacity: 0.45 });

function spawnDust(x, y, z) {
  if (dustParticles.length > 35) return;
  const m = new THREE.Mesh(dustGeo, dustMat);
  m.position.set(x + (Math.random() - 0.5) * 0.6, y + 0.15, z + (Math.random() - 0.5) * 0.6);
  scene.add(m);
  dustParticles.push({ mesh: m, life: 0.55 });
}

function updateDustParticles(dt) {
  for (let i = dustParticles.length - 1; i >= 0; i--) {
    const p = dustParticles[i];
    p.life -= dt;
    p.mesh.position.y += dt * 0.9;
    p.mesh.scale.multiplyScalar(1 + dt * 1.5);
    if (p.life <= 0) {
      scene.remove(p.mesh);
      dustParticles.splice(i, 1);
    }
  }
}

function updateVanPhysics(dt) {
  const van = state.van;
  const pl = state.player;

  // Tampilkan siluet pengemudi hanya saat di dalam mobil
  if (vanDriverMesh) vanDriverMesh.visible = pl.inVan;

  if (pl.inVan) {
    const gas = (keys['KeyW'] || keys['ArrowUp']) ? 1 : 0;
    const rev = (keys['KeyS'] || keys['ArrowDown']) ? 1 : 0;
    const left = (keys['KeyA'] || keys['ArrowLeft']) ? 1 : 0;
    const right = (keys['KeyD'] || keys['ArrowRight']) ? 1 : 0;
    const brake = keys['Space'] ? 1 : 0;

    const hasFuel = van.fuel > 0 && van.hp > 5;
    const accel = 18;
    const maxSpeed = 24;

    if (gas && hasFuel) van.speed += accel * dt;
    else if (rev && hasFuel) van.speed -= accel * 0.65 * dt;
    else van.speed *= Math.pow(0.18, dt);

    if (brake) van.speed *= Math.pow(0.02, dt);

    if ((gas || rev) && !hasFuel && Math.random() < 0.03) {
      showToast(van.fuel <= 0 ? '⛽ Bensin Habis! Cari jerigen atau Pom Bensin!' : '🔧 Mesin rusak berat! Gunakan Kit Perbaikan!');
    }

    van.speed = Math.max(-9, Math.min(maxSpeed, van.speed));

    const targetSteer = (left - right) * 0.55;
    steerVis += (targetSteer - steerVis) * Math.min(1, dt * 10);
    const turnFactor = Math.min(1, Math.abs(van.speed) / 3.5) * (van.speed >= 0 ? 1 : -1);
    van.yaw += steerVis * 2.4 * turnFactor * dt;

    const vx = Math.sin(van.yaw) * van.speed;
    const vz = Math.cos(van.yaw) * van.speed;
    const col = resolveCollisions(van.x + vx * dt, van.z + vz * dt, 1.55);

    if (col.hit && Math.abs(van.speed) > 7) {
      const dmgFactor = van.activeDecor.bullbar ? 0.45 : 1.0;
      van.hp = Math.max(0, van.hp - Math.abs(van.speed) * 0.35 * dmgFactor);
      van.speed *= -0.25;
      AudioSys.bump();
    }
    van.x = col.x;
    van.z = col.z;

    const groundY = WorldGen.getTerrainHeight(van.x, van.z);
    if (groundY < WATER_LEVEL - 0.35) van.speed *= 0.85;
    van.y = Math.max(groundY, WATER_LEVEL - 0.45);

    if (Math.abs(van.speed) > 0.6 && (gas || rev)) {
      van.fuel = Math.max(0, van.fuel - Math.abs(van.speed) * 0.015 * dt);
      state.distanceDriven += Math.abs(van.speed) * dt;
      van.battery = Math.min(getMaxBat(), van.battery + 0.55 * dt);
      if (Math.random() < 0.35 && groundY > WATER_LEVEL + 0.1) spawnDust(van.x, van.y, van.z);
    }

    pl.x = van.x; pl.y = van.y; pl.z = van.z;

    if (!isDragging && activeModal !== 'customize') {
      const desiredCamAngle = van.yaw + Math.PI;
      let diff = desiredCamAngle - camAngle;
      while (diff > Math.PI) diff -= Math.PI * 2;
      while (diff < -Math.PI) diff += Math.PI * 2;
      camAngle += diff * Math.min(1, dt * 3.2);
    }

    AudioSys.updateEngine(Math.abs(van.speed), true);
  } else {
    van.speed *= Math.pow(0.05, dt);
    van.y = Math.max(WorldGen.getTerrainHeight(van.x, van.z), WATER_LEVEL - 0.45);
    AudioSys.updateEngine(0, false);
  }

  // Transform 3D Camper Van & Suspensi
  vanGroup.position.set(van.x, van.y, van.z);
  vanGroup.rotation.y = van.yaw;

  const fwdX = Math.sin(van.yaw) * 1.4, fwdZ = Math.cos(van.yaw) * 1.4;
  const sideX = Math.cos(van.yaw) * 1.0, sideZ = -Math.sin(van.yaw) * 1.0;
  const hF = WorldGen.getTerrainHeight(van.x + fwdX, van.z + fwdZ);
  const hB = WorldGen.getTerrainHeight(van.x - fwdX, van.z - fwdZ);
  const hL = WorldGen.getTerrainHeight(van.x + sideX, van.z + sideZ);
  const hR = WorldGen.getTerrainHeight(van.x - sideX, van.z - sideZ);

  const pitch = Math.atan2(hB - hF, 2.8);
  const roll = Math.atan2(hL - hR, 2.0) + steerVis * (van.speed / 24) * 0.14;
  vanBodyTilt.rotation.x += (pitch - vanBodyTilt.rotation.x) * Math.min(1, dt * 10);
  vanBodyTilt.rotation.z += (roll - vanBodyTilt.rotation.z) * Math.min(1, dt * 10);

  vanWheels.forEach(w => {
    if (w.front) w.pivot.rotation.y = steerVis;
    w.mesh.rotation.x += van.speed * dt * 2.2;
  });

  const braking = (keys['KeyS'] || keys['Space']) && pl.inVan;
  vanMaterials.taillight.emissiveIntensity = braking ? 1.8 : 0.3;
}
NOMAD_END;

    // -----------------------------------------------------------------
    // js/player.js
    // -----------------------------------------------------------------
    $files['js/player.js'] = <<<'NOMAD_END'
// =========================================================================
// KARAKTER ORANG 3D, ANIMASI JALAN/LOMPAT, TURUN/NAIK MOBIL & INTERAKSI
// =========================================================================
const playerMesh = new THREE.Group();
const playerLimbs = {};

(function buildPlayerMesh() {
  const matPants = new THREE.MeshStandardMaterial({ color: 0x1e3a8a });
  const matShirt = new THREE.MeshStandardMaterial({ color: 0xd97706 });
  const matSkin  = new THREE.MeshStandardMaterial({ color: 0xfdba74 });
  const matPack  = new THREE.MeshStandardMaterial({ color: 0x365314 });
  const matHat   = new THREE.MeshStandardMaterial({ color: 0x78350f });

  // Kaki Kiri & Kanan (pivot di pinggul y=0.75)
  const legGeo = new THREE.BoxGeometry(0.19, 0.75, 0.22);
  legGeo.translate(0, -0.375, 0);

  const leftLeg = new THREE.Mesh(legGeo, matPants);
  leftLeg.position.set(-0.13, 0.75, 0); leftLeg.castShadow = true;
  const rightLeg = new THREE.Mesh(legGeo, matPants);
  rightLeg.position.set(0.13, 0.75, 0); rightLeg.castShadow = true;

  // Badan & Ransel
  const torso = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.68, 0.3), matShirt);
  torso.position.y = 1.1; torso.castShadow = true;
  const pack = new THREE.Mesh(new THREE.BoxGeometry(0.38, 0.52, 0.22), matPack);
  pack.position.set(0, 1.12, -0.24); pack.castShadow = true;

  // Lengan Kiri & Kanan (pivot di bahu y=1.38)
  const armGeo = new THREE.BoxGeometry(0.16, 0.6, 0.18);
  armGeo.translate(0, -0.26, 0);

  const leftArm = new THREE.Mesh(armGeo, matShirt);
  leftArm.position.set(-0.34, 1.38, 0); leftArm.castShadow = true;
  const rightArm = new THREE.Mesh(armGeo, matShirt);
  rightArm.position.set(0.34, 1.38, 0); rightArm.castShadow = true;

  // Kepala & Topi
  const head = new THREE.Mesh(new THREE.BoxGeometry(0.36, 0.36, 0.36), matSkin);
  head.position.y = 1.65; head.castShadow = true;
  const hat = new THREE.Mesh(new THREE.BoxGeometry(0.44, 0.11, 0.46), matHat);
  hat.position.set(0, 1.85, 0.03);

  playerMesh.add(leftLeg, rightLeg, torso, pack, leftArm, rightArm, head, hat);
  playerLimbs.leftLeg = leftLeg;
  playerLimbs.rightLeg = rightLeg;
  playerLimbs.leftArm = leftArm;
  playerLimbs.rightArm = rightArm;
  scene.add(playerMesh);
})();

let playerVelY = 0;
let playerGrounded = true;
let walkCycle = 0;
let lastStepPhase = 0;
let nearestInteract = null;

function findSafeExitSpot() {
  const van = state.van;
  const candidates = [
    // Sisi kiri (pintu camper)
    { lx: -2.4, lz: 0.2 },
    // Sisi kanan (pintu pengemudi)
    { lx:  2.4, lz: 0.6 },
    // Belakang camper
    { lx:  0.0, lz: -3.4 },
    // Depan camper
    { lx:  0.0, lz: 3.4 }
  ];
  const cosY = Math.cos(van.yaw), sinY = Math.sin(van.yaw);
  for (const c of candidates) {
    const wx = van.x + c.lx * cosY + c.lz * sinY;
    const wz = van.z - c.lx * sinY + c.lz * cosY;
    const wy = WorldGen.getTerrainHeight(wx, wz);
    const col = resolveCollisions(wx, wz, 0.5);
    if (!col.hit && wy >= WATER_LEVEL - 0.1) {
      return { x: col.x, y: wy, z: col.z };
    }
  }
  return {
    x: van.x + cosY * 2.4,
    y: Math.max(WorldGen.getTerrainHeight(van.x + cosY * 2.4, van.z - sinY * 2.4), WATER_LEVEL),
    z: van.z - sinY * 2.4
  };
}

function toggleVehicleMode() {
  if (state.player.hp <= 0) return;

  if (state.player.inVan) {
    if (Math.abs(state.van.speed) > 4.5) {
      showToast('⚠️ Rem mobil dulu sebelum turun!');
      return;
    }
    state.van.speed = 0;
    state.player.inVan = false;
    const spot = findSafeExitSpot();
    state.player.x = spot.x;
    state.player.y = spot.y;
    state.player.z = spot.z;
    state.player.yaw = state.van.yaw;
    playerVelY = 0;
    playerGrounded = true;
    AudioSys.playTone(260, 0.08);
    showToast('🚶 Kamu turun dari Camper Van! (WASD: Jalan, Space: Lompat, Shift: Lari)');
  } else {
    const dist = Math.hypot(state.player.x - state.van.x, state.player.z - state.van.z);
    if (dist > 5.5) {
      showToast('⚠️ Terlalu jauh dari mobil! Mendekatlah ke camper van.');
      return;
    }
    state.player.inVan = true;
    AudioSys.playTone(340, 0.08);
    showToast('🚐 Kamu naik kembali ke Camper Van');
  }
}

function resolvePlayerVanCollision(px, pz, playerRadius) {
  // Ubah posisi pemain ke koordinat lokal van untuk kolisi kotak yang akurat
  const van = state.van;
  const dx = px - van.x;
  const dz = pz - van.z;
  const cosY = Math.cos(-van.yaw);
  const sinY = Math.sin(-van.yaw);
  let lx = dx * cosY - dz * sinY;
  let lz = dx * sinY + dz * cosY;

  const halfW = 1.18 + playerRadius;
  const halfL = 2.42 + playerRadius;

  if (Math.abs(lx) < halfW && Math.abs(lz) < halfL) {
    // Jika pemain melompat di atas atap van, biarkan berdiri di atap!
    if (state.player.y >= van.y + 2.1) return { x: px, z: pz, onRoof: true };

    const overlapX = halfW - Math.abs(lx);
    const overlapZ = halfL - Math.abs(lz);
    if (overlapX < overlapZ) {
      lx += (lx >= 0 ? overlapX : -overlapX);
    } else {
      lz += (lz >= 0 ? overlapZ : -overlapZ);
    }
    const invCos = Math.cos(van.yaw);
    const invSin = Math.sin(van.yaw);
    return {
      x: van.x + (lx * invCos - lz * invSin),
      z: van.z + (lx * invSin + lz * invCos),
      onRoof: false
    };
  }
  return { x: px, z: pz, onRoof: false };
}

function updatePlayerPhysics(dt) {
  const pl = state.player;
  playerMesh.visible = !pl.inVan;

  if (pl.inVan) {
    walkCycle = 0;
    return;
  }

  // Input jalan kaki relatif terhadap arah kamera
  const fwd = (keys['KeyW'] || keys['ArrowUp'] ? 1 : 0) - (keys['KeyS'] || keys['ArrowDown'] ? 1 : 0);
  const strafe = (keys['KeyD'] || keys['ArrowRight'] ? 1 : 0) - (keys['KeyA'] || keys['ArrowLeft'] ? 1 : 0);

  const sprinting = (keys['ShiftLeft'] || keys['ShiftRight']) && pl.stamina > 3;
  const moveSpeed = sprinting ? 9.4 : 5.6;
  let isMoving = false;

  if (fwd !== 0 || strafe !== 0) {
    isMoving = true;
    const lookX = -Math.sin(camAngle);
    const lookZ = -Math.cos(camAngle);
    const rightX = Math.cos(camAngle);
    const rightZ = -Math.sin(camAngle);

    let dirX = lookX * fwd + rightX * strafe;
    let dirZ = lookZ * fwd + rightZ * strafe;
    const len = Math.hypot(dirX, dirZ) || 1;
    dirX /= len; dirZ /= len;

    const worldCol = resolveCollisions(pl.x + dirX * moveSpeed * dt, pl.z + dirZ * moveSpeed * dt, 0.45);
    const vanCol = resolvePlayerVanCollision(worldCol.x, worldCol.z, 0.42);
    pl.x = vanCol.x;
    pl.z = vanCol.z;

    const targetYaw = Math.atan2(dirX, dirZ);
    let diff = targetYaw - pl.yaw;
    while (diff > Math.PI) diff -= Math.PI * 2;
    while (diff < -Math.PI) diff += Math.PI * 2;
    pl.yaw += diff * Math.min(1, dt * 14);

    if (sprinting) pl.stamina = Math.max(0, pl.stamina - 12 * dt);
  } else {
    pl.stamina = Math.min(100, pl.stamina + 9 * dt);
  }

  // Lompat & Gravitasi
  if (keys['Space'] && playerGrounded) {
    playerVelY = 7.4;
    playerGrounded = false;
    AudioSys.playTone(210, 0.08, 'sine', 0.08);
  }

  playerVelY -= 20 * dt;
  pl.y += playerVelY * dt;

  // Cek tinggi tanah (atau atap camper van!)
  const terrainY = Math.max(WorldGen.getTerrainHeight(pl.x, pl.z), WATER_LEVEL - 0.35);
  const vanCheck = resolvePlayerVanCollision(pl.x, pl.z, 0.42);
  const floorY = vanCheck.onRoof ? Math.max(terrainY, state.van.y + 2.34) : terrainY;

  if (pl.y <= floorY) {
    pl.y = floorY;
    playerVelY = 0;
    playerGrounded = true;
  } else {
    playerGrounded = false;
  }

  // Animasi tangan & kaki
  if (isMoving && playerGrounded) {
    walkCycle += dt * (sprinting ? 14 : 9.5);
    const swing = Math.sin(walkCycle);
    playerLimbs.leftLeg.rotation.x = swing * 0.65;
    playerLimbs.rightLeg.rotation.x = -swing * 0.65;
    playerLimbs.leftArm.rotation.x = -swing * 0.55;
    playerLimbs.rightArm.rotation.x = swing * 0.55;

    const stepPhase = Math.floor(walkCycle / Math.PI);
    if (stepPhase !== lastStepPhase) {
      lastStepPhase = stepPhase;
      AudioSys.footstep();
    }
  } else if (!playerGrounded) {
    playerLimbs.leftLeg.rotation.x = 0.35;
    playerLimbs.rightLeg.rotation.x = -0.35;
    playerLimbs.leftArm.rotation.x = -0.6;
    playerLimbs.rightArm.rotation.x = -0.6;
  } else {
    playerLimbs.leftLeg.rotation.x *= 0.8;
    playerLimbs.rightLeg.rotation.x *= 0.8;
    playerLimbs.leftArm.rotation.x *= 0.8;
    playerLimbs.rightArm.rotation.x *= 0.8;
  }

  playerMesh.position.set(pl.x, pl.y, pl.z);
  playerMesh.rotation.y = pl.yaw;
}

function checkNearestInteraction() {
  const px = state.player.inVan ? state.van.x : state.player.x;
  const pz = state.player.inVan ? state.van.z : state.player.z;
  let best = null, bestDist = 999;

  for (const it of worldInteractables) {
    const d = Math.hypot(px - it.x, pz - it.z);
    if (d <= it.radius && d < bestDist) {
      best = it; bestDist = d;
    }
  }

  if (!best && !state.player.inVan) {
    const groundH = WorldGen.getTerrainHeight(px, pz);
    if (groundH <= WATER_LEVEL + 0.75) {
      best = { kind: 'sea_water', label: '🌊 Ambil Air Laut / Tangkap Ikan ([F])' };
    }
  }

  if (!best && !state.player.inVan) {
    const dv = Math.hypot(px - state.van.x, pz - state.van.z);
    if (dv <= 4.5) {
      best = { kind: 'van_door', label: '🚐 Naik Mobil [E] atau Buka Kulkas & Kompor [F]' };
    }
  }

  nearestInteract = best;
  const promptEl = document.getElementById('interact-prompt');
  if (best && !activeModal) {
    promptEl.textContent = best.label.includes('[F]') ? best.label : `[F] ${best.label}`;
    promptEl.style.display = 'block';
  } else {
    promptEl.style.display = 'none';
  }
}

function triggerInteract() {
  if (!nearestInteract || activeModal) return;
  const it = nearestInteract;

  if (it.kind === 'van_door') { openInventoryModal(); return; }

  if (it.kind === 'gas_pump') {
    state.van.fuel = getMaxFuel();
    addItemToList(state.inventory, 'fuel_can', 1, 100);
    AudioSys.pickup();
    showToast('⛽ Tangki mobil diisi penuh di Pom Bensin + 1 Jerigen cadangan!');
    saveGameState();
    return;
  }

  if (it.kind === 'well') {
    addItemToList(state.inventory, 'water', 2, 100);
    state.player.thirst = Math.min(100, state.player.thirst + 30);
    AudioSys.pickup();
    showToast('💧 Mengisi 2x Air Minum Bersih dari Sumur Desa!');
    saveGameState();
    return;
  }

  if (it.kind === 'sea_water') {
    addItemToList(state.inventory, 'dirty_water', 1, 100);
    if (Math.random() < 0.65) {
      addItemToList(state.inventory, 'raw_fish', 1, 100);
      showToast('🐟 Dapat 1x Ikan Segar & 1x Air Alam (rebus di kompor!)');
    } else {
      showToast('🫙 Mengambil 1x Air Alam (rebus dulu di kompor!)');
    }
    AudioSys.pickup();
    saveGameState();
    return;
  }

  if (it.kind === 'loot') {
    state.lootedIds[it.uid] = true;
    if (it.mesh && it.mesh.parent) it.mesh.parent.remove(it.mesh);
    for (const ch of activeChunks.values()) {
      const idx = ch.interactables.indexOf(it);
      if (idx !== -1) ch.interactables.splice(idx, 1);
    }
    const gained = [];
    it.drops.forEach(d => {
      addItemToList(state.inventory, d.id, d.qty, 100);
      gained.push(`${d.qty}x ${ITEMS[d.id].name}`);
    });
    AudioSys.pickup();
    showToast(`🎒 Mendapat: ${gained.join(', ')}`);
    saveGameState();
  }
}
NOMAD_END;

    // -----------------------------------------------------------------
    // js/ui.js
    // -----------------------------------------------------------------
    $files['js/ui.js'] = <<<'NOMAD_END'
// =========================================================================
// SURVIVAL DECAY, HUD, KULKAS, KOMPOR, HIAS MOBIL & PETA
// =========================================================================
function updateSurvivalAndEnvironment(dt) {
  state.timeOfDay += dt / 360;
  if (state.timeOfDay >= 1) {
    state.timeOfDay -= 1;
    state.day += 1;
    showToast(`🌅 Hari ke-${state.day} dimulai!`);
  }

  const px = state.player.inVan ? state.van.x : state.player.x;
  const pz = state.player.inVan ? state.van.z : state.player.z;
  const biome = WorldGen.getBiomeAt(px, pz);
  const meta = BIOME_META[biome];

  const sunAngle = (state.timeOfDay - 0.25) * Math.PI * 2;
  const sunY = Math.sin(sunAngle);
  const sunX = Math.cos(sunAngle);
  sunLight.position.set(px + sunX * 60, Math.max(-15, sunY * 70), pz + 35);
  sunLight.target.position.set(px, 0, pz);

  const isDay = sunY > 0;
  const dayFactor = Math.max(0, Math.min(1, sunY * 2.5 + 0.2));
  sunLight.intensity = dayFactor * 1.15;
  hemiLight.intensity = 0.22 + dayFactor * 0.6;

  const nightSky = new THREE.Color(0x0b132b);
  const daySky = new THREE.Color(meta.fog);
  const duskSky = new THREE.Color(0xf97316);
  const curSky = nightSky.clone().lerp(daySky, dayFactor);
  if (sunY > -0.15 && sunY < 0.25) curSky.lerp(duskSky, 0.35);
  scene.background.copy(curSky);
  scene.fog.color.copy(curSky);

  const nightGlow = 1 - dayFactor;
  vanMaterials.fairy.emissiveIntensity = 0.4 + nightGlow * 2.2;
  vanInteriorLight.intensity = 0.2 + nightGlow * 0.9;

  if (state.van.activeDecor.solarPanel && isDay) {
    state.van.battery = Math.min(getMaxBat(), state.van.battery + 1.4 * sunY * dt);
  }
  let drain = 0;
  if (state.fridge.on) drain += 0.14;
  if (state.van.headlights) drain += 0.28;
  state.van.battery = Math.max(0, state.van.battery - drain * dt);
  if (state.van.battery <= 0 && state.fridge.on) {
    state.fridge.on = false;
    syncVanVisuals();
    showToast('🪫 Baterai Mobil habis! Kulkas & Lampu mati.');
  }

  const thirstMult = biome === 'gurun' ? 1.7 : 1.0;
  state.player.hunger = Math.max(0, state.player.hunger - 0.32 * dt);
  state.player.thirst = Math.max(0, state.player.thirst - 0.45 * thirstMult * dt);
  if (state.player.inVan) state.player.stamina = Math.min(100, state.player.stamina + 14 * dt);

  if (state.player.hunger <= 0 || state.player.thirst <= 0) {
    state.player.hp = Math.max(0, state.player.hp - 1.8 * dt);
  }

  const heatMult = biome === 'gurun' ? 1.8 : (biome === 'gunung' ? 0.6 : 1.0);
  decayItemList(state.inventory, 0.55 * heatMult * dt);
  decayItemList(state.fridge.items, (state.fridge.on ? 0.08 : 0.45) * dt);

  if (state.player.hp <= 0) triggerGameOver();
}

function decayItemList(list, amount) {
  for (let i = list.length - 1; i >= 0; i--) {
    const slot = list[i];
    const def = ITEMS[slot.id];
    if (!def || !def.perishable) continue;
    slot.fresh -= amount * (def.decayRate || 1);
    if (slot.fresh <= 0) {
      const qty = slot.qty;
      list.splice(i, 1);
      addItemToList(list, 'spoiled', qty, 100);
    }
  }
}

function useItemFrom(sourceType, idx) {
  const list = sourceType === 'fridge' ? state.fridge.items : state.inventory;
  const slot = list[idx];
  if (!slot) return;
  const def = ITEMS[slot.id];

  if (def.type === 'food' || def.type === 'drink' || def.type === 'trash') {
    state.player.hunger = Math.min(100, state.player.hunger + (def.hunger || 0));
    let thirstGain = def.thirst || 0;
    if (sourceType === 'fridge' && state.fridge.on && def.coldBonus) {
      thirstGain += def.coldBonus;
      showToast(`❄️ ${def.name} dingin menyegarkan! (+${thirstGain} Haus)`);
    } else {
      showToast(`${def.icon} Mengonsumsi ${def.name}`);
    }
    state.player.thirst = Math.min(100, state.player.thirst + thirstGain);
    state.player.hp = Math.max(0, Math.min(100, state.player.hp + (def.hp || 0)));
    if (def.stamina) state.player.stamina = Math.min(100, state.player.stamina + def.stamina);
    AudioSys.pickup();
  } else if (def.type === 'fuel') {
    const maxF = getMaxFuel();
    if (state.van.fuel >= maxF - 1) { showToast('⛽ Tangki sudah penuh!'); return; }
    state.van.fuel = Math.min(maxF, state.van.fuel + def.amount);
    AudioSys.pickup();
    showToast(`⛽ Mengisi bensin mobil +${def.amount}%`);
  } else if (def.type === 'repair') {
    if (state.van.hp >= 99) { showToast('🔧 Mobil masih mulus!'); return; }
    state.van.hp = Math.min(100, state.van.hp + def.amount);
    AudioSys.cook();
    showToast(`🔧 Camper Van diperbaiki +${def.amount}%`);
  } else if (def.type === 'med') {
    state.player.hp = Math.min(100, state.player.hp + def.amount);
    AudioSys.pickup();
    showToast(`🩹 Kesehatan pulih +${def.amount}%`);
  } else {
    showToast('ℹ️ Bahan baku untuk masak / merakit dekorasi.');
    return;
  }

  slot.qty -= 1;
  if (slot.qty <= 0) list.splice(idx, 1);
  renderInventoryModal();
  saveGameState();
}

function transferItem(fromType, idx) {
  const src = fromType === 'bag' ? state.inventory : state.fridge.items;
  const dst = fromType === 'bag' ? state.fridge.items : state.inventory;
  const slot = src[idx];
  if (!slot) return;

  if (fromType === 'bag' && dst.length >= getMaxFridgeSlots() && !dst.some(s => s.id === slot.id)) {
    showToast('🧊 Kulkas penuh! Upgrade kapasitas kulkas di menu Hias Mobil.');
    return;
  }
  addItemToList(dst, slot.id, 1, slot.fresh);
  slot.qty -= 1;
  if (slot.qty <= 0) src.splice(idx, 1);
  AudioSys.playTone(420, 0.06);
  renderInventoryModal();
  saveGameState();
}

function discardItem(fromType, idx) {
  const list = fromType === 'bag' ? state.inventory : state.fridge.items;
  list.splice(idx, 1);
  renderInventoryModal();
}

function toggleFridgePower() {
  if (!state.fridge.on && state.van.battery <= 1) {
    showToast('🪫 Baterai mobil kosong!'); return;
  }
  state.fridge.on = !state.fridge.on;
  AudioSys.playTone(state.fridge.on ? 500 : 250, 0.08);
  renderInventoryModal();
}

function craftRecipe(recId) {
  const rec = RECIPES.find(r => r.id === recId);
  if (!rec) return;
  for (const [mat, reqQty] of Object.entries(rec.req)) {
    if (countItem(state.inventory, mat) < reqQty) {
      showToast(`⚠️ Kurang ${ITEMS[mat].name}!`);
      return;
    }
  }
  for (const [mat, reqQty] of Object.entries(rec.req)) consumeItem(state.inventory, mat, reqQty);
  addItemToList(state.inventory, rec.out.id, rec.out.qty, 100);
  AudioSys.cook();
  showToast(`🍳 Berhasil membuat ${rec.out.qty}x ${ITEMS[rec.out.id].name}!`);
  renderInventoryModal();
  saveGameState();
}

function renderInventoryModal() {
  const bagEl = document.getElementById('backpack-list');
  const frEl = document.getElementById('fridge-list');
  const recEl = document.getElementById('recipe-list');

  document.getElementById('inv-count').textContent = `${state.inventory.length} Jenis`;
  document.getElementById('fridge-slots').textContent = `Slot: ${state.fridge.items.length} / ${getMaxFridgeSlots()}`;
  const ftBadge = document.getElementById('fridge-temp-badge');
  const ftBtn = document.getElementById('btn-fridge-toggle');
  ftBadge.textContent = state.fridge.on ? '4°C ❄️ Aktif' : 'Mati (Suhu Ruang)';
  ftBadge.style.color = state.fridge.on ? 'var(--info)' : 'var(--muted)';
  ftBtn.textContent = state.fridge.on ? 'ON' : 'OFF';
  ftBtn.className = `btn btn-xs ${state.fridge.on ? 'success' : ''}`;

  const renderSlot = (slot, idx, loc) => {
    const def = ITEMS[slot.id] || { name: slot.id, icon: '📦', desc: '' };
    const canUse = ['food', 'drink', 'fuel', 'repair', 'med', 'trash'].includes(def.type);
    const freshHtml = def.perishable ? `
      <div class="fresh-bar"><div class="fresh-fill" style="width:${Math.max(0, slot.fresh)}%; background:${slot.fresh > 50 ? '#22c55e' : (slot.fresh > 25 ? '#f59e0b' : '#ef4444')}"></div></div>
    ` : '';
    return `
      <div class="item-card">
        <div class="item-top">
          <span class="item-name">${def.icon} ${def.name} <strong style="color:var(--accent)">x${slot.qty}</strong></span>
          ${def.perishable ? `<span style="font-size:10px; color:var(--muted)">Segar ${Math.round(slot.fresh)}%</span>` : ''}
        </div>
        ${freshHtml}
        <div class="item-desc">${def.desc}</div>
        <div class="item-actions">
          ${canUse ? `<button class="btn btn-xs primary" onclick="useItemFrom('${loc}', ${idx})">${def.type === 'drink' ? 'Minum' : (def.type === 'food' ? 'Makan' : 'Pakai')}</button>` : ''}
          <button class="btn btn-xs" onclick="transferItem('${loc === 'fridge' ? 'fridge' : 'bag'}', ${idx})">${loc === 'fridge' ? '⬅️ Ke Tas' : '🧊 Masuk Kulkas'}</button>
          <button class="btn btn-xs danger" onclick="discardItem('${loc === 'fridge' ? 'fridge' : 'bag'}', ${idx})">🗑️</button>
        </div>
      </div>
    `;
  };

  bagEl.innerHTML = state.inventory.length ? state.inventory.map((s, i) => renderSlot(s, i, 'bag')).join('') : '<div class="item-desc">Tas kosong. Jelajahi dunia untuk mencari barang!</div>';
  frEl.innerHTML = state.fridge.items.length ? state.fridge.items.map((s, i) => renderSlot(s, i, 'fridge')).join('') : '<div class="item-desc">Kulkas kosong. Simpan makanan segar di sini agar tidak cepat basi!</div>';

  recEl.innerHTML = RECIPES.map(r => {
    const reqStr = Object.entries(r.req).map(([id, q]) => {
      const have = countItem(state.inventory, id);
      return `<span style="color:${have >= q ? '#86efac' : '#fca5a5'}">${ITEMS[id].icon}${q} (${have})</span>`;
    }).join(' + ');
    const canCraft = Object.entries(r.req).every(([id, q]) => countItem(state.inventory, id) >= q);
    return `
      <div class="item-card">
        <div class="item-top">
          <span class="item-name">${r.icon} ${r.name}</span>
          <button class="btn btn-xs ${canCraft ? 'success' : ''}" ${canCraft ? '' : 'disabled'} onclick="craftRecipe('${r.id}')">Buat</button>
        </div>
        <div class="item-desc">${r.desc} • Bahan: ${reqStr}</div>
      </div>
    `;
  }).join('');
}

function renderCustomizeModal() {
  document.getElementById('primary-colors').innerHTML = PRIMARY_SWATCHES.map(c =>
    `<div class="swatch ${state.van.primaryColor === c ? 'active' : ''}" style="background:${c}" onclick="setVanColor('primaryColor','${c}')"></div>`
  ).join('');

  document.getElementById('accent-colors').innerHTML = ACCENT_SWATCHES.map(c =>
    `<div class="swatch ${state.van.accentColor === c ? 'active' : ''}" style="background:${c}" onclick="setVanColor('accentColor','${c}')"></div>`
  ).join('');

  document.getElementById('livery-buttons').innerHTML = LIVERIES.map(l =>
    `<button class="btn btn-xs ${state.van.livery === l.id ? 'primary' : ''}" onclick="setVanLivery('${l.id}')">${l.name}</button>`
  ).join('');

  document.getElementById('decor-list').innerHTML = DECORS.map(d => {
    const unlocked = !!state.van.unlockedDecor[d.id];
    const active = !!state.van.activeDecor[d.id];
    const costStr = Object.entries(d.cost).map(([id, q]) => `${ITEMS[id].icon}${q} ${ITEMS[id].name}`).join(', ') || 'Gratis';
    const canAfford = Object.entries(d.cost).every(([id, q]) => countItem(state.inventory, id) >= q);
    return `
      <div class="decor-card">
        <div>
          <div style="font-size:13px; font-weight:600;">${d.icon} ${d.name}</div>
          <div style="font-size:11px; color:var(--muted);">${d.desc}</div>
          ${!unlocked ? `<div style="font-size:11px; color:var(--accent); margin-top:2px;">Biaya rakit: ${costStr}</div>` : ''}
        </div>
        ${unlocked
          ? `<button class="btn btn-xs ${active ? 'success' : ''}" onclick="toggleDecor('${d.id}')">${active ? 'Terpasang' : 'Pasang'}</button>`
          : `<button class="btn btn-xs primary" ${canAfford ? '' : 'disabled'} onclick="unlockDecor('${d.id}')">Rakit</button>`
        }
      </div>
    `;
  }).join('');

  document.getElementById('upgrade-list').innerHTML = UPGRADES.map(u => {
    const lv = state.van.upgrades[u.id] || 1;
    const isMax = lv >= u.max;
    const costObj = !isMax ? u.costs[lv] : null;
    const canAfford = costObj ? Object.entries(costObj).every(([id, q]) => countItem(state.inventory, id) >= q) : false;
    const costStr = costObj ? Object.entries(costObj).map(([id, q]) => `${ITEMS[id].icon}${q} ${ITEMS[id].name}`).join(', ') : '';
    return `
      <div class="decor-card">
        <div>
          <div style="font-size:13px; font-weight:600;">${u.icon} ${u.name} (Lv ${lv}/${u.max})</div>
          <div style="font-size:11px; color:var(--muted);">${u.desc(lv)}</div>
          ${!isMax ? `<div style="font-size:11px; color:var(--info);">Upgrade: ${costStr}</div>` : ''}
        </div>
        ${isMax
          ? `<span style="font-size:11px; color:var(--accent-2); font-weight:700;">MAKS</span>`
          : `<button class="btn btn-xs primary" ${canAfford ? '' : 'disabled'} onclick="buyUpgrade('${u.id}')">Upgrade</button>`
        }
      </div>
    `;
  }).join('');
}

function setVanColor(key, col) { state.van[key] = col; syncVanVisuals(); renderCustomizeModal(); AudioSys.playTone(500, 0.05); saveGameState(); }
function setVanLivery(id) { state.van.livery = id; syncVanVisuals(); renderCustomizeModal(); AudioSys.playTone(540, 0.05); saveGameState(); }
function toggleDecor(id) { state.van.activeDecor[id] = !state.van.activeDecor[id]; syncVanVisuals(); renderCustomizeModal(); AudioSys.playTone(460, 0.06); saveGameState(); }

function unlockDecor(id) {
  const d = DECORS.find(x => x.id === id);
  if (!d) return;
  for (const [m, q] of Object.entries(d.cost)) if (countItem(state.inventory, m) < q) return;
  for (const [m, q] of Object.entries(d.cost)) consumeItem(state.inventory, m, q);
  state.van.unlockedDecor[id] = true;
  state.van.activeDecor[id] = true;
  syncVanVisuals(); renderCustomizeModal(); AudioSys.cook();
  showToast(`✨ ${d.name} berhasil dipasang di Camper Van!`);
  saveGameState();
}

function buyUpgrade(id) {
  const u = UPGRADES.find(x => x.id === id);
  const lv = state.van.upgrades[id] || 1;
  if (!u || lv >= u.max) return;
  const cost = u.costs[lv];
  for (const [m, q] of Object.entries(cost)) if (countItem(state.inventory, m) < q) return;
  for (const [m, q] of Object.entries(cost)) consumeItem(state.inventory, m, q);
  state.van.upgrades[id] = lv + 1;
  AudioSys.cook();
  showToast(`🚀 ${u.name} naik ke Level ${lv + 1}!`);
  renderCustomizeModal();
  saveGameState();
}

function renderMapModal() {
  const c = document.getElementById('map-canvas');
  const ctx = c.getContext('2d');
  const W = c.width, H = c.height;
  const cx = state.van.x, cz = state.van.z;
  const scale = 2.6;
  const img = ctx.createImageData(W, H);
  const data = img.data;
  const pal = {
    hutan: [60, 141, 64], gurun: [228, 190, 109], pantai: [241, 217, 154],
    gunung: [139, 149, 165], desa: [161, 98, 7], kota: [100, 116, 139]
  };

  for (let py = 0; py < H; py += 2) {
    for (let px = 0; px < W; px += 2) {
      const wx = cx + (px - W / 2) * scale;
      const wz = cz + (py - H / 2) * scale;
      const dr = Math.abs(wx - WorldGen.getRoadX(wz));
      let rgb;
      if (dr < 5.5) rgb = [61, 65, 72];
      else {
        const h = WorldGen.getTerrainHeight(wx, wz);
        if (h < WATER_LEVEL) rgb = [14, 165, 233];
        else rgb = pal[WorldGen.getBiomeAt(wx, wz)] || [80, 140, 80];
      }
      for (let oy = 0; oy < 2; oy++) {
        for (let ox = 0; ox < 2; ox++) {
          const idx = ((py + oy) * W + (px + ox)) * 4;
          data[idx] = rgb[0]; data[idx + 1] = rgb[1]; data[idx + 2] = rgb[2]; data[idx + 3] = 255;
        }
      }
    }
  }
  ctx.putImageData(img, 0, 0);

  ctx.fillStyle = '#ef4444';
  ctx.beginPath(); ctx.arc(W / 2, H / 2, 6, 0, Math.PI * 2); ctx.fill();
  ctx.strokeStyle = '#fff'; ctx.lineWidth = 2; ctx.stroke();

  if (!state.player.inVan) {
    const px = W / 2 + (state.player.x - cx) / scale;
    const pz = H / 2 + (state.player.z - cz) / scale;
    ctx.fillStyle = '#facc15';
    ctx.beginPath(); ctx.arc(px, pz, 4, 0, Math.PI * 2); ctx.fill();
  }
}

function openInventoryModal() { closeAllModals(); activeModal = 'inventory'; renderInventoryModal(); document.getElementById('modal-inventory').classList.add('open'); }
function openCustomizeModal() { closeAllModals(); activeModal = 'customize'; renderCustomizeModal(); document.getElementById('modal-customize').classList.add('open'); }
function openMapModal() { closeAllModals(); activeModal = 'map'; renderMapModal(); document.getElementById('modal-map').classList.add('open'); }
function closeAllModals() { activeModal = null; document.querySelectorAll('.modal-backdrop').forEach(m => m.classList.remove('open')); }

function sleepInVan() {
  const dist = Math.hypot(state.player.x - state.van.x, state.player.z - state.van.z);
  if (!state.player.inVan && dist > 5.5) {
    showToast('⚠️ Mendekatlah atau masuk ke Camper Van untuk tidur!');
    return;
  }
  state.timeOfDay += 0.25;
  if (state.timeOfDay >= 1) { state.timeOfDay -= 1; state.day += 1; }
  state.player.stamina = 100;
  state.player.hp = Math.min(100, state.player.hp + 18);
  state.player.hunger = Math.max(0, state.player.hunger - 10);
  state.player.thirst = Math.max(0, state.player.thirst - 12);
  AudioSys.playTone(300, 0.2, 'sine', 0.08);
  showToast('⛺ Tidur nyenyak di Camper Van (+6 jam, Stamina 100%, +18 HP)');
  saveGameState();
}

function triggerGameOver() {
  AudioSys.updateEngine(0, false);
  document.getElementById('gameover-stats').textContent = `Bertahan ${state.day} Hari • Menempuh ${Math.round(state.distanceDriven)} meter`;
  document.getElementById('gameover-screen').classList.add('open');
}

function respawnAtVan() {
  state.player.hp = 85; state.player.hunger = 70; state.player.thirst = 70; state.player.stamina = 100;
  state.player.inVan = true; state.player.x = state.van.x; state.player.z = state.van.z;
  document.getElementById('gameover-screen').classList.remove('open');
  saveGameState();
  showToast('🚑 Bangkit kembali di Camper Van!');
}

function showToast(msg) {
  const cont = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = 'toast';
  el.textContent = msg;
  cont.appendChild(el);
  setTimeout(() => el.remove(), 3200);
}

function updateHUD() {
  const p = state.player, v = state.van;
  const maxF = getMaxFuel(), maxB = getMaxBat();

  document.getElementById('txt-hp').textContent = `${Math.round(p.hp)}%`;
  document.getElementById('bar-hp').style.width = `${p.hp}%`;

  document.getElementById('txt-hunger').textContent = `${Math.round(p.hunger)}%`;
  document.getElementById('bar-hunger').style.width = `${p.hunger}%`;

  document.getElementById('txt-thirst').textContent = `${Math.round(p.thirst)}%`;
  document.getElementById('bar-thirst').style.width = `${p.thirst}%`;

  document.getElementById('txt-stamina').textContent = `${Math.round(p.stamina)}%`;
  document.getElementById('bar-stamina').style.width = `${p.stamina}%`;

  document.getElementById('txt-fuel').textContent = `${Math.round((v.fuel / maxF) * 100)}%`;
  document.getElementById('bar-fuel').style.width = `${(v.fuel / maxF) * 100}%`;

  document.getElementById('txt-bat').textContent = `${Math.round((v.battery / maxB) * 100)}%`;
  document.getElementById('bar-bat').style.width = `${(v.battery / maxB) * 100}%`;

  document.getElementById('txt-van-hp').textContent = `${Math.round(v.hp)}%`;
  document.getElementById('bar-van-hp').style.width = `${v.hp}%`;

  const kmh = p.inVan ? Math.round(Math.abs(v.speed) * 3.8) : 0;
  document.getElementById('txt-speed').textContent = kmh;
  document.getElementById('txt-mode').textContent = p.inVan ? 'KM/J • MOBIL' : '🚶 JALAN KAKI';

  const btnToggle = document.getElementById('btn-toggle-van');
  if (btnToggle) {
    btnToggle.textContent = p.inVan ? '🚶 Turun Mobil [E]' : '🚐 Naik Mobil [E]';
    btnToggle.className = `btn ${p.inVan ? 'primary' : 'success'}`;
  }

  const px = p.inVan ? v.x : p.x;
  const pz = p.inVan ? v.z : p.z;
  const biome = WorldGen.getBiomeAt(px, pz);
  const bMeta = BIOME_META[biome];
  document.getElementById('biome-pill').textContent = bMeta.label;
  document.getElementById('txt-day').textContent = `Hari ${state.day}`;

  const totalMin = Math.floor(state.timeOfDay * 24 * 60);
  const hh = String(Math.floor(totalMin / 60) % 24).padStart(2, '0');
  const mm = String(totalMin % 60).padStart(2, '0');
  document.getElementById('txt-clock').textContent = `${hh}:${mm}`;
  document.getElementById('txt-temp').textContent = `${bMeta.temp}°C`;
  document.getElementById('txt-coords').textContent = `X: ${Math.round(px)} | Z: ${Math.round(pz)} • Jarak: ${Math.round(state.distanceDriven)} m`;
}
NOMAD_END;

    // -----------------------------------------------------------------
    // js/main.js
    // -----------------------------------------------------------------
    $files['js/main.js'] = <<<'NOMAD_END'
// =========================================================================
// INPUT HANDLER, TRANSISI KAMERA HALUS & MAIN GAME LOOP
// =========================================================================
let lastMouseX = 0, lastMouseY = 0;
const camTargetPos = new THREE.Vector3(state.van.x, state.van.y + 1.5, state.van.z);

window.addEventListener('keydown', e => {
  keys[e.code] = true;
  AudioSys.resume();

  if (e.code === 'Escape') { closeAllModals(); return; }
  if (e.code === 'Tab' || e.code === 'KeyI') {
    e.preventDefault();
    activeModal === 'inventory' ? closeAllModals() : openInventoryModal();
    return;
  }
  if (e.code === 'KeyB') {
    activeModal === 'customize' ? closeAllModals() : openCustomizeModal();
    return;
  }
  if (e.code === 'KeyM') {
    activeModal === 'map' ? closeAllModals() : openMapModal();
    return;
  }

  if (activeModal) return;

  if (e.code === 'KeyE') toggleVehicleMode();
  if (e.code === 'KeyF') triggerInteract();
  if (e.code === 'KeyL' && state.player.inVan) {
    state.van.headlights = !state.van.headlights;
    syncVanVisuals();
    showToast(state.van.headlights ? '💡 Lampu Utama Menyala' : '💡 Lampu Utama Mati');
  }
  if (e.code === 'KeyH' && state.player.inVan) AudioSys.horn();
  if (e.code === 'KeyC') {
    camDistMode = (camDistMode + 1) % 3;
    showToast(['🎥 Kamera Normal', '🎥 Kamera Jauh', '🎥 Kamera Dekat'][camDistMode]);
  }
});

window.addEventListener('keyup', e => { keys[e.code] = false; });

function touchKey(code, down) { keys[code] = !!down; AudioSys.resume(); }

canvas.addEventListener('pointerdown', e => {
  isDragging = true;
  lastMouseX = e.clientX;
  lastMouseY = e.clientY;
  AudioSys.resume();
});
window.addEventListener('pointermove', e => {
  if (!isDragging) return;
  const dx = e.clientX - lastMouseX;
  const dy = e.clientY - lastMouseY;
  lastMouseX = e.clientX;
  lastMouseY = e.clientY;
  camAngle -= dx * 0.007;
  camPitch = Math.max(0.08, Math.min(1.15, camPitch + dy * 0.005));
});
window.addEventListener('pointerup', () => { isDragging = false; });

window.addEventListener('resize', () => {
  camera.aspect = window.innerWidth / window.innerHeight;
  camera.updateProjectionMatrix();
  renderer.setSize(window.innerWidth, window.innerHeight);
});

function updateCamera(dt) {
  const focusVan = (activeModal === 'customize' || state.player.inVan);
  const desiredX = focusVan ? state.van.x : state.player.x;
  const desiredY = (focusVan ? state.van.y : state.player.y) + (focusVan ? 1.5 : 1.35);
  const desiredZ = focusVan ? state.van.z : state.player.z;

  camTargetPos.x += (desiredX - camTargetPos.x) * Math.min(1, dt * 10);
  camTargetPos.y += (desiredY - camTargetPos.y) * Math.min(1, dt * 10);
  camTargetPos.z += (desiredZ - camTargetPos.z) * Math.min(1, dt * 10);

  let dist = state.player.inVan ? [10.5, 16.5, 6.8][camDistMode] : [6.2, 9.8, 4.0][camDistMode];
  if (activeModal === 'customize') dist = 7.4;

  const cx = camTargetPos.x + Math.sin(camAngle) * Math.cos(camPitch) * dist;
  const cy = camTargetPos.y + Math.sin(camPitch) * dist;
  const cz = camTargetPos.z + Math.cos(camAngle) * Math.cos(camPitch) * dist;
  const minCamY = WorldGen.getTerrainHeight(cx, cz) + 1.1;

  camera.position.set(cx, Math.max(cy, minCamY), cz);
  camera.lookAt(camTargetPos.x, camTargetPos.y + 0.25, camTargetPos.z);
}

// Inisialisasi Dunia & Mobil
buildCamperVan();

if (state.distanceDriven === 0 && state.van.x === 0 && state.van.z === 0) {
  state.van.x = WorldGen.getRoadX(0);
  state.van.y = WorldGen.getTerrainHeight(state.van.x, 0);
}
if (!state.player.inVan) {
  state.player.y = Math.max(WorldGen.getTerrainHeight(state.player.x, state.player.z), WATER_LEVEL);
}

setInterval(() => saveGameState(true), 15000);
updateChunks();
showToast('🚐 Selamat datang! Tekan [E] atau tombol Turun Mobil kapan saja untuk berjalan kaki!');

let lastTime = performance.now();
let chunkTimer = 0;

function animate(now) {
  requestAnimationFrame(animate);
  const dt = Math.min(0.05, (now - lastTime) / 1000);
  lastTime = now;

  if ((!activeModal || activeModal === 'customize') && state.player.hp > 0) {
    updateVanPhysics(dt);
    updatePlayerPhysics(dt);
    updateDustParticles(dt);
    updateCamera(dt);
  }
  updateSurvivalAndEnvironment(dt);

  chunkTimer += dt;
  if (chunkTimer > 0.25) {
    chunkTimer = 0;
    updateChunks();
    checkNearestInteraction();
  }

  updateHUD();
  renderer.render(scene, camera);
}

requestAnimationFrame(animate);
NOMAD_END;

    // Tulis semua file CSS & JS
    foreach ($files as $relPath => $content) {
        $fullPath = $baseDir . '/' . $relPath;
        if (file_put_contents($fullPath, $content) !== false) {
            logMsg("File ditulis: {$relPath}", 'ok');
        } else {
            logMsg("Gagal menulis: {$relPath}", 'error');
        }
    }

    // 4. Patch index.html menggunakan Anchor
    $html = file_get_contents($indexFile);

    // Anchor A: Ganti <style>...</style> menjadi <link rel="stylesheet" href="css/style.css" />
    if (strpos($html, 'css/style.css') === false) {
        $patchedStyle = preg_replace('~<style>.*?</style>~s', '<link rel="stylesheet" href="css/style.css" />', $html, 1, $countA);
        if ($countA > 0) {
            $html = $patchedStyle;
            logMsg('Anchor <style>...</style> berhasil diganti dengan css/style.css', 'ok');
        } else {
            logMsg('Anchor <style> tidak ditemukan di index.html', 'error');
        }
    } else {
        logMsg('css/style.css sudah tertaut di index.html, dilewati.', 'info');
    }

    // Anchor B: Tambahkan tombol Turun/Naik Mobil [E] di Bottom HUD (.vehicle-dash)
    if (strpos($html, 'id="btn-toggle-van"') === false) {
        $anchorDash = '<div class="speedo">';
        $replacementDash = '<button class="btn primary" id="btn-toggle-van" onclick="toggleVehicleMode()" style="min-height:42px;">🚶 Turun Mobil [E]</button>' . "\n        " . $anchorDash;
        if (strpos($html, $anchorDash) !== false) {
            $html = str_replace($anchorDash, $replacementDash, $html);
            logMsg('Anchor .speedo berhasil disisipi tombol #btn-toggle-van [E]', 'ok');
        } else {
            logMsg('Anchor .speedo tidak ditemukan di index.html', 'error');
        }
    }

    // Anchor C: Update teks petunjuk kontrol agar mencantumkan Lompat [Space] & Lari [Shift]
    $anchorHint = '<div><kbd>W A S D</kbd> Jalan / Stir • <kbd>Space</kbd> Rem Tangan / Lompat</div>';
    $newHint    = '<div><kbd>W A S D</kbd> Stir / Jalan • <kbd>Space</kbd> Rem / Lompat • <kbd>Shift</kbd> Lari</div>';
    if (strpos($html, $anchorHint) !== false) {
        $html = str_replace($anchorHint, $newHint, $html);
        logMsg('Anchor petunjuk kontrol diperbarui.', 'ok');
    }

    // Anchor D: Ganti blok <script> inline besar dengan pemanggilan modul js/*.js
    if (strpos($html, 'js/main.js') === false) {
        $scriptsBlock = <<<'NOMAD_SCRIPTS'
  <script src="js/data.js"></script>
  <script src="js/audio.js"></script>
  <script src="js/state.js"></script>
  <script src="js/scene.js"></script>
  <script src="js/world.js"></script>
  <script src="js/van.js"></script>
  <script src="js/player.js"></script>
  <script src="js/ui.js"></script>
  <script src="js/main.js"></script>
</body>
NOMAD_SCRIPTS;
        $patchedScript = preg_replace('~<script>\s*// =+.*?</script>\s*</body>~s', $scriptsBlock, $html, 1, $countD);
        if ($countD === 0) {
            // Fallback bila komentar anchor berbeda
            $patchedScript = preg_replace('~<script>(?![^<]*src=).*?</script>\s*</body>~s', $scriptsBlock, $html, 1, $countD);
        }
        if ($countD > 0) {
            $html = $patchedScript;
            logMsg('Anchor <script> inline berhasil dipecah menjadi 9 modul js/*.js', 'ok');
        } else {
            logMsg('Anchor <script> inline tidak ditemukan di index.html', 'error');
        }
    } else {
        logMsg('Modul js/main.js sudah tertaut di index.html, dilewati.', 'info');
    }

    if (file_put_contents($indexFile, $html) !== false) {
        logMsg('index.html berhasil diperbarui!', 'ok');
    } else {
        logMsg('Gagal menyimpan perubahan ke index.html', 'error');
    }
}

if (!$isCli): ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Patcher: Modularisasi & Turun Mobil</title>
  <style>
    body { font-family: system-ui, sans-serif; background: #0f172a; color: #f8fafc; padding: 32px; max-width: 760px; margin: auto; }
    .card { background: #1e293b; border: 1px solid #334155; border-radius: 14px; padding: 20px; }
    .log { padding: 8px 12px; border-radius: 8px; margin: 6px 0; font-size: 14px; }
    .ok { background: rgba(16, 185, 129, 0.16); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.3); }
    .info { background: rgba(56, 189, 248, 0.15); color: #7dd3fc; border: 1px solid rgba(56,189,248,0.3); }
    .error { background: rgba(239, 68, 68, 0.18); color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }
    a.play-btn { display: inline-block; margin-top: 16px; background: #f59e0b; color: #111827; padding: 10px 18px; border-radius: 10px; text-decoration: none; font-weight: 700; }
  </style>
</head>
<body>
  <div class="card">
    <h2 style="margin-top:0">🚐 Update Berhasil Diterapkan</h2>
    <p style="color:#94a3b8; font-size:14px;">File: <code>update_20261124_0311_modular_dan_turun_mobil.php</code></p>
    <?php foreach ($logs as $item): ?>
      <div class="log <?= htmlspecialchars($item['type']) ?>"><?= htmlspecialchars($item['msg']) ?></div>
    <?php endforeach; ?>
    <a class="play-btn" href="index.html">🎮 Buka & Mainkan Game Sekarang</a>
  </div>
</body>
</html>
<?php endif; ?>