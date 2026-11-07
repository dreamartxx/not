// Manual adult reference paths. No patient data, automatic diagnosis or medication scheduling.
// Source PDFs and dose details are attached to the corresponding medicalTopics record.
const n=(title,text,choices=[],tone='neutral')=>({title,text,choices,tone});
export const clinicalAlgorithms={
 arrest:{label:'Kardiyak arrest',subtitle:'VF / nabızsız VT · Asistoli / NEA',icon:'↯',start:'start',nodes:{
  start:n('CPR + monitör / defibrilatör','Balon-maske ventilasyonu ve oksijen. Ritmi değerlendir.',[['VF / nabızsız VT','shock1'],['Asistoli / NEA (PEA)','pea']]),
  shock1:n('İlk defibrilasyon → 2 dk CPR','IV/IO erişim. Ardından ritmi yeniden değerlendir.',[['VF / pVT sürüyor','shock2'],['Asistoli / NEA','pea'],['ROSC','rosc']],'shock'),
  shock2:n('Defibrilasyon → 2 dk CPR','Adrenalin 1 mg IV/IO; 3–5 dk aralıklarla. İleri havayolu ve kapnografiyi değerlendir.',[['VF / pVT sürüyor','shock3'],['Asistoli / NEA','pea'],['ROSC','rosc']],'shock'),
  shock3:n('Defibrilasyon → 2 dk CPR','Amiodaron veya lidokain; verilen önceki dozları kontrol et. Geri döndürülebilir nedenleri tedavi et.',[['VF / pVT sürüyor','shock2'],['Asistoli / NEA','pea'],['ROSC','rosc']],'shock'),
  pea:n('Şok yok · 2 dk CPR','Adrenalin 1 mg IV/IO mümkün olan en erken zamanda; 3–5 dk aralıklarını koru. IV/IO, havayolu, kapnografi ve geri döndürülebilir nedenler.',[['Asistoli / NEA sürüyor','pea'],['VF / nabızsız VT gelişti','shock2'],['ROSC','rosc']],'critical'),
  rosc:n('Spontan dolaşım geri döndü','Arrest sonrası bakım algoritmasına geç. Yeniden arrest gelişirse ritmi değerlendir.',[['Yeniden arrest','start']],'success')
 }},
 tachy:{label:'Nabızlı taşikardi',subtitle:'İnstabilite → QRS → düzen',icon:'⌁',start:'start',nodes:{
  start:n('Taşikardi hemodinamik bozukluğa neden oluyor mu?','Hipotansiyon, akut bilinç değişikliği, şok, iskemik göğüs rahatsızlığı, akut kalp yetersizliği. ABC, hipoksemide O₂, monitör, IV, 12 derivasyon EKG.',[['Evet · instabil','unstable'],['Hayır · stabil','qrs'],['Nabız yok','arrest']]),
  unstable:n('Senkronize kardiyoversiyon','Uygunsa sedasyon. Polimorfik VT’de senkronize olmayan yüksek enerjili şok. Enerji ve senkronizasyon için elektrik tedavisi sekmesini aç.',[['Yanıt yok','refractory'],['Yeniden değerlendirme','start']],'critical'),
  qrs:n('QRS genişliği ≥120 ms mi?','Ritim düzenini ve morfolojisini de değerlendir.',[['Geniş','wide'],['Dar','narrow']]),
  wide:n('Geniş kompleks: düzenli ve monomorfik mi?','Antiaritmik infüzyon ve uzman değerlendirmesi.',[['Evet','regularWide'],['Hayır / emin değilim','irregularWide']]),
  regularWide:n('Düzenli monomorfik geniş kompleks','Adenosin düşünülebilir. Uygun antiaritmik infüzyon ve uzman desteği.',[['Durumu yeniden değerlendir','start']]),
  irregularWide:n('Adenosin verme','Düzensiz/polimorfik geniş komplekste veya preeksitasyon şüphesinde AV düğüm baskılayıcılarını rutin kullanma. Polimorfik VT: defibrilasyon. Acil uzman desteği.',[['Yeniden değerlendir','start']],'critical'),
  narrow:n('Dar kompleks düzenli mi?','Tedavi öncesinde sinüs taşikardisi ve altta yatan nedeni değerlendir.',[['Düzenli','regular'],['Düzensiz','irregular']]),
  regular:n('Vagal manevra / adenosin','Adenosin: 6 mg hızlı IV + serum fizyolojik; gerekirse 12 mg. Yanıta göre ritme uygun tedavi ve uzman görüşü.',[['Yeniden değerlendir','start']]),
  irregular:n('Ritme özgü yaklaşım','Preeksitasyon ve kontrendikasyonları dışlayarak uygun hız kontrolü; uzman değerlendirmesi.',[['Yeniden değerlendir','start']]),
  refractory:n('Dirençli taşiaritmi','Neden, enerji artırımı, antiaritmik ve uzman desteğini değerlendir.',[['Yeniden değerlendir','start']]),
  arrest:n('Arrest algoritmasına geç','Nabızlı taşikardi akışı burada sona erer.',[],'critical')
 }},
 brady:{label:'Bradikardi',subtitle:'Perfüzyon → atropin → pacing',icon:'⌇',start:'start',nodes:{
  start:n('Kardiyopulmoner bozulma var mı?','Hipotansiyon, akut bilinç değişikliği, şok, iskemik rahatsızlık veya akut kalp yetersizliği. Hız genellikle <50/dk; klinik belirleyicidir.',[['Evet','support'],['Hayır','observe'],['Nabız yok','arrest']]),
  support:n('ABC desteği ve monitörizasyon','Havayolu, oksijen, gerekirse pozitif basınçlı ventilasyon. Bradikardiyle birlikte bozulma sürüyor mu?', [['Evet','atropine'],['Hayır','observe']]),
  atropine:n('Atropin 1 mg IV','3–5 dk arayla; toplam en fazla 3 mg. Yüksek dereceli blokta pacing hazırlığını geciktirme.',[['Etkisiz','pacing'],['Düzeldi','observe']],'critical'),
  pacing:n('Transkütan pacing ve/veya infüzyon','Dopamin 5–20 mikrogram/kg/dk VEYA adrenalin 2–10 mikrogram/dk. Yanıta göre titre et. Uzman görüşü / transvenöz pacing.',[['Yeniden değerlendirme','start']],'critical'),
  observe:n('Nedeni tedavi et ve izle','12 derivasyon EKG; iskemi, hipoksi, ilaçlar ve elektrolit bozukluğu. ABC desteğini sürdür.',[['Bozulma gelişti','support'],['Nabız kayboldu','arrest']]),
  arrest:n('Arrest algoritmasına geç','Nabızlı bradikardi akışı burada sona erer.',[],'critical')
 }},
 cardioversion:{label:'Elektrik tedavisi',subtitle:'Senkronizasyon · enerji seçimi',icon:'ϟ',start:'start',nodes:{
  start:n('Nabız ve ritim tipi','Nabızlı taşiaritmiye bağlı ciddi bulgularda ritme uygun elektrik tedavisini seç.',[['Nabızlı · monomorfik / supraventriküler','rhythm'],['Polimorfik VT','poly'],['Nabız yok','arrest']]),
  rhythm:n('Senkronize kardiyoversiyon','SpO₂, IV, aspirasyon ve havayolu hazırlığı. Uygunsa sedasyon; senkronizasyon işaretlerini doğrula.',[['Atriyal fibrilasyon','af'],['Atriyal flutter','flutter'],['Dar kompleks taşikardi','narrow'],['Monomorfik VT','vt']]),
  af:n('AF · başlangıç 200 J','Senkronize şok. Cihaz önerisi ve klinik yanıta göre.',[['Şok sonrası','review']],'shock'),
  flutter:n('Flutter · başlangıç 200 J','Senkronize şok. Cihaz önerisi ve klinik yanıta göre.',[['Şok sonrası','review']],'shock'),
  narrow:n('Dar kompleks · başlangıç 100 J','Senkronize şok. Cihaz önerisi ve klinik yanıta göre.',[['Şok sonrası','review']],'shock'),
  vt:n('Monomorfik VT · başlangıç 100 J','Nabız varsa senkronize şok.',[['Şok sonrası','review']],'shock'),
  review:n('Ritim ve perfüzyonu tekrar değerlendir','Tekrar şok gerekiyorsa senkronizasyonu yeniden doğrula. Kritik durumda senkronizasyon gecikiyorsa senkronize olmayan şoka geç.',[['Ritmi yeniden seç','start']]),
  poly:n('Polimorfik VT · defibrilasyon','Senkronize olmayan yüksek enerjili şok; senkronizasyon için bekleme.',[['Yeniden değerlendirme','start']],'critical'),
  arrest:n('Arrest algoritmasına geç','Nabızsız ritimde senkronize kardiyoversiyon uygulanmaz. VF/pVT ile asistoli/NEA ayrımını yap.',[],'critical')
 }}
};
export function advanceClinical(id,path,target){const a=clinicalAlgorithms[id];const key=path.at(-1)||a.start;return a.nodes[key].choices.some(c=>c[1]===target)?[...path,target]:path;}
