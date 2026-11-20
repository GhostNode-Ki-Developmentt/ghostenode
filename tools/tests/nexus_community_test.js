const K = require(process.argv[2] + '/keys.json');   // Test-Schlüssel (nexus_test_keys.js)
const A = 'usr_00000000-0000-4000-8000-000000000001', B = 'usr_00000000-0000-4000-8000-000000000011';
const H = 'http://127.0.0.1:8902/';
const j = (f, a, b) => fetch(H + f + '?a=' + a, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
const call = async (f, a, b) => { const r = await j(f, a, b); const ct = r.headers.get('content-type') || ''; return [r.status, ct.includes('json') ? await r.json() : (await r.arrayBuffer()).byteLength + ' bytes ' + ct]; };
const mock = b => fetch('http://127.0.0.1:8792/api/1/__mock/set', { method: 'POST', body: JSON.stringify(b) });
const login = async (id, age) => { const t = (await call('nexusacct.php', 'register', { vrc_id: id, name: id.slice(-4), device: 't' }))[1].token; await call('nexusacct.php', 'age', { token: t, status: age }); return t; };   // seit 3.0.2: direkt mit der VRChat-ID; 18+ meldet die App
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64').toString('base64');
const ok = (c, m) => { if (!c) { console.log('✖', m); process.exitCode = 1; } else console.log('✔', m); };
(async () => {
  const ta = await login(A, '18+'), tb = await login(B, '');
  await call('nexusacct.php', 'nsfw', { token: ta, on: true });
  // B ist noch nicht im mock bekannt (kein Freund) -> nutze Freund 20? 
  const a = { token: ta, key: K.me }, b = { token: tb };
  let r = await call('nexuscomm.php', 'me', a); ok(r[1].mod === true && r[1].nsfw === true, 'A: Moderator + NSFW');
  r = await call('nexuscomm.php', 'me', b); ok(r[1].mod === false && r[1].nsfw === false, 'B: weder Mod noch NSFW');
  r = await call('nexuscomm.php', 'post', Object.assign({ text: 'Hallo Community', rating: 'sfw' }, a)); const p1 = r[1].post.id;
  r = await call('nexuscomm.php', 'feed', Object.assign({ rating: 'sfw' }, b)); ok(r[1].items.some(x => x.id === p1), 'B sieht SFW-Beitrag');
  r = await call('nexuscomm.php', 'post', Object.assign({ text: 'spicy', rating: 'nsfw' }, a)); const p2 = r[1].post.id; ok(p2 > 0, 'A postet NSFW');
  r = await call('nexuscomm.php', 'feed', Object.assign({ rating: 'sfw' }, a)); ok(!r[1].items.some(x => x.id === p2), 'NSFW nie in der normalen Timeline');
  r = await call('nexuscomm.php', 'feed', Object.assign({ rating: 'nsfw' }, b)); ok(r[0] === 400 && /NSFW/.test(r[1].error), 'B: NSFW-Feed gesperrt');
  r = await call('nexuscomm.php', 'thread', Object.assign({ id: p2 }, b)); ok(r[0] === 400, 'B: NSFW-Beitrag direkt abrufen gesperrt');
  r = await call('nexuscomm.php', 'post', Object.assign({ text: 'x', rating: 'nsfw' }, b)); ok(r[0] === 400, 'B: darf nicht NSFW posten');
  r = await call('nexuscomm.php', 'post', Object.assign({ text: 'antwort', parent: p2 }, b)); ok(r[0] === 400, 'B: darf nicht auf NSFW antworten');
  // Bild NSFW → Vorab-Prüfung (A ist Mod → sofort ok; darum ohne Mod-Schlüssel hochladen)
  r = await call('nexuscomm.php', 'upload', { token: ta, data: png, rating: 'nsfw' }); const m1 = r[1].id; ok(r[1].status === 'pending', 'NSFW-Bild wartet auf Freigabe');
  r = await call('nexuscomm.php', 'post', { token: ta, text: 'mit bild', rating: 'nsfw', media: [m1] }); const p3 = r[1].post.id; ok(r[1].post.status === 'pending', 'Beitrag mit NSFW-Bild wartet');
  r = await call('nexuscomm.php', 'media', Object.assign({ id: m1 }, b)); ok(r[0] === 400, 'B: NSFW-Bild gesperrt');
  r = await call('nexuscomm.php', 'mod_queue', b); ok(r[0] === 400, 'B: keine Mod-Warteschlange');
  r = await call('nexuscomm.php', 'mod_queue', a); ok(r[1].media.some(x => x.id === m1) && r[1].pending.some(x => x.id === p3), 'Mod sieht Bild + Beitrag in der Warteschlange');
  r = await call('nexuscomm.php', 'mod', Object.assign({ do: 'media_ok', media: m1 }, a)); ok(r[1].ok, 'Mod gibt Bild frei');
  r = await call('nexuscomm.php', 'feed', Object.assign({ rating: 'nsfw' }, a)); ok(r[1].items.some(x => x.id === p3 && x.status === 'visible'), 'Beitrag erscheint nach Freigabe');
  r = await call('nexuscomm.php', 'media', Object.assign({ id: m1 }, a)); ok(/image\/png/.test(r[1]), 'A bekommt das Bild');
  // Melden + umstufen
  r = await call('nexuscomm.php', 'post', Object.assign({ text: 'eigentlich nsfw' }, b)); const p4 = r[1].post.id;
  r = await call('nexuscomm.php', 'report', Object.assign({ id: p4, reason: 'gehört in NSFW' }, a)); ok(r[1].ok, 'Melden');
  r = await call('nexuscomm.php', 'mod', Object.assign({ do: 'nsfw', id: p4 }, a));
  r = await call('nexuscomm.php', 'feed', Object.assign({ rating: 'sfw' }, b)); ok(!r[1].items.some(x => x.id === p4), 'umgestuft: weg aus der Timeline');
  r = await call('nexuscomm.php', 'mod_queue', a); ok(!r[1].reports.some(x => x.post_id === p4), 'Meldung automatisch erledigt');
  // Reaktion, Profil
  r = await call('nexuscomm.php', 'react', Object.assign({ id: p1, emoji: '🦊' }, b)); ok(r[1].post.reactions['🦊'] === 1, 'Reaktion');
  r = await call('nexuscomm.php', 'profile_save', Object.assign({ bio: 'Hi', pronouns: 'sie/ihr', nsfw_bio: 'geheim', links: ['https://x.example', 'javascript:alert(1)'] }, a)); ok(r[1].profile.links.length === 1, 'Profil: nur https-Links');
  r = await call('nexuscomm.php', 'profile', Object.assign({ id: A }, b)); ok(r[1].profile.bio === 'Hi' && r[1].profile.nsfw_bio === undefined, 'B sieht 18+-Profilteil nicht');
  // Steckbrief (für alle) + 18+-Steckbrief (nur Freigabe): nur feste Werte, Kinks begrenzt
  r = await call('nexuscomm.php', 'profile_save', Object.assign({ bio: 'Hi', facts: { gender: ['female', 'trans_female', 'quatsch'], orientation: 'pan', relationship: 'taken', looking: 'evil' },
    nsfw_facts: { position: 'bottom', behavior: 'versatile', can_host: true, kinks: { kissing: [2, 1], 'bad key!': [2, 2], bondage: [9, -5], empty: [null, null] } } }, a));
  const pf = r[1].profile;
  ok(pf.facts.gender.join() === 'female,trans_female' && pf.facts.orientation === 'pan' && !pf.facts.looking, 'Steckbrief: nur erlaubte Werte');
  ok(pf.nsfw_facts.position === 'bottom' && pf.nsfw_facts.can_host === true && pf.nsfw_facts.kinks.kissing.join() === '2,1' && pf.nsfw_facts.kinks.bondage.join() === '2,-1' && !pf.nsfw_facts.kinks['bad key!'] && !pf.nsfw_facts.kinks.empty, '18+-Steckbrief: Werte begrenzt, falsche Schlüssel verworfen');
  r = await call('nexuscomm.php', 'profile', Object.assign({ id: A }, b)); ok(r[1].profile.facts.orientation === 'pan' && r[1].profile.nsfw_facts === undefined, 'B sieht Steckbrief, aber keinen 18+-Steckbrief');
  r = await call('nexuscomm.php', 'profile_save', Object.assign({ bio: 'B', nsfw_facts: { position: 'top' } }, b));
  r = await call('nexuscomm.php', 'profile', Object.assign({ id: B }, b)); ok(!(r[1].profile.nsfw_facts || {}).position, 'B ohne Freigabe kann keinen 18+-Steckbrief speichern');
  r = await call('nexuscomm.php', 'profile_save', Object.assign({ bio: 'Hi' }, a));
  r = await call('nexuscomm.php', 'profile', Object.assign({ id: A }, a)); ok(r[1].profile.nsfw_facts.position === 'bottom' && r[1].profile.facts.orientation === 'pan', 'ältere App ohne Steckbrief überschreibt nichts');
  // Stummschalten / Sperren
  r = await call('nexuscomm.php', 'mod', Object.assign({ do: 'mute', vrc_id: B, hours: 2, note: 'Spam' }, a));
  r = await call('nexuscomm.php', 'post', Object.assign({ text: 'nochmal' }, b)); ok(r[0] === 400 && /stumm/.test(r[1].error), 'B stummgeschaltet');
  r = await call('nexuscomm.php', 'mod', Object.assign({ do: 'unban', vrc_id: B }, a));
  r = await call('nexuscomm.php', 'post', Object.assign({ text: 'wieder da' }, b)); ok(r[0] === 200, 'Sperre aufgehoben');
  r = await call('nexuscomm.php', 'mod', Object.assign({ do: 'ban', vrc_id: A }, a)); ok(r[0] === 400, 'Selbst-Sperre verboten');
  r = await call('nexuscomm.php', 'mod_queue', a); ok(r[1].log.length >= 4, 'Protokoll: ' + r[1].log.length + ' Einträge');
})();
