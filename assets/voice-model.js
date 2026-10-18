// Speech state is kept in memory. No audio is recorded or uploaded by the application.
export function speechChunks(text, limit=180) {
 const words=String(text).trim().split(/\s+/u),chunks=[];let chunk='';
 for(const word of words){if(!word)continue;const pieces=Array.from(word);while(pieces.length>limit){if(chunk){chunks.push(chunk);chunk='';}chunks.push(pieces.splice(0,limit).join(''));}const part=pieces.join('');if(!part)continue;if(Array.from(chunk+' '+part).length>limit&&chunk){chunks.push(chunk);chunk='';}chunk+=(chunk?' ':'')+part;if(/[.!?…]$/u.test(part)&&chunk.length>45){chunks.push(chunk);chunk='';}}
 if(chunk)chunks.push(chunk);return chunks;
}
export function appendSpeech(block,text){
 const spoken=String(text).trim();if(!spoken)return false;
 const suffix=(block.text&&!/\s$/u.test(block.text)?' ':'')+spoken;
 if(Array.from(block.text+suffix).length>100000)throw Error('Bu notun metin sınırına ulaşıldı. Yeni bir metin bloğunda devam et.');
 if(block.runs?.map(r=>r.text).join('')===block.text){const last=block.runs.at(-1);if(last&&Object.keys(last).length===1)last.text+=suffix;else block.runs.push({text:suffix});}else delete block.runs;
 block.text+=suffix;return true;
}
export const commandKey=text=>String(text).toLocaleLowerCase('tr-TR').normalize('NFKD').replace(/\p{M}/gu,'').replace(/ı/g,'i').replace(/[^\p{L}\p{N}\s]/gu,' ').replace(/\s+/g,' ').trim();
export function voiceCommand(text,pages=[]){
 const key=commandKey(text).replace(/^bir /,'');
 const areas={education:['egitim','egitimi','egitim alani','egitim alanini'],football:['futbol','futbolu','futbol alani','futbol alanini'],dnd:['dnd','d d','d and d'],reminders:['hatirlaticilar','hatirlaticilari'],home:['tum sayfalar','tum sayfalari'],favorites:['favoriler','favorileri'],blank:['calisma alani','calisma alanini']};
 for(const [view,names]of Object.entries(areas))if(names.some(n=>key===n+' ac'))return {type:'area',view};
 const destinations={'egitime git':'education','futbola git':'football','dnd ye git':'dnd','dndye git':'dnd','d d ye git':'dnd','hatirlaticilara git':'reminders','tum sayfalara git':'home','favorilere git':'favorites','calisma alanina git':'blank'};if(destinations[key])return {type:'area',view:destinations[key]};
 const exact={ 'sonraki sayfa':'nextPage','onceki sayfa':'prevPage','sonraki not':'nextPage','onceki not':'prevPage','sonraki paragraf':'nextBlock','onceki paragraf':'prevBlock','sonraki blok':'nextBlock','onceki blok':'prevBlock','asagi kaydir':'down','yukari kaydir':'up','basa git':'top','sona git':'bottom','notu oku':'read','notu sesli oku':'read','sayfayi oku':'read','sayfayi sesli oku':'read','bu blogu oku':'readBlock','komutlari durdur':'stop','dinlemeyi durdur':'stop' };
 if(exact[key])return {type:exact[key]};
 const match=key.match(/^(.+?) (?:sayfasini|notunu) ac$/)||key.match(/^(.+?) (?:sayfasina|notuna) git$/);
 if(match){const found=pages.filter(p=>commandKey(p.title)===match[1]);if(found.length===1)return {type:'page',id:found[0].id};return {type:'error',message:found.length?'Bu başlıkla birden fazla sayfa var. Ayırt edebilmek için sayfalara farklı başlıklar ver.':'Bu isimde bir sayfa bulunamadı. Sayfanın tam başlığını söyle.'};}
 return {type:'error',message:'Komut anlaşılmadı. Paneldeki örneklerden birini söyle.'};
}
export const recognitionErrors={
 'not-allowed':'Mikrofon izni verilmedi. Tarayıcıdaki site ayarlarından mikrofon iznini açıp tekrar dene.',
 'service-not-allowed':'Tarayıcı konuşma hizmetine izin vermiyor. Uygulamayı destekleyen bir tarayıcıda aç.',
 'audio-capture':'Mikrofon bulunamadı veya başka bir uygulama kullanıyor.',
 'network':'Konuşma hizmetine bağlanılamadı. İnternetini kontrol edip tekrar dene.',
 'no-speech':'Ses algılanmadı. Mikrofonunu kontrol edip tekrar başlat.',
 'language-not-supported':'Bu tarayıcının konuşma hizmeti Türkçeyi desteklemiyor.',
 aborted:'Dinleme durduruldu.'
};
export function createSpeechEngine({Recognition,Utterance,synth,secure=true,onFinal=()=>{},onChange=()=>{}}){
 const state={recording:'idle',reading:'idle',interim:'',error:'',chunks:[],index:0};
 let recognition=null,token=0,utterance=null,readToken=0,readOptions={};
 const notify=()=>onChange({...state});
 function abort(){token++;const old=recognition;recognition=null;state.recording='idle';state.interim='';try{old?.abort();}catch{}notify();}
 function stopReading(){readToken++;utterance=null;state.reading='idle';state.chunks=[];state.index=0;try{synth?.cancel();}catch{}notify();}
 function start(){
  if(!Recognition||!secure){state.error=!secure?'Mikrofon için HTTPS bağlantısı gerekli.':'Bu tarayıcı sesle yazdırmayı desteklemiyor. Chrome veya Safari’de açmayı ya da klavyenin dikte özelliğini deneyebilirsin.';notify();return;}
  abort();stopReading();state.error='';state.recording='starting';notify();const current=++token;let r;
  try{r=new Recognition();recognition=r;r.lang='tr-TR';r.continuous=true;r.interimResults=true;r.maxAlternatives=1;const applied=new Set();
   r.onstart=()=>{if(current!==token)return;state.recording='listening';notify();};
   r.onresult=e=>{if(current!==token)return;let interim='';for(let i=e.resultIndex;i<e.results.length;i++){const result=e.results[i],text=result[0]?.transcript?.trim()||'';if(result.isFinal&&!applied.has(i)){applied.add(i);if(text){try{onFinal(text);}catch(err){abort();state.error=err.message;notify();return;}if(current!==token)return;}}else if(!result.isFinal)interim+=(interim?' ':'')+text;}state.interim=interim;notify();};
   r.onerror=e=>{if(current!==token)return;abort();state.error=recognitionErrors[e.error]||'Konuşma tanıma başlatılamadı. Tekrar dene.';notify();};
   r.onend=()=>{if(current!==token)return;recognition=null;state.recording='idle';state.interim='';notify();};r.start();
  }catch{abort();state.error='Mikrofon başlatılamadı. Site iznini ve tarayıcı desteğini kontrol et.';notify();}
 }
 function stop(){if(!recognition)return;state.recording='stopping';notify();try{recognition.stop();}catch{abort();}}
 function next(){
  if(state.index>=state.chunks.length){state.reading='done';utterance=null;notify();return;}
  const current=readToken,u=new Utterance(state.chunks[state.index]);utterance=u;u.lang=readOptions.voice?.lang||'tr-TR';if(readOptions.voice)u.voice=readOptions.voice;u.rate=readOptions.rate||1;
  u.onend=()=>{if(current!==readToken)return;utterance=null;state.index++;if(state.reading!=='paused')next();notify();};
  u.onerror=e=>{if(current!==readToken)return;stopReading();state.error=e.error==='not-allowed'?'Sesli okumayı başlatmak için Oynat düğmesine dokun.':'Sesli okuma tamamlanamadı. Başka bir ses seçip tekrar dene.';notify();};
  try{synth.speak(u);}catch{stopReading();state.error='Bu cihazda sesli okuma başlatılamadı.';}notify();
 }
 function read(text,options={}){abort();stopReading();state.error='';if(!synth||!Utterance){state.error='Bu tarayıcı sesli okumayı desteklemiyor.';notify();return;}state.chunks=speechChunks(text);if(!state.chunks.length){state.error='Okunacak yazı yok.';notify();return;}readOptions=options;state.reading='playing';synth.resume();next();}
 function pause(){if(state.reading!=='playing')return;state.reading='paused';synth.pause();notify();}
 function resume(){if(state.reading!=='paused')return;state.reading='playing';if(utterance)synth.resume();else next();notify();}
 return {state,start,stop,abort,read,pause,resume,stopReading,close(){abort();stopReading();}};
}
