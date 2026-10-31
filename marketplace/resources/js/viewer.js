/**
 * Katman 3D önizleme görüntüleyicisi (Three.js).
 *
 * Yalnızca ürün sayfasında, kullanıcı 3D sekmesini açtığında dinamik olarak
 * yüklenir; ana pakete eklenmez. Herkese açık, detayı azaltılmış önizleme
 * dosyasını gösterir — satılan dosyalar bu görüntüleyiciye asla verilmez.
 */
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { STLLoader } from 'three/examples/jsm/loaders/STLLoader.js';
import { OBJLoader } from 'three/examples/jsm/loaders/OBJLoader.js';
import { ThreeMFLoader } from 'three/examples/jsm/loaders/3MFLoader.js';

const BACKGROUNDS = [null, '#f6f7f5', '#121816'];

export function mountViewer(container, url, extension, { onProgress, onError } = {}) {
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(40, 1, 0.1, 100000);
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    container.appendChild(renderer.domElement);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.08;

    const hemisphere = new THREE.HemisphereLight(0xffffff, 0x8a9692, 1.4);
    const key = new THREE.DirectionalLight(0xffffff, 2.2);
    const fill = new THREE.DirectionalLight(0xffffff, 0.8);
    key.position.set(1, 2, 1.5);
    fill.position.set(-1.5, 0.5, -1);
    scene.add(hemisphere, key, fill);

    const grid = new THREE.GridHelper(1, 20, 0x0fa592, 0x94a3a0);
    grid.material.opacity = 0.35;
    grid.material.transparent = true;
    scene.add(grid);

    let model = null;
    let framing = null;
    let backgroundIndex = 0;
    let lightIndex = 0;
    let frame = 0;

    const resize = () => {
        const { clientWidth: width, clientHeight: height } = container;
        if (!width || !height) return;
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
    };
    const observer = new ResizeObserver(resize);
    observer.observe(container);
    resize();

    const render = () => {
        frame = requestAnimationFrame(render);
        controls.update();
        renderer.render(scene, camera);
    };
    render();

    const material = new THREE.MeshStandardMaterial({ color: 0x0fa592, roughness: 0.55, metalness: 0.05 });

    const loaders = {
        stl: () => new STLLoader(),
        obj: () => new OBJLoader(),
        '3mf': () => new ThreeMFLoader(),
    };

    const loader = loaders[extension]?.();
    if (!loader) {
        onError?.('unsupported');
    } else {
        loader.load(
            url,
            (result) => {
                if (result.isBufferGeometry) {
                    // Bazı dışa aktarıcılar STL normallerini sıfır yazar; bu durumda model siyah görünür.
                    result.computeVertexNormals();
                }

                model = result.isBufferGeometry ? new THREE.Mesh(result, material) : result;

                model.traverse?.((child) => {
                    if (!child.isMesh) return;
                    if (!child.geometry.getAttribute('normal')) child.geometry.computeVertexNormals();
                    if (!child.material || extension === 'obj') child.material = material;
                });

                // STL ve 3MF dosyaları genellikle Z-yukarı modellenir; sahne Y-yukarıdır.
                if (extension !== 'obj') {
                    model.rotation.x = -Math.PI / 2;
                }

                scene.add(model);
                framing = frameModel();
                onProgress?.(100);
            },
            (event) => event.total && onProgress?.(Math.round((event.loaded / event.total) * 100)),
            () => onError?.('load'),
        );
    }

    /** Modeli tablanın ortasına oturtur ve kamerayı modele göre konumlar. */
    function frameModel() {
        model.updateMatrixWorld(true);
        const box = new THREE.Box3().setFromObject(model);
        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());

        model.position.x -= center.x;
        model.position.z -= center.z;
        model.position.y -= box.min.y;

        const radius = Math.max(size.x, size.y, size.z) || 1;
        grid.scale.setScalar(radius * 2);

        const target = new THREE.Vector3(0, size.y / 2, 0);
        const distance = radius / (2 * Math.tan((camera.fov * Math.PI) / 360)) * 1.6;
        const position = new THREE.Vector3(distance * 0.75, distance * 0.55, distance * 0.9);

        camera.near = radius / 100;
        camera.far = radius * 100;
        camera.updateProjectionMatrix();

        reset({ target, position });
        return { target, position };
    }

    function reset(view = framing) {
        if (!view) return;
        controls.target.copy(view.target);
        camera.position.copy(view.position);
        controls.update();
    }

    return {
        center: () => reset(),
        zoom(factor) {
            const offset = camera.position.clone().sub(controls.target).multiplyScalar(factor);
            camera.position.copy(controls.target).add(offset);
        },
        cycleBackground() {
            backgroundIndex = (backgroundIndex + 1) % BACKGROUNDS.length;
            const color = BACKGROUNDS[backgroundIndex];
            scene.background = color ? new THREE.Color(color) : null;
            return backgroundIndex;
        },
        cycleLighting() {
            lightIndex = (lightIndex + 1) % 3;
            const presets = [[1.4, 2.2, 0.8], [2.2, 1.2, 1.2], [0.6, 3.2, 0.2]];
            [hemisphere.intensity, key.intensity, fill.intensity] = presets[lightIndex];
            return lightIndex;
        },
        toggleGrid() {
            grid.visible = !grid.visible;
        },
        fullscreen() {
            const target = container.closest('[data-viewer-root]') ?? container;
            document.fullscreenElement ? document.exitFullscreen() : target.requestFullscreen?.();
        },
        destroy() {
            cancelAnimationFrame(frame);
            observer.disconnect();
            controls.dispose();
            scene.traverse((child) => {
                child.geometry?.dispose();
                if (child.material && child.material !== material) child.material.dispose?.();
            });
            material.dispose();
            renderer.dispose();
            renderer.domElement.remove();
        },
    };
}
