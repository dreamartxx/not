// Browser-provided, on-device translation. No remote translation endpoint or API key.
export function translationChunks(text,limit=1800){const chunks=[];let rest=String(text);while(rest.length>limit){let end=rest.lastIndexOf('. ',limit);if(end<limit/3)end=rest.lastIndexOf(' ',limit);if(end<1)end=limit;else end+=1;chunks.push(rest.slice(0,end));rest=rest.slice(end);}if(rest)chunks.push(rest);return chunks;}
export function translationTokens(text){return {numbers:(text.match(/-?\d+(?:[.,]\d+)*/g)||[]).map(v=>v.replace(',','.')),units:(text.match(/\b(?:H|P|EUH)\d{3}\b|°\s*[CF]|\b(?:ppm|ppb|mg\/m[3³]|g\/mol|mm\s?Hg|kPa|mPa|LD50|LC50)\b/g)||[]).map(v=>v.replace(/\s/g,'')).sort()};}
export function validateLocalTranslation(source,translated){if(!translated?.trim())throw new Error('Türkçe çeviri boş döndü.');const a=translationTokens(source),b=translationTokens(translated);if(JSON.stringify(a)!==JSON.stringify(b))throw new Error('Çeviride sayı veya birim değişti; bu metin forma işlenmedi.');if(source.trim()===translated.trim()&&/[a-z]{3}/i.test(source))throw new Error('Metin Türkçeye çevrilemedi; forma işlenmedi.');return translated;}
export async function sourceFingerprint(text){const bytes=await crypto.subtle.digest('SHA-256',new TextEncoder().encode(text));return [...new Uint8Array(bytes)].map(x=>x.toString(16).padStart(2,'0')).join('');}
// Keep units, study identifiers and clinically meaningful routes out of machine translation.
export function protectedChemicalLine(line){
 const m=line.match(/^(Unit|Original unit|Dataset|Model|Species Latin name|Qualifier|Value|Original value|Duration|Duration unit|Original duration|Original duration unit|Exposure route|Original route): (.*)$/);
 if(!m)return null;const [,key,value]=m;
 const labels={'Unit':'Birim','Original unit':'Özgün birim','Dataset':'Veri kümesi','Model':'Model','Species Latin name':'Türün Latince adı','Qualifier':'Karşılaştırma işareti','Value':'Değer','Original value':'Özgün değer','Duration':'Süre','Duration unit':'Süre birimi','Original duration':'Özgün süre','Original duration unit':'Özgün süre birimi','Exposure route':'Maruziyet yolu','Original route':'Özgün maruziyet yolu'};
 if(/route$/.test(key)){
  const routes={'oral':'Oral (ağız yoluyla)','inhalation':'İnhalasyon (solunum yoluyla)','dermal':'Dermal (deri yoluyla)','skin':'Deri yoluyla','intravenous':'İntravenöz (damar içi)','intraperitoneal':'İntraperitoneal (periton içi)','subcutaneous':'Subkutan (deri altı)','intramuscular':'İntramüsküler (kas içi)','ocular':'Oküler (göz yoluyla)','eye':'Göz yoluyla','intragastric':'İntragastrik (mide içine)','unknown':'Belirtilmemiş','not reported':'Bildirilmemiş','unspecified':'Belirtilmemiş'};
  return labels[key]+': '+(routes[value.toLowerCase()]??value+' (özgün kaynak terimi)');
 }
 // Textual original values still need translation; scalar quantities and identifiers stay exact.
 if(/^(Original value|Duration|Original duration)$/.test(key)&&!/^[-+\d.,eE <>=%/]+$/.test(value))return null;
 return labels[key]+': '+value;
}
export async function translateChemicalText(text,translate){
 const result=[];let pending=[];
 const flush=async()=>{if(!pending.length)return;for(const chunk of translationChunks(pending.join('\n')))result.push(await translate(chunk));pending=[];};
 for(const line of String(text).split('\n')){const fixed=protectedChemicalLine(line);if(fixed!==null){await flush();result.push(fixed);}else pending.push(line);}
 await flush();return validateLocalTranslation(text,result.join('\n').trim());
}
export function createLocalChemicalTranslator({platform=globalThis,onProgress=()=>{}}={}){
 let engine=null;
 return {supported:()=>typeof platform.Translator?.create==='function',
  async start(){if(!this.supported())throw new Error('Bu tarayıcı yerel çeviriyi desteklemiyor. Güncel masaüstü Chrome ile açabilirsin.');engine=await platform.Translator.create({sourceLanguage:'en',targetLanguage:'tr',monitor(m){m.addEventListener('downloadprogress',e=>onProgress(Math.round(e.loaded*100)));}});},
  async translate(text){if(!engine)throw new Error('Önce yerel çeviriyi etkinleştir.');return translateChemicalText(text,chunk=>engine.translate(chunk));},
  destroy(){engine?.destroy?.();engine=null;}
 };
}
