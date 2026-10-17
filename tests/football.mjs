import assert from 'node:assert/strict';
import {youtubeEmbed,fixtureDay,fixtureTime} from '../assets/football.js';
assert.equal(youtubeEmbed('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
for(const url of ['javascript:alert(1)','https://www.youtube.com.evil.test/watch?v=dQw4w9WgXcQ','https://www.youtube.com/watch?v=bad','https://evil.test/',''])assert.equal(youtubeEmbed(url),null);
assert.equal(fixtureDay('2026-10-17T22:30:00Z'),'2026-10-18');
assert.equal(fixtureTime({date:'2026-10-17T16:30:00Z',time_confirmed:true}),'19:30');
assert.equal(fixtureTime({date:'2026-10-17T00:00:00Z',time_confirmed:false}),'Saat açıklanmadı');
console.log('PASS: Turkey timezone, midnight rollover, unannounced kickoff, safe YouTube embeds.');
