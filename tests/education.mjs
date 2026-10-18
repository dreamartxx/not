import assert from 'node:assert/strict';
import fs from 'node:fs';
import {chapterAt,progress,searchBook,reviewCard,studyDay} from '../assets/education-model.js';
const root=new URL('../assets/data/education/',import.meta.url),book=JSON.parse(fs.readFileSync(new URL('catalog.json',root)));
book.pages=Array.from({length:8},(_,i)=>JSON.parse(fs.readFileSync(new URL(`pages-${i+1}.json`,root)))).flat();
assert.equal(book.pages.length,307);assert.deepEqual(book.pages.map(p=>p.number),Array.from({length:307},(_,i)=>i+1));
assert.equal(book.pages[11].label,'3');assert.equal(book.pages[306].label,'298');
assert.equal(book.chapters.length,5);for(const c of book.chapters){assert.equal(chapterAt(book,c.start),c);assert.equal(chapterAt(book,c.end),c);for(const [p,t] of c.units){assert.ok(p>=c.start&&p<=c.end);assert.ok(t);}}
assert.ok(book.pages[11].paragraphs.some(t=>t.includes('Türk Yargı Teşkilatı')));assert.ok(book.pages[251].visual);assert.equal(chapterAt(book,300),undefined);
assert.ok(searchBook(book,'BİLİRKİŞİ').length>150);assert.ok(searchBook(book,'tarafsızlık').some(r=>r.page>78));assert.deepEqual(searchBook(book,'  '),[]);assert.deepEqual(searchBook(book,'<script>'),[]);
assert.deepEqual(progress([12,12,13,200],12,15),{done:2,total:4,percent:50});
const now=new Date('2026-10-18T22:30:00Z');assert.equal(studyDay(now),'2026-10-19');let c={level:0,due:'2026-10-18'};
c=reviewCard(c,'known',now);assert.equal(c.due,'2026-10-20');assert.equal(c.level,1);c=reviewCard(c,'known',now);assert.equal(c.due,'2026-10-22');assert.equal(reviewCard(c,'again',now).due,'2026-10-19');assert.equal(reviewCard({level:6},'known',new Date('2026-12-20T10:00:00Z')).due,'2027-02-18');
console.log('PASS: complete 307-page source, chapter/page mapping, Turkish search, deduplicated progress, Istanbul dates and review intervals.');
const {courseScore}=await import('../assets/education-course.js');
const course=JSON.parse(fs.readFileSync(new URL('lessons.json',root)));
assert.equal(course.lessons.length,5);const questionIds=new Set();
for(const lesson of course.lessons){assert.ok(book.chapters.some(c=>c.id===lesson.id));assert.equal(lesson.concepts.length,3);assert.equal(lesson.steps.length,3);assert.equal(lesson.quiz.length,3);assert.ok(lesson.scenario.correct<lesson.scenario.options.length);for(const c of lesson.concepts)assert.ok(book.pages[c[3]-1].paragraphs.length);const answers={};for(const q of lesson.quiz){assert.ok(!questionIds.has(q.id));questionIds.add(q.id);assert.ok(q.correct>=0&&q.correct<q.options.length);assert.ok(q.page>=1&&q.page<=307);answers[q.id]=q.correct;}assert.equal(courseScore(lesson,{}),0);assert.equal(courseScore(lesson,answers),3);answers[lesson.quiz[0].id]=(lesson.quiz[0].correct+1)%3;assert.equal(courseScore(lesson,answers),2);}
assert.equal(questionIds.size,15);
console.log('PASS: five interactive lessons, valid source references, unique questions and correct scoring.');

const {tutorialProgress}=await import('../assets/education-tutorial.js');
const tutorials=JSON.parse(fs.readFileSync(new URL('tutorials.json',root))).units;
assert.equal(tutorials.length,20);assert.equal(new Set(tutorials.map(u=>u.id)).size,20);
for(const lesson of course.lessons)assert.equal(tutorials.filter(u=>u.lesson===lesson.id).length,4);
for(const u of tutorials){
 assert.ok(u.pages.length);for(const n of u.pages)assert.ok(book.pages[n-1]?.paragraphs.length);
 assert.equal(u.sections.length,3);for(const s of u.sections){assert.ok(s.title);assert.ok(s.text.split(/\s+/).length>=30);}
 assert.equal(u.steps.length,3);for(const s of u.steps){assert.ok(s.title);assert.ok(s.text.length>100);}
 assert.ok(u.comparison.wrong&&u.comparison.right&&u.comparison.why);
 assert.equal(u.check.options.length,3);assert.equal(u.check.feedback.length,3);assert.ok(u.check.correct>=0&&u.check.correct<3);
 assert.ok(u.check.feedback.every(f=>f.length>40));assert.ok(u.reflection.prompt.length>40&&u.reflection.model.length>100);
}
assert.deepEqual(tutorialProgress(tutorials,{}),{done:0,total:20});
const study={answers:Object.fromEntries(tutorials.map(u=>[u.id,u.check.correct]))};
assert.deepEqual(tutorialProgress(tutorials,study),{done:20,total:20});
study.answers[tutorials[0].id]=(tutorials[0].check.correct+1)%3;study.answers.unknown=0;
assert.deepEqual(tutorialProgress(tutorials,study),{done:19,total:20});
console.log('PASS: 20 detailed topics, 60 solution steps, source references, option-specific feedback and topic progress.');

const outline=JSON.parse(fs.readFileSync(new URL('outline.json',root)));
const guides=JSON.parse(fs.readFileSync(new URL('guides.json',root)));
const {outlineAncestors,outlineProgress,outlineRefs}=await import('../assets/education-outline-model.js');
const {educationVisuals,educationVisual}=await import('../assets/education-visuals.js');
const refs=outline.nodes.flatMap(n=>n.refs.map(r=>r.page+':'+r.index));
const expected=book.pages.filter(p=>p.number>=10).flatMap(p=>p.paragraphs.map((_,i)=>p.number+':'+i));
assert.deepEqual(refs,expected);assert.equal(new Set(refs).size,expected.length);
assert.equal(outline.nodes.length,244);assert.equal(outline.toc.length,135);assert.equal(guides.length,37);
for(const n of outline.nodes){assert.ok(outlineRefs(book,n).every(r=>r.text));if(n.parent){const parent=outline.nodes.find(x=>x.id===n.parent);assert.ok(parent);assert.ok(parent.level<n.level);assert.equal(parent.lesson,n.lesson);}assert.ok(outlineAncestors(outline.nodes,n.id).length<=8);}
for(const t of outline.toc){assert.ok(outline.nodes.some(n=>n.id===t.node));assert.ok(t.match>.65);}
for(const c of outline.chapters)assert.ok(outline.nodes.some(n=>n.lesson===c.id));
assert.equal(outlineProgress(outline.nodes,['book-12-3','book-12-3','missing']).done,1);
assert.equal(outlineAncestors(outline.nodes,'book-23-3').at(-1).title,'b.- Ceza Mahkemeleri');
assert.equal(outline.nodes.find(n=>n.id==='book-262-1').parent,'book-251-4');
const figures=new Set(outline.nodes.flatMap(n=>n.figurePages));assert.deepEqual([...figures].sort((a,b)=>a-b),outline.figurePages);
for(const p of figures){const bytes=fs.readFileSync(new URL('figures/page-'+p+'.jpg',root));assert.equal(bytes[0],255);assert.equal(bytes[1],216);}
assert.equal(Object.keys(educationVisuals).length,9);for(const [key,v]of Object.entries(educationVisuals)){assert.ok(educationVisual(key,s=>String(s),'').includes('aria-pressed="true"'));for(const node of v.nodes)assert.ok(book.pages[node[4]-1]);}
console.log('PASS: all 2299 source paragraphs covered exactly once, 135 TOC mappings, 244 nested headings, 42 original images and 9 teaching diagrams.');

const {createBookCourse}=await import('../assets/education-book-course.js');
book.course={...course,outline,guides,tutorials:{units:tutorials}};
const guestData={learning:{selected:'yargilama',completed:[],answers:{},book:{current:'book-12-3',visited:[],notes:{}}}};
const args={book:()=>book,data:()=>guestData,esc:s=>String(s),paint:()=>{},changed:()=>{},source:()=>{}};
assert.ok(createBookCourse(args).view('yargilama').includes('id="book-lesson-note"'));
assert.ok(!createBookCourse({...args,guest:true,figureApi:'guest-api.php'}).view('yargilama').includes('<textarea'));
guestData.learning.book.current='book-251-4';guestData.learning.selected='uyap';
const guestSource=createBookCourse({...args,guest:true,figureApi:'guest-api.php'}).view('uyap');
assert.ok(guestSource.includes('src="guest-api.php?action=education-figure'));
assert.ok(!guestSource.includes('src="api.php?action=education-figure'));
console.log('PASS: owner study notes retained, guest writing controls absent and guest figures use isolated endpoint.');
