// 增强版3D地球可视化 - 支持实时数据展示
class GlobeVisualization {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
        this.renderer = new THREE.WebGLRenderer({ antialias: true });
        this.markers = new Map();
        this.connections = new Map();
        this.dataCache = new Map();
        this.wsConnection = null;
        
        this.init();
        this.setupWebSocket();
        this.setupEventListeners();
    }

    init() {
        // 场景初始化
        this.scene.background = new THREE.Color(0x020210);
        this.camera.position.z = 5;
        
        this.renderer.setSize(window.innerWidth, window.innerHeight);
        this.container.appendChild(this.renderer.domElement);

        // 创建地球
        const earthGeometry = new THREE.SphereGeometry(2, 64, 64);
        const earthTexture = new THREE.TextureLoader().load('textures/earth.jpg');
        const earthMaterial = new THREE.MeshPhongMaterial({
            map: earthTexture,
            specular: new THREE.Color(0x333333),
            shininess: 5,
            transparent: true,
            opacity: 0.9
        });
        this.earth = new THREE.Mesh(earthGeometry, earthMaterial);
        this.scene.add(this.earth);

        // 添加大气层效果
        const atmosphereGeometry = new THREE.SphereGeometry(2.1, 64, 64);
        const atmosphereMaterial = new THREE.ShaderMaterial({
            vertexShader: `
                varying vec3 vNormal;
                void main() {
                    vNormal = normalize(normalMatrix * normal);
                    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
                }
            `,
            fragmentShader: `
                varying vec3 vNormal;
                void main() {
                    float intensity = pow(0.7 - dot(vNormal, vec3(0.0, 0.0, 1.0)), 2.0);
                    gl_FragColor = vec4(0.3, 0.6, 1.0, 1.0) * intensity;
                }
            `,
            blending: THREE.AdditiveBlending,
            side: THREE.BackSide
        });
        const atmosphere = new THREE.Mesh(atmosphereGeometry, atmosphereMaterial);
        this.scene.add(atmosphere);

        // 添加光照
        const ambientLight = new THREE.AmbientLight(0x404040);
        this.scene.add(ambientLight);

        const directionalLight = new THREE.DirectionalLight(0xffffff, 1);
        directionalLight.position.set(5, 3, 5);
        this.scene.add(directionalLight);

        // 添加控制器
        this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.05;
        this.controls.autoRotate = true;
        this.controls.autoRotateSpeed = 0.5;

        // 添加星空背景
        this.createStarfield();
    }

    createStarfield() {
        const starsGeometry = new THREE.BufferGeometry();
        const starsMaterial = new THREE.PointsMaterial({
            color: 0xffffff,
            size: 0.05,
            transparent: true
        });

        const starsVertices = [];
        for (let i = 0; i < 2000; i++) {
            const x = (Math.random() - 0.5) * 2000;
            const y = (Math.random() - 0.5) * 2000;
            const z = (Math.random() - 0.5) * 2000;
            starsVertices.push(x, y, z);
        }

        starsGeometry.setAttribute('position', new THREE.Float32BufferAttribute(starsVertices, 3));
        const stars = new THREE.Points(starsGeometry, starsMaterial);
        this.scene.add(stars);
    }

    setupWebSocket() {
        const wsProtocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        this.wsConnection = new WebSocket(`${wsProtocol}//${window.location.host}/ws/globe-data`);

        this.wsConnection.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this.handleWebSocketMessage(data);
            } catch (error) {
                console.error('WebSocket消息处理错误:', error);
            }
        };

        this.wsConnection.onclose = () => {
            console.log('WebSocket连接已关闭，5秒后重试...');
            setTimeout(() => this.setupWebSocket(), 5000);
        };
    }

    handleWebSocketMessage(data) {
        switch (data.type) {
            case 'user_location':
                this.updateUserMarker(data);
                break;
            case 'network_stats':
                this.updateNetworkStats(data);
                break;
            case 'global_metrics':
                this.updateGlobalMetrics(data);
                break;
        }
    }

    updateUserMarker(data) {
        const { latitude, longitude, userId, userType } = data;
        const markerKey = `user-${userId}`;

        if (!this.markers.has(markerKey)) {
            // 创建新标记
            const markerGeometry = new THREE.SphereGeometry(0.03, 16, 16);
            const markerMaterial = new THREE.MeshBasicMaterial({
                color: userType === 'active' ? 0x00ff00 : 0xff0000
            });
            const marker = new THREE.Mesh(markerGeometry, markerMaterial);
            
            // 计算位置
            const position = this.latLongToVector3(latitude, longitude);
            marker.position.copy(position);
            
            this.scene.add(marker);
            this.markers.set(markerKey, marker);

            // 添加光晕效果
            const glowGeometry = new THREE.SphereGeometry(0.04, 16, 16);
            const glowMaterial = new THREE.ShaderMaterial({
                uniforms: {
                    c: { type: 'f', value: 0.1 },
                    p: { type: 'f', value: 3.0 },
                    glowColor: { type: 'c', value: new THREE.Color(0x00ff00) }
                },
                vertexShader: `
                    varying vec3 vNormal;
                    void main() {
                        vNormal = normalize(normalMatrix * normal);
                        gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
                    }
                `,
                fragmentShader: `
                    uniform vec3 glowColor;
                    uniform float c;
                    uniform float p;
                    varying vec3 vNormal;
                    void main() {
                        float intensity = pow(c - dot(vNormal, vec3(0.0, 0.0, 1.0)), p);
                        gl_FragColor = vec4(glowColor, intensity);
                    }
                `,
                side: THREE.FrontSide,
                blending: THREE.AdditiveBlending,
                transparent: true
            });

            const glow = new THREE.Mesh(glowGeometry, glowMaterial);
            marker.add(glow);
        }
    }

    updateNetworkStats(data) {
        const { nodes, connections } = data;
        
        // 更新加速节点
        nodes.forEach(node => {
            const nodeKey = `node-${node.id}`;
            if (!this.markers.has(nodeKey)) {
                const nodeGeometry = new THREE.BoxGeometry(0.05, 0.05, 0.05);
                const nodeMaterial = new THREE.MeshPhongMaterial({
                    color: 0x00ffff,
                    emissive: 0x006666
                });
                const nodeMesh = new THREE.Mesh(nodeGeometry, nodeMaterial);
                
                const position = this.latLongToVector3(node.latitude, node.longitude);
                nodeMesh.position.copy(position);
                
                this.scene.add(nodeMesh);
                this.markers.set(nodeKey, nodeMesh);
            }
        });

        // 更新连接线
        connections.forEach(conn => {
            const connKey = `${conn.from}-${conn.to}`;
            if (!this.connections.has(connKey)) {
                const startPos = this.latLongToVector3(conn.fromLat, conn.fromLong);
                const endPos = this.latLongToVector3(conn.toLat, conn.toLong);
                
                const curve = new THREE.QuadraticBezierCurve3(
                    startPos,
                    new THREE.Vector3(
                        (startPos.x + endPos.x) * 0.5,
                        (startPos.y + endPos.y) * 0.5,
                        (startPos.z + endPos.z) * 0.5 + 1
                    ),
                    endPos
                );

                const points = curve.getPoints(50);
                const geometry = new THREE.BufferGeometry().setFromPoints(points);
                const material = new THREE.LineBasicMaterial({
                    color: 0x00ffff,
                    transparent: true,
                    opacity: 0.6
                });

                const curveObject = new THREE.Line(geometry, material);
                this.scene.add(curveObject);
                this.connections.set(connKey, curveObject);
            }
        });
    }

    updateGlobalMetrics(data) {
        // 更新统计数据
        document.getElementById('user-count').textContent = data.totalUsers.toLocaleString();
        document.getElementById('request-count').textContent = data.requestsPerMinute.toLocaleString();
        document.getElementById('node-count').textContent = data.activeNodes.toLocaleString();
    }

    latLongToVector3(lat, long) {
        const phi = (90 - lat) * (Math.PI / 180);
        const theta = (180 - long) * (Math.PI / 180);
        const radius = 2;

        return new THREE.Vector3(
            -radius * Math.sin(phi) * Math.cos(theta),
            radius * Math.cos(phi),
            radius * Math.sin(phi) * Math.sin(theta)
        );
    }

    setupEventListeners() {
        window.addEventListener('resize', () => {
            this.camera.aspect = window.innerWidth / window.innerHeight;
            this.camera.updateProjectionMatrix();
            this.renderer.setSize(window.innerWidth, window.innerHeight);
        });
    }

    animate() {
        requestAnimationFrame(() => this.animate());
        this.controls.update();
        this.renderer.render(this.scene, this.camera);
    }
}

// 初始化可视化
document.addEventListener('DOMContentLoaded', () => {
    const globe = new GlobeVisualization('globe');
    globe.animate();
});