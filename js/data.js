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
  { id: 'spareTire',   name: 'Ban Cadangan & Tangga Belakang', icon: '🛞', cost: { scrap: 2 }, desc: 'Tampilan ekspedisi tangguh di pintu belakang.' },
  { id: 'tentExtension', name: 'Tenda Extension Camper', icon: '⛺', cost: { wood: 3, scrap: 1 }, desc: 'Ruang tenda samping di pintu geser camper! Tekan [T] untuk pasang/lipat.' }
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