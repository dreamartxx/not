"""Reconcile every indexed 5eTürkçe spell without copying non-SRD descriptions.

Usage: python3 tests/import_spell_catalog.py DIRECTORY
DIRECTORY contains index.json and each spells-*.json named by that index.
Existing IDs and licensed translations are retained so character books survive.
"""
import json
import pathlib
import re
import sys
from urllib.parse import quote

root = pathlib.Path(__file__).resolve().parents[1]
directory = pathlib.Path(sys.argv[1])
target = root / 'assets/data/dnd-spells-2014.json'
doc = json.loads(target.read_text())
existing = {(s.get('source', 'PHB'), s['translation_name']): s for s in doc['spells']}
index = json.loads((directory / 'index.json').read_text())
schools = dict(zip('ACDEVINT', ['Abjuration', 'Conjuration', 'Divination', 'Enchantment', 'Evocation', 'Illusion', 'Necromancy', 'Transmutation']))
classes = {'Büyücü': 'Wizard', 'Rahip': 'Cleric', 'Ozan': 'Bard', 'Kolcu': 'Ranger'}
units = {'eylem': 'action', 'bonus': 'bonus action', 'reaksiyon': 'reaction', 'dakika': 'minute', 'saat': 'hour', 'gün': 'day'}

def duration(d):
    if d['type'] == 'timed':
        v = d['duration']
        return ('Up to ' if d.get('concentration') or v.get('upTo') else '') + str(v['amount']) + ' ' + units.get(v['type'], v['type'])
    return {'instant': 'Instantaneous', 'special': 'Special', 'permanent': 'Until dispelled or triggered' if 'trigger' in d.get('ends', []) else 'Until dispelled'}[d['type']]

def spell_range(r):
    if r['type'] == 'special':
        return 'Special'
    d = r['distance']
    value = (str(d['amount']) + ' ' if 'amount' in d else '') + d['type']
    return value if r['type'] == 'point' else 'Self (' + value + ' ' + r['type'] + ')'

spells, counts, matched = [], {}, set()
for source, filename in index.items():
    entries = json.loads((directory / filename).read_text())['spell']
    counts[source] = len(entries)
    for entry in entries:
        assert entry['source'] == source
        name = entry['name'].split(' (')[0]
        key = (source, entry['name'])
        if key in existing:
            s = existing[key].copy()
            matched.add(key)
            assert s['level'] == entry['level'], name
            if s['name'] != name:
                s['aliases'] = sorted(set(s.get('aliases', []) + [s['name']]))
            s['name'] = name
        else:
            s = dict(id=re.sub(r'[^a-z0-9]+', '-', name.lower()).strip('-'), name=name,
                     tr=entry['name'].split(' (', 1)[-1].rstrip(')'), translation_name=entry['name'],
                     level=entry['level'], school=schools[entry['school']],
                     classes=[classes.get(c['name'], c['name']) for c in entry['classes']['fromClassList']],
                     casting=' or '.join(str(t['number']) + ' ' + units.get(t['unit'], t['unit']) for t in entry['time']),
                     range=spell_range(entry['range']), components=', '.join(c.upper() for c in ['v', 's', 'm'] if entry['components'].get(c)),
                     duration=' / '.join(duration(d) for d in entry['duration']),
                     ritual=entry.get('meta', {}).get('ritual', False), concentration=any(d.get('concentration') for d in entry['duration']),
                     description='', higher='', material='', description_mode='source')
        # Match the publisher's encodeURIComponent(part).toLowerCase() URL builder.
        s.update(source=source, page=entry.get('page', ''), source_url='https://kanguen.github.io/spells.html#' + '_'.join(quote(p, safe="~()*!.'-").lower() for p in [entry['name'], source]))
        spells.append(s)

assert matched == set(existing), 'Existing spells must never disappear'
assert len({s['id'] for s in spells}) == len(spells), 'Duplicate IDs'
assert len({s['source_url'] for s in spells}) == len(spells), 'Duplicate source records'
doc.update(source='5eTürkçe · PHB / SCAG / XGE (2014 rules)', spells=spells,
           catalog={'index': 'https://kanguen.github.io/data/spells/index.json', 'sources': counts, 'total': len(spells), 'retrieved': '2026-10-17'})
doc['translation']['adaptation'] = '319 existing SRD translations retained. Every PHB, SCAG and XGE record indexed with factual metadata and a direct publisher link. Non-SRD descriptions are displayed only on the publisher site, not copied. Existing spell IDs retained; English source names and SRD aliases searchable.'
target.write_text(json.dumps(doc, ensure_ascii=False, separators=(',', ':')) + '\n')
print(json.dumps({'sources': counts, 'total': len(spells), 'preserved': len(matched), 'source_descriptions': sum(s.get('description_mode') == 'source' for s in spells)}))
