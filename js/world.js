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
    const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.4, 2.6, 7), MAT_WOOD);
    trunk.position.y = 1.3; trunk.castShadow = true;
    const top1 = new THREE.Mesh(new THREE.DodecahedronGeometry(1.55, 1), MAT_LEAF);
    top1.position.set(0, 3.3, 0); top1.castShadow = true;
    const top2 = new THREE.Mesh(new THREE.DodecahedronGeometry(1.15, 1), MAT_LEAF);
    top2.position.set(0.75, 2.9, 0.45); top2.castShadow = true;
    const top3 = new THREE.Mesh(new THREE.DodecahedronGeometry(1.05, 1), MAT_LEAF);
    top3.position.set(-0.7, 2.85, -0.4); top3.castShadow = true;
    g.add(trunk, top1, top2, top3);
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

  const terrainMat = (typeof GraphicsSys !== 'undefined') ? GraphicsSys.getTerrainMaterial() : new THREE.MeshStandardMaterial({ vertexColors: true, roughness: 0.9, metalness: 0.02 });
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
        const propScale = 0.86 + WorldGen.hash2(cx, cz, i * 11 + 5) * 0.38;
        m.position.set(rx, ry, rz);
        m.rotation.y = rKind * Math.PI * 2;
        m.scale.setScalar(propScale);
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

  if (typeof GraphicsSys !== 'undefined') GraphicsSys.populateChunkGrass(group, ox, oz, cx, cz);

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
      ch.group.traverse(obj => { if (obj.geometry && obj.geometry.dispose && !obj.userData.sharedGeo) obj.geometry.dispose(); });
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