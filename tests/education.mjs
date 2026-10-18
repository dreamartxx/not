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
