<?php
/**
 * Patcher: Interior Kabin 3D, Pintu Geser, Mode Kabin [V] & Cuaca Dinamis
 * Tanggal: 2026-11-24 03:20 WIB
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
$requiredFiles = ['index.html', 'js/state.js', 'js/audio.js', 'js/van.js', 'js/player.js', 'js/ui.js', 'js/main.js'];
$missing = [];
foreach ($requiredFiles as $rf) {
    if (!file_exists($baseDir . '/' . $rf)) $missing[] = $rf;
}

if (!empty($missing)) {
    logMsg('File modular tidak lengkap (' . implode(', ', $missing) . '). Pastikan patcher modularisasi sebelumnya sudah dijalankan.', 'error');
} else {
    // 1. Auto-Backup file yang akan di-patch
    $stamp = '20261124_0320';
    foreach ($requiredFiles as $rf) {
        $src = $baseDir . '/' . $rf;
        $bak = $src . '.bak_' . $stamp;
        if (!file_exists($bak)) copy($src, $bak);
    }
    logMsg("Backup file target dibuat (*.bak_{$stamp})", 'ok');

    // 2. Buat file baru js/weather.js
    $weatherJs = <<<'NOMAD_END'
// =========================================================================
// SISTEM CUACA DINAMIS, PARTIKEL 3D & PENAMPUNG AIR HUJAN
// =========================================================================
const WeatherSys = {
  current: 'clear', // clear | fog | rain | storm | sandstorm | snow
  timer: 42,
  lightningTimer: 0,
  lightningFlash: 0,
  particles: null,
  positions: null,
  count: 550,

  META: {
    clear:     { label: '☀️ Cerah',         fog: 0.0062, solar: 1.0,  grip: 1.0,  rainVol: 0.0 },
    fog:       { label: '🌫️ Kabut Tipis',   fog: 0.0155, solar: 0.65, grip: 0.95, rainVol: 0.0 },
    rain:      { label: '🌧️ Hujan',         fog: 0.0115, solar: 0.35, grip: 0.84, rainVol: 0.55 },
    storm:     { label: '⛈️ Badai Petir',   fog: 0.0165, solar: 0.2,  grip: 0.74, rainVol: 0.9 },
    sandstorm: { label: '🌪️ Badai Pasir',   fog: 0.021,  solar: 0.3,  grip: 0.82, rainVol: 0.15 },
    snow:      { label: '❄️ Salju Turun',   fog: 0.0135, solar: 0.45, grip: 0.78, rainVol: 0.0 }
  },

  initParticles() {
    if (this.particles) return;
    const geo = new THREE.BufferGeometry();
    this.positions = new Float32Array(this.count * 3);
    for (let i = 0; i < this.count; i++) {
      this.positions[i * 3]     = (Math.random() - 0.5) * 44;
      this.positions[i * 3 + 1] = Math.random() * 24;
      this.positions[i * 3 + 2] = (Math.random() - 0.5) * 44;
    }
    geo.setAttribute('position', new THREE.BufferAttribute(this.positions, 3));
    const mat = new THREE.PointsMaterial({
      color: 0xcbd5e1, size: 0.22, transparent: true, opacity: 0.65
    });
    this.particles = new THREE.Points(geo, mat);
    this.particles.frustumCulled = false;
    scene.add(this.particles);
  },

  pickNextWeather(biome) {
    const r = Math.random();
    if (biome === 'gurun') {
      return r < 0.62 ? 'clear' : (r < 0.9 ? 'sandstorm' : 'rain');
    }
    if (biome === 'gunung') {
      return r < 0.45 ? 'clear' : (r < 0.8 ? 'snow' : 'fog');
    }
    if (r < 0.48) return 'clear';
    if (r < 0.66) return 'fog';
    if (r < 0.88) return 'rain';
    return 'storm';
  },

  update(dt, biome) {
    this.initParticles();

    // Ganti cuaca berkala atau menyesuaikan jika pindah bioma ekstrem
    this.timer -= dt;
    if (this.current === 'snow' && biome !== 'gunung') this.timer = Math.min(this.timer, 4);
    if (this.current === 'sandstorm' && biome !== 'gurun') this.timer = Math.min(this.timer, 4);

    if (this.timer <= 0) {
      const next = this.pickNextWeather(biome);
      this.timer = 45 + Math.random() * 55;
      if (next !== this.current) {
        this.current = next;
        showToast(`Cuaca berubah: ${this.META[next].label}`);
      }
    }

    const meta = this.META[this.current];
    // Transisi kepadatan kabut halus
    scene.fog.density += (meta.fog - scene.fog.density) * Math.min(1, dt * 0.8);

    // Petir saat badai
    if (this.current === 'storm') {
      this.lightningTimer -= dt;
      if (this.lightningTimer <= 0) {
        this.lightningTimer = 7 + Math.random() * 12;
        this.lightningFlash = 1.0;
        AudioSys.playTone(62, 0.35, 'sawtooth', 0.14);
      }
    }
    if (this.lightningFlash > 0) {
      this.lightningFlash = Math.max(0, this.lightningFlash - dt * 3.5);
    }

    // Penampung air hujan saat hujan / badai
    if (this.current === 'rain' || this.current === 'storm') {
      const rate = (this.current === 'storm' ? 4.8 : 2.6) * (state.van.activeDecor.roofRack ? 1.4 : 1.0);
      state.van.rainCollector = Math.min(100, (state.van.rainCollector || 0) + rate * dt);
      if (state.van.rainCollector >= 100) {
        state.van.rainCollector = 0;
        const cleanWater = !!state.van.activeDecor.roofRack;
        const itemId = cleanWater ? 'water' : 'dirty_water';
        addItemToList(state.inventory, itemId, 1, 100);
        AudioSys.pickup();
        showToast(`🌧️ Penampung penuh! Mendapat 1x ${ITEMS[itemId].name}`);
      }
    }

    // Update Partikel 3D di sekitar kamera
    const showPart = ['rain', 'storm', 'snow', 'sandstorm'].includes(this.current);
    this.particles.visible = showPart;
    if (showPart) {
      const mat = this.particles.material;
      if (this.current === 'snow') {
        mat.color.setHex(0xffffff); mat.size = 0.28; mat.opacity = 0.85;
      } else if (this.current === 'sandstorm') {
        mat.color.setHex(0xd9a760); mat.size = 0.3; mat.opacity = 0.55;
      } else {
        mat.color.setHex(0x93c5fd); mat.size = 0.18; mat.opacity = 0.65;
      }

      const cx = camera.position.x, cy = camera.position.y, cz = camera.position.z;
      const pos = this.positions;
      for (let i = 0; i < this.count; i++) {
        const ix = i * 3;
        if (this.current === 'rain' || this.current === 'storm') {
          pos[ix + 1] -= (this.current === 'storm' ? 28 : 20) * dt;
          pos[ix] += (this.current === 'storm' ? 5 : 1.5) * dt;
        } else if (this.current === 'snow') {
          pos[ix + 1] -= 3.2 * dt;
          pos[ix] += Math.sin(performance.now() * 0.001 + i) * 1.1 * dt;
        } else if (this.current === 'sandstorm') {
          pos[ix] += 18 * dt;
          pos[ix + 1] -= 1.8 * dt;
        }

        if (pos[ix + 1] < cy - 8) pos[ix + 1] = cy + 16;
        if (pos[ix] < cx - 22) pos[ix] += 44;
        if (pos[ix] > cx + 22) pos[ix] -= 44;
        if (pos[ix + 2] < cz - 22) pos[ix + 2] += 44;
        if (pos[ix + 2] > cz + 22) pos[ix + 2] -= 44;
      }
      this.particles.geometry.attributes.position.needsUpdate = true;
    }
  },

  getSolarMult() { return this.META[this.current].solar; },
  getGripMult()  { return this.META[this.current].grip; },
  getRainVol()   { return this.META[this.current].rainVol; },
  getLabel()     {
    const base = this.META[this.current].label;
    if (this.current === 'rain' || this.current === 'storm') {
      return `${base} (${Math.round(state.van.rainCollector || 0)}%)`;
    }
    return base;
  }
};
NOMAD_END;

    if (file_put_contents($baseDir . '/js/weather.js', $weatherJs) !== false) {
        logMsg('File baru dibuat: js/weather.js', 'ok');
    } else {
        logMsg('Gagal membuat js/weather.js', 'error');
    }

    // Helper anchor patcher
    function patchFile($relPath, $patches) {
        global $baseDir;
        $full = $baseDir . '/' . $relPath;
        $content = file_get_contents($full);
        foreach ($patches as $p) {
            if (!empty($p['skipIf']) && strpos($content, $p['skipIf']) !== false) {
                logMsg("{$relPath}: [{$p['name']}] sudah terpasang, dilewati.", 'info');
                continue;
            }
            if (!empty($p['regex'])) {
                $new = preg_replace($p['anchor'], $p['replace'], $content, 1, $cnt);
                if ($cnt > 0) {
                    $content = $new;
                    logMsg("{$relPath}: [{$p['name']}] berhasil di-patch.", 'ok');
                } else {
                    logMsg("{$relPath}: Anchor [{$p['name']}] tidak ditemukan!", 'error');
                }
            } else {
                if (strpos($content, $p['anchor']) !== false) {
                    $content = str_replace($p['anchor'], $p['replace'], $content);
                    logMsg("{$relPath}: [{$p['name']}] berhasil di-patch.", 'ok');
                } else {
                    logMsg("{$relPath}: Anchor [{$p['name']}] tidak ditemukan!", 'error');
                }
            }
        }
        file_put_contents($full, $content);
    }

    // 3. Patch js/state.js (doorOpen, rainCollector & kompatibilitas save lama)
    patchFile('js/state.js', [
        [
            'name' => 'Tambah doorOpen & rainCollector pada default state',
            'skipIf' => 'doorOpen:',
            'anchor' => 'headlights: false,',
            'replace' => "headlights: false,\n      doorOpen: false,\n      rainCollector: 0,"
        ],
        [
            'name' => 'Backfill properti baru untuk save lama',
            'skipIf' => 'parsed.van.doorOpen === undefined',
            'anchor' => 'if (parsed && parsed.van && parsed.player) return parsed;',
            'replace' => "if (parsed && parsed.van && parsed.player) {\n        if (parsed.van.doorOpen === undefined) parsed.van.doorOpen = false;\n        if (parsed.van.rainCollector === undefined) parsed.van.rainCollector = 0;\n        return parsed;\n      }"
        ]
    ]);

    // 4. Patch js/audio.js (Suara hujan ambient adaptif luar vs dalam kabin)
    patchFile('js/audio.js', [
        [
            'name' => 'Inisialisasi node noise hujan',
            'skipIf' => 'rainGain',
            'anchor' => 'this.engineOsc.start();',
            'replace' => "this.engineOsc.start();\n    // Noise hujan procedural\n    const bufSize = this.ctx.sampleRate * 2;\n    const noiseBuf = this.ctx.createBuffer(1, bufSize, this.ctx.sampleRate);\n    const out = noiseBuf.getChannelData(0);\n    for (let i = 0; i < bufSize; i++) out[i] = Math.random() * 2 - 1;\n    const rainSrc = this.ctx.createBufferSource();\n    rainSrc.buffer = noiseBuf;\n    rainSrc.loop = true;\n    this.rainFilter = this.ctx.createBiquadFilter();\n    this.rainFilter.type = 'lowpass';\n    this.rainFilter.frequency.value = 1400;\n    this.rainGain = this.ctx.createGain();\n    this.rainGain.gain.value = 0;\n    rainSrc.connect(this.rainFilter);\n    this.rainFilter.connect(this.rainGain);\n    this.rainGain.connect(this.ctx.destination);\n    rainSrc.start();"
        ],
        [
            'name' => 'Tambah method updateRain',
            'skipIf' => 'updateRain(',
            'anchor' => "bump()     { this.playTone(85, 0.18, 'square', 0.14); }",
            'replace' => "bump()     { this.playTone(85, 0.18, 'square', 0.14); },\n  updateRain(vol, muffled) {\n    if (!this.ctx || !this.rainGain) return;\n    const targetVol = this.enabled ? vol * (muffled ? 0.035 : 0.065) : 0;\n    const targetFreq = muffled ? 440 : 1650;\n    this.rainGain.gain.setTargetAtTime(targetVol, this.ctx.currentTime, 0.25);\n    this.rainFilter.frequency.setTargetAtTime(targetFreq, this.ctx.currentTime, 0.25);\n  }"
        ]
    ]);

    // 5. Patch js/van.js (Kabin berongga, Kasur, Kulkas 3D, Kompor, Pintu Geser + Grip Cuaca)
    $newVanCabinBlock = <<<'NOMAD_END'
let vanHeadLightObj, vanInteriorLight, vanDriverMesh, vanSideDoorGroup, vanFridgeLedMat;
let liveryMeshLeft, liveryMeshRight;
let steerVis = 0;
let doorSlideProgress = 0;

function buildCamperVan() {
  while (vanBodyTilt.children.length) vanBodyTilt.remove(vanBodyTilt.children[0]);
  vanWheels = [];

  const matWoodFloor = new THREE.MeshStandardMaterial({ color: 0x854d0e, roughness: 0.85 });
  const matCabinet   = new THREE.MeshStandardMaterial({ color: 0xd6d3d1, roughness: 0.7 });
  const matBedBlanket = new THREE.MeshStandardMaterial({ color: 0xea580c, roughness: 0.85 });
  const matPillow    = new THREE.MeshStandardMaterial({ color: 0xf8fafc, roughness: 0.8 });
  const matSeat      = new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.8 });
  vanFridgeLedMat    = new THREE.MeshStandardMaterial({ color: 0x22d3ee, emissive: 0x22d3ee, emissiveIntensity: 1.5 });

  // 1. Lantai Kabin Kayu + Rangka Bawah
  const floor = new THREE.Mesh(new THREE.BoxGeometry(2.16, 0.12, 4.66), matWoodFloor);
  floor.position.set(0, 0.5, 0);
  floor.castShadow = true; floor.receiveShadow = true;
  vanBodyTilt.add(floor);

  // 2. Hidung Depan & Kaca Depan
  const frontNose = new THREE.Mesh(new THREE.BoxGeometry(2.2, 0.86, 0.42), vanMaterials.primary);
  frontNose.position.set(0, 0.99, 2.14);
  frontNose.castShadow = true;
  vanBodyTilt.add(frontNose);

  const windshield = new THREE.Mesh(new THREE.BoxGeometry(1.94, 0.72, 0.08), vanMaterials.glass);
  windshield.position.set(0, 1.8, 2.08);
  windshield.rotation.x = -0.18;
  vanBodyTilt.add(windshield);

  // Pilar Kaca Depan & Atap Kabin
  [-1.02, 1.02].forEach(px => {
    const pillar = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.9, 0.16), vanMaterials.accent);
    pillar.position.set(px, 1.84, 2.06);
    vanBodyTilt.add(pillar);
  });

  // Dashboard, Setir & 2 Kursi Depan
  const dash = new THREE.Mesh(new THREE.BoxGeometry(1.98, 0.36, 0.42), matSeat);
  dash.position.set(0, 1.2, 1.78);
  const steerWheel = new THREE.Mesh(new THREE.TorusGeometry(0.18, 0.03, 8, 16), vanMaterials.tire);
  steerWheel.position.set(0.46, 1.44, 1.58);
  steerWheel.rotation.x = 0.45;
  vanBodyTilt.add(dash, steerWheel);

  [-0.48, 0.46].forEach(sx => {
    const seatBase = new THREE.Mesh(new THREE.BoxGeometry(0.52, 0.35, 0.52), matSeat);
    seatBase.position.set(sx, 0.74, 1.25);
    const seatBack = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.65, 0.14), matSeat);
    seatBack.position.set(sx, 1.15, 1.04);
    vanBodyTilt.add(seatBase, seatBack);
  });

  // 3. Dinding Kanan (x = +1.05) dengan Jendela Transparan
  const rightLower = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.88, 4.66), vanMaterials.primary);
  rightLower.position.set(1.05, 0.99, 0); rightLower.castShadow = true;
  const rightWin = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.68, 3.6), vanMaterials.glass);
  rightWin.position.set(1.05, 1.8, -0.1);
  const rightPillarMid = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.86, 0.25), vanMaterials.accent);
  rightPillarMid.position.set(1.05, 1.84, 0.35);
  const rightPillarBack = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.86, 0.45), vanMaterials.accent);
  rightPillarBack.position.set(1.05, 1.84, -2.08);
  vanBodyTilt.add(rightLower, rightWin, rightPillarMid, rightPillarBack);

  // 4. Dinding Kiri (x = -1.05) dengan Lubang Pintu Camper (z = -0.25 .. +0.95)
  const leftFrontLower = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.88, 1.38), vanMaterials.primary);
  leftFrontLower.position.set(-1.05, 0.99, 1.64); leftFrontLower.castShadow = true;
  const leftFrontWin = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.68, 1.05), vanMaterials.glass);
  leftFrontWin.position.set(-1.05, 1.8, 1.45);

  const leftRearLower = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.88, 2.08), vanMaterials.primary);
  leftRearLower.position.set(-1.05, 0.99, -1.29); leftRearLower.castShadow = true;
  const leftRearWin = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.68, 1.4), vanMaterials.glass);
  leftRearWin.position.set(-1.05, 1.8, -1.25);
  const leftPillarBack = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.86, 0.45), vanMaterials.accent);
  leftPillarBack.position.set(-1.05, 1.84, -2.08);
  vanBodyTilt.add(leftFrontLower, leftFrontWin, leftRearLower, leftRearWin, leftPillarBack);

  // Pintu Geser Camper Kiri (Animasi Buka/Tutup [G])
  vanSideDoorGroup = new THREE.Group();
  const doorLower = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.86, 1.22), vanMaterials.primary);
  doorLower.position.set(0, 0.99, 0);
  const doorUpperFrame = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.84, 1.22), vanMaterials.accent);
  doorUpperFrame.position.set(0, 1.82, 0);
  const doorWin = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.56, 0.92), vanMaterials.glass);
  doorWin.position.set(0, 1.8, 0);
  const doorHandle = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.08, 0.22), vanMaterials.chrome);
  doorHandle.position.set(-0.07, 1.25, 0.42);
  vanSideDoorGroup.add(doorLower, doorUpperFrame, doorWin, doorHandle);
  vanSideDoorGroup.position.set(-1.06, 0, 0.35);
  vanBodyTilt.add(vanSideDoorGroup);

  // 5. Dinding Belakang + Jendela + Atap
  const rearLower = new THREE.Mesh(new THREE.BoxGeometry(2.2, 0.88, 0.14), vanMaterials.primary);
  rearLower.position.set(0, 0.99, -2.28); rearLower.castShadow = true;
  const rearWin = new THREE.Mesh(new THREE.BoxGeometry(1.4, 0.62, 0.08), vanMaterials.glass);
  rearWin.position.set(0, 1.8, -2.26);
  const roof = new THREE.Mesh(new THREE.BoxGeometry(2.14, 0.1, 4.35), vanMaterials.accent);
  roof.position.set(0, 2.28, -0.08); roof.castShadow = true;
  vanBodyTilt.add(rearLower, rearWin, roof);

  // 6. FURNITUR INTERIOR KABIN (Kasur, Kulkas 3D, Meja Kompor)
  // Kasur Camper di bagian belakang kanan
  const bedFrame = new THREE.Mesh(new THREE.BoxGeometry(1.12, 0.28, 1.85), matWoodFloor);
  bedFrame.position.set(0.42, 0.7, -1.25);
  const bedMattress = new THREE.Mesh(new THREE.BoxGeometry(1.06, 0.16, 1.78), matBedBlanket);
  bedMattress.position.set(0.42, 0.92, -1.25);
  const pillow = new THREE.Mesh(new THREE.BoxGeometry(0.62, 0.12, 0.34), matPillow);
  pillow.position.set(0.42, 1.05, -1.92);
  vanBodyTilt.add(bedFrame, bedMattress, pillow);

  // Kulkas Mini 3D di belakang kiri + LED status pendingin
  const fridgeBody = new THREE.Mesh(new THREE.BoxGeometry(0.58, 0.86, 0.56), matCabinet);
  fridgeBody.position.set(-0.66, 0.99, -1.82);
  const fridgeLed = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.06, 0.04), vanFridgeLedMat);
  fridgeLed.position.set(-0.46, 1.35, -1.52);
  vanBodyTilt.add(fridgeBody, fridgeLed);

  // Meja Dapur & Kompor + Ketel di sisi kanan tengah
  const kitchenCounter = new THREE.Mesh(new THREE.BoxGeometry(0.56, 0.76, 1.05), matWoodFloor);
  kitchenCounter.position.set(0.7, 0.94, 0.22);
  const stoveTop = new THREE.Mesh(new THREE.BoxGeometry(0.42, 0.05, 0.42), vanMaterials.tire);
  stoveTop.position.set(0.7, 1.34, 0.22);
  const kettle = new THREE.Mesh(new THREE.CylinderGeometry(0.11, 0.14, 0.18, 10), vanMaterials.chrome);
  kettle.position.set(0.7, 1.46, 0.22);
  vanBodyTilt.add(kitchenCounter, stoveTop, kettle);

  // Siluet Pengemudi di Kursi Kanan
  vanDriverMesh = new THREE.Group();
  const drvTorso = new THREE.Mesh(new THREE.BoxGeometry(0.42, 0.42, 0.26), new THREE.MeshStandardMaterial({ color: 0xd97706 }));
  const drvHead = new THREE.Mesh(new THREE.BoxGeometry(0.32, 0.32, 0.32), new THREE.MeshStandardMaterial({ color: 0xfdba74 }));
  drvHead.position.set(0, 0.36, 0);
  const drvHat = new THREE.Mesh(new THREE.BoxGeometry(0.38, 0.1, 0.4), new THREE.MeshStandardMaterial({ color: 0x78350f }));
  drvHat.position.set(0, 0.54, 0.02);
  vanDriverMesh.add(drvTorso, drvHead, drvHat);
  vanDriverMesh.position.set(0.46, 1.28, 1.22);
  vanBodyTilt.add(vanDriverMesh);

  // Livery Panel Samping
  const livMat = new THREE.MeshStandardMaterial({ transparent: true, roughness: 0.4 });
  liveryMeshRight = new THREE.Mesh(new THREE.PlaneGeometry(4.4, 0.8), livMat);
  liveryMeshRight.position.set(1.11, 0.98, 0);
  liveryMeshRight.rotation.y = Math.PI / 2;
  vanBodyTilt.add(liveryMeshRight);

  liveryMeshLeft = new THREE.Mesh(new THREE.PlaneGeometry(2.0, 0.8), livMat);
  liveryMeshLeft.position.set(-1.11, 0.98, -1.29);
  liveryMeshLeft.rotation.y = -Math.PI / 2;
  vanBodyTilt.add(liveryMeshLeft);
  updateVanLiveryTexture();

  // Bumper & Grille
  const fBumper = new THREE.Mesh(new THREE.BoxGeometry(2.3, 0.24, 0.25), vanMaterials.chrome);
  fBumper.position.set(0, 0.48, 2.38);
  const rBumper = new THREE.Mesh(new THREE.BoxGeometry(2.3, 0.24, 0.25), vanMaterials.chrome);
  rBumper.position.set(0, 0.48, -2.38);
  const grille = new THREE.Mesh(new THREE.BoxGeometry(1.1, 0.32, 0.08), vanMaterials.chrome);
  grille.position.set(0, 0.92, 2.36);
  vanBodyTilt.add(fBumper, rBumper, grille);

  // Lampu Depan & Belakang
  [-0.78, 0.78].forEach(x => {
    const hl = new THREE.Mesh(new THREE.CylinderGeometry(0.16, 0.16, 0.1, 12), vanMaterials.headlight);
    hl.rotation.x = Math.PI / 2;
    hl.position.set(x, 0.95, 2.36);
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

  vanInteriorLight = new THREE.PointLight(0xfbbf24, 0.75, 8);
  vanInteriorLight.position.set(0, 2.05, -0.35);
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
NOMAD_END;

    patchFile('js/van.js', [
        [
            'name' => 'Ganti buildCamperVan menjadi model kabin berongga + furnitur + pintu geser',
            'regex' => true,
            'skipIf' => 'vanSideDoorGroup',
            'anchor' => '~let vanHeadLightObj,.*?function updateVanLiveryTexture\(\) \{~s',
            'replace' => $newVanCabinBlock
        ],
        [
            'name' => 'Sinkronkan LED kulkas 3D di syncVanVisuals',
            'skipIf' => 'vanFridgeLedMat',
            'anchor' => 'vanMaterials.headlight.emissiveIntensity = hlOn ? 2.0 : 0.1;',
            'replace' => "vanMaterials.headlight.emissiveIntensity = hlOn ? 2.0 : 0.1;\n  if (vanFridgeLedMat) {\n    const fOn = state.fridge.on && state.van.battery > 0;\n    vanFridgeLedMat.color.setHex(fOn ? 0x22d3ee : 0x7f1d1d);\n    vanFridgeLedMat.emissive.setHex(fOn ? 0x22d3ee : 0x7f1d1d);\n  }"
        ],
        [
            'name' => 'Sembunyikan siluet pengemudi saat mode kabin first-person',
            'skipIf' => 'inCabinView',
            'anchor' => 'if (vanDriverMesh) vanDriverMesh.visible = pl.inVan;',
            'replace' => 'if (vanDriverMesh) vanDriverMesh.visible = pl.inVan && !inCabinView;'
        ],
        [
            'name' => 'Animasi pintu geser camper & efek jalan licin cuaca',
            'skipIf' => 'doorSlideProgress',
            'anchor' => 'const turnFactor = Math.min(1, Math.abs(van.speed) / 3.5) * (van.speed >= 0 ? 1 : -1);',
            'replace' => "const grip = (typeof WeatherSys !== 'undefined') ? WeatherSys.getGripMult() : 1.0;\n    const turnFactor = Math.min(1, Math.abs(van.speed) / 3.5) * (van.speed >= 0 ? 1 : -1) * grip;"
        ],
        [
            'name' => 'Animasi posisi pintu geser vanSideDoorGroup',
            'skipIf' => 'vanSideDoorGroup.position.z',
            'anchor' => 'vanMaterials.taillight.emissiveIntensity = braking ? 1.8 : 0.3;',
            'replace' => "vanMaterials.taillight.emissiveIntensity = braking ? 1.8 : 0.3;\n\n  // Animasi pintu geser samping\n  const targetDoor = state.van.doorOpen ? 1 : 0;\n  doorSlideProgress += (targetDoor - doorSlideProgress) * Math.min(1, dt * 8);\n  if (vanSideDoorGroup) {\n    vanSideDoorGroup.position.x = -1.06 - doorSlideProgress * 0.08;\n    vanSideDoorGroup.position.z = 0.35 - doorSlideProgress * 1.18;\n  }"
        ]
    ]);

    // 6. Patch js/player.js (Mode Kabin [V], Pintu [G], Buka pintu otomatis saat turun)
    $cabinFns = <<<'NOMAD_END'
let inCabinView = false;

function toggleVanDoor() {
  state.van.doorOpen = !state.van.doorOpen;
  AudioSys.playTone(state.van.doorOpen ? 380 : 280, 0.09);
  showToast(state.van.doorOpen ? '🚪 Pintu samping Camper dibuka' : '🚪 Pintu samping Camper ditutup');
  saveGameState();
}

function toggleCabinView() {
  if (!state.player.inVan) {
    const dist = Math.hypot(state.player.x - state.van.x, state.player.z - state.van.z);
    if (dist > 5.5) {
      showToast('⚠️ Mendekatlah ke Camper Van untuk masuk ke kabin!');
      return;
    }
    state.player.inVan = true;
    inCabinView = true;
  } else {
    inCabinView = !inCabinView;
  }
  AudioSys.playTone(inCabinView ? 420 : 320, 0.08);
  showToast(inCabinView ? '🛋️ Mode Kabin Dalam (Geser mouse untuk melihat sekitar)' : '🎥 Kembali ke Kamera Luar');
}
NOMAD_END;

    patchFile('js/player.js', [
        [
            'name' => 'Tambah fungsi toggleVanDoor & toggleCabinView',
            'skipIf' => 'toggleCabinView',
            'anchor' => 'let nearestInteract = null;',
            'replace' => "let nearestInteract = null;\n" . $cabinFns
        ],
        [
            'name' => 'Otomatis matikan inCabinView & buka pintu saat turun dari mobil',
            'skipIf' => 'state.van.doorOpen = true;',
            'anchor' => 'state.player.inVan = false;',
            'replace' => "state.player.inVan = false;\n    inCabinView = false;\n    state.van.doorOpen = true;"
        ]
    ]);

    // 7. Patch js/ui.js (Integrasi WeatherSys, Lightning, Solar Mendung, Rain Audio & HUD Cuaca)
    patchFile('js/ui.js', [
        [
            'name' => 'Update cuaca, petir & suara hujan di updateSurvivalAndEnvironment',
            'skipIf' => 'WeatherSys.update',
            'anchor' => 'const isDay = sunY > 0;',
            'replace' => "if (typeof WeatherSys !== 'undefined') WeatherSys.update(dt, biome);\n  const isDay = sunY > 0;"
        ],
        [
            'name' => 'Kilatan petir & pengurangan cahaya saat badai/mendung',
            'skipIf' => 'WeatherSys.lightningFlash',
            'anchor' => 'sunLight.intensity = dayFactor * 1.15;',
            'replace' => "const weatherLightMult = (typeof WeatherSys !== 'undefined') ? (0.45 + WeatherSys.getSolarMult() * 0.55) : 1;\n  const flash = (typeof WeatherSys !== 'undefined') ? WeatherSys.lightningFlash * 2.5 : 0;\n  sunLight.intensity = dayFactor * 1.15 * weatherLightMult + flash;"
        ],
        [
            'name' => 'Solar panel dipengaruhi mendung & suara hujan adaptif',
            'skipIf' => 'WeatherSys.getSolarMult()',
            'anchor' => 'if (state.van.activeDecor.solarPanel && isDay) {',
            'replace' => "if (typeof WeatherSys !== 'undefined') {\n    AudioSys.updateRain(WeatherSys.getRainVol(), state.player.inVan || inCabinView);\n  }\n  if (state.van.activeDecor.solarPanel && isDay) {\n    const sMult = (typeof WeatherSys !== 'undefined') ? WeatherSys.getSolarMult() : 1;"
        ],
        [
            'name' => 'Kalikan pengisian solar panel dengan sMult',
            'skipIf' => '1.4 * sunY * sMult * dt',
            'anchor' => 'state.van.battery = Math.min(getMaxBat(), state.van.battery + 1.4 * sunY * dt);',
            'replace' => 'state.van.battery = Math.min(getMaxBat(), state.van.battery + 1.4 * sunY * sMult * dt);'
        ],
        [
            'name' => 'Update label HUD cuaca & tombol pintu/kabin',
            'skipIf' => 'txt-weather',
            'anchor' => "document.getElementById('txt-temp').textContent = `\${bMeta.temp}°C`;",
            'replace' => "document.getElementById('txt-temp').textContent = `\${bMeta.temp}°C`;\n  const wEl = document.getElementById('txt-weather');\n  if (wEl && typeof WeatherSys !== 'undefined') wEl.textContent = WeatherSys.getLabel();\n  const dBtn = document.getElementById('btn-door-van');\n  if (dBtn) dBtn.textContent = state.van.doorOpen ? '🚪 Tutup Pintu [G]' : '🚪 Buka Pintu [G]';\n  const cBtn = document.getElementById('btn-cabin-view');\n  if (cBtn) cBtn.className = `btn \${inCabinView ? 'success' : ''}`;"
        ]
    ]);

    // 8. Patch js/main.js (Shortcut KeyG, KeyV & Kamera First-Person Kabin)
    patchFile('js/main.js', [
        [
            'name' => 'Tambah shortcut G (Pintu) dan V (Mode Kabin)',
            'skipIf' => "e.code === 'KeyG'",
            'anchor' => "if (e.code === 'KeyE') toggleVehicleMode();",
            'replace' => "if (e.code === 'KeyE') toggleVehicleMode();\n  if (e.code === 'KeyG') toggleVanDoor();\n  if (e.code === 'KeyV') toggleCabinView();"
        ],
        [
            'name' => 'Dukungan kamera First-Person di dalam Kabin Camper',
            'skipIf' => 'if (inCabinView && state.player.inVan)',
            'anchor' => 'function updateCamera(dt) {',
            'replace' => "function updateCamera(dt) {\n  if (inCabinView && state.player.inVan) {\n    const cosY = Math.cos(state.van.yaw), sinY = Math.sin(state.van.yaw);\n    const lx = -0.1, lz = -0.35;\n    const eyeX = state.van.x + lx * cosY + lz * sinY;\n    const eyeY = state.van.y + 1.68;\n    const eyeZ = state.van.z - lx * sinY + lz * cosY;\n    camera.position.set(eyeX, eyeY, eyeZ);\n    const lookX = eyeX - Math.sin(camAngle) * 5;\n    const lookY = eyeY - (camPitch - 0.28) * 4.5;\n    const lookZ = eyeZ - Math.cos(camAngle) * 5;\n    camera.lookAt(lookX, lookY, lookZ);\n    return;\n  }"
        ]
    ]);

    // 9. Patch index.html (Badge Cuaca, Tombol Pintu & Kabin, dan load js/weather.js)
    patchFile('index.html', [
        [
            'name' => 'Tambah badge cuaca di HUD atas',
            'skipIf' => 'id="txt-weather"',
            'anchor' => '<span id="txt-temp">26°C</span>',
            'replace' => '<span id="txt-temp">26°C</span><span>•</span><span id="txt-weather">☀️ Cerah</span>'
        ],
        [
            'name' => 'Tambah tombol Buka Pintu [G] & Mode Kabin [V] di HUD bawah',
            'skipIf' => 'id="btn-cabin-view"',
            'anchor' => '<button class="btn primary" id="btn-toggle-van" onclick="toggleVehicleMode()" style="min-height:42px;">🚶 Turun Mobil [E]</button>',
            'replace' => '<button class="btn primary" id="btn-toggle-van" onclick="toggleVehicleMode()" style="min-height:42px;">🚶 Turun Mobil [E]</button>' . "\n        " . '<button class="btn" id="btn-door-van" onclick="toggleVanDoor()" style="min-height:42px;">🚪 Buka Pintu [G]</button>' . "\n        " . '<button class="btn" id="btn-cabin-view" onclick="toggleCabinView()" style="min-height:42px;">🛋️ Kabin [V]</button>'
        ],
        [
            'name' => 'Tambah petunjuk shortcut G dan V',
            'skipIf' => '<kbd>V</kbd> Kabin',
            'anchor' => '<div><kbd>L</kbd> Lampu • <kbd>H</kbd> Klakson • <kbd>C</kbd> Kamera • <kbd>Mouse Drag</kbd> Putar</div>',
            'replace' => '<div><kbd>G</kbd> Pintu • <kbd>V</kbd> Kabin • <kbd>L</kbd> Lampu • <kbd>H</kbd> Klakson • <kbd>C</kbd> Kamera</div>'
        ],
        [
            'name' => 'Muat script js/weather.js sebelum js/ui.js',
            'skipIf' => 'js/weather.js',
            'anchor' => '<script src="js/ui.js"></script>',
            'replace' => "<script src=\"js/weather.js\"></script>\n  <script src=\"js/ui.js\"></script>"
        ]
    ]);
}

if (!$isCli): ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Patcher: Interior Kabin 3D & Cuaca Dinamis</title>
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
    <h2 style="margin-top:0">🛋️⛈️ Update Interior Kabin & Cuaca Selesai</h2>
    <p style="color:#94a3b8; font-size:14px;">File: <code>update_20261124_0320_interior_kabin_dan_cuaca.php</code></p>
    <?php foreach ($logs as $item): ?>
      <div class="log <?= htmlspecialchars($item['type']) ?>"><?= htmlspecialchars($item['msg']) ?></div>
    <?php endforeach; ?>
    <a class="play-btn" href="index.html">🎮 Buka & Coba Sekarang</a>
  </div>
</body>
</html>
<?php endif; ?>