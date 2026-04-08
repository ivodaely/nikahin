// frontend/assets/js/three-particles.js
// Romantic floating particles background using Three.js

function initParticles(canvas) {
  if (!canvas || typeof THREE === 'undefined') return;

  const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(window.innerWidth, window.innerHeight);
  renderer.setClearColor(0x000000, 0);

  const scene  = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(60, window.innerWidth / window.innerHeight, 0.1, 1000);
  camera.position.z = 5;

  // ── Floating particles ──────────────────────
  const COUNT = 180;
  const geo   = new THREE.BufferGeometry();
  const pos   = new Float32Array(COUNT * 3);
  const vel   = new Float32Array(COUNT * 3);
  const sizes = new Float32Array(COUNT);

  for (let i = 0; i < COUNT; i++) {
    pos[i * 3]     = (Math.random() - .5) * 20;
    pos[i * 3 + 1] = (Math.random() - .5) * 20;
    pos[i * 3 + 2] = (Math.random() - .5) * 10;
    vel[i * 3]     = (Math.random() - .5) * .002;
    vel[i * 3 + 1] = Math.random() * .003 + .001;
    vel[i * 3 + 2] = 0;
    sizes[i]       = Math.random() * 3 + 1;
  }

  geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  geo.setAttribute('size',     new THREE.BufferAttribute(sizes, 1));

  // Custom shader material for soft glowing dots
  const mat = new THREE.ShaderMaterial({
    transparent: true,
    depthWrite:  false,
    uniforms: {
      uTime:  { value: 0 },
      uColor: { value: new THREE.Color(0xc9a96e) },
    },
    vertexShader: `
      attribute float size;
      uniform float uTime;
      varying float vAlpha;
      void main() {
        vAlpha = 0.3 + 0.3 * sin(uTime * 0.5 + position.x);
        vec4 mv = modelViewMatrix * vec4(position, 1.0);
        gl_PointSize = size * (300.0 / -mv.z);
        gl_Position  = projectionMatrix * mv;
      }
    `,
    fragmentShader: `
      uniform vec3 uColor;
      varying float vAlpha;
      void main() {
        float d = length(gl_PointCoord - vec2(0.5));
        if (d > 0.5) discard;
        float a = smoothstep(0.5, 0.0, d) * vAlpha;
        gl_FragColor = vec4(uColor, a);
      }
    `,
  });

  const points = new THREE.Points(geo, mat);
  scene.add(points);

  // ── Subtle lines (ornament) ──────────────────
  const lineGeo = new THREE.BufferGeometry();
  const linePos = [];
  for (let i = 0; i < 6; i++) {
    const x = (Math.random() - .5) * 16;
    const y = (Math.random() - .5) * 16;
    linePos.push(x - 1, y, -3, x + 1, y, -3);
  }
  lineGeo.setAttribute('position', new THREE.BufferAttribute(new Float32Array(linePos), 3));
  const lineMat = new THREE.LineBasicMaterial({ color: 0x3d2560, transparent: true, opacity: .3 });
  const lines   = new THREE.LineSegments(lineGeo, lineMat);
  scene.add(lines);

  // ── Animation loop ──────────────────────────
  let frame;
  function animate(t = 0) {
    frame = requestAnimationFrame(animate);
    mat.uniforms.uTime.value = t * .001;

    const p = geo.attributes.position.array;
    for (let i = 0; i < COUNT; i++) {
      p[i * 3]     += vel[i * 3];
      p[i * 3 + 1] += vel[i * 3 + 1];
      // Wrap around
      if (p[i * 3 + 1] >  10) p[i * 3 + 1] = -10;
      if (p[i * 3]     >  10) p[i * 3]     = -10;
      if (p[i * 3]     < -10) p[i * 3]     =  10;
    }
    geo.attributes.position.needsUpdate = true;

    lines.rotation.z += .0003;
    renderer.render(scene, camera);
  }
  animate();

  // Resize
  window.addEventListener('resize', () => {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
  });

  return () => { cancelAnimationFrame(frame); renderer.dispose(); };
}

// ── Invitation cinematic scene ────────────────────────────
function initInvitationScene(canvas, designJson) {
  if (!canvas || typeof THREE === 'undefined') return;

  const W = canvas.offsetWidth  || 400;
  const H = canvas.offsetHeight || 300;

  const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(W, H);
  renderer.setClearColor(0x000000, 0);

  const scene  = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(50, W / H, 0.1, 100);
  camera.position.z = 3;

  // Color from design
  const accent = designJson?.palette?.accent || '#c9a96e';
  const color  = new THREE.Color(accent);

  // Floating ring
  const ringGeo = new THREE.TorusGeometry(.8, .02, 8, 60);
  const ringMat = new THREE.MeshBasicMaterial({ color, wireframe: false, transparent: true, opacity: .6 });
  const ring    = new THREE.Mesh(ringGeo, ringMat);
  scene.add(ring);

  // Inner ring
  const ring2    = new THREE.Mesh(new THREE.TorusGeometry(.55, .01, 8, 60), new THREE.MeshBasicMaterial({ color, transparent: true, opacity: .3 }));
  scene.add(ring2);

  // Particles
  const pCount = 60;
  const pGeo   = new THREE.BufferGeometry();
  const pPos   = new Float32Array(pCount * 3);
  for (let i = 0; i < pCount; i++) {
    const angle = (i / pCount) * Math.PI * 2;
    const r     = .9 + (Math.random() - .5) * .4;
    pPos[i * 3]     = Math.cos(angle) * r;
    pPos[i * 3 + 1] = Math.sin(angle) * r;
    pPos[i * 3 + 2] = (Math.random() - .5) * .3;
  }
  pGeo.setAttribute('position', new THREE.BufferAttribute(pPos, 3));
  const pMat = new THREE.PointsMaterial({ color, size: .02, transparent: true, opacity: .7 });
  scene.add(new THREE.Points(pGeo, pMat));

  let frame;
  function animate(t = 0) {
    frame = requestAnimationFrame(animate);
    const time = t * .001;
    ring.rotation.z  = time * .3;
    ring.rotation.x  = Math.sin(time * .2) * .3;
    ring2.rotation.z = -time * .5;
    renderer.render(scene, camera);
  }
  animate();

  window.addEventListener('resize', () => {
    const nW = canvas.offsetWidth;
    const nH = canvas.offsetHeight;
    camera.aspect = nW / nH;
    camera.updateProjectionMatrix();
    renderer.setSize(nW, nH);
  });

  return () => { cancelAnimationFrame(frame); renderer.dispose(); };
}
