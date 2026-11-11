(() => {
 const video=document.getElementById('faceVideo'); if(!video)return;
 const start=document.getElementById('cameraStart'),save=document.getElementById('faceSave'),recognizeBtn=document.getElementById('recognizeNow'),status=document.getElementById('cameraStatus');
 const csrf=document.querySelector('meta[name="csrf-token"]')?.content || '';
 const MODEL_URL='https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.15/model/';
 let stream=null,modelsReady=false,busy=false;
 function say(t){if(status)status.textContent=t;}
 async function loadModels(){
  if(modelsReady)return;
  if(!window.faceapi)throw new Error('Pustaka face-api.js gagal dimuatkan. Semak sambungan internet.');
  await Promise.all([faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)]);
  modelsReady=true;
 }
 async function getDescriptor(){
  const found=await faceapi.detectSingleFace(video,new faceapi.TinyFaceDetectorOptions({inputSize:320,scoreThreshold:.5})).withFaceLandmarks().withFaceDescriptor();
  if(!found)throw new Error('Wajah tidak dikesan. Pastikan wajah jelas di hadapan kamera.');
  return Array.from(found.descriptor);
 }
 async function send(action,descriptor){
  const response=await fetch('api/face.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({action,descriptor})});
  const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.message||'Permintaan gagal.');return data;
 }
 start?.addEventListener('click',async()=>{
  start.disabled=true;say('Memuatkan model AI...');
  try{await loadModels();stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'user'},audio:false});video.srcObject=stream;await video.play();if(save)save.disabled=false;if(recognizeBtn)recognizeBtn.disabled=false;say('Kamera aktif. Pastikan wajah berada dalam bingkai.');}
  catch(err){say(err.message);start.disabled=false;}
 });
 save?.addEventListener('click',async()=>{
  if(busy)return;busy=true;save.disabled=true;
  try{const data=await send('save',await getDescriptor());say(data.message);}
  catch(err){say(err.message);}
  finally{busy=false;save.disabled=false;}
 });
 recognizeBtn?.addEventListener('click',async()=>{
  if(busy)return;busy=true;recognizeBtn.disabled=true;
  try{const data=await send('recognize',await getDescriptor());say(data.message+(data.name?' — '+data.name:''));}
  catch(err){say(err.message);}
  finally{busy=false;recognizeBtn.disabled=false;}
 });
 window.addEventListener('beforeunload',()=>stream?.getTracks().forEach(track=>track.stop()));
})();