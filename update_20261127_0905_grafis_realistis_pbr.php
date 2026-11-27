<?php
/**
 * Patcher: Upgrade Grafis Realistis Tahap 1 (Procedural PBR, ACES ToneMapping, Sky Dome, Riak Air, Rumput & Detail Van)
 * Tanggal: 2026-11-27 09:05 WIB
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
$requiredFiles = ['index.html', 'js/scene.js', 'js/world.js', 'js/van.js', 'js/ui.js', 'js/main.js'];
$missing = [];
foreach ($requiredFiles as $rf) {
    if (!file_exists($baseDir . '/' . $rf)) $missing[] = $rf;
}

if (!empty($missing)) {
    logMsg('File project tidak lengkap: ' . implode(', ', $missing), 'error');
} else {
    // 1. Auto-Backup file target
    $stamp = '20261127_0905';
    foreach ($requiredFiles as $rf) {
        $src = $baseDir . '/' . $rf;
        $bak = $src . '.bak_' . $stamp;
        if (!file_exists($bak)) copy($src, $bak);
    }
    logMsg("Backup file target dibuat (*.bak_{$stamp})", 'ok');

    // 2. Buat file baru js/graphics.js
    $graphicsJs = <<<'NOMAD_END'
// =========================================================================
// GRAPHICS ENGINE: PROCEDURAL PBR TEXTURES, SKY DOME, AIR, AWAN & RUMPUT
// =========================================================================
const GraphicsSys = {
  initialized: false,
  texTerrainBump: null,
  texTerrainRough: null,
  texWood: null,
  texWoodBump: null,
  texRock: null,
  texRockBump: null,
  texFabric: null,
  texFabricBump: null,
  texWaterNormal: null,
  texGrassBlade: null,

  terrainMat: null,
  grassGeo: null,
  grassMat: null,

  skyDome: null,
  skyUniforms: null,
  sunDisc: null,
  moonDisc: null,
  starPoints: null,
  cloudGroup: null,
  clouds: [],

  makeCanvas(w, h, drawFn) {
    const c = document.createElement('canvas');
    c.width = w; c.height = h;
    const ctx = c.getContext('2d');
    drawFn(ctx, w, h);
    return c;
  },

  toTex(canvas, repeatX = 1, repeatY = 1, isSRGB = false) {
    const tex = new THREE.CanvasTexture(canvas);
    tex.wrapS = THREE.RepeatWrapping;
    tex.wrapT = THREE.RepeatWrapping;
    tex.repeat.set(repeatX, repeatY);
    tex.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());
    if (isSRGB) tex.encoding = THREE.sRGBEncoding;
    return tex;
  },

  generateTextures() {
    // 1. Terrain Micro-Grain Bump & Roughness (tileable 512x512)
    const tbCanvas = this.makeCanvas(256, 256, (ctx, w, h) => {
      const img = ctx.createImageData(w, h);
      const d = img.data;
      for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
          const i = (y * w + x) * 4;
          const n1 = Math.sin(x * 0.147) * Math.cos(y * 0.147) * 26;
          const n2 = Math.sin((x + y) * 0.294) * 18;
          const grain = (Math.random() - 0.5) * 68;
          const v = Math.max(0, Math.min(255, 128 + n1 + n2 + grain));
          d[i] = d[i + 1] = d[i + 2] = v;
          d[i + 3] = 255;
        }
      }
      ctx.putImageData(img, 0, 0);
    });
    this.texTerrainBump = this.toTex(tbCanvas, 10, 10, false);

    const trCanvas = this.makeCanvas(256, 256, (ctx, w, h) => {
      ctx.fillStyle = '#dcdcdc';
      ctx.fillRect(0, 0, w, h);
      for (let i = 0; i < 2400; i++) {
        const v = 185 + Math.floor(Math.random() * 65);
        ctx.fillStyle = `rgb(${v},${v},${v})`;
        ctx.fillRect(Math.random() * w, Math.random() * h, 3, 3);
      }
    });
    this.texTerrainRough = this.toTex(trCanvas, 10, 10, false);

    // 2. Serat Kayu (Netral terang agar dikalikan warna material tetap pas)
    const woodCanvas = this.makeCanvas(256, 256, (ctx, w, h) => {
      ctx.fillStyle = '#e2dcd5';
      ctx.fillRect(0, 0, w, h);
      for (let y = 0; y < h; y += 2) {
        const wave = Math.sin(y * 0.18) * 14 + (Math.random() - 0.5) * 18;
        const shade = Math.max(150, Math.min(248, 215 + wave));
        ctx.fillStyle = `rgb(${shade},${shade - 4},${shade - 10})`;
        ctx.fillRect(0, y, w, 2);
      }
      ctx.fillStyle = 'rgba(90, 60, 35, 0.18)';
      for (let x = 0; x < w; x += 64) ctx.fillRect(x, 0, 2, h);
    });
    this.texWood = this.toTex(woodCanvas, 1, 1, true);
    this.texWoodBump = this.toTex(woodCanvas, 1, 1, false);

    // 3. Batu & Tebing
    const rockCanvas = this.makeCanvas(256, 256, (ctx, w, h) => {
      ctx.fillStyle = '#c8ccd2';
      ctx.fillRect(0, 0, w, h);
      for (let i = 0; i < 1800; i++) {
        const v = 135 + Math.floor(Math.random() * 110);
        ctx.fillStyle = `rgba(${v},${v},${v},0.45)`;
        ctx.beginPath();
        ctx.arc(Math.random() * w, Math.random() * h, 1.5 + Math.random() * 5, 0, Math.PI * 2);
        ctx.fill();
      }
    });
    this.texRock = this.toTex(rockCanvas, 2, 2, true);
    this.texRockBump = this.toTex(rockCanvas, 2, 2, false);

    // 4. Kain Kanvas Tenda & Selimut
    const fabCanvas = this.makeCanvas(128, 128, (ctx, w, h) => {
      ctx.fillStyle = '#ededed';
      ctx.fillRect(0, 0, w, h);
      ctx.fillStyle = 'rgba(0,0,0,0.06)';
      for (let y = 0; y < h; y += 4) ctx.fillRect(0, y, w, 1);
      for (let x = 0; x < w; x += 4) ctx.fillRect(x, 0, 1, h);
    });
    this.texFabric = this.toTex(fabCanvas, 4, 4, true);
    this.texFabricBump = this.toTex(fabCanvas, 4, 4, false);

    // 5. Riak Gelombang Air
    const watCanvas = this.makeCanvas(256, 256, (ctx, w, h) => {
      const img = ctx.createImageData(w, h);
      const d = img.data;
      for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
          const i = (y * w + x) * 4;
          const ax = (x / w) * Math.PI * 2;
          const ay = (y / h) * Math.PI * 2;
          const v = 128
            + Math.sin(ax * 4 + Math.cos(ay * 3)) * 42
            + Math.cos(ay * 5 - Math.sin(ax * 2)) * 38;
          const c = Math.max(0, Math.min(255, v));
          d[i] = d[i + 1] = d[i + 2] = c;
          d[i + 3] = 255;
        }
      }
      ctx.putImageData(img, 0, 0);
    });
    this.texWaterNormal = this.toTex(watCanvas, 6, 6, false);

    // 6. Helai Rumput Alpha-Cutout
    const grCanvas = this.makeCanvas(64, 64, (ctx, w, h) => {
      ctx.clearRect(0, 0, w, h);
      const blades = [
        { x: 14, h: 42, lean: -7 },
        { x: 24, h: 54, lean: -3 },
        { x: 32, h: 58, lean: 2 },
        { x: 42, h: 48, lean: 6 },
        { x: 52, h: 38, lean: 9 }
      ];
      blades.forEach((b, idx) => {
        const grad = ctx.createLinearGradient(0, h, 0, h - b.h);
        grad.addColorStop(0, '#1e5622');
        grad.addColorStop(1, idx % 2 === 0 ? '#65a30d' : '#4ade80');
        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.moveTo(b.x - 4, h);
        ctx.quadraticCurveTo(b.x + b.lean * 0.4, h - b.h * 0.55, b.x + b.lean, h - b.h);
        ctx.quadraticCurveTo(b.x + 3 + b.lean * 0.3, h - b.h * 0.55, b.x + 4, h);
        ctx.closePath();
        ctx.fill();
      });
    });
    this.texGrassBlade = new THREE.CanvasTexture(grCanvas);
    this.texGrassBlade.encoding = THREE.sRGBEncoding;
  },

  upgradeSharedMaterials() {
    MAT_WOOD.map = this.texWood;
    MAT_WOOD.bumpMap = this.texWoodBump;
    MAT_WOOD.bumpScale = 0.08;
    MAT_WOOD.roughness = 0.85;
    MAT_WOOD.needsUpdate = true;

    MAT_ROCK.map = this.texRock;
    MAT_ROCK.bumpMap = this.texRockBump;
    MAT_ROCK.bumpScale = 0.22;
    MAT_ROCK.flatShading = false;
    MAT_ROCK.roughness = 0.88;
    MAT_ROCK.needsUpdate = true;

    MAT_WALL.bumpMap = this.texRockBump;
    MAT_WALL.bumpScale = 0.06;
    MAT_WALL.needsUpdate = true;

    MAT_CITY.bumpMap = this.texRockBump;
    MAT_CITY.bumpScale = 0.09;
    MAT_CITY.needsUpdate = true;

    MAT_WATER.bumpMap = this.texWaterNormal;
    MAT_WATER.bumpScale = 0.2;
    MAT_WATER.roughness = 0.1;
    MAT_WATER.metalness = 0.38;
    MAT_WATER.opacity = 0.8;
    MAT_WATER.needsUpdate = true;

    // Shared Smooth Terrain Material
    this.terrainMat = new THREE.MeshStandardMaterial({
      vertexColors: true,
      roughness: 0.9,
      metalness: 0.03,
      bumpMap: this.texTerrainBump,
      bumpScale: 0.28,
      roughnessMap: this.texTerrainRough,
      flatShading: false
    });

    // Shared Grass Blade Geometry & Material
    const p1 = new THREE.PlaneGeometry(0.65, 0.58);
    p1.translate(0, 0.27, 0);
    const p2 = p1.clone();
    p2.rotateY(Math.PI / 3);
    const p3 = p1.clone();
    p3.rotateY(-Math.PI / 3);

    // Gabung 3 bidang menjadi satu BufferGeometry silang
    const merged = new THREE.BufferGeometry();
    const parts = [p1, p2, p3];
    const posArr = [], normArr = [], uvArr = [], idxArr = [];
    let vOff = 0;
    for (const g of parts) {
      const pos = g.attributes.position.array;
      const norm = g.attributes.normal.array;
      const uv = g.attributes.uv.array;
      const idx = g.index.array;
      for (let i = 0; i < pos.length; i++) posArr.push(pos[i]);
      for (let i = 0; i < norm.length; i++) normArr.push(norm[i]);
      for (let i = 0; i < uv.length; i++) uvArr.push(uv[i]);
      for (let i = 0; i < idx.length; i++) idxArr.push(idx[i] + vOff);
      vOff += g.attributes.position.count;
      g.dispose();
    }
    merged.setAttribute('position', new THREE.Float32BufferAttribute(posArr, 3));
    merged.setAttribute('normal', new THREE.Float32BufferAttribute(normArr, 3));
    merged.setAttribute('uv', new THREE.Float32BufferAttribute(uvArr, 2));
    merged.setIndex(idxArr);
    this.grassGeo = merged;

    this.grassMat = new THREE.MeshStandardMaterial({
      map: this.texGrassBlade,
      alphaTest: 0.42,
      side: THREE.DoubleSide,
      roughness: 0.85,
      metalness: 0.0
    });
  },

  setupEnvironmentReflection() {
    if (typeof THREE.PMREMGenerator === 'undefined') return;
    const pmrem = new THREE.PMREMGenerator(renderer);
    pmrem.compileEquirectangularShader();

    const envScene = new THREE.Scene();
    const skyGeo = new THREE.SphereGeometry(20, 16, 12);
    const pos = skyGeo.attributes.position;
    const cols = new Float32Array(pos.count * 3);
    const cTop = new THREE.Color(0x60a5fa);
    const cMid = new THREE.Color(0xe0f2fe);
    const cBot = new THREE.Color(0x4d5e42);
    const tmp = new THREE.Color();
    for (let i = 0; i < pos.count; i++) {
      const ny = pos.getY(i) / 20;
      if (ny >= 0) tmp.copy(cMid).lerp(cTop, Math.pow(ny, 0.7));
      else tmp.copy(cMid).lerp(cBot, Math.min(1, -ny * 2.5));
      cols[i * 3] = tmp.r; cols[i * 3 + 1] = tmp.g; cols[i * 3 + 2] = tmp.b;
    }
    skyGeo.setAttribute('color', new THREE.BufferAttribute(cols, 3));
    const envMat = new THREE.MeshBasicMaterial({ vertexColors: true, side: THREE.BackSide });
    envScene.add(new THREE.Mesh(skyGeo, envMat));

    const sunPatch = new THREE.Mesh(
      new THREE.SphereGeometry(2.2, 8, 8),
      new THREE.MeshBasicMaterial({ color: 0xfffbeb })
    );
    sunPatch.position.set(12, 11, 8);
    envScene.add(sunPatch);

    const rt = pmrem.fromScene(envScene, 0.04);
    scene.environment = rt.texture;
    skyGeo.dispose();
    envMat.dispose();
    pmrem.dispose();
  },

  setupSkyAndAtmosphere() {
    // Sky Dome Gradient Shader
    this.skyUniforms = {
      uTopColor:     { value: new THREE.Color(0x2563eb) },
      uHorizonColor: { value: new THREE.Color(0x9ec5ab) },
      uSunDir:       { value: new THREE.Vector3(0.4, 0.7, 0.3) },
      uSunGlow:      { value: 1.0 }
    };

    const skyMat = new THREE.ShaderMaterial({
      uniforms: this.skyUniforms,
      side: THREE.BackSide,
      depthWrite: false,
      fog: false,
      vertexShader: `
        varying vec3 vDir;
        void main() {
          vDir = normalize(position);
          gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
        }
      `,
      fragmentShader: `
        uniform vec3 uTopColor;
        uniform vec3 uHorizonColor;
        uniform vec3 uSunDir;
        uniform float uSunGlow;
        varying vec3 vDir;
        void main() {
          vec3 d = normalize(vDir);
          float h = clamp(d.y, 0.0, 1.0);
          vec3 col = mix(uHorizonColor, uTopColor, pow(h, 0.52));
          float s = max(dot(d, normalize(uSunDir)), 0.0);
          col += vec3(1.0, 0.68, 0.3) * pow(s, 12.0) * 0.42 * uSunGlow;
          col += vec3(1.0, 0.94, 0.8) * pow(s, 140.0) * 0.9 * uSunGlow;
          gl_FragColor = vec4(col, 1.0);
        }
      `
    });
    this.skyDome = new THREE.Mesh(new THREE.SphereGeometry(330, 24, 16), skyMat);
    this.skyDome.frustumCulled = false;
    this.skyDome.renderOrder = -10;
    scene.add(this.skyDome);

    // Matahari & Bulan
    this.sunDisc = new THREE.Mesh(
      new THREE.SphereGeometry(8.5, 16, 12),
      new THREE.MeshBasicMaterial({ color: 0xfff7d6, fog: false })
    );
    this.moonDisc = new THREE.Mesh(
      new THREE.SphereGeometry(6.2, 16, 12),
      new THREE.MeshBasicMaterial({ color: 0xe2e8f0, fog: false })
    );
    scene.add(this.sunDisc, this.moonDisc);

    // Bintang Malam
    const starCount = 420;
    const starPos = new Float32Array(starCount * 3);
    for (let i = 0; i < starCount; i++) {
      const theta = Math.random() * Math.PI * 2;
      const phi = Math.acos(0.08 + Math.random() * 0.92);
      const r = 290;
      starPos[i * 3]     = r * Math.sin(phi) * Math.cos(theta);
      starPos[i * 3 + 1] = r * Math.cos(phi);
      starPos[i * 3 + 2] = r * Math.sin(phi) * Math.sin(theta);
    }
    const starGeo = new THREE.BufferGeometry();
    starGeo.setAttribute('position', new THREE.BufferAttribute(starPos, 3));
    this.starPoints = new THREE.Points(
      starGeo,
      new THREE.PointsMaterial({ color: 0xf8fafc, size: 1.5, transparent: true, opacity: 0, fog: false })
    );
    this.starPoints.frustumCulled = false;
    scene.add(this.starPoints);

    // Awan 3D Lembut
    this.cloudGroup = new THREE.Group();
    const cloudMat = new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.72, fog: true });
    for (let i = 0; i < 18; i++) {
      const cg = new THREE.Group();
      const puffs = 2 + Math.floor(Math.random() * 3);
      for (let p = 0; p < puffs; p++) {
        const puff = new THREE.Mesh(new THREE.DodecahedronGeometry(4.5 + Math.random() * 3.5, 1), cloudMat);
        puff.scale.set(1.5, 0.42, 1.1);
        puff.position.set((p - 1) * 4.8, (Math.random() - 0.5) * 1.1, (Math.random() - 0.5) * 3.2);
        cg.add(puff);
      }
      const ox = (Math.random() - 0.5) * 320;
      const oz = (Math.random() - 0.5) * 320;
      cg.position.set(ox, 58 + Math.random() * 18, oz);
      this.cloudGroup.add(cg);
      this.clouds.push({ mesh: cg, ox, oz, speed: 1.2 + Math.random() * 1.4 });
    }
    scene.add(this.cloudGroup);
  },

  getTerrainMaterial() {
    if (!this.initialized) this.init();
    return this.terrainMat;
  },

  populateChunkGrass(group, ox, oz, cx, cz) {
    if (!this.initialized || !this.grassGeo || !this.grassMat) return;
    const count = 90;
    const dummy = new THREE.Object3D();
    const matrices = [];
    const colors = [];
    const cGreen1 = new THREE.Color(0x4d9e3f);
    const cGreen2 = new THREE.Color(0x6fb347);
    const cDry    = new THREE.Color(0xcab465);

    for (let i = 0; i < count; i++) {
      const rx = ox + (WorldGen.hash2(cx, cz, i * 13 + 31) - 0.5) * (CHUNK_SIZE - 2);
      const rz = oz + (WorldGen.hash2(cx, cz, i * 13 + 32) - 0.5) * (CHUNK_SIZE - 2);
      if (Math.abs(rx - WorldGen.getRoadX(rz)) < 4.5) continue;

      const ry = WorldGen.getTerrainHeight(rx, rz);
      if (ry < WATER_LEVEL + 0.35 || ry > 14.5) continue;

      const biome = WorldGen.getBiomeAt(rx, rz);
      if (biome === 'kota') continue;
      if (biome === 'gurun' && WorldGen.hash2(cx, cz, i * 13 + 33) > 0.25) continue;

      dummy.position.set(rx, ry - 0.03, rz);
      dummy.rotation.set(0, WorldGen.hash2(cx, cz, i * 13 + 34) * Math.PI * 2, 0);
      const s = 0.75 + WorldGen.hash2(cx, cz, i * 13 + 35) * 0.85;
      dummy.scale.set(s, s, s);
      dummy.updateMatrix();
      matrices.push(dummy.matrix.clone());

      const col = biome === 'gurun' || biome === 'pantai'
        ? cDry
        : (i % 2 === 0 ? cGreen1 : cGreen2);
      colors.push(col.clone());
    }

    if (matrices.length === 0) return;
    const im = new THREE.InstancedMesh(this.grassGeo, this.grassMat, matrices.length);
    im.userData.sharedGeo = true;
    im.receiveShadow = true;
    for (let i = 0; i < matrices.length; i++) {
      im.setMatrixAt(i, matrices[i]);
      im.setColorAt(i, colors[i]);
    }
    im.instanceMatrix.needsUpdate = true;
    if (im.instanceColor) im.instanceColor.needsUpdate = true;
    group.add(im);
  },

  init() {
    if (this.initialized) return;
    this.initialized = true;
    this.generateTextures();
    this.upgradeSharedMaterials();
    this.setupEnvironmentReflection();
    this.setupSkyAndAtmosphere();
  },

  update(dt, env) {
    if (!this.initialized) return;
    const { px, pz, sunX, sunY, dayFactor, curSky } = env;

    // Riak gelombang air laut bergerak pelan
    if (this.texWaterNormal) {
      this.texWaterNormal.offset.x = (this.texWaterNormal.offset.x + dt * 0.028) % 1;
      this.texWaterNormal.offset.y = (this.texWaterNormal.offset.y + dt * 0.019) % 1;
    }

    // Sky Dome mengikuti pemain
    if (this.skyDome) {
      this.skyDome.position.set(px, 0, pz);
      const dayTop   = new THREE.Color(0x2563eb);
      const duskTop  = new THREE.Color(0x4338ca);
      const nightTop = new THREE.Color(0x030712);
      const stormTop = new THREE.Color(0x475569);

      const topCol = nightTop.clone().lerp(dayTop, dayFactor);
      if (sunY > -0.15 && sunY < 0.25) topCol.lerp(duskTop, 0.45);
      if (typeof WeatherSys !== 'undefined' && ['rain', 'storm', 'fog', 'sandstorm'].includes(WeatherSys.current)) {
        topCol.lerp(stormTop, dayFactor * 0.65);
      }

      this.skyUniforms.uTopColor.value.copy(topCol);
      this.skyUniforms.uHorizonColor.value.copy(curSky);
      this.skyUniforms.uSunDir.value.set(sunX, sunY, 0.45).normalize();
      this.skyUniforms.uSunGlow.value = Math.max(0.0, Math.min(1.0, (sunY + 0.2) * 2.2));
    }

    // Posisi Matahari & Bulan
    if (this.sunDisc && this.moonDisc) {
      const dir = new THREE.Vector3(sunX, sunY, 0.45).normalize();
      this.sunDisc.position.set(px + dir.x * 265, dir.y * 265, pz + dir.z * 265);
      this.sunDisc.visible = dir.y > -0.15;

      this.moonDisc.position.set(px - dir.x * 265, -dir.y * 265, pz - dir.z * 265);
      this.moonDisc.visible = -dir.y > -0.15;
    }

    // Bintang Malam
    if (this.starPoints) {
      this.starPoints.position.set(px, 0, pz);
      const starAlpha = Math.max(0, 1 - dayFactor * 2.2);
      this.starPoints.material.opacity = starAlpha;
      this.starPoints.visible = starAlpha > 0.02;
    }

    // Awan Berarak
    if (this.cloudGroup) {
      this.cloudGroup.position.set(px, 0, pz);
      for (const c of this.clouds) {
        c.ox += c.speed * dt;
        if (c.ox > 160) c.ox = -160;
        c.mesh.position.x = c.ox;
      }
      const cloudBright = 0.28 + dayFactor * 0.72;
      this.cloudGroup.children[0]?.children[0]?.material.color.setRGB(cloudBright, cloudBright, cloudBright + 0.03);
    }
  }
};
NOMAD_END;

    if (file_put_contents($baseDir . '/js/graphics.js', $graphicsJs) !== false) {
        logMsg('File baru dibuat: js/graphics.js', 'ok');
    } else {
        logMsg('Gagal membuat js/graphics.js', 'error');
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
            if (strpos($content, $p['anchor']) !== false) {
                $content = str_replace($p['anchor'], $p['replace'], $content);
                logMsg("{$relPath}: [{$p['name']}] berhasil di-patch.", 'ok');
            } else {
                logMsg("{$relPath}: Anchor [{$p['name']}] tidak ditemukan!", 'error');
            }
        }
        file_put_contents($full, $content);
    }

    // 3. Patch js/scene.js (ACESFilmicToneMapping, sRGBEncoding, 2048px Soft Shadows)
    patchFile('js/scene.js', [
        [
            'name' => 'Aktifkan ACESFilmicToneMapping & sRGBEncoding pada renderer',
            'skipIf' => 'ACESFilmicToneMapping',
            'anchor' => 'renderer.shadowMap.type = THREE.PCFSoftShadowMap;',
            'replace' => "renderer.shadowMap.type = THREE.PCFSoftShadowMap;\nrenderer.outputEncoding = THREE.sRGBEncoding;\nrenderer.toneMapping = THREE.ACESFilmicToneMapping;\nrenderer.toneMappingExposure = 1.06;"
        ],
        [
            'name' => 'Naikkan resolusi bayangan ke 2048px & tambah anti shadow-acne bias',
            'skipIf' => 'sunLight.shadow.bias',
            'anchor' => "sunLight.shadow.mapSize.width = 1024;\nsunLight.shadow.mapSize.height = 1024;",
            'replace' => "sunLight.shadow.mapSize.width = 2048;\nsunLight.shadow.mapSize.height = 2048;\nsunLight.shadow.bias = -0.0006;\nsunLight.shadow.normalBias = 0.035;"
        ]
    ]);

    // 4. Patch js/world.js (Smooth Terrain + Material PBR + Rumput InstancedMesh + Variasi Pohon)
    patchFile('js/world.js', [
        [
            'name' => 'Perindah tajuk pohon Oak agar tidak bulat tunggal',
            'skipIf' => 'top2',
            'anchor' => "  } else if (kind === 'oak') {\n    const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.38, 2.5, 6), MAT_WOOD);\n    trunk.position.y = 1.25; trunk.castShadow = true;\n    const top = new THREE.Mesh(new THREE.DodecahedronGeometry(1.75), MAT_LEAF);\n    top.position.y = 3.2; top.castShadow = true;\n    g.add(trunk, top);",
            'replace' => "  } else if (kind === 'oak') {\n    const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.4, 2.6, 7), MAT_WOOD);\n    trunk.position.y = 1.3; trunk.castShadow = true;\n    const top1 = new THREE.Mesh(new THREE.DodecahedronGeometry(1.55, 1), MAT_LEAF);\n    top1.position.set(0, 3.3, 0); top1.castShadow = true;\n    const top2 = new THREE.Mesh(new THREE.DodecahedronGeometry(1.15, 1), MAT_LEAF);\n    top2.position.set(0.75, 2.9, 0.45); top2.castShadow = true;\n    const top3 = new THREE.Mesh(new THREE.DodecahedronGeometry(1.05, 1), MAT_LEAF);\n    top3.position.set(-0.7, 2.85, -0.4); top3.castShadow = true;\n    g.add(trunk, top1, top2, top3);"
        ],
        [
            'name' => 'Gunakan material terrain halus dengan bumpMap & roughnessMap',
            'skipIf' => 'GraphicsSys.getTerrainMaterial()',
            'anchor' => "const terrainMat = new THREE.MeshStandardMaterial({ vertexColors: true, roughness: 0.92, metalness: 0.02, flatShading: true });",
            'replace' => "const terrainMat = (typeof GraphicsSys !== 'undefined') ? GraphicsSys.getTerrainMaterial() : new THREE.MeshStandardMaterial({ vertexColors: true, roughness: 0.9, metalness: 0.02 });"
        ],
        [
            'name' => 'Tambah variasi ukuran alami pada pohon & batu',
            'skipIf' => 'const propScale',
            'anchor' => "      if (propKind) {\n        const m = makePropMesh(propKind);\n        m.position.set(rx, ry, rz);\n        m.rotation.y = rKind * Math.PI * 2;\n        group.add(m);",
            'replace' => "      if (propKind) {\n        const m = makePropMesh(propKind);\n        const propScale = 0.86 + WorldGen.hash2(cx, cz, i * 11 + 5) * 0.38;\n        m.position.set(rx, ry, rz);\n        m.rotation.y = rKind * Math.PI * 2;\n        m.scale.setScalar(propScale);\n        group.add(m);"
        ],
        [
            'name' => 'Sebarkan rumput liar InstancedMesh pada setiap chunk',
            'skipIf' => 'GraphicsSys.populateChunkGrass',
            'anchor' => "  scene.add(group);\n  activeChunks.set(key, { group, colliders, interactables });",
            'replace' => "  if (typeof GraphicsSys !== 'undefined') GraphicsSys.populateChunkGrass(group, ox, oz, cx, cz);\n\n  scene.add(group);\n  activeChunks.set(key, { group, colliders, interactables });"
        ],
        [
            'name' => 'Lindungi sharedGeo agar geometry rumput tidak ter-dispose saat unload chunk',
            'skipIf' => '!obj.userData.sharedGeo',
            'anchor' => 'ch.group.traverse(obj => { if (obj.geometry && obj.geometry.dispose) obj.geometry.dispose(); });',
            'replace' => 'ch.group.traverse(obj => { if (obj.geometry && obj.geometry.dispose && !obj.userData.sharedGeo) obj.geometry.dispose(); });'
        ]
    ]);

    // 5. Patch js/van.js (Cat Metalik, Kaca Reflektif, Tekstur Kayu & Kain, Spion, Wiper, Spakbor & Velg Detail)
    $vanExtrasCode = <<<'NOMAD_END'
  // Detail Eksterior Realistis: Spion Kiri/Kanan, Wiper, Plat Nomor, Ventilasi Atap & Spakbor Roda
  [-1.14, 1.14].forEach((mx, idx) => {
    const arm = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.04, 0.04), vanMaterials.tire);
    arm.position.set(mx, 1.56, 1.98);
    const mirror = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.22, 0.14), vanMaterials.tire);
    mirror.position.set(mx + (idx === 0 ? -0.07 : 0.07), 1.58, 1.96);
    vanBodyTilt.add(arm, mirror);
  });
  [-0.38, 0.38].forEach(wx => {
    const wiper = new THREE.Mesh(new THREE.BoxGeometry(0.55, 0.025, 0.03), vanMaterials.tire);
    wiper.position.set(wx, 1.5, 2.15);
    wiper.rotation.z = 0.22;
    vanBodyTilt.add(wiper);
  });
  const frontPlate = new THREE.Mesh(new THREE.BoxGeometry(0.52, 0.13, 0.04), new THREE.MeshStandardMaterial({ color: 0xf8fafc, roughness: 0.4 }));
  frontPlate.position.set(0, 0.56, 2.52);
  const rearPlate = frontPlate.clone();
  rearPlate.position.set(0, 0.62, -2.51);
  const roofVent = new THREE.Mesh(new THREE.BoxGeometry(0.58, 0.1, 0.58), vanMaterials.chrome);
  roofVent.position.set(0, 2.36, -1.25);
  vanBodyTilt.add(frontPlate, rearPlate, roofVent);

  // Bumper & Grille
NOMAD_END;

    patchFile('js/van.js', [
        [
            'name' => 'Tune material Camper Van menjadi metalik & kaca reflektif',
            'skipIf' => 'envMapIntensity',
            'anchor' => "  primary:   new THREE.MeshStandardMaterial({ color: state.van.primaryColor, roughness: 0.4, metalness: 0.15 }),\n  accent:    new THREE.MeshStandardMaterial({ color: state.van.accentColor, roughness: 0.45 }),\n  glass:     new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.1, metalness: 0.5, transparent: true, opacity: 0.72 }),\n  chrome:    new THREE.MeshStandardMaterial({ color: 0xcbd5e1, roughness: 0.25, metalness: 0.8 }),",
            'replace' => "  primary:   new THREE.MeshStandardMaterial({ color: state.van.primaryColor, roughness: 0.26, metalness: 0.36, envMapIntensity: 1.2 }),\n  accent:    new THREE.MeshStandardMaterial({ color: state.van.accentColor, roughness: 0.34, metalness: 0.18, envMapIntensity: 1.0 }),\n  glass:     new THREE.MeshStandardMaterial({ color: 0x0f172a, roughness: 0.05, metalness: 0.85, transparent: true, opacity: 0.62, envMapIntensity: 1.6 }),\n  chrome:    new THREE.MeshStandardMaterial({ color: 0xdbe2ea, roughness: 0.16, metalness: 0.9, envMapIntensity: 1.6 }),"
        ],
        [
            'name' => 'Terapkan tekstur kayu & kain pada material interior van',
            'skipIf' => 'GraphicsSys.texWood',
            'anchor' => "  const matWoodFloor = new THREE.MeshStandardMaterial({ color: 0x854d0e, roughness: 0.85 });\n  const matCabinet   = new THREE.MeshStandardMaterial({ color: 0xd6d3d1, roughness: 0.7 });\n  const matBedBlanket = new THREE.MeshStandardMaterial({ color: 0xea580c, roughness: 0.85 });",
            'replace' => "  const hasG = typeof GraphicsSys !== 'undefined' && GraphicsSys.initialized;\n  const matWoodFloor = new THREE.MeshStandardMaterial({ color: 0x854d0e, roughness: 0.82, map: hasG ? GraphicsSys.texWood : null, bumpMap: hasG ? GraphicsSys.texWoodBump : null, bumpScale: 0.05 });\n  const matCabinet   = new THREE.MeshStandardMaterial({ color: 0xd6d3d1, roughness: 0.65 });\n  const matBedBlanket = new THREE.MeshStandardMaterial({ color: 0xea580c, roughness: 0.9, map: hasG ? GraphicsSys.texFabric : null, bumpMap: hasG ? GraphicsSys.texFabricBump : null, bumpScale: 0.05 });"
        ],
        [
            'name' => 'Tambah detail spion, wiper, plat nomor & ventilasi atap van',
            'skipIf' => 'roofVent',
            'anchor' => "  // Bumper & Grille",
            'replace' => $vanExtrasCode
        ],
        [
            'name' => 'Terapkan tekstur kain kanvas pada Tenda Extension',
            'skipIf' => 'hasG ? GraphicsSys.texFabric',
            'anchor' => "  const matCanvas  = new THREE.MeshStandardMaterial({ color: 0xd97706, roughness: 0.85, side: THREE.DoubleSide });\n  const matWall    = new THREE.MeshStandardMaterial({ color: 0xfef3c7, roughness: 0.85, side: THREE.DoubleSide });",
            'replace' => "  const hasG = typeof GraphicsSys !== 'undefined' && GraphicsSys.initialized;\n  const matCanvas  = new THREE.MeshStandardMaterial({ color: 0xd97706, roughness: 0.88, side: THREE.DoubleSide, map: hasG ? GraphicsSys.texFabric : null, bumpMap: hasG ? GraphicsSys.texFabricBump : null, bumpScale: 0.06 });\n  const matWall    = new THREE.MeshStandardMaterial({ color: 0xfef3c7, roughness: 0.9, side: THREE.DoubleSide, map: hasG ? GraphicsSys.texFabric : null, bumpMap: hasG ? GraphicsSys.texFabricBump : null, bumpScale: 0.05 });"
        ]
    ]);

    // 6. Patch js/ui.js (Update Sky Dome, Matahari, Bulan, Bintang, Awan & Riak Air tiap frame)
    patchFile('js/ui.js', [
        [
            'name' => 'Hubungkan GraphicsSys.update pada updateSurvivalAndEnvironment',
            'skipIf' => 'GraphicsSys.update',
            'anchor' => "  scene.background.copy(curSky);\n  scene.fog.color.copy(curSky);",
            'replace' => "  scene.background.copy(curSky);\n  scene.fog.color.copy(curSky);\n  if (typeof GraphicsSys !== 'undefined') {\n    GraphicsSys.update(dt, { px, pz, sunX, sunY, dayFactor, curSky });\n  }"
        ]
    ]);

    // 7. Patch js/main.js (Inisialisasi GraphicsSys sebelum buildCamperVan)
    patchFile('js/main.js', [
        [
            'name' => 'Panggil GraphicsSys.init() sebelum buildCamperVan()',
            'skipIf' => 'GraphicsSys.init()',
            'anchor' => 'buildCamperVan();',
            'replace' => "if (typeof GraphicsSys !== 'undefined') GraphicsSys.init();\nbuildCamperVan();"
        ]
    ]);

    // 8. Patch index.html (Muat script js/graphics.js setelah js/scene.js)
    patchFile('index.html', [
        [
            'name' => 'Muat script js/graphics.js setelah js/scene.js',
            'skipIf' => 'js/graphics.js',
            'anchor' => '<script src="js/scene.js"></script>',
            'replace' => "<script src=\"js/scene.js\"></script>\n  <script src=\"js/graphics.js\"></script>"
        ]
    ]);
}

if (!$isCli): ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Patcher: Upgrade Grafis Realistis Tahap 1</title>
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
    <h2 style="margin-top:0">✨🌅 Upgrade Grafis Realistis Tahap 1 Selesai</h2>
    <p style="color:#94a3b8; font-size:14px;">File: <code>update_20261127_0905_grafis_realistis_pbr.php</code></p>
    <?php foreach ($logs as $item): ?>
      <div class="log <?= htmlspecialchars($item['type']) ?>"><?= htmlspecialchars($item['msg']) ?></div>
    <?php endforeach; ?>
    <a class="play-btn" href="index.html">🎮 Buka & Rasakan Visual Baru</a>
  </div>
</body>
</html>
<?php endif; ?>
