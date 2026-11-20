<?php
/**
 * Nexus-Privat-Chat – Briefkasten für Ende-zu-Ende-verschlüsselte Nachrichten (X3DH + Double Ratchet in der App).
 * <title>Nexus-Chat</title>
 *
 * Der Server sieht NIE Klartext oder private Schlüssel: nur öffentliche Geräte-Schlüssel und Chiffretexte.
 * Jede Anfrage braucht das Konto-Token (nexusacct). Geräte gehören fest zu einem Konto.
 *   publish  {token, ik_dh, ik_sig, spk:{id,pub,sig}, opks:[{id,pub}]}   → {device, opks}
 *   devices  {token, uid}                     → Geräte (öffentliche Identitäten) – für Sicherheitsnummern
 *   bundles  {token, uid, skip:[device…]}     → je Gerät ein Paket inkl. EINEM Einmal-Pre-Key (wird verbraucht)
 *   send     {token, device, msgs:[{to, dev, body}]}
 *   fetch    {token, device}                  → {msgs:[{id, from, from_dev, body, at}], opks}
 *   ack      {token, device, ids:[…]}
 *   remove   {token, device}
 * Nachrichten liegen höchstens 30 Tage und werden nach dem Abholen gelöscht.
 */
require_once __DIR__ . '/inc/core/pages.php';
require_once __DIR__ . '/inc/modules/nexusacct.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');
$out = function (array $o, int $code = 200) { http_response_code($code); echo json_encode($o, JSON_UNESCAPED_UNICODE); exit; };
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') $out(['error' => 'Nur POST.'], 405);
$a = (string)($_GET['a'] ?? '');
if (nw_rate_over('ch|' . client_ip(), 240)) $out(['error' => 'Zu viele Anfragen – kurz warten.'], 429);
$in = json_decode((string)file_get_contents('php://input', false, null, 0, 1048576), true);
if (!is_array($in)) $out(['error' => 'JSON erwartet.'], 400);

function ch_db(): PDO { return db_get('vrchat'); }
function ch_key(string $b64): string { $r = base64_decode($b64, true); if ($r === false || strlen($r) !== 32) throw new NxError('Schlüssel ungültig.'); return $r; }
function ch_dev(array $acc, string $dev): array {
    $st = ch_db()->prepare('SELECT * FROM chat_devices WHERE device_id = ? AND vrc_id = ?');
    $st->execute([$dev, $acc['vrc_id']]);
    $d = $st->fetch(PDO::FETCH_ASSOC);
    if (!$d) throw new NxError('Gerät nicht registriert.');
    ch_db()->prepare('UPDATE chat_devices SET last_seen = ? WHERE device_id = ?')->execute([time(), $dev]);
    return $d;
}
function ch_opks(string $dev): int { $st = ch_db()->prepare('SELECT COUNT(*) FROM chat_opks WHERE device_id = ?'); $st->execute([$dev]); return (int)$st->fetchColumn(); }
function ch_devices(string $uid): array {
    $st = ch_db()->prepare('SELECT * FROM chat_devices WHERE vrc_id = ? AND last_seen > ? ORDER BY created');
    $st->execute([$uid, time() - 60 * 86400]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

try {
    $acc = nx_auth((string)($in['token'] ?? ''));
    $db = ch_db();
    if (mt_rand(1, 100) === 1) $db->prepare('DELETE FROM chat_inbox WHERE created < ?')->execute([time() - 30 * 86400]);
    switch ($a) {
        case 'publish': {
            $ikd = ch_key((string)($in['ik_dh'] ?? '')); $iks = ch_key((string)($in['ik_sig'] ?? ''));
            $spk = (array)($in['spk'] ?? []);
            $sp = ch_key((string)($spk['pub'] ?? '')); $sig = base64_decode((string)($spk['sig'] ?? ''), true);
            if ($sig === false || strlen($sig) !== 64 || !sodium_crypto_sign_verify_detached($sig, 'spk' . $sp . $ikd, $iks)) throw new NxError('Signatur des Pre-Keys ungültig.');
            $dev = substr(hash('sha256', $iks), 0, 16);
            $ex = $db->prepare('SELECT vrc_id FROM chat_devices WHERE device_id = ?'); $ex->execute([$dev]);
            $owner = (string)$ex->fetchColumn();
            if ($owner !== '' && $owner !== $acc['vrc_id']) throw new NxError('Gerät gehört zu einem anderen Konto.');
            if ($owner === '') {
                if (count(ch_devices($acc['vrc_id'])) >= 10) throw new NxError('Höchstens 10 Geräte – erst ein altes entfernen.');
                $db->prepare('INSERT INTO chat_devices (device_id, vrc_id, ik_dh, ik_sig, spk_id, spk_pub, spk_sig, created, last_seen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$dev, $acc['vrc_id'], base64_encode($ikd), base64_encode($iks), (int)($spk['id'] ?? 0), base64_encode($sp), base64_encode($sig), time(), time()]);
                nw_log($acc['vrc_id'], 'Chat-Gerät angemeldet', $acc['vrc_id'], $dev);
            } else $db->prepare('UPDATE chat_devices SET spk_id = ?, spk_pub = ?, spk_sig = ?, last_seen = ? WHERE device_id = ?')->execute([(int)($spk['id'] ?? 0), base64_encode($sp), base64_encode($sig), time(), $dev]);
            $ins = $db->prepare('INSERT OR IGNORE INTO chat_opks (device_id, opk_id, pub) VALUES (?, ?, ?)');
            foreach (array_slice((array)($in['opks'] ?? []), 0, 100) as $o) $ins->execute([$dev, (int)($o['id'] ?? 0), base64_encode(ch_key((string)($o['pub'] ?? '')))]);
            $out(['device' => $dev, 'opks' => ch_opks($dev)]);
        }
        case 'devices': {
            $uid = strtolower((string)($in['uid'] ?? ''));
            if (!nx_uid_ok($uid)) throw new NxError('Ungültige VRChat-User-ID.');
            $out(['devices' => array_map(fn($d) => ['device' => $d['device_id'], 'ik_dh' => $d['ik_dh'], 'ik_sig' => $d['ik_sig'], 'created' => (int)$d['created']], ch_devices($uid))]);
        }
        case 'bundles': {
            $uid = strtolower((string)($in['uid'] ?? ''));
            if (!nx_uid_ok($uid)) throw new NxError('Ungültige VRChat-User-ID.');
            if (!nx_account($uid)) throw new NxError('Diese Person hat (noch) kein Nexus-Konto.');
            $skip = array_map('strval', (array)($in['skip'] ?? []));
            $res = [];
            foreach (ch_devices($uid) as $d) {
                if (in_array($d['device_id'], $skip, true)) continue;
                $o = $db->prepare('SELECT opk_id, pub FROM chat_opks WHERE device_id = ? ORDER BY opk_id LIMIT 1'); $o->execute([$d['device_id']]);
                $opk = $o->fetch(PDO::FETCH_ASSOC);
                if ($opk) $db->prepare('DELETE FROM chat_opks WHERE device_id = ? AND opk_id = ?')->execute([$d['device_id'], $opk['opk_id']]);
                $res[] = ['device' => $d['device_id'], 'ik_dh' => $d['ik_dh'], 'ik_sig' => $d['ik_sig'], 'spk' => ['id' => (int)$d['spk_id'], 'pub' => $d['spk_pub'], 'sig' => $d['spk_sig']],
                    'opk' => $opk ? ['id' => (int)$opk['opk_id'], 'pub' => $opk['pub']] : null];
            }
            $out(['bundles' => $res]);
        }
        case 'send': {
            $from = ch_dev($acc, (string)($in['device'] ?? ''));
            $msgs = (array)($in['msgs'] ?? []);
            if (!$msgs || count($msgs) > 50) throw new NxError('1 bis 50 Nachrichten je Aufruf.');
            $n = $db->prepare('SELECT COUNT(*) FROM chat_inbox WHERE from_uid = ? AND created > ?'); $n->execute([$acc['vrc_id'], time() - 3600]);
            if ((int)$n->fetchColumn() > 3000) throw new NxError('Zu viele Nachrichten – kurz Pause.');
            $chk = $db->prepare('SELECT 1 FROM chat_devices WHERE device_id = ? AND vrc_id = ?');
            $ins = $db->prepare('INSERT INTO chat_inbox (to_device, to_uid, from_uid, from_device, body, created) VALUES (?, ?, ?, ?, ?, ?)');
            $ok = 0;
            foreach ($msgs as $m) {
                $to = strtolower((string)($m['to'] ?? '')); $dev = (string)($m['dev'] ?? ''); $body = (string)($m['body'] ?? '');
                if (!nx_uid_ok($to) || $body === '' || strlen($body) > 30000) continue;
                $chk->execute([$dev, $to]);
                if (!$chk->fetchColumn()) continue;
                $ins->execute([$dev, $to, $acc['vrc_id'], $from['device_id'], $body, time()]);
                $ok++;
            }
            $out(['ok' => $ok]);
        }
        case 'fetch': {
            $d = ch_dev($acc, (string)($in['device'] ?? ''));
            $st = $db->prepare('SELECT id, from_uid, from_device, body, created FROM chat_inbox WHERE to_device = ? ORDER BY id LIMIT 200');
            $st->execute([$d['device_id']]);
            $out(['msgs' => array_map(fn($m) => ['id' => (int)$m['id'], 'from' => $m['from_uid'], 'from_dev' => $m['from_device'], 'body' => $m['body'], 'at' => (int)$m['created']], $st->fetchAll(PDO::FETCH_ASSOC)), 'opks' => ch_opks($d['device_id'])]);
        }
        case 'ack': {
            $d = ch_dev($acc, (string)($in['device'] ?? ''));
            $del = $db->prepare('DELETE FROM chat_inbox WHERE id = ? AND to_device = ?');
            foreach (array_slice((array)($in['ids'] ?? []), 0, 500) as $id) $del->execute([(int)$id, $d['device_id']]);
            $out(['ok' => true]);
        }
        case 'remove': {
            $dev = (string)($in['device'] ?? '');
            $own = $db->prepare('SELECT 1 FROM chat_devices WHERE device_id = ? AND vrc_id = ?'); $own->execute([$dev, $acc['vrc_id']]);
            if (!$own->fetchColumn()) throw new NxError('Gerät nicht gefunden.');
            foreach (['chat_opks WHERE device_id', 'chat_inbox WHERE to_device', 'chat_devices WHERE device_id'] as $t) $db->prepare('DELETE FROM ' . $t . ' = ?')->execute([$dev]);
            nw_log($acc['vrc_id'], 'Chat-Gerät entfernt', $acc['vrc_id'], $dev);
            $out(['ok' => true]);
        }
    }
    $out(['error' => 'Unbekannte Aktion.'], 400);
} catch (NxError $e) {
    $out(['error' => $e->getMessage()], $e->getMessage() === 'Nicht angemeldet.' ? 401 : 400);
} catch (Throwable $e) {
    error_log('nexuschat: ' . $e->getMessage());
    $out(['error' => 'Serverfehler.'], 500);
}
