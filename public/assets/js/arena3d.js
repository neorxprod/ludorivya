import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';

const canvas = document.querySelector('#arenaCanvas');
const dataNode = document.querySelector('#arena-data');

if (canvas && dataNode) {
    const games = JSON.parse(dataNode.textContent || '[]');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const scene = new THREE.Scene();
    scene.fog = new THREE.FogExp2(0x08090d, 0.055);

    const camera = new THREE.PerspectiveCamera(52, 1, 0.1, 100);
    camera.position.set(0, 7, 13);

    const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, preserveDrawingBuffer: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.setClearColor(0x08090d, 1);

    const controls = new OrbitControls(camera, canvas);
    controls.enableDamping = true;
    controls.autoRotate = !prefersReducedMotion;
    controls.autoRotateSpeed = 0.7;
    controls.maxDistance = 22;
    controls.minDistance = 7;

    scene.add(new THREE.AmbientLight(0x9ccfff, 1.2));
    const keyLight = new THREE.PointLight(0x4dd4ff, 120, 30);
    keyLight.position.set(0, 7, 7);
    scene.add(keyLight);

    const ring = new THREE.Mesh(
        new THREE.TorusGeometry(5.4, 0.018, 16, 160),
        new THREE.MeshBasicMaterial({ color: 0x4dd4ff, transparent: true, opacity: 0.42 })
    );
    ring.rotation.x = Math.PI / 2;
    scene.add(ring);

    const core = new THREE.Mesh(
        new THREE.IcosahedronGeometry(1.1, 2),
        new THREE.MeshStandardMaterial({
            color: 0x11141b,
            emissive: 0x4dd4ff,
            emissiveIntensity: 0.45,
            metalness: 0.4,
            roughness: 0.35,
            wireframe: true,
        })
    );
    scene.add(core);

    const starCount = 520;
    const starPositions = new Float32Array(starCount * 3);
    for (let i = 0; i < starCount; i++) {
        const radius = 16 + Math.random() * 18;
        const theta = Math.random() * Math.PI * 2;
        const phi = Math.acos(2 * Math.random() - 1);
        starPositions[i * 3] = radius * Math.sin(phi) * Math.cos(theta);
        starPositions[i * 3 + 1] = radius * Math.sin(phi) * Math.sin(theta);
        starPositions[i * 3 + 2] = radius * Math.cos(phi);
    }

    const starGeometry = new THREE.BufferGeometry();
    starGeometry.setAttribute('position', new THREE.BufferAttribute(starPositions, 3));
    const stars = new THREE.Points(
        starGeometry,
        new THREE.PointsMaterial({ color: 0x98a2b3, size: 0.035, transparent: true, opacity: 0.75 })
    );
    scene.add(stars);

    const maxPlayers = Math.max(...games.map((game) => game.players), 1);
    const gameMeshes = [];
    const gameGroup = new THREE.Group();
    scene.add(gameGroup);

    games.forEach((game, index) => {
        const angle = (index / Math.max(games.length, 1)) * Math.PI * 2;
        const orbit = 4.3 + (index % 2) * 1.6;
        const size = 0.48 + Math.sqrt(game.players / maxPlayers) * 0.9;
        const color = game.rating >= 17 ? 0x4dd4ff : game.rating >= 14 ? 0xffce6a : 0xff6b9a;

        const planet = new THREE.Mesh(
            new THREE.SphereGeometry(size, 42, 24),
            new THREE.MeshStandardMaterial({
                color,
                emissive: color,
                emissiveIntensity: 0.22,
                metalness: 0.18,
                roughness: 0.4,
            })
        );

        planet.position.set(Math.cos(angle) * orbit, (index % 3 - 1) * 0.9, Math.sin(angle) * orbit);
        planet.userData.game = game;
        planet.userData.baseScale = size;
        gameGroup.add(planet);
        gameMeshes.push(planet);

        const lineGeometry = new THREE.BufferGeometry().setFromPoints([
            new THREE.Vector3(0, 0, 0),
            planet.position.clone(),
        ]);
        const line = new THREE.Line(
            lineGeometry,
            new THREE.LineBasicMaterial({ color, transparent: true, opacity: 0.2 })
        );
        gameGroup.add(line);
    });

    const title = document.querySelector('#arenaTitle');
    const meta = document.querySelector('#arenaMeta');
    const description = document.querySelector('#arenaDescription');
    const raycaster = new THREE.Raycaster();
    const pointer = new THREE.Vector2(99, 99);
    let selected = null;

    const composer = new EffectComposer(renderer);
    composer.addPass(new RenderPass(scene, camera));
    composer.addPass(new UnrealBloomPass(new THREE.Vector2(1, 1), 0.55, 0.45, 0.12));

    function updateCard(game) {
        if (!title || !meta || !description) {
            return;
        }

        title.textContent = game.title;
        meta.textContent = `${game.studio} - ${game.players.toLocaleString('fr-FR')} joueurs - ${game.rating || 'NR'}/20`;
        description.textContent = game.description;
    }

    function resize() {
        const rect = canvas.getBoundingClientRect();
        const width = Math.max(rect.width, 1);
        const height = Math.max(rect.height, 1);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        renderer.setSize(width, height, false);
        composer.setSize(width, height);
    }

    function setPointer(event) {
        const rect = canvas.getBoundingClientRect();
        pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
        pointer.y = -((event.clientY - rect.top) / rect.height) * 2 + 1;
    }

    canvas.addEventListener('pointermove', setPointer);
    canvas.addEventListener('click', () => {
        if (selected?.userData?.game?.id) {
            window.location.href = `game.php?id=${selected.userData.game.id}`;
        }
    });

    window.addEventListener('resize', resize);
    resize();

    function animate() {
        requestAnimationFrame(animate);
        controls.update();
        core.rotation.x += prefersReducedMotion ? 0 : 0.004;
        core.rotation.y += prefersReducedMotion ? 0 : 0.007;
        stars.rotation.y += prefersReducedMotion ? 0 : 0.0008;
        gameGroup.rotation.y += prefersReducedMotion ? 0 : 0.0015;

        raycaster.setFromCamera(pointer, camera);
        const hit = raycaster.intersectObjects(gameMeshes, false)[0]?.object || null;

        gameMeshes.forEach((mesh) => {
            const target = mesh === hit ? 1.18 : 1;
            mesh.scale.lerp(new THREE.Vector3(target, target, target), 0.12);
        });

        if (hit && hit !== selected) {
            selected = hit;
            updateCard(hit.userData.game);
        }

        composer.render();
    }

    if (games[0]) {
        updateCard(games[0]);
    }

    animate();
}
