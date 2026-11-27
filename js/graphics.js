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