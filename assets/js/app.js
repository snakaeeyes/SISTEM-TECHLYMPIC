(() => {
 const video=document.getElementById('faceVideo');
 if(!video)return;
 const start=document.getElementById('cameraStart'),save=document.getElementById('faceSave'),status=document.getElementById('cameraStatus');
 const csrf=document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="csrf"]')?.value || '';
 let stream=null,modelsReady=false,busy=false;
 const MODEL_URL='https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@master/weights';
 function say(t){if(status)status.textContent=t;}
 async function loadModels(){
  if(modelsReady)return;
  if(!window.faceapi)throw new Error('Pustaka face-api.js tidak dapat dimuatkan. Semak sambungan internet.');
  await Promise.all([faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)]);
  modelsReady=true;
 }
 async function descriptor(){
  const result=await faceapi.detectSingleFace(video,new faceapi.TinyFaceDetectorOptions({inputSize:320,scoreThreshold:.5})).withFaceLandmarks().withFaceDescriptor();
  if(!result)throw new Error('Wajah tidak dikesan. Pastikan wajah jelas di hadapan kamera.');
  return Array.from(result.descriptor);
 }
 async function post(action,desc){
  const r=await fetch('api/face.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({action,descriptor:desc})});
  const data=await r.json();if(!r.ok||!data.ok)throw new Error(data.message||'Permintaan gagal.');return data;
 }
 start?.addEventListener('click',async()=>{
  try{say('Memuatkan model AI...');await loadModels();stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user'},audio:false});video.srcObject=stream;await video.play();save.disabled=false;start.disabled=true;say('Kamera aktif. Pastikan pencahayaan mencukupi.');}
  catch(err){say(err.message);start.disabled=false;}
 });
 save?.addEventListener('click',async()=>{
  if(busy)return;busy=true;save.disabled=true;
  try{const d=await descriptor();const result=await post('save',d);say(result.message);}
  catch(err){say(err.message);}
  finally{busy=false;save.disabled=false;}
 });
 const recognizeBtn=document.getElementById('recognizeNow');
 recognizeBtn?.addEventListener('click',async()=>{
  if(busy)return;busy=true;recognizeBtn.disabled=true;
  try{const d=await descriptor();const result=await post('recognize',d);say(result.message+(result.name?' — '+result.name:''));}
  catch(err){say(err.message);}
  finally{busy=false;recognizeBtn.disabled=false;}
 });
 window.addEventListener('beforeunload',()=>stream?.getTracks().forEach(t=>t.stop()));
})();