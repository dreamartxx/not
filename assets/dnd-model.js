export const abilities=[['str','Güç','STR'],['dex','Çeviklik','DEX'],['con','Dayanıklılık','CON'],['int','Zekâ','INT'],['wis','Bilgelik','WIS'],['cha','Karizma','CHA']];
export const skills=[['Akrobasi',1],['Hayvan İdaresi',4],['Arcana',3],['Atletizm',0],['Aldatma',5],['Tarih',3],['Sezgi',4],['Gözdağı',5],['Araştırma',3],['Tıp',4],['Doğa',3],['Algı',4],['Performans',5],['İkna',5],['Din',3],['El Çabukluğu',1],['Gizlenme',1],['Hayatta Kalma',4]];
export const classes=['Barbarian','Bard','Cleric','Druid','Fighter','Monk','Paladin','Ranger','Rogue','Sorcerer','Warlock','Wizard'];
export const conditions=['Kör','Büyülenmiş','Sağır','Korkmuş','Kavranmış','Etkisiz','Görünmez','Felçli','Taşlaşmış','Zehirlenmiş','Yerde','Kısıtlanmış','Sersemlemiş','Bilinçsiz'];
export function selectSpells(spells,character,{query='',level='all',school='all',feature='all',className='all',collection='selected'}={}) {
 const normalize=s=>String(s||'').toLocaleLowerCase('tr').replaceAll('ı','i');
 const q=normalize(query),known=new Set([...(character.known||[]),...(character.prepared||[])]),prepared=new Set(character.prepared||[]);
 return spells.filter(s=>(collection==='all'||(collection==='prepared'?prepared.has(s.id):known.has(s.id)))
  &&normalize(s.name+' '+(s.tr||'')+' '+(s.translation_name||'')).includes(q)&&(level==='all'||s.level===Number(level))
  &&(school==='all'||s.school===school)&&(className==='all'||s.classes?.includes(className))
  &&(feature==='all'||feature==='ritual'&&s.ritual||feature==='concentration'&&s.concentration||feature==='no-concentration'&&!s.concentration
   ||feature==='action'&&s.casting==='1 action'||feature==='bonus'&&s.casting==='1 bonus action'||feature==='reaction'&&s.casting?.startsWith('1 reaction')))
  .sort((a,b)=>a.level-b.level||a.name.localeCompare(b.name,'en'));
}
export const mod=n=>Math.floor((Number(n)-10)/2);
export const prof=level=>2+Math.floor((level-1)/4);
export const signed=n=>n>=0?'+'+n:String(n);
export const skillBonus=(c,i)=>mod(c.abilities[skills[i][1]])+prof(c.level)*c.skills[i];
export const spellBonus=c=>mod(c.abilities[abilities.findIndex(a=>a[0]===c.spellAbility)])+prof(c.level);
export function newCharacter(id){return {id,name:'Yeni karakter',race:'',class:'',subclass:'',background:'',alignment:'',level:1,xp:0,abilities:[10,10,10,10,10,10],skills:Array(18).fill(0),saves:Array(6).fill(false),hp:10,maxHp:10,tempHp:0,ac:10,speed:30,initiativeBonus:0,spellAbility:'int',deathSuccess:0,deathFailure:0,hitDice:1,hitDie:'d8',inspiration:false,exhaustion:0,conditions:[],concentration:'',slots:Array.from({length:9},()=>({max:0,current:0})),known:[],prepared:[],inventory:[],coins:{cp:0,sp:0,ep:0,gp:0,pp:0},attacks:[],resources:[],biography:'',features:'',allies:''};}
export function changeHP(c,amount){if(amount<0){const absorbed=Math.min(c.tempHp,-amount);c.tempHp-=absorbed;c.hp=Math.max(0,c.hp+amount+absorbed);}else c.hp=Math.min(c.maxHp,c.hp+amount);if(c.hp>0){c.deathSuccess=0;c.deathFailure=0;}}
export function rest(c,long,food=false){if(long){c.hp=c.maxHp;c.tempHp=0;c.deathSuccess=0;c.deathFailure=0;c.hitDice=Math.min(c.level,c.hitDice+Math.max(1,Math.floor(c.level/2)));c.slots.forEach(s=>s.current=s.max);if(food)c.exhaustion=Math.max(0,c.exhaustion-1);}c.resources.forEach(r=>{if(r.reset==='short'||long&&r.reset==='long')r.current=r.max;});if(long)c.concentration='';}
export function rollDice(formula,mode='normal',random=faces=>{const a=new Uint32Array(1),limit=Math.floor(4294967296/faces)*faces;do{crypto.getRandomValues(a);}while(a[0]>=limit);return a[0]%faces+1;}){
 const m=/^(\d{1,2})d(4|6|8|10|12|20|100)([+-]\d{1,3})?$/i.exec(formula.replace(/\s/g,''));if(!m||+m[1]<1||+m[1]>40)throw Error('Örnek: 1d20+5 veya 2d6+3. En fazla 40 zar.');
 const count=+m[1],faces=+m[2],bonus=+(m[3]||0);if(mode!=='normal'&&(count!==1||faces!==20))throw Error('Avantaj/dezavantaj yalnızca 1d20 için kullanılır.');
 const rolls=Array.from({length:mode==='normal'?count:2},()=>random(faces));const value=mode==='advantage'?Math.max(...rolls):mode==='disadvantage'?Math.min(...rolls):rolls.reduce((a,b)=>a+b,0);return {total:value+bonus,detail:rolls.join(' · ')+(bonus?' '+signed(bonus):'')+(mode==='normal'?'':mode==='advantage'?' · avantaj':' · dezavantaj')};
}
