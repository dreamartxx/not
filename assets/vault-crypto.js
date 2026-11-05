// Envelope format 1 fixes KDF parameters. Never derive keys from the account password.
const enc=new TextEncoder();
export const rand=n=>crypto.getRandomValues(new Uint8Array(n));
export function b64(bytes){let s='';for(let i=0;i<bytes.length;i+=8192)s+=String.fromCharCode(...bytes.subarray(i,i+8192));return btoa(s);}
const bytes=s=>Uint8Array.from(atob(s),c=>c.charCodeAt(0));
export async function vaultKey(pass,salt){
 const material=await crypto.subtle.importKey('raw',enc.encode(pass),'PBKDF2',false,['deriveKey']);
 return crypto.subtle.deriveKey({name:'PBKDF2',hash:'SHA-256',salt:bytes(salt),iterations:600000},material,{name:'AES-GCM',length:256},false,['encrypt','decrypt']);
}
export async function seal(key,salt,entries){
 const iv=rand(12),plain=enc.encode(JSON.stringify(entries));
 if(plain.length+16>1000000)throw Error('Kasa boyutu sınırına ulaşıldı.');
 const data=await crypto.subtle.encrypt({name:'AES-GCM',iv},key,plain);
 return {format:1,salt,iv:b64(iv),data:b64(new Uint8Array(data))};
}
export async function unseal(key,e){
 if(e.format!==1)throw Error('Bilinmeyen kasa biçimi.');
 const plain=await crypto.subtle.decrypt({name:'AES-GCM',iv:bytes(e.iv)},key,bytes(e.data));
 const entries=JSON.parse(new TextDecoder().decode(plain));
 if(!Array.isArray(entries))throw Error('Geçersiz kasa.');return entries;
}
function base32(s){
 const a='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567',raw=s.toUpperCase().replace(/\s/g,'').replace(/=+$/,'');
 if(!/^[A-Z2-7]{2,1024}$/.test(raw)||[1,3,6].includes(raw.length%8))throw Error('Authenticator kurulum anahtarı geçersiz.');
 let acc=0,n=0,out=[];for(const c of raw){acc=(acc<<5)|a.indexOf(c);n+=5;if(n>=8){n-=8;out.push((acc>>n)&255);}}
 if((acc&((1<<n)-1))!==0)throw Error('Kurulum anahtarı eksik veya hatalı.');
 return new Uint8Array(out);
}
export function parseOTP(input){
 let secret=input.trim(),hash='SHA1',digits=6,period=30;
 if(/^otpauth:/i.test(secret)){let url;try{url=new URL(secret);}catch{throw Error('Authenticator bağlantısı geçersiz.');}
  if(url.hostname!=='totp')throw Error('Yalnızca zamana bağlı TOTP hesapları desteklenir.');
  const q=url.searchParams;secret=q.get('secret')||'';hash=(q.get('algorithm')||'SHA1').toUpperCase();digits=Number(q.get('digits')||6);period=Number(q.get('period')||30);
 }
 if(!['SHA1','SHA256','SHA512'].includes(hash)||![6,8].includes(digits)||!Number.isInteger(period)||period<1||period>300)throw Error('Desteklenmeyen Authenticator ayarları.');
 return {key:base32(secret),hash:hash.replace('SHA','SHA-'),digits,period};
}
export async function otpAt(cfg,seconds){
 const key=await crypto.subtle.importKey('raw',cfg.key,{name:'HMAC',hash:cfg.hash},false,['sign']);
 const counter=new DataView(new ArrayBuffer(8));counter.setBigUint64(0,BigInt(Math.floor(seconds/cfg.period)));
 const mac=new Uint8Array(await crypto.subtle.sign('HMAC',key,counter.buffer)),off=mac.at(-1)&15;
 return String((new DataView(mac.buffer).getUint32(off)&0x7fffffff)%10**cfg.digits).padStart(cfg.digits,'0');
}
