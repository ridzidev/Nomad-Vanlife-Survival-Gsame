// =========================================================================
// CAMPER VAN 3D MODEL, DEKORASI & FISIKA BERKENDARA
// =========================================================================
const vanGroup = new THREE.Group();
const vanBodyTilt = new THREE.Group();
vanGroup.add(vanBodyTilt);
scene.add(vanGroup);

const vanMaterials = {
  primary:   new THREE.MeshStandardMaterial({ color: state.van.primaryColor, roughness: 0.4, metalness: 0.15 }),
  accent:    new THREE.MeshStandardMaterial({ color: state.van.accentColor, roughness: 0.45 }),
  glass:     new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.1, metalness: 0.5, transparent: true, opacity: 0.72 }),
  chrome:    new THREE.MeshStandardMaterial({ color: 0xcbd5e1, roughness: 0.25, metalness: 0.8 }),
  tire:      new THREE.MeshStandardMaterial({ color: 0x18181b, roughness: 0.9 }),
  headlight: new THREE.MeshStandardMaterial({ color: 0xfef08a, emissive: 0xfef08a, emissiveIntensity: 0.1 }),
  taillight: new THREE.MeshStandardMaterial({ color: 0xdc2626, emissive: 0xdc2626, emissiveIntensity: 0.3 }),
  fairy:     new THREE.MeshStandardMaterial({ color: 0xfde047, emissive: 0xfacc15, emissiveIntensity: 1.5 })
};

let vanWheels = [];
let vanDecorMeshes = {};
let vanHeadLightObj, vanInteriorLight, vanDriverMesh, vanSideDoorGroup, vanFridgeLedMat;
let liveryMeshLeft, liveryMeshRight;
let vanTentGroup, vanTentLight;
let tentDeployProgress = 0;
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

function updateVanLiveryTexture() {
  if (!liveryMeshRight) return;
  const c = document.createElement('canvas');
  c.width = 512; c.height = 128;
  const ctx = c.getContext('2d');
  ctx.clearRect(0, 0, 512, 128);

  const liv = state.van.livery;
  if (liv === 'stripe') {
    ctx.fillStyle = state.van.accentColor;
    ctx.fillRect(0, 36, 512, 24);
    ctx.fillStyle = '#f59e0b';
    ctx.fillRect(0, 66, 512, 14);
  } else if (liv === 'wave') {
    ctx.fillStyle = '#38bdf8';
    ctx.beginPath(); ctx.moveTo(0, 128);
    for (let x = 0; x <= 512; x += 16) ctx.lineTo(x, 68 + Math.sin(x * 0.04) * 24);
    ctx.lineTo(512, 128); ctx.closePath(); ctx.fill();
  } else if (liv === 'mountain') {
    ctx.fillStyle = state.van.accentColor;
    ctx.beginPath();
    ctx.moveTo(40, 128); ctx.lineTo(130, 30); ctx.lineTo(220, 128);
    ctx.lineTo(310, 20); ctx.lineTo(420, 128);
    ctx.fill();
  }
  const tex = new THREE.CanvasTexture(c);
  liveryMeshRight.material.map = tex;
  liveryMeshRight.material.needsUpdate = true;
}

function syncVanVisuals() {
  vanMaterials.primary.color.set(state.van.primaryColor);
  vanMaterials.accent.color.set(state.van.accentColor);
  updateVanLiveryTexture();
  for (const [k, mesh] of Object.entries(vanDecorMeshes)) {
    mesh.visible = !!state.van.activeDecor[k];
  }
  const hlOn = state.van.headlights && state.van.battery > 0;
  vanHeadLightObj.intensity = hlOn ? 2.4 : 0;
  vanMaterials.headlight.emissiveIntensity = hlOn ? 2.0 : 0.1;
}

// Dust particles
const dustParticles = [];
const dustGeo = new THREE.SphereGeometry(0.22, 5, 5);
const dustMat = new THREE.MeshBasicMaterial({ color: 0xd6c7a9, transparent: true, opacity: 0.45 });

function spawnDust(x, y, z) {
  if (dustParticles.length > 35) return;
  const m = new THREE.Mesh(dustGeo, dustMat);
  m.position.set(x + (Math.random() - 0.5) * 0.6, y + 0.15, z + (Math.random() - 0.5) * 0.6);
  scene.add(m);
  dustParticles.push({ mesh: m, life: 0.55 });
}

function updateDustParticles(dt) {
  for (let i = dustParticles.length - 1; i >= 0; i--) {
    const p = dustParticles[i];
    p.life -= dt;
    p.mesh.position.y += dt * 0.9;
    p.mesh.scale.multiplyScalar(1 + dt * 1.5);
    if (p.life <= 0) {
      scene.remove(p.mesh);
      dustParticles.splice(i, 1);
    }
  }
}

function updateVanPhysics(dt) {
  const van = state.van;
  const pl = state.player;

  // Tampilkan siluet pengemudi hanya saat di dalam mobil
  if (vanDriverMesh) vanDriverMesh.visible = pl.inVan && !inCabinView;

  if (pl.inVan) {
    const gas = (keys['KeyW'] || keys['ArrowUp']) ? 1 : 0;
    const rev = (keys['KeyS'] || keys['ArrowDown']) ? 1 : 0;
    const left = (keys['KeyA'] || keys['ArrowLeft']) ? 1 : 0;
    const right = (keys['KeyD'] || keys['ArrowRight']) ? 1 : 0;
    const brake = keys['Space'] ? 1 : 0;

    if (van.tentOpen && (gas || rev)) {
      van.speed = 0;
      if (Math.random() < 0.05) showToast('⛺ Lipat Tenda Extension [T] dulu sebelum jalan!');
    }
    const hasFuel = van.fuel > 0 && van.hp > 5 && !van.tentOpen;
    const accel = 18;
    const maxSpeed = 24;

    if (gas && hasFuel) van.speed += accel * dt;
    else if (rev && hasFuel) van.speed -= accel * 0.65 * dt;
    else van.speed *= Math.pow(0.18, dt);

    if (brake) van.speed *= Math.pow(0.02, dt);

    if ((gas || rev) && !hasFuel && Math.random() < 0.03) {
      showToast(van.fuel <= 0 ? '⛽ Bensin Habis! Cari jerigen atau Pom Bensin!' : '🔧 Mesin rusak berat! Gunakan Kit Perbaikan!');
    }

    van.speed = Math.max(-9, Math.min(maxSpeed, van.speed));

    const targetSteer = (left - right) * 0.55;
    steerVis += (targetSteer - steerVis) * Math.min(1, dt * 10);
    const turnFactor = Math.min(1, Math.abs(van.speed) / 3.5) * (van.speed >= 0 ? 1 : -1);
    van.yaw += steerVis * 2.4 * turnFactor * dt;

    const vx = Math.sin(van.yaw) * van.speed;
    const vz = Math.cos(van.yaw) * van.speed;
    let col = resolveCollisions(van.x + vx * dt, van.z + vz * dt, 1.55);
    if (typeof BuildSys !== 'undefined') {
      const bCol = BuildSys.resolveVanCollision(col.x, col.z, 1.55);
      if (bCol.hit) col = { x: bCol.x, z: bCol.z, hit: true };
    }

    if (col.hit && Math.abs(van.speed) > 7) {
      const dmgFactor = van.activeDecor.bullbar ? 0.45 : 1.0;
      van.hp = Math.max(0, van.hp - Math.abs(van.speed) * 0.35 * dmgFactor);
      van.speed *= -0.25;
      AudioSys.bump();
    }
    van.x = col.x;
    van.z = col.z;

    const groundY = WorldGen.getTerrainHeight(van.x, van.z);
    if (groundY < WATER_LEVEL - 0.35) van.speed *= 0.85;
    van.y = Math.max(groundY, WATER_LEVEL - 0.45);

    if (Math.abs(van.speed) > 0.6 && (gas || rev)) {
      van.fuel = Math.max(0, van.fuel - Math.abs(van.speed) * 0.015 * dt);
      state.distanceDriven += Math.abs(van.speed) * dt;
      van.battery = Math.min(getMaxBat(), van.battery + 0.55 * dt);
      if (Math.random() < 0.35 && groundY > WATER_LEVEL + 0.1) spawnDust(van.x, van.y, van.z);
    }

    pl.x = van.x; pl.y = van.y; pl.z = van.z;

    if (!isDragging && activeModal !== 'customize') {
      const desiredCamAngle = van.yaw + Math.PI;
      let diff = desiredCamAngle - camAngle;
      while (diff > Math.PI) diff -= Math.PI * 2;
      while (diff < -Math.PI) diff += Math.PI * 2;
      camAngle += diff * Math.min(1, dt * 3.2);
    }

    AudioSys.updateEngine(Math.abs(van.speed), true);
  } else {
    van.speed *= Math.pow(0.05, dt);
    van.y = Math.max(WorldGen.getTerrainHeight(van.x, van.z), WATER_LEVEL - 0.45);
    AudioSys.updateEngine(0, false);
  }

  // Transform 3D Camper Van & Suspensi
  vanGroup.position.set(van.x, van.y, van.z);
  vanGroup.rotation.y = van.yaw;

  const fwdX = Math.sin(van.yaw) * 1.4, fwdZ = Math.cos(van.yaw) * 1.4;
  const sideX = Math.cos(van.yaw) * 1.0, sideZ = -Math.sin(van.yaw) * 1.0;
  const hF = WorldGen.getTerrainHeight(van.x + fwdX, van.z + fwdZ);
  const hB = WorldGen.getTerrainHeight(van.x - fwdX, van.z - fwdZ);
  const hL = WorldGen.getTerrainHeight(van.x + sideX, van.z + sideZ);
  const hR = WorldGen.getTerrainHeight(van.x - sideX, van.z - sideZ);

  const pitch = Math.atan2(hB - hF, 2.8);
  const roll = Math.atan2(hL - hR, 2.0) + steerVis * (van.speed / 24) * 0.14;
  vanBodyTilt.rotation.x += (pitch - vanBodyTilt.rotation.x) * Math.min(1, dt * 10);
  vanBodyTilt.rotation.z += (roll - vanBodyTilt.rotation.z) * Math.min(1, dt * 10);

  vanWheels.forEach(w => {
    if (w.front) w.pivot.rotation.y = steerVis;
    w.mesh.rotation.x += van.speed * dt * 2.2;
  });

  const braking = (keys['KeyS'] || keys['Space']) && pl.inVan;
  vanMaterials.taillight.emissiveIntensity = braking ? 1.8 : 0.3;

  // Animasi pintu geser samping
  const targetDoor = state.van.doorOpen ? 1 : 0;
  doorSlideProgress += (targetDoor - doorSlideProgress) * Math.min(1, dt * 8);
  if (vanSideDoorGroup) {
    vanSideDoorGroup.position.x = -1.06 - doorSlideProgress * 0.08;
    vanSideDoorGroup.position.z = 0.35 - doorSlideProgress * 1.18;
  }

  // Animasi Tenda Extension Camper
  const targetTent = (state.van.tentOpen && state.van.unlockedDecor.tentExtension) ? 1 : 0;
  tentDeployProgress += (targetTent - tentDeployProgress) * Math.min(1, dt * 6);
  if (vanTentGroup) {
    vanTentGroup.visible = tentDeployProgress > 0.02;
    if (vanTentGroup.visible) {
      vanTentGroup.scale.set(Math.max(0.05, tentDeployProgress), 1, 1);
      vanTentGroup.position.x = (1 - tentDeployProgress) * -1.05;
    }
    if (vanTentLight) {
      const nightFactor = Math.max(0, 1 - Math.max(0, Math.sin((state.timeOfDay - 0.25) * Math.PI * 2) * 2 + 0.2));
      vanTentLight.intensity = tentDeployProgress * (0.35 + nightFactor * 1.5);
    }
  }
}