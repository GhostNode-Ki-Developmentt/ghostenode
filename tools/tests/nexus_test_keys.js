const c = require('crypto');
const { publicKey, privateKey } = c.generateKeyPairSync('ed25519');
const pub = publicKey.export({ type: 'spki', format: 'der' }).toString('base64');
const b64u = b => Buffer.from(b).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
const tok = (uid, f) => { const body = b64u(JSON.stringify({ v: 1, id: c.randomBytes(6).toString('hex'), uid, f, iat: 1, exp: 0 })); return 'NXK1.' + body + '.' + b64u(c.sign(null, Buffer.from(body), privateKey)); };
const u = n => 'usr_00000000-0000-4000-8000-' + String(n).padStart(12, '0');
console.log(JSON.stringify({ pub, me: tok(u(1), ['mod']), b: tok(u(20), ['nsfw']), c: tok(u(21), ['nsfw']) }));
