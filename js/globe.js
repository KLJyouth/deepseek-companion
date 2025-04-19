// 3D地球可视化
document.addEventListener('DOMContentLoaded', function() {
    // 初始化场景
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x020210);
    
    // 初始化相机
    const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    camera.position.z = 5;
    
    // 初始化渲染器
    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    document.getElementById('globe-container').appendChild(renderer.domElement);
    
    // 创建地球
    const geometry = new THREE.SphereGeometry(2, 64, 64);
    const texture = new THREE.TextureLoader().load('textures/earth.jpg');
    const material = new THREE.MeshPhongMaterial({ 
        map: texture,
        specular: new THREE.Color(0x333333),
        shininess: 5
    });
    const earth = new THREE.Mesh(geometry, material);
    scene.add(earth);
    
    // 添加光照
    const ambientLight = new THREE.AmbientLight(0x404040);
    scene.add(ambientLight);
    
    const directionalLight = new THREE.DirectionalLight(0xffffff, 1);
    directionalLight.position.set(5, 3, 5);
    scene.add(directionalLight);
    
    // 添加标记点
    const markers = [
        { lat: 40.7128, lng: -74.0060, color: 0xff0000 }, // 纽约
        { lat: 51.5074, lng: -0.1278, color: 0x00ff00 },   // 伦敦
        { lat: 35.6762, lng: 139.6503, color: 0x0000ff }, // 东京
        { lat: 30.0444, lng: 31.2357, color: 0xffff00 },   // 开罗
        { lat: -33.8688, lng: 151.2093, color: 0xff00ff }  // 悉尼
    ];
    
    markers.forEach(marker => {
        const markerGeometry = new THREE.SphereGeometry(0.05, 16, 16);
        const markerMaterial = new THREE.MeshBasicMaterial({ color: marker.color });
        const markerMesh = new THREE.Mesh(markerGeometry, markerMaterial);
        
        // 将经纬度转换为3D坐标
        const phi = (90 - marker.lat) * (Math.PI / 180);
        const theta = (180 - marker.lng) * (Math.PI / 180);
        
        markerMesh.position.set(
            -Math.sin(phi) * Math.cos(theta) * 2.05,
            Math.cos(phi) * 2.05,
            Math.sin(phi) * Math.sin(theta) * 2.05
        );
        
        scene.add(markerMesh);
    });
    
    // 添加星星背景
    const starsGeometry = new THREE.BufferGeometry();
    const starsMaterial = new THREE.PointsMaterial({
        color: 0xffffff,
        size: 0.05
    });
    
    const starsVertices = [];
    for (let i = 0; i < 1000; i++) {
        const x = (Math.random() - 0.5) * 2000;
        const y = (Math.random() - 0.5) * 2000;
        const z = (Math.random() - 0.5) * 2000;
        starsVertices.push(x, y, z);
    }
    
    starsGeometry.setAttribute('position', new THREE.Float32BufferAttribute(starsVertices, 3));
    const stars = new THREE.Points(starsGeometry, starsMaterial);
    scene.add(stars);
    
    // 添加控制器
    const controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    controls.autoRotate = true;
    controls.autoRotateSpeed = 0.5;
    
    // 窗口大小调整
    window.addEventListener('resize', () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    });
    
    // 动画循环
    function animate() {
        requestAnimationFrame(animate);
        controls.update();
        renderer.render(scene, camera);
    }
    
    animate();
    
    // 数字动画
    function animateCounter(element, target, duration = 2000) {
        const start = 0;
        const increment = target / (duration / 16);
        let current = start;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                clearInterval(timer);
                current = target;
            }
            element.textContent = Math.floor(current).toLocaleString();
        }, 16);
    }
    
    // WebSocket连接
    const socket = new WebSocket(`ws://${window.location.hostname}:8080`);
    let signingKey = '';
    
    socket.addEventListener('open', () => {
        console.log('WebSocket连接已建立');
        
        // 获取签名密钥(实际应从安全接口获取)
        fetch('/api/get_ws_token')
            .then(response => response.json())
            .then(data => {
                signingKey = data.token;
                
                // 订阅监控数据
                const message = {
                    type: 'subscribe_metrics',
                    timestamp: Math.floor(Date.now() / 1000)
                };
                
                const nonce = generateNonce();
                const signature = signMessage(JSON.stringify(message), nonce, message.timestamp);
                
                socket.send(JSON.stringify({
                    ...message,
                    nonce,
                    signature
                }));
            });
    });
    
    // 生成随机数
    function generateNonce() {
        const array = new Uint8Array(8);
        window.crypto.getRandomValues(array);
        return Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
    }
    
    // 签名消息
    function signMessage(message, nonce, timestamp) {
        const encoder = new TextEncoder();
        const data = encoder.encode(timestamp + nonce + message);
        
        return crypto.subtle.digest('SHA-256', data)
            .then(hash => {
                const hashArray = Array.from(new Uint8Array(hash));
                return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
            });
    }
    
    socket.addEventListener('message', async (event) => {
        let data;
        try {
            // 尝试解析为JSON
            data = JSON.parse(event.data);
            
            // 如果是压缩消息则解压
            if (data.compressed) {
                data = await decompressMessage(data);
            }
            
            switch (data.type) {
                case 'initial_metrics':
                case 'update_metrics':
                    // 更新计数器
                    updateCounter('user-count', data.data.users);
                    updateCounter('request-count', data.data.requests);
                    break;
            }
        } catch (e) {
            console.error('消息处理失败:', e);
        }
    });
    
    // 解压消息
    async function decompressMessage(data) {
        if (typeof data === 'string') {
            try {
                // Base64解码
                const byteString = atob(data);
                const bytes = new Uint8Array(byteString.length);
                for (let i = 0; i < byteString.length; i++) {
                    bytes[i] = byteString.charCodeAt(i);
                }
                
                // 使用pako解压
                if (typeof pako !== 'undefined') {
                    const decompressed = pako.inflate(bytes, { to: 'string' });
                    return JSON.parse(decompressed);
                }
                
                // 回退到原始数据
                return JSON.parse(data);
            } catch (e) {
                console.error('解压失败:', e);
                return JSON.parse(data);
            }
        }
        return data;
    }
    
    socket.addEventListener('close', () => {
        console.log('WebSocket连接已关闭，尝试重新连接...');
        setTimeout(() => window.location.reload(), 5000);
    });
    
    // 更新计数器
    function updateCounter(elementId, value) {
        const element = document.getElementById(elementId);
        animateCounter(element, value);
    }
    
    // 初始化计数器
    updateCounter('user-count', 0);
    updateCounter('request-count', 0);
});