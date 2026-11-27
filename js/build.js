// =========================================================================
// SISTEM BANGUNAN SURVIVAL: PAGAR KAYU, PINTU PAGAR & API UNGGUN 3D
// =========================================================================
const BuildSys = {
  GRID_SIZE: 2.0,
  mode: null, // null | 'fence' | 'gate' | 'campfire'
  rot: 0,     // 0 | 1
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
    gate:     { wood: 1, label: 'Pintu Pagar' },
    campfire: { wood: 2, label: 'Api Unggun' }
  },

  initPreview() {
    if (this.previewGroup) return;
    this.previewMatOk  = new THREE.MeshBasicMaterial({ color: 0x22c55e, transparent: true, opacity: 0.45 });
    this.previewMatBad = new THREE.MeshBasicMaterial({ color: 0xef4444, transparent: true, opacity: 0.45 });
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
    } else if (this.mode === 'gate') {
      const isX = this.rot === 0;
      [-0.9, 0.9].forEach(off => {
        const p = new THREE.Mesh(new THREE.BoxGeometry(0.24, 1.25, 0.24), m);
        p.position.set(isX ? off : 0, 0.625, isX ? 0 : off);
        this.previewGroup.add(p);
      });
      const panel = new THREE.Mesh(new THREE.BoxGeometry(isX ? 1.56 : 0.14, 0.78, isX ? 0.14 : 1.56), m);
      panel.position.y = 0.68;
      this.previewGroup.add(panel);
    } else if (this.mode === 'campfire') {
      const ring = new THREE.Mesh(new THREE.CylinderGeometry(0.7, 0.78, 0.2, 8), m);
      ring.position.y = 0.1;
      const flame = new THREE.Mesh(new THREE.ConeGeometry(0.32, 0.85, 6), m);
      flame.position.y = 0.55;
      this.previewGroup.add(ring, flame);
    }
  },

  isGridType(type) {
    return type === 'fence' || type === 'gate';
  },

  getTargetSpot() {
    if (!this.mode || state.player.inVan) return null;
    const pl = state.player;
    const lookX = -Math.sin(camAngle);
    const lookZ = -Math.cos(camAngle);
    const rawX = pl.x + lookX * 2.6;
    const rawZ = pl.z + lookZ * 2.6;

    let gx = null, gz = null, x, z;
    if (this.isGridType(this.mode)) {
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

    const dxV = x - state.van.x, dzV = z - state.van.z;
    if (Math.hypot(dxV, dzV) < 2.3) valid = false;

    for (const e of this.entries) {
      if (this.isGridType(this.mode) && this.isGridType(e.data.type) && e.data.gx === gx && e.data.gz === gz) {
        valid = false; break;
      }
      if (Math.hypot(e.data.x - x, e.data.z - z) < 0.8) {
        valid = false; break;
      }
    }

    return { type: this.mode, gx, gz, x, y, z, rot: this.rot, valid };
  },

  findGridAt(gx, gz) {
    return this.entries.find(e => this.isGridType(e.data.type) && e.data.gx === gx && e.data.gz === gz);
  },

  buildFenceMesh(data) {
    const g = new THREE.Group();
    g.position.set(data.x, data.y, data.z);

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
      const nb = this.findGridAt(data.gx + d.dx, data.gz + d.dz);
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

  getGateAxis(data) {
    const hasX = this.findGridAt(data.gx + 1, data.gz) || this.findGridAt(data.gx - 1, data.gz);
    const hasZ = this.findGridAt(data.gx, data.gz + 1) || this.findGridAt(data.gx, data.gz - 1);
    if (hasX && !hasZ) return 'x';
    if (hasZ && !hasX) return 'z';
    return (data.rot || 0) === 0 ? 'x' : 'z';
  },

  buildGateMesh(data) {
    const g = new THREE.Group();
    g.position.set(data.x, data.y, data.z);

    const axis = this.getGateAxis(data);
    const isX = axis === 'x';
    const matGateWood = new THREE.MeshStandardMaterial({ color: 0xa16207, roughness: 0.8 });
    const matLatch    = new THREE.MeshStandardMaterial({ color: 0x334155, metalness: 0.7, roughness: 0.3 });

    // Dua tiang engsel di kiri-kanan sel grid
    [-0.9, 0.9].forEach(off => {
      const post = new THREE.Mesh(new THREE.BoxGeometry(0.22, 1.28, 0.22), MAT_WOOD);
      post.position.set(isX ? off : 0, 0.64, isX ? 0 : off);
      post.castShadow = true; post.receiveShadow = true;
      const cap = new THREE.Mesh(new THREE.BoxGeometry(0.28, 0.08, 0.28), matGateWood);
      cap.position.set(isX ? off : 0, 1.3, isX ? 0 : off);
      g.add(post, cap);
    });

    // Daun pintu pagar berengsel di -0.82
    const doorPivot = new THREE.Group();
    doorPivot.position.set(isX ? -0.8 : 0, 0, isX ? 0 : -0.8);

    // Palang atas & bawah pintu pagar
    [0.42, 0.94].forEach(ry => {
      const bar = new THREE.Mesh(
        isX ? new THREE.BoxGeometry(1.58, 0.14, 0.11) : new THREE.BoxGeometry(0.11, 0.14, 1.58),
        matGateWood
      );
      bar.position.set(isX ? 0.79 : 0, ry, isX ? 0 : 0.79);
      bar.castShadow = true;
      doorPivot.add(bar);
    });

    // Bilah vertikal pintu pagar + kunci grendel besi
    [0.35, 0.8, 1.25].forEach(so => {
      const slat = new THREE.Mesh(
        isX ? new THREE.BoxGeometry(0.12, 0.72, 0.09) : new THREE.BoxGeometry(0.09, 0.72, 0.12),
        matGateWood
      );
      slat.position.set(isX ? so : 0, 0.68, isX ? 0 : so);
      slat.castShadow = true;
      doorPivot.add(slat);
    });

    const latch = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.12, 0.16), matLatch);
    latch.position.set(isX ? 1.46 : 0, 0.86, isX ? 0 : 1.46);
    doorPivot.add(latch);

    const openAngle = data.open ? Math.PI * 0.48 : 0;
    doorPivot.rotation.y = openAngle;
    g.add(doorPivot);

    return { group: g, doorPivot, openProg: data.open ? 1 : 0 };
  },

  buildCampfireMesh(data) {
    const g = new THREE.Group();
    g.position.set(data.x, data.y, data.z);

    for (let i = 0; i < 7; i++) {
      const a = (i / 7) * Math.PI * 2;
      const st = new THREE.Mesh(new THREE.DodecahedronGeometry(0.22), MAT_ROCK);
      st.scale.set(1.2, 0.75, 1.0);
      st.position.set(Math.cos(a) * 0.62, 0.12, Math.sin(a) * 0.62);
      st.rotation.y = a;
      st.castShadow = true;
      g.add(st);
    }

    for (let i = 0; i < 4; i++) {
      const log = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.09, 1.05, 6), MAT_WOOD);
      log.rotation.z = Math.PI / 2 - 0.25;
      log.rotation.y = (i / 4) * Math.PI;
      log.position.y = 0.18;
      log.castShadow = true;
      g.add(log);
    }

    const ember = new THREE.Mesh(
      new THREE.CylinderGeometry(0.36, 0.42, 0.08, 8),
      new THREE.MeshBasicMaterial({ color: 0xea580c })
    );
    ember.position.y = 0.06;
    g.add(ember);

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
    } else if (data.type === 'gate') {
      const built = this.buildGateMesh(data);
      scene.add(built.group);
      const entry = { data, mesh: built.group, doorPivot: built.doorPivot, openProg: built.openProg };
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

  refreshGridAndNeighbors(gx, gz) {
    const targets = [
      [gx, gz], [gx + 1, gz], [gx - 1, gz], [gx, gz + 1], [gx, gz - 1]
    ];
    for (const [tx, tz] of targets) {
      const entry = this.findGridAt(tx, tz);
      if (!entry) continue;
      scene.remove(entry.mesh);
      entry.mesh.traverse(o => { if (o.geometry) o.geometry.dispose(); });
      if (entry.data.type === 'fence') {
        entry.mesh = this.buildFenceMesh(entry.data);
      } else if (entry.data.type === 'gate') {
        const built = this.buildGateMesh(entry.data);
        entry.mesh = built.group;
        entry.doorPivot = built.doorPivot;
        entry.openProg = built.openProg;
      }
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
      if (b.type === 'gate' && b.open === undefined) b.open = false;
      this.spawnEntry(b);
    }
    for (const e of this.entries) {
      if (this.isGridType(e.data.type)) this.refreshGridAndNeighbors(e.data.gx, e.data.gz);
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
      open: false,
      fuel: 100
    };
    state.placedBuilds.push(data);
    this.spawnEntry(data);
    if (this.isGridType(data.type)) this.refreshGridAndNeighbors(data.gx, data.gz);

    AudioSys.cook();
    const msgs = {
      fence: '🪵 Pagar Kayu terpasang!',
      gate: '🚪 Pintu Pagar Kayu terpasang! (Dekati & tekan [F] untuk buka/tutup)',
      campfire: '🔥 Api Unggun dinyalakan! Menerangi area malam.'
    };
    showToast(msgs[data.type]);
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
      showToast('ℹ️ Tidak ada pagar / pintu / api unggun di dekatmu untuk dibongkar.');
      return;
    }

    const entry = this.entries[bestIdx];
    const data = entry.data;
    scene.remove(entry.mesh);
    entry.mesh.traverse(o => { if (o.geometry) o.geometry.dispose(); });
    this.entries.splice(bestIdx, 1);

    const sIdx = state.placedBuilds.findIndex(b => b.id === data.id);
    if (sIdx !== -1) state.placedBuilds.splice(sIdx, 1);

    if (this.isGridType(data.type)) this.refreshGridAndNeighbors(data.gx, data.gz);

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
        if (playerY >= d.y + 1.12) continue;
        const hasX = this.findGridAt(d.gx + 1, d.gz) || this.findGridAt(d.gx - 1, d.gz);
        const hasZ = this.findGridAt(d.gx, d.gz + 1) || this.findGridAt(d.gx, d.gz - 1);
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
      } else if (d.type === 'gate') {
        if (d.open || playerY >= d.y + 1.12) continue;
        const isX = this.getGateAxis(d) === 'x';
        const hw = isX ? 1.02 + r : 0.22 + r;
        const hd = isX ? 0.22 + r : 1.02 + r;
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
      if (d.type === 'gate' && d.open) continue;
      const minD = (this.isGridType(d.type) ? 0.95 : 0.75) + r;
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
        if (e.data.type === 'campfire') {
          best = {
            kind: 'campfire',
            entry: e,
            label: `🔥 [F] Masak di Api Unggun (${Math.round(e.data.fuel)}%) • [X] Bongkar`
          };
        } else if (e.data.type === 'gate') {
          best = {
            kind: 'gate',
            entry: e,
            label: `🚪 [F] ${e.data.open ? 'Tutup' : 'Buka'} Pintu Pagar • [X] Bongkar`
          };
        } else {
          best = {
            kind: 'fence_build',
            entry: e,
            label: `🪵 [X] Bongkar Pagar Kayu`
          };
        }
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

    const now = performance.now() * 0.008;
    const px = state.player.inVan ? state.van.x : state.player.x;
    const pz = state.player.inVan ? state.van.z : state.player.z;

    for (const e of this.entries) {
      if (e.data.type === 'gate' && e.doorPivot) {
        const target = e.data.open ? 1 : 0;
        e.openProg += (target - e.openProg) * Math.min(1, dt * 10);
        e.doorPivot.rotation.y = e.openProg * Math.PI * 0.48;
        continue;
      }

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