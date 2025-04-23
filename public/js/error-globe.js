const initGlobe = () => {
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    
    renderer.setSize(window.innerWidth, window.innerHeight);
    document.getElementById('globe-container').appendChild(renderer.domElement);

    // 创建地球
    const geometry = new THREE.SphereGeometry(5, 32, 32);
    const textureLoader = new THREE.TextureLoader();
    const texture = textureLoader.load('/img/earth-map.jpg');
    const material = new THREE.MeshPhongMaterial({
        map: texture,
        bumpMap: textureLoader.load('/img/earth-bump.jpg'),
        bumpScale: 0.3,
        specularMap: textureLoader.load('/img/earth-specular.jpg'),
        specular: new THREE.Color('grey'),
    });
    
    const globe = new THREE.Mesh(geometry, material);
    scene.add(globe);
    
    // 添加环境光和点光源
    const ambientLight = new THREE.AmbientLight(0x333333);
    scene.add(ambientLight);
    
    const pointLight = new THREE.PointLight(0xffffff, 1, 100);
    pointLight.position.set(5, 3, 5);
    scene.add(pointLight);
    
    camera.position.z = 10;
    
    // 动画
    const animate = () => {
        requestAnimationFrame(animate);
        globe.rotation.y += 0.005;
        renderer.render(scene, camera);
    };
    
    animate();
    
    // 响应式调整
    window.addEventListener('resize', () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    });
};
