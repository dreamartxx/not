// Browser-provided, on-device translation. No remote translation endpoint or API key.
export function translationChunks(text,limit=1800){const chunks=[];let rest=String(text);while(rest.length>limit){let end=rest.lastIndexOf('. ',limit);if(end<limit/3)end=rest.lastIndexOf(' ',limit);if(end<1)end=limit;else end+=1;chunks.push(rest.slice(0,end));rest=rest.slice(end);}if(rest)chunks.push(rest);return chunks;}
export function translationTokens(text){return {numbers:(text.match(/-?\d+(?:[.,]\d+)*/g)||[]).map(v=>v.replace(',','.')),units:(text.match(/\b(?:H|P|EUH)\d{3}\b|°\s*[CF]|\b(?:ppm|ppb|mg\/m[3³]|g\/mol|mm\s?Hg|kPa|mPa|LD50|LC50)\b/g)||[]).map(v=>v.replace(/\s/g,'')).sort()};}
export function validateLocalTranslation(source,translated){if(!translated?.trim())throw new Error('Türkçe çeviri boş döndü.');const a=translationTokens(source),b=translationTokens(translated);if(JSON.stringify(a)!==JSON.stringify(b))throw new Error('Çeviride sayı veya birim değişti; bu metin forma işlenmedi.');if(source.trim()===translated.trim()&&/[a-z]{3}/i.test(source))throw new Error('Metin Türkçeye çevrilemedi; forma işlenmedi.');return translated;}
export async function sourceFingerprint(text){const bytes=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(text));return [...new Uint8Array(bytes)].map(x=>x.toString(16).padStart(2,'0')).join('');}
export function createLocalChemicalTranslator({platform=globalThis,onProgress=()=>{}}={}){
 let engine=null;
 return {supported:()=>typeof platform.Translator?.create==='function',
  async start(){if(!this.supported())throw new Error('Bu tarayıcı yerel çeviriyi desteklemiyor. Güncel masaüstü Chrome ile açabilirsin.');engine=await platform.Translator.create({sourceLanguage:'en',targetLanguage:'tr',monitor(m){m.addEventListener('downloadprogress',e=>onProgress(Math.round(e.loaded*100)));}});},
  async translate(text){if(!engine)throw new Error('Önce yerel çeviriyi etkinleştir.');let result='';for(const chunk of translationChunks(text)){const translated=await engine.translate(chunk);result+=translated+'\n';}return validateLocalTranslation(text,result.trim());},
  destroy(){engine?.destroy?.();engine=null;}
 };
}
