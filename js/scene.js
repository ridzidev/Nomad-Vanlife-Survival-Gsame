// =========================================================================
// THREE.JS SCENE, KAMERA, CAHAYA & GLOBAL INPUT STATE
// =========================================================================
const keys = {};
let camAngle = Math.PI;
let camPitch = 0.32;
let camDistMode = 0;
let isDragging = false;
let activeModal = null;

const canvas = document.getElementById('game-canvas');
const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, powerPreference: 'high-performance' });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.setSize(window.innerWidth, window.innerHeight);
renderer.shadowMap.enabled = true;
renderer.shadowMap.type = THREE.PCFSoftShadowMap;

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x87ceeb);
scene.fog = new THREE.FogExp2(0x9ec5ab, 0.0068);

const camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.2, 420);

const hemiLight = new THREE.HemisphereLight(0xdbeafe, 0x3f4d36, 0.75);
scene.add(hemiLight);

const sunLight = new THREE.DirectionalLight(0xfff5e0, 1.1);
sunLight.castShadow = true;
sunLight.shadow.mapSize.width = 1024;
sunLight.shadow.mapSize.height = 1024;
sunLight.shadow.camera.near = 5;
sunLight.shadow.camera.far = 160;
const dCam = 48;
sunLight.shadow.camera.left = -dCam;
sunLight.shadow.camera.right = dCam;
sunLight.shadow.camera.top = dCam;
sunLight.shadow.camera.bottom = -dCam;
scene.add(sunLight);
scene.add(sunLight.target);

// Shared Materials
const MAT_WOOD   = new THREE.MeshStandardMaterial({ color: 0x7c4a2d, roughness: 0.9 });
const MAT_LEAF   = new THREE.MeshStandardMaterial({ color: 0x2e7d32, roughness: 0.85 });
const MAT_PINE   = new THREE.MeshStandardMaterial({ color: 0x1b5e20, roughness: 0.85 });
const MAT_CACTUS = new THREE.MeshStandardMaterial({ color: 0x3f8f29, roughness: 0.8 });
const MAT_ROCK   = new THREE.MeshStandardMaterial({ color: 0x7c8594, roughness: 0.92, flatShading: true });
const MAT_WALL   = new THREE.MeshStandardMaterial({ color: 0xd6d3d1, roughness: 0.85 });
const MAT_CITY   = new THREE.MeshStandardMaterial({ color: 0x64748b, roughness: 0.8 });
const MAT_ROOF   = new THREE.MeshStandardMaterial({ color: 0x9a3412, roughness: 0.8 });
const MAT_WATER  = new THREE.MeshStandardMaterial({ color: 0x0ea5e9, transparent: true, opacity: 0.72, roughness: 0.15, metalness: 0.2 });