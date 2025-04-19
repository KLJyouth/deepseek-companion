// 3D伴侣模型实现
let scene, camera, renderer, model;

// 初始化3D场景
function init3DScene() {
    // 创建场景
    scene = new THREE.Scene();
    scene.background = new THREE.Color(0xffd3d3);
    
    // 创建相机
    camera = new THREE.PerspectiveCamera(75, window.innerWidth/window.innerHeight, 0.1, 1000);
    camera.position.z = 5;
    
    // 创建渲染器
    renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(document.getElementById('companionModel').clientWidth, 
                    document.getElementById('companionModel').clientHeight);
    document.getElementById('companionModel').appendChild(renderer.domElement);
    
    // 添加光源
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.8);
    scene.add(ambientLight);
    
    const directionalLight = new THREE.DirectionalLight(0xffffff, 0.5);
    directionalLight.position.set(1, 1, 1);
    scene.add(directionalLight);
    
    // 创建简单人物模型
    createSimpleModel();
    
    // 添加窗口大小变化监听
    window.addEventListener('resize', onWindowResize);
    
    // 开始动画循环
    animate();
}

// 创建人物模型
function createModel(gender = 'male') {
    // 清除现有模型
    if (model) {
        scene.remove(model);
    }
    
    // 头部参数
    const headRadius = gender === 'male' ? 0.5 : 0.45;
    const headYPosition = gender === 'male' ? 0.5 : 0.55;
    
    // 头部
    const headGeometry = new THREE.SphereGeometry(headRadius, 32, 32);
    const headMaterial = new THREE.MeshPhongMaterial({ color: 0xffccaa });
    const head = new THREE.Mesh(headGeometry, headMaterial);
    head.position.y = headYPosition;
    
    // 身体参数
    const bodyTopRadius = gender === 'male' ? 0.4 : 0.35;
    const bodyBottomRadius = gender === 'male' ? 0.3 : 0.25;
    const bodyHeight = gender === 'male' ? 1 : 0.9;
    const bodyColor = gender === 'male' ? 0x66aaff : 0xff6b8b;
    
    // 身体
    const bodyGeometry = new THREE.CylinderGeometry(bodyTopRadius, bodyBottomRadius, bodyHeight, 32);
    const bodyMaterial = new THREE.MeshPhongMaterial({ color: bodyColor });
    const body = new THREE.Mesh(bodyGeometry, bodyMaterial);
    body.position.y = -0.5;
    
    // 头发(简单表示)
    if (gender === 'female') {
        const hairGeometry = new THREE.SphereGeometry(headRadius * 0.9, 32, 32);
        const hairMaterial = new THREE.MeshPhongMaterial({ color: 0x333333 });
        const hair = new THREE.Mesh(hairGeometry, hairMaterial);
        hair.position.set(0, headRadius * 0.3, headRadius * 0.5);
        head.add(hair);
    }
    
    // 组合模型
    model = new THREE.Group();
    model.add(head);
    model.add(body);
    scene.add(model);
}

// 更新模型性别
window.updateCompanionGender = function(gender) {
    createModel(gender);
};

// 更新模型动画基于态度
function updateModelAnimation(attitude) {
    if (!model) return;
    
    // 根据态度调整动画幅度
    const rotationSpeed = 0.01 + (attitude * 0.005);
    const bounceHeight = 0.05 + (attitude * 0.02);
    
    model.rotationSpeed = rotationSpeed;
    model.bounceHeight = bounceHeight;
}

// 创建初始模型
function createSimpleModel() {
    createModel('male');
}

// 窗口大小变化处理
function onWindowResize() {
    camera.aspect = document.getElementById('companionModel').clientWidth / 
                    document.getElementById('companionModel').clientHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(document.getElementById('companionModel').clientWidth, 
                    document.getElementById('companionModel').clientHeight);
}

// 动画循环
function animate() {
    requestAnimationFrame(animate);
    
    // 简单动画 - 轻微摆动
    if (model) {
        model.rotation.y += 0.01;
        model.position.y = Math.sin(Date.now() * 0.001) * 0.05;
    }
    
    renderer.render(scene, camera);
}

// 初始化3D场景
document.addEventListener('DOMContentLoaded', () => {
    // 检查Three.js是否已加载
    if (typeof THREE !== 'undefined') {
        init3DScene();
    } else {
        console.error('Three.js未加载');
        document.getElementById('companionModel').innerHTML = '<p>3D功能不可用</p>';
    }
});
