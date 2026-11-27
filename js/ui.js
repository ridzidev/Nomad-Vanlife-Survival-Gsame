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

  if (typeof WeatherSys !== 'undefined') WeatherSys.update(dt, biome);
  const isDay = sunY > 0;
  const dayFactor = Math.max(0, Math.min(1, sunY * 2.5 + 0.2));
  const weatherLightMult = (typeof WeatherSys !== 'undefined') ? (0.45 + WeatherSys.getSolarMult() * 0.55) : 1;
  const flash = (typeof WeatherSys !== 'undefined') ? WeatherSys.lightningFlash * 2.5 : 0;
  sunLight.intensity = dayFactor * 1.15 * weatherLightMult + flash;
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

  const inTent = (typeof isPlayerInTent === 'function') ? isPlayerInTent() : false;
  if (typeof WeatherSys !== 'undefined') {
    AudioSys.updateRain(WeatherSys.getRainVol(), state.player.inVan || inCabinView || inTent);
  }
  if (state.van.activeDecor.solarPanel && isDay) {
    const sMult = (typeof WeatherSys !== 'undefined') ? WeatherSys.getSolarMult() : 1;
    state.van.battery = Math.min(getMaxBat(), state.van.battery + 1.4 * sunY * sMult * dt);
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

  const nearFire = (typeof BuildSys !== 'undefined') ? BuildSys.isNearLitCampfire(px, pz) : null;
  const thirstMult = (biome === 'gurun' && !inTent && !state.player.inVan) ? 1.7 : 1.0;
  state.player.hunger = Math.max(0, state.player.hunger - 0.32 * dt);
  state.player.thirst = Math.max(0, state.player.thirst - 0.45 * thirstMult * dt);
  if (state.player.inVan || inTent || nearFire) {
    state.player.stamina = Math.min(100, state.player.stamina + (nearFire ? 18 : 13) * dt);
  }
  if (nearFire && state.player.hp < 100 && state.player.hunger > 35 && state.player.thirst > 35) {
    state.player.hp = Math.min(100, state.player.hp + 0.5 * dt);
  }

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
function toggleDecor(id) {
  state.van.activeDecor[id] = !state.van.activeDecor[id];
  if (id === 'tentExtension') state.van.tentOpen = !!state.van.activeDecor[id];
  if (state.van.tentOpen) { state.van.speed = 0; state.van.doorOpen = true; }
  syncVanVisuals(); renderCustomizeModal(); AudioSys.playTone(460, 0.06); saveGameState();
}

function unlockDecor(id) {
  const d = DECORS.find(x => x.id === id);
  if (!d) return;
  for (const [m, q] of Object.entries(d.cost)) if (countItem(state.inventory, m) < q) return;
  for (const [m, q] of Object.entries(d.cost)) consumeItem(state.inventory, m, q);
  state.van.unlockedDecor[id] = true;
  state.van.activeDecor[id] = true;
  if (id === 'tentExtension') { state.van.tentOpen = true; state.van.doorOpen = true; state.van.speed = 0; }
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
  const wEl = document.getElementById('txt-weather');
  if (wEl && typeof WeatherSys !== 'undefined') wEl.textContent = WeatherSys.getLabel();
  const dBtn = document.getElementById('btn-door-van');
  if (dBtn) dBtn.textContent = state.van.doorOpen ? '🚪 Tutup Pintu [G]' : '🚪 Buka Pintu [G]';
  const cBtn = document.getElementById('btn-cabin-view');
  if (cBtn) cBtn.className = `btn ${inCabinView ? 'success' : ''}`;
  const tBtn = document.getElementById('btn-tent-van');
  if (tBtn) {
    tBtn.textContent = !v.unlockedDecor.tentExtension ? '⛺ Bikin Tenda [T]' : (v.tentOpen ? '⛺ Lipat Tenda [T]' : '⛺ Pasang Tenda [T]');
    tBtn.className = `btn ${v.tentOpen ? 'success' : ''}`;
  }
  const bBar = document.getElementById('build-bar');
  const bBtn = document.getElementById('btn-build-mode');
  if (typeof BuildSys !== 'undefined' && bBar && bBtn) {
    bBar.style.display = (BuildSys.mode && !p.inVan) ? 'flex' : 'none';
    bBtn.className = `btn ${BuildSys.mode ? 'success' : ''}`;
    const fBtn = document.getElementById('btn-build-fence');
    const gBtn = document.getElementById('btn-build-gate');
    const cfBtn = document.getElementById('btn-build-fire');
    if (fBtn) fBtn.className = `btn ${BuildSys.mode === 'fence' ? 'primary' : ''}`;
    if (gBtn) gBtn.className = `btn ${BuildSys.mode === 'gate' ? 'primary' : ''}`;
    if (cfBtn) cfBtn.className = `btn ${BuildSys.mode === 'campfire' ? 'primary' : ''}`;
    const wCount = countItem(state.inventory, 'wood');
    const wEl2 = document.getElementById('build-wood-count');
    if (wEl2) wEl2.textContent = `🪵 Kayu: ${wCount}`;
  }
  document.getElementById('txt-coords').textContent = `X: ${Math.round(px)} | Z: ${Math.round(pz)} • Jarak: ${Math.round(state.distanceDriven)} m`;
}