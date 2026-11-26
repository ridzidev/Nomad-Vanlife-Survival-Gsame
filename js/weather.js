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