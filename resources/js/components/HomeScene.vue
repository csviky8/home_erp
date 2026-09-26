<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { Home, Sparkles, Maximize2, Rotate3d } from 'lucide-vue-next';
const canvas = ref(null); const selectedRoom = ref('Living room'); const sceneError = ref(false); const emit = defineEmits(['select']);
let scene, camera, renderer, controls, frame, observer, raycaster, pointer; const interactive = [];
const rooms = [{name:'Living room',x:-2.8,z:0,color:0x1b6760},{name:'Kitchen',x:2.4,z:-1.9,color:0x275b72},{name:'Bedroom',x:2.5,z:1.9,color:0x5c4671},{name:'Garden',x:-2.8,z:-3.1,color:0x3d704c}];
const mat = (color, roughness=.55, metalness=.05) => new THREE.MeshStandardMaterial({color,roughness,metalness});
const add = (geometry, material, x, y, z, room) => { const mesh = new THREE.Mesh(geometry,material); mesh.position.set(x,y,z); mesh.castShadow=true; mesh.receiveShadow=true; if(room){mesh.userData.room=room;interactive.push(mesh);} scene.add(mesh); return mesh; };
const cube = (w,h,d,m,x,y,z,room) => add(new THREE.BoxGeometry(w,h,d),m,x,y,z,room);
const cyl = (r1,r2,h,m,x,y,z) => add(new THREE.CylinderGeometry(r1,r2,h,20),m,x,y,z);
function build() {
  scene=new THREE.Scene(); scene.background=new THREE.Color(0x071e1c); camera=new THREE.PerspectiveCamera(38,1,.1,100); camera.position.set(9,8.5,10); renderer=new THREE.WebGLRenderer({canvas:canvas.value,antialias:true,alpha:true}); renderer.setPixelRatio(Math.min(devicePixelRatio,2)); renderer.shadowMap.enabled=true; renderer.shadowMap.type=THREE.PCFSoftShadowMap; renderer.outputColorSpace=THREE.SRGBColorSpace;
  scene.add(new THREE.HemisphereLight(0xb9ffe0,0x08201e,2.2)); const key=new THREE.DirectionalLight(0x9cf7d2,3.2); key.position.set(3,10,4); key.castShadow=true; scene.add(key); const rim=new THREE.PointLight(0x4b9fff,7,15); rim.position.set(-5,4,-3); scene.add(rim);
  const floor=new THREE.Mesh(new THREE.PlaneGeometry(12,9),mat(0x0d3631,.82)); floor.rotation.x=-Math.PI/2; floor.receiveShadow=true; scene.add(floor); const grid=new THREE.GridHelper(12,24,0x246c5e,0x16483f); grid.position.y=.012; scene.add(grid);
  const wall=mat(0x1b5b52); cube(11.6,.32,8.6,mat(0x123e39),0,-.18,0); cube(11.6,1.8,.18,wall,0,.9,-4.2); cube(.18,1.8,8.4,wall,-5.7,.9,0); cube(.18,1.5,3.7,wall,5.7,.75,-2); cube(.18,1.5,3.7,wall,5.7,.75,2);
  rooms.forEach(r=>cube(2.8,.08,2.1,new THREE.MeshBasicMaterial({color:r.color,transparent:true,opacity:.17}),r.x,.06,r.z,r.name));
  const wood=mat(0x8f5e3e,.7), fabric=mat(0x236b68,.9), dark=mat(0x102d2d,.45), metal=mat(0x78918b,.25,.7);
  cube(2.25,.38,.85,fabric,-3.35,.46,.8); cube(2.25,.65,.22,fabric,-3.35,.8,1.12); cube(.2,.55,.85,fabric,-4.35,.65,.8); cube(.2,.55,.85,fabric,-2.35,.65,.8); cube(1.2,.12,.65,wood,-2.2,.58,-.25); cyl(.06,.06,.55,metal,-2.2,.28,-.25); cube(.6,.06,.5,mat(0xc5a27a),-2.2,.86,-.25);
  cube(1.55,.9,.42,dark,-3.3,1.15,-1.2); cube(1.35,.75,.05,mat(0x53c4b0,.2,.3),-3.3,1.16,-.96); cube(1.2,1.5,.65,mat(0xc5d2ca,.35,.2),3.2,.76,-2.2); cube(1.5,.8,.7,mat(0x506d7c,.6),3.15,.4,2.05); cube(1.3,.15,.55,mat(0xe4d6bd),3.15,.88,2.05);
  cube(1.25,.28,.3,mat(0xd5e4dc,.3,.2),-3.3,1.9,-4.02,'Living room'); cube(.4,.12,.05,mat(0x8debd2,.2,.2),-3.3,1.86,-3.85);
  [[-4.7,-3.2],[-1.2,-3.25],[4.6,2.8]].forEach(([x,z])=>{cyl(.28,.18,.6,mat(0x80533c),x,.3,z);add(new THREE.SphereGeometry(.42,12,8),mat(0x4baf70,.9),x,1,z);});
  const glow=new THREE.Mesh(new THREE.TorusGeometry(1.7,.025,8,64),new THREE.MeshBasicMaterial({color:0x74e7bd,transparent:true,opacity:.32})); glow.rotation.x=Math.PI/2.7; glow.position.y=.2; scene.add(glow);
  controls=new OrbitControls(camera,renderer.domElement); controls.enableDamping=true; controls.enablePan=false; controls.minDistance=7; controls.maxDistance=18; controls.maxPolarAngle=Math.PI/2.05; controls.target.set(0,.4,0); raycaster=new THREE.Raycaster(); pointer=new THREE.Vector2(); renderer.domElement.addEventListener('pointerdown',select); resize(); animate();
}
function resize(){if(!renderer)return;const b=canvas.value?.getBoundingClientRect();if(!b)return;renderer.setSize(b.width,b.height,false);camera.aspect=b.width/b.height;camera.updateProjectionMatrix();}
function select(e){const b=renderer.domElement.getBoundingClientRect();pointer.x=((e.clientX-b.left)/b.width)*2-1;pointer.y=-((e.clientY-b.top)/b.height)*2+1;raycaster.setFromCamera(pointer,camera);const hit=raycaster.intersectObjects(interactive,true)[0];if(hit){let n=hit.object;while(n&&!n.userData.room)n=n.parent;if(n?.userData.room){selectedRoom.value=n.userData.room;emit('select',n.userData.room);}}}
function animate(){frame=requestAnimationFrame(animate);controls.update();renderer.render(scene,camera);}
onMounted(()=>{try{build();observer=new ResizeObserver(resize);observer.observe(canvas.value);}catch(error){console.warn('3D home unavailable',error);sceneError.value=true;}}); onBeforeUnmount(()=>{cancelAnimationFrame(frame);observer?.disconnect();controls?.dispose();renderer?.dispose();scene?.traverse(o=>{o.geometry?.dispose();o.material?.dispose();});});
</script>
<template>
    <section class="glass relative min-h-[390px] overflow-hidden rounded-[1.5rem] p-4 sm:p-5">
        <div class="relative z-10 flex items-start justify-between gap-3"><div><p class="eyebrow flex items-center gap-2"><Sparkles :size="13" /> Live spatial view</p><h2 class="mt-2 text-xl font-semibold text-white">Your home, in perspective</h2><p class="mt-1 text-xs text-[#86ada3]">Click a room to inspect its household records.</p></div><div class="flex gap-2"><button class="icon-btn" title="Reset camera" @click="controls?.reset()"><Rotate3d :size="16" /></button><button class="icon-btn" title="Full screen view"><Maximize2 :size="16" /></button></div></div>
        <div class="absolute inset-x-0 bottom-0 top-20"><canvas v-show="!sceneError" ref="canvas" class="h-full w-full" aria-label="Interactive 3D home view"></canvas><div v-if="sceneError" class="flex h-full items-center justify-center px-6 text-center text-sm text-[#8eb8aa]">3D preview is unavailable on this device, but your household dashboard and records are still ready.</div></div>
        <div class="pointer-events-none absolute bottom-4 left-4 right-4 z-10 flex flex-wrap gap-2 sm:left-5"><button v-for="room in rooms" :key="room.name" class="pointer-events-auto rounded-full border px-3 py-1.5 text-[11px] font-semibold transition" :class="selectedRoom === room.name ? 'border-emerald-200/50 bg-emerald-200/15 text-emerald-100' : 'border-white/10 bg-[#082421]/75 text-[#8eb9aa] hover:border-emerald-200/30'" @click="selectedRoom = room.name"><Home :size="12" class="mr-1 inline" />{{ room.name }}</button></div>
        <div class="absolute right-4 top-4 z-10 rounded-full border border-emerald-200/20 bg-[#071e1c]/70 px-2.5 py-1 text-[10px] text-emerald-100/80"><span class="mr-1.5 inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-300" /> 3D live</div>
    </section>
</template>
