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
    // Noise hujan procedural
    const bufSize = this.ctx.sampleRate * 2;
    const noiseBuf = this.ctx.createBuffer(1, bufSize, this.ctx.sampleRate);
    const out = noiseBuf.getChannelData(0);
    for (let i = 0; i < bufSize; i++) out[i] = Math.random() * 2 - 1;
    const rainSrc = this.ctx.createBufferSource();
    rainSrc.buffer = noiseBuf;
    rainSrc.loop = true;
    this.rainFilter = this.ctx.createBiquadFilter();
    this.rainFilter.type = 'lowpass';
    this.rainFilter.frequency.value = 1400;
    this.rainGain = this.ctx.createGain();
    this.rainGain.gain.value = 0;
    rainSrc.connect(this.rainFilter);
    this.rainFilter.connect(this.rainGain);
    this.rainGain.connect(this.ctx.destination);
    rainSrc.start();
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
  bump()     { this.playTone(85, 0.18, 'square', 0.14); },
  updateRain(vol, muffled) {
    if (!this.ctx || !this.rainGain) return;
    const targetVol = this.enabled ? vol * (muffled ? 0.035 : 0.065) : 0;
    const targetFreq = muffled ? 440 : 1650;
    this.rainGain.gain.setTargetAtTime(targetVol, this.ctx.currentTime, 0.25);
    this.rainFilter.frequency.setTargetAtTime(targetFreq, this.ctx.currentTime, 0.25);
  }
};

function toggleAudio() {
  AudioSys.enabled = !AudioSys.enabled;
  document.getElementById('btn-audio').textContent = AudioSys.enabled ? '🔊 Audio' : '🔇 Bisukan';
}