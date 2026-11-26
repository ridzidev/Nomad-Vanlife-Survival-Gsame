<?php
/**
 * Patcher: Pagar Kayu Grid Minecraft, Api Unggun 3D & Tenda Extension Camper Van
 * Tanggal: 2026-11-26 22:05 WIB
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
$requiredFiles = [
    'index.html',
    'css/style.css',
    'js/data.js',
    'js/state.js',
    'js/van.js',
    'js/player.js',
    'js/ui.js',
    'js/main.js'
];

$missing = [];
foreach ($requiredFiles as $rf) {
    if (!file_exists($baseDir . '/' . $rf)) $missing[] = $rf;
}

if (!empty($missing)) {
    logMsg('File project tidak lengkap: ' . implode(', ', $missing), 'error');
} else {
    // 1. Auto-Backup file target
    $stamp = '20261126_2205';
    foreach ($requiredFiles as $rf) {
        $src = $baseDir . '/' . $rf;
        $bak = $src . '.bak_' . $stamp;
        if (!file_exists($bak)) copy($src, $bak);
    }
    logMsg("Backup file target dibuat (*.bak_{$stamp})", 'ok');

    // 2. Buat file baru js/build.js (Pagar Kayu Grid + Auto-Connect + Api Unggun 3D)
    $buildJs = <<<'NOMAD_END'
// =========================================================================
// SISTEM BANGUNAN SURVIVAL: PAGAR KAYU ALA MINECRAFT & API UNGGUN 3D
// =========================================================================
const BuildSys = {
  GRID_SIZE: 2.0,
  mode: null, // null | 'fence' | 'campfire'
  rot: 0,     // 0 | 1 (orientasi pagar berdiri sendiri)
  entries: [],
  previewGroup: null,
  previewMatOk: null,
  previewMatBad: null,
  lastTarget: null,
  embers: [],
  emberGeo: null,
  emberMat: null,

  COST: {
    fence:    { wood: 1, label: 'Pagar Kayu' },
    campfire: { wood: 2, label: 'Api Unggun' }
  },

  initPreview() {
    if (this.previewGroup) return;
    this.previewMatOk  = new THREE.MeshBasicMaterial({ color: 0x22c55e, transparent: true, opacity: 0.45, wireframe: false });
    this.previewMatBad = new THREE.MeshBasicMaterial({ color: 0xef4444, transparent: true, opacity: 0.45, wireframe: false });
    this.previewGroup = new THREE.Group();
    this.previewGroup.visible = false;
    scene.add(this.previewGroup);

    this.emberGeo = new THREE.BoxGeometry(0.08, 0.08, 0.08);
    this.emberMat = new THREE.MeshBasicMaterial({ color: 0xfbbf24, transparent: true, opacity: 0.85 });
  },

  setMode(newMode) {
    if (newMode && state.player.inVan) {
      showToast('🚶 Turun dari Camper Van [E] dulu untuk membangun!');
      return;
    }
    this.initPreview();
    this.mode = (this.mode === newMode) ? null : newMode;
    this.rebuildPreviewMesh();
    if (this.mode) {
      AudioSys.playTone(460, 0.06);
      showToast(`🔨 Mode Bangun: ${this.COST[this.mode].label} ([F/Klik] Pasang, [R] Putar, [Q] Batal)`);
    } else {
      this.previewGroup.visible = false;
    }
  },

  rotate() {
    if (!this.mode) return;
    this.rot = (this.rot + 1) % 2;
    this.rebuildPreviewMesh();
    AudioSys.playTone(540, 0.05);
  },

  rebuildPreviewMesh() {
    this.initPreview();
    while (this.previewGroup.children.length) this.previewGroup.remove(this.previewGroup.children[0]);
    if (!this.mode) return;

    const m = this.previewMatOk;
    if (this.mode === 'fence') {
      const post = new THREE.Mesh(new THREE.BoxGeometry(0.26, 1.25, 0.26), m);
      post.position.y = 0.625;
      const rTop = new THREE.Mesh(new THREE.BoxGeometry(this.rot === 0 ? 2.0 : 0.14, 0.16, this.rot === 0 ? 0.14 : 2.0), m);
      rTop.position.y = 0.96;
      const rBot = rTop.clone();
      rBot.position.y = 0.48;
      this.previewGroup.add(post, rTop, rBot);
    } else if (this.mode === 'campfire') {
      const ring = new THREE.Mesh(new THREE.CylinderGeometry(0.7, 0.78, 0.2, 8), m);
      ring.position.y = 0.1;
      const flame = new THREE.Mesh(new THREE.ConeGeometry(0.32, 0.85, 6), m);
      flame.position.y = 0.55;
      this.previewGroup.add(ring, flame);
    }
  },

  getTargetSpot() {
    if (!this.mode || state.player.inVan) return null;
    const pl = state.player;
    const lookX = -Math.sin(camAngle);
    const lookZ = -Math.cos(camAngle);
    const rawX = pl.x + lookX * 2.6;
    const rawZ = pl.z + lookZ * 2.6;

    let gx = null, gz = null, x, z;
    if (this.mode === 'fence') {
      gx = Math.round(rawX / this.GRID_SIZE);
      gz = Math.round(rawZ / this.GRID_SIZE);
      x = gx * this.GRID_SIZE;
      z = gz * this.GRID_SIZE;
    } else {
      x = Math.round(rawX * 2) / 2;
      z = Math.round(rawZ * 2) / 2;
    }

    const y = WorldGen.getTerrainHeight(x, z);
    let valid = true;

    if (y < WATER_LEVEL + 0.1) valid = false;
    if (countItem(state.inventory, 'wood') < this.COST[this.mode].wood) valid = false;

    // Tidak menabrak badan mobil
    const dxV = x - state.van.x, dzV = z - state.van.z;
    if (Math.hypot(dxV, dzV) < 2.3) valid = false;

    // Tidak menumpuk di titik yang sama persis
    for (const e of this.entries) {
      if (this.mode === 'fence' && e.data.type === 'fence' && e.data.gx === gx && e.data.gz === gz) {
        valid = false; break;
      }
      if (Math.hypot(e.data.x - x, e.data.z - z) < 0.8) {
        valid = false; break;
      }
    }

    return { type: this.mode, gx, gz, x, y, z, rot: this.rot, valid };
  },

  findFenceAt(gx, gz) {
    return this.entries.find(e => e.data.type === 'fence' && e.data.gx === gx && e.data.gz === gz);
  },

  buildFenceMesh(data) {
    const g = new THREE.Group();
    g.position.set(data.x, data.y, data.z);

    // Tiang utama di tengah sel grid
    const post = new THREE.Mesh(new THREE.BoxGeometry(0.24, 1.25, 0.24), MAT_WOOD);
    post.position.y = 0.625;
    post.castShadow = true; post.receiveShadow = true;
    const cap = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.08, 0.3), MAT_WOOD);
    cap.position.y = 1.26;
    g.add(post, cap);

    const dirs = [
      { dx:  1, dz: 0, axis: 'x', sign:  1 },
      { dx: -1, dz: 0, axis: 'x', sign: -1 },
      { dx:  0, dz: 1, axis: 'z', sign:  1 },
      { dx:  0, dz:-1, axis: 'z', sign: -1 }
    ];

    let hasConn = false;
    const halfSpan = this.GRID_SIZE * 0.5;

    for (const d of dirs) {
      const nb = this.findFenceAt(data.gx + d.dx, data.gz + d.dz);
      if (!nb) continue;
      hasConn = true;
      const dy = nb.data.y - data.y;
      const segLen = Math.hypot(halfSpan, dy * 0.5) + 0.06;
      const slope = Math.atan2(dy, this.GRID_SIZE);

      [0.48, 0.96].forEach(ry => {
        const railGeo = d.axis === 'x'
          ? new THREE.BoxGeometry(segLen, 0.14, 0.11)
          : new THREE.BoxGeometry(0.11, 0.14, segLen);
        const rail = new THREE.Mesh(railGeo, MAT_WOOD);
        rail.castShadow = true;
        if (d.axis === 'x') {
          rail.position.set(d.sign * halfSpan * 0.5, ry + dy * 0.25, 0);
          rail.rotation.z = d.sign * slope;
        } else {
          rail.position.set(0, ry + dy * 0.25, d.sign * halfSpan * 0.5);
          rail.rotation.x = -d.sign * slope;
        }
        g.add(rail);
      });
    }

    // Jika berdiri sendiri (belum tersambung tetangga), gambar panel pagar sesuai rotasi [R]
    if (!hasConn) {
      const isX = (data.rot || 0) === 0;
      [-0.86, 0.86].forEach(off => {
        const endPost = new THREE.Mesh(new THREE.BoxGeometry(0.18, 1.15, 0.18), MAT_WOOD);
        endPost.position.set(isX ? off : 0, 0.575, isX ? 0 : off);
        endPost.castShadow = true;
        g.add(endPost);
      });
      [0.48, 0.96].forEach(ry => {
        const railGeo = isX
          ? new THREE.BoxGeometry(1.88, 0.14, 0.1)
          : new THREE.BoxGeometry(0.1, 0.14, 1.88);
        const rail = new THREE.Mesh(railGeo, MAT_WOOD);
        rail.position.y = ry;
        rail.castShadow = true;
        g.add(rail);
      });
    }

    return g;
  },

  buildCampfireMesh(data) {
    const g = new THREE.Group();
    g.position.set(data.x, data.y, data.z);

    // Lingkaran batu
    for (let i = 0; i < 7; i++) {
      const a = (i / 7) * Math.PI * 2;
      const st = new THREE.Mesh(new THREE.DodecahedronGeometry(0.22), MAT_ROCK);
      st.scale.set(1.2, 0.75, 1.0);
      st.position.set(Math.cos(a) * 0.62, 0.12, Math.sin(a) * 0.62);
      st.rotation.y = a;
      st.castShadow = true;
      g.add(st);
    }

    // Kayu silang
    for (let i = 0; i < 4; i++) {
      const log = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.09, 1.05, 6), MAT_WOOD);
      log.rotation.z = Math.PI / 2 - 0.25;
      log.rotation.y = (i / 4) * Math.PI;
      log.position.y = 0.18;
      log.castShadow = true;
      g.add(log);
    }

    // Bara merah
    const ember = new THREE.Mesh(
      new THREE.CylinderGeometry(0.36, 0.42, 0.08, 8),
      new THREE.MeshBasicMaterial({ color: 0xea580c })
    );
    ember.position.y = 0.06;
    g.add(ember);

    // 3 Lidah api animasi
    const flames = [];
    const flameSpecs = [
      { r: 0.26, h: 0.78, col: 0xf97316, x: 0, z: 0 },
      { r: 0.18, h: 0.56, col: 0xfacc15, x: 0.1, z: 0.06 },
      { r: 0.16, h: 0.52, col: 0xef4444, x: -0.09, z: -0.05 }
    ];
    flameSpecs.forEach((fs, idx) => {
      const fm = new THREE.Mesh(
        new THREE.ConeGeometry(fs.r, fs.h, 6),
        new THREE.MeshBasicMaterial({ color: fs.col, transparent: true, opacity: 0.9 })
      );
      fm.position.set(fs.x, 0.15 + fs.h * 0.45, fs.z);
      g.add(fm);
      flames.push({ mesh: fm, baseY: fm.position.y, phase: idx * 2.1 });
    });

    // Cahaya penerangan malam
    const light = new THREE.PointLight(0xff8c3b, 2.2, 20, 1.4);
    light.position.set(0, 1.15, 0);
    g.add(light);

    return { group: g, light, flames };
  },

  spawnEntry(data) {
    if (data.type === 'fence') {
      const mesh = this.buildFenceMesh(data);
      scene.add(mesh);
      const entry = { data, mesh };
      this.entries.push(entry);
      return entry;
    } else {
      const built = this.buildCampfireMesh(data);
      scene.add(built.group);
      const entry = { data, mesh: built.group, light: built.light, flames: built.flames };
      this.entries.push(entry);
      return entry;
    }
  },

  refreshFenceAndNeighbors(gx, gz) {
    const targets = [
      [gx, gz], [gx + 1, gz], [gx - 1, gz], [gx, gz + 1], [gx, gz - 1]
    ];
    for (const [tx, tz] of targets) {
      const entry = this.findFenceAt(tx, tz);
      if (!entry) continue;
      scene.remove(entry.mesh);
      entry.mesh.traverse(o => { if (o.geometry) o.geometry.dispose(); });
      entry.mesh = this.buildFenceMesh(entry.data);
      scene.add(entry.mesh);
    }
  },

  restoreAll() {
    for (const e of this.entries) {
      scene.remove(e.mesh);
    }
    this.entries = [];
    if (!Array.isArray(state.placedBuilds)) state.placedBuilds = [];
    for (const b of state.placedBuilds) {
      if (b.y === undefined) b.y = WorldGen.getTerrainHeight(b.x, b.z);
      this.spawnEntry(b);
    }
    // Refresh semua pagar agar koneksi terpasang rapi
    for (const e of this.entries) {
      if (e.data.type === 'fence') this.refreshFenceAndNeighbors(e.data.gx, e.data.gz);
    }
  },

  placeCurrent() {
    if (!this.mode) return false;
    const spot = this.getTargetSpot();
    if (!spot) return false;

    const costWood = this.COST[spot.type].wood;
    if (countItem(state.inventory, 'wood') < costWood) {
      showToast(`⚠️ Butuh ${costWood}x Kayu Bakar untuk membuat ${this.COST[spot.type].label}!`);
      return false;
    }
    if (!spot.valid) {
      showToast('⚠️ Tidak bisa memasang di lokasi ini (terlalu dekat mobil / air / bangunan lain)!');
      return false;
    }

    consumeItem(state.inventory, 'wood', costWood);
    const data = {
      id: 'b_' + Date.now() + '_' + Math.floor(Math.random() * 999),
      type: spot.type,
      gx: spot.gx,
      gz: spot.gz,
      x: spot.x,
      y: spot.y,
      z: spot.z,
      rot: spot.rot,
      fuel: 100
    };
    state.placedBuilds.push(data);
    this.spawnEntry(data);
    if (data.type === 'fence') this.refreshFenceAndNeighbors(data.gx, data.gz);

    AudioSys.cook();
    showToast(data.type === 'fence' ? '🪵 Pagar Kayu terpasang!' : '🔥 Api Unggun dinyalakan! Menerangi area malam.');
    saveGameState();
    return true;
  },

  dismantleNearest() {
    if (state.player.inVan) return;
    const px = state.player.x, pz = state.player.z;
    let bestIdx = -1, bestDist = 3.6;
    for (let i = 0; i < this.entries.length; i++) {
      const d = Math.hypot(px - this.entries[i].data.x, pz - this.entries[i].data.z);
      if (d < bestDist) { bestDist = d; bestIdx = i; }
    }
    if (bestIdx === -1) {
      showToast('ℹ️ Tidak ada pagar / api unggun di dekatmu untuk dibongkar.');
      return;
    }

    const entry = this.entries[bestIdx];
    const data = entry.data;
    scene.remove(entry.mesh);
    entry.mesh.traverse(o => { if (o.geometry) o.geometry.dispose(); });
    this.entries.splice(bestIdx, 1);

    const sIdx = state.placedBuilds.findIndex(b => b.id === data.id);
    if (sIdx !== -1) state.placedBuilds.splice(sIdx, 1);

    if (data.type === 'fence') this.refreshFenceAndNeighbors(data.gx, data.gz);

    addItemToList(state.inventory, 'wood', 1, 100);
    AudioSys.pickup();
    showToast(`🪓 Membongkar ${this.COST[data.type].label} (+1x Kayu Bakar)`);
    saveGameState();
  },

  resolvePlayerCollision(x, z, r, playerY) {
    let rx = x, rz = z;
    for (const e of this.entries) {
      const d = e.data;
      if (d.type === 'fence') {
        // Bisa dilompati jika melompat di atas pagar (tinggi pagar 1.25m)
        if (playerY >= d.y + 1.12) continue;
        const hasX = this.findFenceAt(d.gx + 1, d.gz) || this.findFenceAt(d.gx - 1, d.gz);
        const hasZ = this.findFenceAt(d.gx, d.gz + 1) || this.findFenceAt(d.gx, d.gz - 1);
        const alone = !hasX && !hasZ;
        const hw = (hasX || (alone && d.rot === 0)) ? 1.02 + r : 0.22 + r;
        const hd = (hasZ || (alone && d.rot === 1)) ? 1.02 + r : 0.22 + r;
        const dx = rx - d.x, dz = rz - d.z;
        if (Math.abs(dx) < hw && Math.abs(dz) < hd) {
          const ox = hw - Math.abs(dx);
          const oz = hd - Math.abs(dz);
          if (ox < oz) rx += dx >= 0 ? ox : -ox;
          else rz += dz >= 0 ? oz : -oz;
        }
      } else if (d.type === 'campfire') {
        const dx = rx - d.x, dz = rz - d.z;
        const dist = Math.hypot(dx, dz);
        const minD = 0.72 + r;
        if (dist < minD && dist > 0.001) {
          rx += (dx / dist) * (minD - dist);
          rz += (dz / dist) * (minD - dist);
        }
      }
    }
    return { x: rx, z: rz };
  },

  resolveVanCollision(x, z, r) {
    let rx = x, rz = z, hit = false;
    for (const e of this.entries) {
      const d = e.data;
      const minD = (d.type === 'fence' ? 0.95 : 0.75) + r;
      const dx = rx - d.x, dz = rz - d.z;
      const distSq = dx * dx + dz * dz;
      if (distSq < minD * minD && distSq > 0.0001) {
        const dist = Math.sqrt(distSq);
        rx += (dx / dist) * (minD - dist);
        rz += (dz / dist) * (minD - dist);
        hit = true;
      }
    }
    return { x: rx, z: rz, hit };
  },

  getNearestInteract(px, pz) {
    let best = null, bestDist = 3.5;
    for (const e of this.entries) {
      const dist = Math.hypot(px - e.data.x, pz - e.data.z);
      if (dist < bestDist) {
        bestDist = dist;
        best = {
          kind: e.data.type === 'campfire' ? 'campfire' : 'fence_build',
          entry: e,
          label: e.data.type === 'campfire'
            ? `🔥 [F] Masak di Api Unggun (${Math.round(e.data.fuel)}%) • [X] Bongkar`
            : `🪵 [X] Bongkar Pagar Kayu`
        };
      }
    }
    return best;
  },

  isNearLitCampfire(px, pz) {
    for (const e of this.entries) {
      if (e.data.type === 'campfire' && e.data.fuel > 0) {
        if (Math.hypot(px - e.data.x, pz - e.data.z) <= 6.0) return e;
      }
    }
    return null;
  },

  update(dt) {
    this.initPreview();

    // Update ghost preview
    if (this.mode && !state.player.inVan && !activeModal) {
      const spot = this.getTargetSpot();
      if (spot) {
        this.previewGroup.visible = true;
        this.previewGroup.position.set(spot.x, spot.y, spot.z);
        const mat = spot.valid ? this.previewMatOk : this.previewMatBad;
        this.previewGroup.traverse(o => { if (o.isMesh) o.material = mat; });
        this.lastTarget = spot;
      } else {
        this.previewGroup.visible = false;
      }
    } else if (this.previewGroup) {
      this.previewGroup.visible = false;
    }

    // Animasi api unggun, bahan bakar & partikel bara api
    const now = performance.now() * 0.008;
    const px = state.player.inVan ? state.van.x : state.player.x;
    const pz = state.player.inVan ? state.van.z : state.player.z;

    for (const e of this.entries) {
      if (e.data.type !== 'campfire') continue;
      if (e.data.fuel > 0) {
        e.data.fuel = Math.max(0, e.data.fuel - 0.14 * dt);
      }
      const lit = e.data.fuel > 0;
      const dist = Math.hypot(px - e.data.x, pz - e.data.z);

      e.flames.forEach(f => {
        f.mesh.visible = lit;
        if (lit) {
          const s = 0.85 + Math.sin(now + f.phase) * 0.18 + Math.cos(now * 1.7 + f.phase) * 0.1;
          f.mesh.scale.set(1, s, 1);
          f.mesh.position.y = f.baseY * s;
        }
      });

      if (e.light) {
        e.light.visible = lit && dist < 42;
        if (e.light.visible) {
          e.light.intensity = (1.9 + Math.sin(now * 1.3 + e.data.x) * 0.35) * (0.4 + 0.6 * (e.data.fuel / 100));
        }
      }

      if (lit && dist < 24 && Math.random() < 0.18 && this.embers.length < 36) {
        const em = new THREE.Mesh(this.emberGeo, this.emberMat);
        em.position.set(
          e.data.x + (Math.random() - 0.5) * 0.35,
          e.data.y + 0.5,
          e.data.z + (Math.random() - 0.5) * 0.35
        );
        scene.add(em);
        this.embers.push({ mesh: em, life: 1.1, vx: (Math.random() - 0.5) * 0.5, vz: (Math.random() - 0.5) * 0.5 });
      }
    }

    for (let i = this.embers.length - 1; i >= 0; i--) {
      const em = this.embers[i];
      em.life -= dt;
      em.mesh.position.y += dt * 1.6;
      em.mesh.position.x += em.vx * dt;
      em.mesh.position.z += em.vz * dt;
      em.mesh.scale.setScalar(Math.max(0.1, em.life));
      if (em.life <= 0) {
        scene.remove(em.mesh);
        this.embers.splice(i, 1);
      }
    }
  }
};

function toggleBuildMode(forceType = null) {
  if (forceType) BuildSys.setMode(forceType);
  else BuildSys.setMode(BuildSys.mode ? null : 'fence');
}
NOMAD_END;

    if (file_put_contents($baseDir . '/js/build.js', $buildJs) !== false) {
        logMsg('File baru dibuat: js/build.js', 'ok');
    } else {
        logMsg('Gagal membuat js/build.js', 'error');
    }

    // Helper patcher
    function patchFile($relPath, $patches) {
        global $baseDir;
        $full = $baseDir . '/' . $relPath;
        $content = file_get_contents($full);
        foreach ($patches as $p) {
            if (!empty($p['skipIf']) && strpos($content, $p['skipIf']) !== false) {
                logMsg("{$relPath}: [{$p['name']}] sudah terpasang, dilewati.", 'info');
                continue;
            }
            if (strpos($content, $p['anchor']) !== false) {
                $content = str_replace($p['anchor'], $p['replace'], $content);
                logMsg("{$relPath}: [{$p['name']}] berhasil di-patch.", 'ok');
            } else {
                logMsg("{$relPath}: Anchor [{$p['name']}] tidak ditemukan!", 'error');
            }
        }
        file_put_contents($full, $content);
    }

    // 3. Patch js/state.js
    patchFile('js/state.js', [
        [
            'name' => 'Tambah tentOpen pada van state',
            'skipIf' => 'tentOpen:',
            'anchor' => 'doorOpen: false,',
            'replace' => "doorOpen: false,\n      tentOpen: false,"
        ],
        [
            'name' => 'Tambah placedBuilds pada root state',
            'skipIf' => 'placedBuilds:',
            'anchor' => 'lootedIds: {},',
            'replace' => "lootedIds: {},\n    placedBuilds: [],"
        ],
        [
            'name' => 'Backfill tentOpen & placedBuilds untuk save lama',
            'skipIf' => 'parsed.placedBuilds',
            'anchor' => 'if (parsed.van.rainCollector === undefined) parsed.van.rainCollector = 0;',
            'replace' => "if (parsed.van.rainCollector === undefined) parsed.van.rainCollector = 0;\n        if (parsed.van.tentOpen === undefined) parsed.van.tentOpen = false;\n        if (!Array.isArray(parsed.placedBuilds)) parsed.placedBuilds = [];"
        ]
    ]);

    // 4. Patch js/data.js (Tambah Tenda Extension di daftar Dekorasi/Rakit)
    patchFile('js/data.js', [
        [
            'name' => 'Tambah Tenda Extension Camper pada DECORS',
            'skipIf' => 'tentExtension',
            'anchor' => "{ id: 'spareTire',   name: 'Ban Cadangan & Tangga Belakang', icon: '🛞', cost: { scrap: 2 }, desc: 'Tampilan ekspedisi tangguh di pintu belakang.' }",
            'replace' => "{ id: 'spareTire',   name: 'Ban Cadangan & Tangga Belakang', icon: '🛞', cost: { scrap: 2 }, desc: 'Tampilan ekspedisi tangguh di pintu belakang.' },\n  { id: 'tentExtension', name: 'Tenda Extension Camper', icon: '⛺', cost: { wood: 3, scrap: 1 }, desc: 'Ruang tenda samping di pintu geser camper! Tekan [T] untuk pasang/lipat.' }"
        ]
    ]);

    // 5. Patch js/van.js (Model 3D Tenda Extension + Kunci Jalan saat Tenda Terbuka + Kolisi Bangunan)
    $tentBuildCode = <<<'NOMAD_END'
  syncVanVisuals();
  buildTentExtension();
}

function buildTentExtension() {
  if (vanTentGroup) vanGroup.remove(vanTentGroup);
  vanTentGroup = new THREE.Group();

  const matCanvas  = new THREE.MeshStandardMaterial({ color: 0xd97706, roughness: 0.85, side: THREE.DoubleSide });
  const matWall    = new THREE.MeshStandardMaterial({ color: 0xfef3c7, roughness: 0.85, side: THREE.DoubleSide });
  const matMeshWin = new THREE.MeshStandardMaterial({ color: 0x334155, transparent: true, opacity: 0.45, side: THREE.DoubleSide });
  const matMat     = new THREE.MeshStandardMaterial({ color: 0x1e3a8a, roughness: 0.95 });
  const matTable   = new THREE.MeshStandardMaterial({ color: 0x92400e, roughness: 0.8 });

  // Terpal alas lantai tenda (di samping kiri pintu geser camper: x = -1.08 .. -4.08)
  const floorMat = new THREE.Mesh(new THREE.BoxGeometry(3.0, 0.05, 2.9), matMat);
  floorMat.position.set(-2.58, 0.04, 0.25);
  floorMat.receiveShadow = true;
  vanTentGroup.add(floorMat);

  // Atap kanopi tenda miring menyambung dari atap van (x=-1.08, y=2.24 -> x=-4.08, y=1.95)
  const roofTent = new THREE.Mesh(new THREE.BoxGeometry(3.08, 0.07, 2.96), matCanvas);
  roofTent.position.set(-2.58, 2.1, 0.25);
  roofTent.rotation.z = 0.095;
  roofTent.castShadow = true;
  vanTentGroup.add(roofTent);

  // 2 Tiang penyangga luar + tiang penghubung ke atap van
  [-1.16, 1.66].forEach(pz => {
    const pole = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.035, 1.96, 8), vanMaterials.chrome);
    pole.position.set(-4.04, 0.98, pz);
    pole.castShadow = true;
    const beam = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 3.04, 8), vanMaterials.chrome);
    beam.rotation.z = Math.PI / 2 + 0.095;
    beam.position.set(-2.56, 2.08, pz);
    vanTentGroup.add(pole, beam);
  });

  // Dinding luar tenda (x = -4.05) + Jendela jaring besar
  const outerLower = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.9, 2.86), matWall);
  outerLower.position.set(-4.05, 0.48, 0.25);
  outerLower.castShadow = true;
  const outerWin = new THREE.Mesh(new THREE.PlaneGeometry(2.4, 0.95), matMeshWin);
  outerWin.position.set(-4.05, 1.42, 0.25);
  outerWin.rotation.y = Math.PI / 2;
  vanTentGroup.add(outerLower, outerWin);

  // Dinding belakang tenda (z = -1.16)
  const backWall = new THREE.Mesh(new THREE.BoxGeometry(2.96, 1.92, 0.06), matWall);
  backWall.position.set(-2.58, 0.99, -1.16);
  backWall.castShadow = true;
  vanTentGroup.add(backWall);

  // Bingkai pintu masuk tenda di depan (z = +1.66) dengan lubang pintu terbuka
  const frontSideLeft = new THREE.Mesh(new THREE.BoxGeometry(1.15, 1.92, 0.06), matWall);
  frontSideLeft.position.set(-3.48, 0.99, 1.66);
  const frontHeader = new THREE.Mesh(new THREE.BoxGeometry(1.85, 0.36, 0.06), matCanvas);
  frontHeader.position.set(-2.0, 1.84, 1.66);
  vanTentGroup.add(frontSideLeft, frontHeader);

  // Furnitur santai dalam tenda: Meja lipat, 2 kursi & lentera gantung hangat
  const tableTop = new THREE.Mesh(new THREE.BoxGeometry(0.95, 0.05, 1.25), matTable);
  tableTop.position.set(-2.85, 0.68, -0.35);
  tableTop.castShadow = true;
  const tableLeg = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, 0.65, 6), vanMaterials.chrome);
  tableLeg.position.set(-2.85, 0.35, -0.35);
  vanTentGroup.add(tableTop, tableLeg);

  [-0.95, 0.25].forEach(cz => {
    const chairSeat = new THREE.Mesh(new THREE.BoxGeometry(0.45, 0.06, 0.45), matCanvas);
    chairSeat.position.set(-2.15, 0.42, cz);
    const chairLeg = new THREE.Mesh(new THREE.BoxGeometry(0.4, 0.38, 0.4), vanMaterials.chrome);
    chairLeg.position.set(-2.15, 0.21, cz);
    vanTentGroup.add(chairSeat, chairLeg);
  });

  const lanternBulb = new THREE.Mesh(new THREE.SphereGeometry(0.09, 8, 8), vanMaterials.fairy);
  lanternBulb.position.set(-2.6, 1.92, 0.25);
  vanTentLight = new THREE.PointLight(0xfde68a, 0, 11);
  vanTentLight.position.set(-2.6, 1.82, 0.25);
  vanTentGroup.add(lanternBulb, vanTentLight);

  vanTentGroup.visible = false;
  vanGroup.add(vanTentGroup);
}
NOMAD_END;

    patchFile('js/van.js', [
        [
            'name' => 'Deklarasi variabel vanTentGroup & tentDeployProgress',
            'skipIf' => 'vanTentGroup',
            'anchor' => 'let liveryMeshLeft, liveryMeshRight;',
            'replace' => "let liveryMeshLeft, liveryMeshRight;\nlet vanTentGroup, vanTentLight;\nlet tentDeployProgress = 0;"
        ],
        [
            'name' => 'Tambah buildTentExtension() pada akhir buildCamperVan()',
            'skipIf' => 'function buildTentExtension()',
            'anchor' => "  syncVanVisuals();\n}",
            'replace' => $tentBuildCode
        ],
        [
            'name' => 'Kunci mengemudi saat Tenda Extension masih terbuka',
            'skipIf' => 'van.tentOpen && (gas || rev)',
            'anchor' => 'const hasFuel = van.fuel > 0 && van.hp > 5;',
            'replace' => "if (van.tentOpen && (gas || rev)) {\n      van.speed = 0;\n      if (Math.random() < 0.05) showToast('⛺ Lipat Tenda Extension [T] dulu sebelum jalan!');\n    }\n    const hasFuel = van.fuel > 0 && van.hp > 5 && !van.tentOpen;"
        ],
        [
            'name' => 'Tambah kolisi camper van dengan pagar/api unggun',
            'skipIf' => 'BuildSys.resolveVanCollision',
            'anchor' => 'const col = resolveCollisions(van.x + vx * dt, van.z + vz * dt, 1.55);',
            'replace' => "let col = resolveCollisions(van.x + vx * dt, van.z + vz * dt, 1.55);\n    if (typeof BuildSys !== 'undefined') {\n      const bCol = BuildSys.resolveVanCollision(col.x, col.z, 1.55);\n      if (bCol.hit) col = { x: bCol.x, z: bCol.z, hit: true };\n    }"
        ],
        [
            'name' => 'Animasi buka/lipat 3D Tenda Extension & lampu tenda',
            'skipIf' => 'tentDeployProgress',
            'anchor' => "    vanSideDoorGroup.position.z = 0.35 - doorSlideProgress * 1.18;\n  }",
            'replace' => "    vanSideDoorGroup.position.z = 0.35 - doorSlideProgress * 1.18;\n  }\n\n  // Animasi Tenda Extension Camper\n  const targetTent = (state.van.tentOpen && state.van.unlockedDecor.tentExtension) ? 1 : 0;\n  tentDeployProgress += (targetTent - tentDeployProgress) * Math.min(1, dt * 6);\n  if (vanTentGroup) {\n    vanTentGroup.visible = tentDeployProgress > 0.02;\n    if (vanTentGroup.visible) {\n      vanTentGroup.scale.set(Math.max(0.05, tentDeployProgress), 1, 1);\n      vanTentGroup.position.x = (1 - tentDeployProgress) * -1.05;\n    }\n    if (vanTentLight) {\n      const nightFactor = Math.max(0, 1 - Math.max(0, Math.sin((state.timeOfDay - 0.25) * Math.PI * 2) * 2 + 0.2));\n      vanTentLight.intensity = tentDeployProgress * (0.3 + nightFactor * 1.4);\n    }\n  }"
        ]
    ]);

    // 6. Patch js/player.js (Deteksi dalam Tenda, Kolisi Pagar/Api Unggun, Aksi Bangun & Masak Api Unggun)
    $tentHelperCode = <<<'NOMAD_END'
function isPlayerInTent() {
  if (state.player.inVan || !state.van.tentOpen || !state.van.unlockedDecor.tentExtension) return false;
  const dx = state.player.x - state.van.x;
  const dz = state.player.z - state.van.z;
  const cosY = Math.cos(-state.van.yaw), sinY = Math.sin(-state.van.yaw);
  const lx = dx * cosY - dz * sinY;
  const lz = dx * sinY + dz * cosY;
  return lx >= -4.25 && lx <= -1.0 && lz >= -1.2 && lz <= 1.7;
}

function toggleTentExtension() {
  if (!state.van.unlockedDecor.tentExtension) {
    if (countItem(state.inventory, 'wood') >= 3 && countItem(state.inventory, 'scrap') >= 1) {
      consumeItem(state.inventory, 'wood', 3);
      consumeItem(state.inventory, 'scrap', 1);
      state.van.unlockedDecor.tentExtension = true;
      state.van.activeDecor.tentExtension = true;
      state.van.tentOpen = true;
      state.van.doorOpen = true;
      state.van.speed = 0;
      AudioSys.cook();
      showToast('⛺ Berhasil merakit & memasang Tenda Extension Camper!');
      saveGameState();
    } else {
      showToast('⛺ Untuk bikin Tenda Extension butuh 3x Kayu Bakar & 1x Besi Tua (Scrap)!');
    }
    return;
  }
  if (!state.van.tentOpen && Math.abs(state.van.speed) > 2.5) {
    showToast('⚠️ Hentikan mobil dulu sebelum memasang Tenda Extension!');
    return;
  }
  state.van.tentOpen = !state.van.tentOpen;
  if (state.van.tentOpen) {
    state.van.speed = 0;
    state.van.doorOpen = true;
  }
  AudioSys.playTone(state.van.tentOpen ? 360 : 260, 0.12);
  showToast(state.van.tentOpen ? '⛺ Tenda Extension dipasang di sisi Camper Van!' : '⛺ Tenda Extension dilipat, siap jalan!');
  saveGameState();
}
NOMAD_END;

    patchFile('js/player.js', [
        [
            'name' => 'Tambah isPlayerInTent & toggleTentExtension',
            'skipIf' => 'function toggleTentExtension()',
            'anchor' => 'function toggleVanDoor() {',
            'replace' => $tentHelperCode . "\n\nfunction toggleVanDoor() {"
        ],
        [
            'name' => 'Matikan mode bangun saat naik ke dalam mobil',
            'skipIf' => 'BuildSys.setMode(null)',
            'anchor' => "    state.player.inVan = true;\n    AudioSys.playTone(340, 0.08);",
            'replace' => "    state.player.inVan = true;\n    if (typeof BuildSys !== 'undefined') BuildSys.setMode(null);\n    AudioSys.playTone(340, 0.08);"
        ],
        [
            'name' => 'Tambah kolisi pemain dengan pagar kayu & api unggun',
            'skipIf' => 'BuildSys.resolvePlayerCollision',
            'anchor' => "    const vanCol = resolvePlayerVanCollision(worldCol.x, worldCol.z, 0.42);\n    pl.x = vanCol.x;\n    pl.z = vanCol.z;",
            'replace' => "    const vanCol = resolvePlayerVanCollision(worldCol.x, worldCol.z, 0.42);\n    const buildCol = (typeof BuildSys !== 'undefined')\n      ? BuildSys.resolvePlayerCollision(vanCol.x, vanCol.z, 0.42, pl.y)\n      : { x: vanCol.x, z: vanCol.z };\n    pl.x = buildCol.x;\n    pl.z = buildCol.z;"
        ],
        [
            'name' => 'Prioritaskan prompt Mode Bangun & interaksi Api Unggun / Pagar',
            'skipIf' => 'BuildSys.getNearestInteract',
            'anchor' => "  if (!best && !state.player.inVan) {\n    const dv = Math.hypot(px - state.van.x, pz - state.van.z);",
            'replace' => "  if (typeof BuildSys !== 'undefined' && BuildSys.mode && !state.player.inVan) {\n    best = { kind: 'build_place', label: '🔨 [F / Klik] Pasang • [R] Putar • [Q] Batal' };\n  }\n\n  if (!best && !state.player.inVan && typeof BuildSys !== 'undefined') {\n    best = BuildSys.getNearestInteract(px, pz);\n  }\n\n  if (!best && !state.player.inVan) {\n    const dv = Math.hypot(px - state.van.x, pz - state.van.z);"
        ],
        [
            'name' => 'Handle triggerInteract untuk memasang bangunan & Api Unggun',
            'skipIf' => "it.kind === 'campfire'",
            'anchor' => "  if (it.kind === 'van_door') { openInventoryModal(); return; }",
            'replace' => "  if (it.kind === 'build_place') { BuildSys.placeCurrent(); return; }\n\n  if (it.kind === 'campfire') {\n    if (it.entry.data.fuel < 70 && countItem(state.inventory, 'wood') > 0) {\n      consumeItem(state.inventory, 'wood', 1);\n      it.entry.data.fuel = Math.min(100, it.entry.data.fuel + 60);\n      showToast('🔥 Menambah 1x Kayu ke Api Unggun & membuka menu masak!');\n      saveGameState();\n    } else {\n      showToast('🔥 Memasak dengan kehangatan Api Unggun!');\n    }\n    openInventoryModal();\n    return;\n  }\n\n  if (it.kind === 'van_door') { openInventoryModal(); return; }"
        ]
    ]);

    // 7. Patch js/ui.js (Perbaiki bug sMult, Suara Hujan dalam Tenda, Kehangatan Api Unggun & HUD Bangun/Tenda)
    patchFile('js/ui.js', [
        [
            'name' => 'Perbaiki sMult & tambah suara hujan adaptif dalam tenda/kabin',
            'skipIf' => 'const sMult =',
            'anchor' => "  if (state.van.activeDecor.solarPanel && isDay) {\n    state.van.battery = Math.min(getMaxBat(), state.van.battery + 1.4 * sunY * sMult * dt);\n  }",
            'replace' => "  const inTent = (typeof isPlayerInTent === 'function') ? isPlayerInTent() : false;\n  if (typeof WeatherSys !== 'undefined') {\n    AudioSys.updateRain(WeatherSys.getRainVol(), state.player.inVan || inCabinView || inTent);\n  }\n  if (state.van.activeDecor.solarPanel && isDay) {\n    const sMult = (typeof WeatherSys !== 'undefined') ? WeatherSys.getSolarMult() : 1;\n    state.van.battery = Math.min(getMaxBat(), state.van.battery + 1.4 * sunY * sMult * dt);\n  }"
        ],
        [
            'name' => 'Tambah efek berteduh di Tenda Extension & kehangatan Api Unggun',
            'skipIf' => 'nearFire',
            'anchor' => "  const thirstMult = biome === 'gurun' ? 1.7 : 1.0;\n  state.player.hunger = Math.max(0, state.player.hunger - 0.32 * dt);\n  state.player.thirst = Math.max(0, state.player.thirst - 0.45 * thirstMult * dt);\n  if (state.player.inVan) state.player.stamina = Math.min(100, state.player.stamina + 14 * dt);",
            'replace' => "  const nearFire = (typeof BuildSys !== 'undefined') ? BuildSys.isNearLitCampfire(px, pz) : null;\n  const thirstMult = (biome === 'gurun' && !inTent && !state.player.inVan) ? 1.7 : 1.0;\n  state.player.hunger = Math.max(0, state.player.hunger - 0.32 * dt);\n  state.player.thirst = Math.max(0, state.player.thirst - 0.45 * thirstMult * dt);\n  if (state.player.inVan || inTent || nearFire) {\n    state.player.stamina = Math.min(100, state.player.stamina + (nearFire ? 18 : 13) * dt);\n  }\n  if (nearFire && state.player.hp < 100 && state.player.hunger > 35 && state.player.thirst > 35) {\n    state.player.hp = Math.min(100, state.player.hp + 0.5 * dt);\n  }"
        ],
        [
            'name' => 'Otomatis buka Tenda Extension saat selesai dirakit di menu Hias Mobil',
            'skipIf' => "if (id === 'tentExtension') state.van.tentOpen = true;",
            'anchor' => "  state.van.unlockedDecor[id] = true;\n  state.van.activeDecor[id] = true;",
            'replace' => "  state.van.unlockedDecor[id] = true;\n  state.van.activeDecor[id] = true;\n  if (id === 'tentExtension') { state.van.tentOpen = true; state.van.doorOpen = true; state.van.speed = 0; }"
        ],
        [
            'name' => 'Update status tombol Tenda Extension & Build Toolbar di HUD',
            'skipIf' => 'btn-tent-van',
            'anchor' => "  const cBtn = document.getElementById('btn-cabin-view');\n  if (cBtn) cBtn.className = `btn \${inCabinView ? 'success' : ''}`;",
            'replace' => "  const cBtn = document.getElementById('btn-cabin-view');\n  if (cBtn) cBtn.className = `btn \${inCabinView ? 'success' : ''}`;\n  const tBtn = document.getElementById('btn-tent-van');\n  if (tBtn) {\n    tBtn.textContent = !v.unlockedDecor.tentExtension ? '⛺ Bikin Tenda [T]' : (v.tentOpen ? '⛺ Lipat Tenda [T]' : '⛺ Pasang Tenda [T]');\n    tBtn.className = `btn \${v.tentOpen ? 'success' : ''}`;\n  }\n  const bBar = document.getElementById('build-bar');\n  const bBtn = document.getElementById('btn-build-mode');\n  if (typeof BuildSys !== 'undefined' && bBar && bBtn) {\n    bBar.style.display = (BuildSys.mode && !p.inVan) ? 'flex' : 'none';\n    bBtn.className = `btn \${BuildSys.mode ? 'success' : ''}`;\n    const fBtn = document.getElementById('btn-build-fence');\n    const cfBtn = document.getElementById('btn-build-fire');\n    if (fBtn) fBtn.className = `btn \${BuildSys.mode === 'fence' ? 'primary' : ''}`;\n    if (cfBtn) cfBtn.className = `btn \${BuildSys.mode === 'campfire' ? 'primary' : ''}`;\n    const wCount = countItem(state.inventory, 'wood');\n    const wEl2 = document.getElementById('build-wood-count');\n    if (wEl2) wEl2.textContent = `🪵 Kayu: \${wCount}`;\n  }"
        ]
    ]);

    // 8. Patch js/main.js (Shortcut Q, 1, 2, R, X, T + Klik Pasang + Loop BuildSys)
    patchFile('js/main.js', [
        [
            'name' => 'Tambah shortcut bangun Q/1/2/R/X dan Tenda T',
            'skipIf' => "e.code === 'KeyT'",
            'anchor' => "  if (e.code === 'KeyG') toggleVanDoor();\n  if (e.code === 'KeyV') toggleCabinView();",
            'replace' => "  if (e.code === 'KeyG') toggleVanDoor();\n  if (e.code === 'KeyV') toggleCabinView();\n  if (e.code === 'KeyT') toggleTentExtension();\n  if (e.code === 'KeyQ') toggleBuildMode();\n  if (e.code === 'Digit1' && !state.player.inVan) BuildSys.setMode('fence');\n  if (e.code === 'Digit2' && !state.player.inVan) BuildSys.setMode('campfire');\n  if (e.code === 'KeyR' && BuildSys.mode) BuildSys.rotate();\n  if (e.code === 'KeyX' && !state.player.inVan) BuildSys.dismantleNearest();"
        ],
        [
            'name' => 'Tambah Esc untuk keluar dari Mode Bangun lebih dulu',
            'skipIf' => 'BuildSys.mode',
            'anchor' => "  if (e.code === 'Escape') { closeAllModals(); return; }",
            'replace' => "  if (e.code === 'Escape') {\n    if (activeModal) { closeAllModals(); return; }\n    if (typeof BuildSys !== 'undefined' && BuildSys.mode) { BuildSys.setMode(null); return; }\n    return;\n  }"
        ],
        [
            'name' => 'Tambah klik kiri pada canvas untuk memasang pagar/api unggun',
            'skipIf' => 'pointerDownPos',
            'anchor' => "let lastMouseX = 0, lastMouseY = 0;",
            'replace' => "let lastMouseX = 0, lastMouseY = 0;\nlet pointerDownX = 0, pointerDownY = 0;"
        ],
        [
            'name' => 'Simpan koordinat pointerdown & eksekusi BuildSys.placeCurrent saat klik singkat',
            'skipIf' => 'BuildSys.placeCurrent()',
            'anchor' => "canvas.addEventListener('pointerdown', e => {\n  isDragging = true;\n  lastMouseX = e.clientX;\n  lastMouseY = e.clientY;\n  AudioSys.resume();\n});",
            'replace' => "canvas.addEventListener('pointerdown', e => {\n  isDragging = true;\n  lastMouseX = pointerDownX = e.clientX;\n  lastMouseY = pointerDownY = e.clientY;\n  AudioSys.resume();\n});"
        ],
        [
            'name' => 'Trigger pasang bangunan saat pointerup tanpa drag',
            'skipIf' => 'Math.hypot(e.clientX - pointerDownX',
            'anchor' => "window.addEventListener('pointerup', () => { isDragging = false; });",
            'replace' => "window.addEventListener('pointerup', e => {\n  if (isDragging && !activeModal && typeof BuildSys !== 'undefined' && BuildSys.mode && e.target === canvas) {\n    if (Math.hypot(e.clientX - pointerDownX, e.clientY - pointerDownY) < 6) {\n      BuildSys.placeCurrent();\n    }\n  }\n  isDragging = false;\n});"
        ],
        [
            'name' => 'Restore bangunan tersimpan saat start & update animasi di loop',
            'skipIf' => 'BuildSys.restoreAll()',
            'anchor' => "updateChunks();",
            'replace' => "updateChunks();\nif (typeof BuildSys !== 'undefined') BuildSys.restoreAll();"
        ],
        [
            'name' => 'Jalankan BuildSys.update(dt) pada animate loop',
            'skipIf' => 'BuildSys.update(dt)',
            'anchor' => "    updateDustParticles(dt);",
            'replace' => "    updateDustParticles(dt);\n    if (typeof BuildSys !== 'undefined') BuildSys.update(dt);"
        ]
    ]);

    // 9. Patch css/style.css (Styling untuk Build Toolbar)
    patchFile('css/style.css', [
        [
            'name' => 'Tambah styling #build-bar',
            'skipIf' => '#build-bar',
            'anchor' => '/* Prompt */',
            'replace' => "/* Build Toolbar */\n#build-bar {\n  position: fixed; bottom: 162px; left: 50%; transform: translateX(-50%);\n  display: none; gap: 7px; align-items: center; flex-wrap: wrap; justify-content: center;\n  background: var(--bg-glass); backdrop-filter: blur(10px);\n  border: 1px solid var(--accent); border-radius: 14px; padding: 8px 12px;\n  z-index: 16; pointer-events: auto; box-shadow: 0 8px 28px rgba(0,0,0,0.45);\n}\n\n/* Prompt */"
        ]
    ]);

    // 10. Patch index.html (Tombol Tenda [T], Tombol Bangun [Q], Build Toolbar & Load js/build.js)
    patchFile('index.html', [
        [
            'name' => 'Tambah tombol Tenda Extension [T] dan Mode Bangun [Q] di HUD bawah',
            'skipIf' => 'id="btn-tent-van"',
            'anchor' => '<button class="btn" id="btn-cabin-view" onclick="toggleCabinView()" style="min-height:42px;">🛋️ Kabin [V]</button>',
            'replace' => '<button class="btn" id="btn-cabin-view" onclick="toggleCabinView()" style="min-height:42px;">🛋️ Kabin [V]</button>' . "\n        " . '<button class="btn" id="btn-tent-van" onclick="toggleTentExtension()" style="min-height:42px;">⛺ Tenda [T]</button>' . "\n        " . '<button class="btn" id="btn-build-mode" onclick="toggleBuildMode()" style="min-height:42px;">🔨 Bangun [Q]</button>'
        ],
        [
            'name' => 'Update petunjuk kontrol dengan shortcut baru',
            'skipIf' => '<kbd>Q</kbd> Bangun',
            'anchor' => '<div><kbd>G</kbd> Pintu • <kbd>V</kbd> Kabin • <kbd>L</kbd> Lampu • <kbd>H</kbd> Klakson • <kbd>C</kbd> Kamera</div>',
            'replace' => '<div><kbd>G</kbd> Pintu • <kbd>V</kbd> Kabin • <kbd>T</kbd> Tenda • <kbd>Q</kbd> Bangun • <kbd>L</kbd> Lampu • <kbd>C</kbd> Kamera</div>'
        ],
        [
            'name' => 'Tambah floating Build Toolbar sebelum interact-prompt',
            'skipIf' => 'id="build-bar"',
            'anchor' => '<!-- Interact Prompt -->',
            'replace' => "<!-- Build Mode Toolbar -->\n  <div id=\"build-bar\">\n    <span id=\"build-wood-count\" style=\"font-size:12px; font-weight:700; color:#fbbf24; margin-right:4px;\">🪵 Kayu: 0</span>\n    <button class=\"btn\" id=\"btn-build-fence\" onclick=\"BuildSys.setMode('fence')\">🪵 [1] Pagar (1 Kayu)</button>\n    <button class=\"btn\" id=\"btn-build-fire\" onclick=\"BuildSys.setMode('campfire')\">🔥 [2] Api Unggun (2 Kayu)</button>\n    <button class=\"btn\" onclick=\"BuildSys.rotate()\">🔄 Putar [R]</button>\n    <button class=\"btn success\" onclick=\"BuildSys.placeCurrent()\">✅ Pasang [F]</button>\n    <button class=\"btn danger\" onclick=\"BuildSys.dismantleNearest()\">🪓 Bongkar [X]</button>\n    <button class=\"btn\" onclick=\"BuildSys.setMode(null)\">✖ [Q]</button>\n  </div>\n\n  <!-- Interact Prompt -->"
        ],
        [
            'name' => 'Muat script js/build.js sebelum js/player.js',
            'skipIf' => 'js/build.js',
            'anchor' => '<script src="js/player.js"></script>',
            'replace' => "<script src=\"js/build.js\"></script>\n  <script src=\"js/player.js\"></script>"
        ]
    ]);
}

if (!$isCli): ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Patcher: Pagar Kayu, Api Unggun & Tenda Extension</title>
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
    <h2 style="margin-top:0">🪵🔥⛺ Update Pagar, Api Unggun & Tenda Extension Selesai</h2>
    <p style="color:#94a3b8; font-size:14px;">File: <code>update_20261126_2205_pagar_api_unggun_tenda.php</code></p>
    <?php foreach ($logs as $item): ?>
      <div class="log <?= htmlspecialchars($item['type']) ?>"><?= htmlspecialchars($item['msg']) ?></div>
    <?php endforeach; ?>
    <a class="play-btn" href="index.html">🎮 Buka & Coba Sekarang</a>
  </div>
</body>
</html>
<?php endif; ?>