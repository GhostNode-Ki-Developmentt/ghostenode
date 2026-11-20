<?php
/**
 * Nexus-Warnliste (Crasher / Ripper / Troller) – gemeinsamer Speicher für freigeschaltete VRC-Nexus-Nutzer.
 *
 * Wer darf was:
 *  - Lesen & melden: nur mit gültigem NXK1-Freischalt-Schlüssel (Ed25519, vom Besitzer unterschrieben, an EINE VRChat-User-ID gebunden).
 *    Die Webseite prüft die Unterschrift selbst – ein Schlüssel lässt sich nicht fälschen oder auf eine andere ID umschreiben.
 *  - Sichtbar wird eine Warnung erst ab N unabhängigen Meldern (Standard 2, im Admin einstellbar).
 *  - Beweis ist Pflicht, Meldungen verfallen (Standard 6 Monate, höchstens 1 Jahr).
 *  - Betroffene können Einspruch einlegen (mit eigenem Schlüssel). Admin (Besitzerin + von ihr ernannte Moderatoren
 *    mit dem Recht „mod“ im Schlüssel, außerdem Webseiten-Admins): alles sehen, bearbeiten, löschen, Einsprüche bearbeiten,
 *    Schlüssel sperren. Jede Änderung landet im Protokoll.
 * Datensparsam: gespeichert werden nur User-IDs, Anzeigenamen, Grund, Notiz, Beweis-Text und Zeiten.
 * Tabellen liegen im Modul „vrchat“ (Migration 9 in inc/modules/vrchat.php).
 */

/** öffentliche Schlüssel des Besitzers (Ed25519, SPKI/DER base64) – dieselben wie in der App (backend/nsfw.js, TRUSTED) */
const NW_TRUSTED = [
    'MCowBQYDK2VwAyEAg3Ejm0ec/StudDQryGwNGNr55UKQT3EQYvdPhL3dvo4=',
];
/** SHA-256 der VRChat-User-ID des Besitzers (wie in der App) */
const NW_OWNER_HASH = '23e799d90cf62600203499e3f4d2712bb1a016e71dac64718b2c7d8267b2789e';
const NW_REASONS = ['crasher' => ['💥', 'Crasher'], 'ripper' => ['🏴‍☠️', 'Ripper'], 'troll' => ['🧌', 'Troll'], 'other' => ['⚠', 'Sonstiges']];
const NW_UID = '/^usr_[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

class NwError extends RuntimeException {}

function nw_db(): PDO { return db_get('vrchat'); }
/** einfache Bremse pro IP (Datei im gesperrten data/-Ordner, keine Datenbank-Schreiblast) */
function nw_rate_over(string $ip, int $max): bool {
    $dir = DATA_DIR . '/nexuswarn';
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n"); }
    $f = $dir . '/rate_' . substr(hash('sha256', 'nw|' . $ip), 0, 16) . '.php';
    $h = @fopen($f, 'c+');
    if (!$h) return false;
    flock($h, LOCK_EX);
    $raw = (string)stream_get_contents($h);
    $d = json_decode((string)preg_replace('/^<\?php[^\n]*\n/', '', $raw), true) ?: [];
    $m = (int)floor(time() / 60);
    if (($d['m'] ?? 0) !== $m) $d = ['m' => $m, 'n' => 0];
    $over = ++$d['n'] > $max;
    ftruncate($h, 0); rewind($h); fwrite($h, "<?php http_response_code(404); exit; ?>\n" . json_encode($d)); flock($h, LOCK_UN); fclose($h);
    if (mt_rand(1, 300) === 1) foreach (glob($dir . '/rate_*.php') ?: [] as $x) if (time() - (int)@filemtime($x) > 86400) @unlink($x);
    return $over;
}
/** vertrauenswürdige Schlüssel – zum Testen kann der Server per Umgebungsvariable einen zusätzlichen bekommen (nie über das Netz) */
function nw_trusted(): array { $t = NW_TRUSTED; $x = (string)getenv('NEXUS_WARN_TEST_PUBKEY'); if ($x !== '') $t[] = $x; return $t; }
function nw_b64u(string $s): string { return (string)base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true); }
function nw_cut(string $s, int $n): string { return mb_substr(trim(str_replace("\r", '', $s)), 0, $n); }
function nw_min(): int { return max(1, min(10, (int)setting('nexuswarn_min', '2'))); }
function nw_revoked(): array { return array_values(array_filter(explode(',', setting('nexuswarn_revoked', '')))); }
function nw_is_owner(string $uid): bool { return hash_equals(NW_OWNER_HASH, hash('sha256', $uid)); }

/**
 * NXK1-Schlüssel prüfen → ['uid' => …, 'admin' => bool, 'kid' => …]
 * Der Besitzer schickt einen kurzlebigen, selbst unterschriebenen Schlüssel für die eigene ID (nur er hat den privaten Schlüssel).
 */
function nw_auth(string $token): array {
    if (!function_exists('sodium_crypto_sign_verify_detached')) throw new NwError('Auf dem Server fehlt die PHP-Erweiterung „sodium“.');
    if (!preg_match('/^NXK1\.([\w-]+)\.([\w-]+)$/', trim($token), $m)) throw new NwError('Kein gültiger VRC-Nexus-Freischalt-Schlüssel.');
    $sig = nw_b64u($m[2]);
    $ok = false;
    if (strlen($sig) === SODIUM_CRYPTO_SIGN_BYTES) foreach (nw_trusted() as $spki) {
        $pk = substr((string)base64_decode($spki), -SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);
        try { $ok = sodium_crypto_sign_verify_detached($sig, $m[1], $pk); } catch (Throwable $e) { $ok = false; }
        if ($ok) break;
    }
    if (!$ok) throw new NwError('Unterschrift des Schlüssels ungültig.');
    $p = json_decode(nw_b64u($m[1]), true);
    if (!is_array($p) || !preg_match(NW_UID, (string)($p['uid'] ?? ''))) throw new NwError('Schlüssel beschädigt.');
    if (!empty($p['exp']) && (int)$p['exp'] < time()) throw new NwError('Der Schlüssel ist abgelaufen.');
    $kid = (string)($p['id'] ?? '');
    if ($kid !== '' && in_array($kid, nw_revoked(), true)) throw new NwError('Der Schlüssel wurde gesperrt.');
    $uid = strtolower((string)$p['uid']);
    $f = is_array($p['f'] ?? null) ? $p['f'] : [];
    // Admin = Besitzerin oder Schlüssel mit dem Recht „mod“ (Moderator) (vergibt nur die Besitzerin im Admin-Bereich der App)
    return ['uid' => $uid, 'admin' => nw_is_owner($uid) || in_array('mod', $f, true), 'owner' => nw_is_owner($uid), 'kid' => $kid];
}

function nw_log(string $actor, string $action, string $target = '', string $detail = ''): void {
    nw_db()->prepare('INSERT INTO warn_log (ts, actor, action, target, detail) VALUES (?, ?, ?, ?, ?)')->execute([time(), $actor, $action, $target, nw_cut($detail, 300)]);
}
function nw_cleanup(): void {
    $db = nw_db();
    $db->prepare('DELETE FROM warn_reports WHERE expires < ?')->execute([time()]);
    $db->prepare('DELETE FROM warn_log WHERE ts < ?')->execute([time() - 400 * 86400]);
}

/** sichtbare Warnungen (ab N Meldern) – ohne Melder und ohne Beweise */
function nw_list(string $me = ''): array {
    $rows = nw_db()->query('SELECT target, target_name, reporter, reason, created, updated FROM warn_reports WHERE expires >= ' . time() . ' ORDER BY updated DESC')->fetchAll(PDO::FETCH_ASSOC);
    $by = [];
    foreach ($rows as $r) {
        $t = $r['target'];
        $e = $by[$t] ?? ['id' => $t, 'name' => '', 'count' => 0, 'reasons' => [], 'first' => PHP_INT_MAX, 'last' => 0, 'mine' => false];
        if ($e['name'] === '' && $r['target_name'] !== '') $e['name'] = $r['target_name'];
        $e['count']++;
        $e['reasons'][$r['reason']] = ($e['reasons'][$r['reason']] ?? 0) + 1;
        $e['first'] = min($e['first'], (int)$r['created']);
        $e['last'] = max($e['last'], (int)$r['updated']);
        if ($me !== '' && $r['reporter'] === $me) $e['mine'] = true;
        $by[$t] = $e;
    }
    $min = nw_min();
    $out = [];
    foreach ($by as $e) {
        if ($e['count'] < $min) continue;
        arsort($e['reasons']);
        $e['reason'] = (string)array_key_first($e['reasons']);
        $out[] = $e;
    }
    return $out;
}
function nw_mine(string $me): array {
    $st = nw_db()->prepare('SELECT target, target_name, reason, note, evidence, created, expires FROM warn_reports WHERE reporter = ? AND expires >= ? ORDER BY updated DESC');
    $st->execute([$me, time()]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function nw_report(array $who, array $in): array {
    $target = strtolower(trim((string)($in['target'] ?? '')));
    if (!preg_match(NW_UID, $target)) throw new NwError('Ungültige VRChat-User-ID.');
    if ($target === $who['uid']) throw new NwError('Dich selbst kannst du nicht melden.');
    $reason = (string)($in['reason'] ?? '');
    if (!isset(NW_REASONS[$reason])) throw new NwError('Unbekannter Grund.');
    $evidence = nw_cut((string)($in['evidence'] ?? ''), 1000);
    if ($evidence === '') throw new NwError('Ein Beweis ist Pflicht (Screenshot-Pfad oder Spiel-Log-Zeile).');
    $days = max(1, min(365, (int)($in['days'] ?? 182)));
    $db = nw_db();
    $n = $db->prepare('SELECT COUNT(*) FROM warn_reports WHERE reporter = ? AND created > ?');
    $n->execute([$who['uid'], time() - 86400]);
    if ((int)$n->fetchColumn() >= 30 && !$who['admin']) throw new NwError('Zu viele Meldungen heute – bitte morgen weiter.');
    $vals = [nw_cut((string)($in['name'] ?? ''), 60), nw_cut((string)($in['reporter_name'] ?? ''), 60), $reason, nw_cut((string)($in['note'] ?? ''), 500), $evidence, time(), time() + $days * 86400];
    $ex = $db->prepare('SELECT id FROM warn_reports WHERE target = ? AND reporter = ?');
    $ex->execute([$target, $who['uid']]);
    if ($id = $ex->fetchColumn()) {
        $db->prepare('UPDATE warn_reports SET target_name = ?, reporter_name = ?, reason = ?, note = ?, evidence = ?, updated = ?, expires = ? WHERE id = ?')->execute(array_merge($vals, [(int)$id]));
        nw_log($who['uid'], 'Meldung geändert', $target, $reason);
    } else {
        $db->prepare('INSERT INTO warn_reports (target_name, reporter_name, reason, note, evidence, updated, expires, target, reporter, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute(array_merge($vals, [$target, $who['uid'], time()]));
        nw_log($who['uid'], 'Gemeldet', $target, $reason);
    }
    return ['ok' => true, 'msg' => 'Meldung gespeichert.'];
}
function nw_withdraw(array $who, string $target): array {
    $st = nw_db()->prepare('DELETE FROM warn_reports WHERE target = ? AND reporter = ?');
    $st->execute([strtolower($target), $who['uid']]);
    if ($st->rowCount()) nw_log($who['uid'], 'Meldung zurückgezogen', strtolower($target));
    return ['ok' => true, 'msg' => 'Meldung zurückgezogen.'];
}
function nw_appeal(array $who, string $text): array {
    $text = nw_cut($text, 1000);
    if (mb_strlen($text) < 10) throw new NwError('Bitte kurz begründen (mindestens 10 Zeichen).');
    $db = nw_db();
    $st = $db->prepare("SELECT COUNT(*) FROM warn_appeals WHERE target = ? AND status = 'open'");
    $st->execute([$who['uid']]);
    if ((int)$st->fetchColumn() > 0) throw new NwError('Es gibt schon einen offenen Einspruch von dir.');
    $db->prepare("INSERT INTO warn_appeals (target, text, created, status, answer, closed) VALUES (?, ?, ?, 'open', '', 0)")->execute([$who['uid'], $text, time()]);
    nw_log($who['uid'], 'Einspruch eingelegt', $who['uid']);
    return ['ok' => true, 'msg' => 'Einspruch eingereicht – der Admin sieht ihn sich an.'];
}
/** Status für die eigene ID (bin ich gelistet? offener Einspruch?) */
function nw_me(array $who): array {
    $db = nw_db();
    $st = $db->prepare('SELECT COUNT(*) FROM warn_reports WHERE target = ? AND expires >= ?');
    $st->execute([$who['uid'], time()]);
    $n = (int)$st->fetchColumn();
    $a = $db->prepare('SELECT status, answer, created FROM warn_appeals WHERE target = ? ORDER BY id DESC LIMIT 1');
    $a->execute([$who['uid']]);
    return ['listed' => $n >= nw_min(), 'appeal' => $a->fetch(PDO::FETCH_ASSOC) ?: null];
}

// ---------- Admin ----------
function nw_admin_data(): array {
    $db = nw_db();
    return [
        'min' => nw_min(), 'revoked' => nw_revoked(), 'reasons' => NW_REASONS,
        'reports' => $db->query('SELECT * FROM warn_reports ORDER BY target, updated DESC')->fetchAll(PDO::FETCH_ASSOC),
        'appeals' => $db->query('SELECT * FROM warn_appeals ORDER BY (status = \'open\') DESC, id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC),
        'log' => $db->query('SELECT * FROM warn_log ORDER BY id DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC),
    ];
}
function nw_admin(string $actor, string $a, array $in): array {
    $db = nw_db();
    switch ($a) {
        case 'admin_list': return nw_admin_data();
        case 'admin_update': {
            $id = (int)($in['id'] ?? 0);
            $reason = (string)($in['reason'] ?? '');
            if (!isset(NW_REASONS[$reason])) throw new NwError('Unbekannter Grund.');
            $days = (int)($in['days'] ?? 0);
            $db->prepare('UPDATE warn_reports SET reason = ?, note = ?, evidence = ?, target_name = ?, updated = ?' . ($days > 0 ? ', expires = ' . (time() + min(365, $days) * 86400) : '') . ' WHERE id = ?')
                ->execute([$reason, nw_cut((string)($in['note'] ?? ''), 500), nw_cut((string)($in['evidence'] ?? ''), 1000), nw_cut((string)($in['name'] ?? ''), 60), time(), $id]);
            nw_log($actor, 'Admin: Meldung bearbeitet', '', '#' . $id);
            return ['ok' => true, 'msg' => 'Gespeichert.'];
        }
        case 'admin_delete': {
            $id = (int)($in['id'] ?? 0);
            $t = $db->prepare('SELECT target FROM warn_reports WHERE id = ?'); $t->execute([$id]);
            $db->prepare('DELETE FROM warn_reports WHERE id = ?')->execute([$id]);
            nw_log($actor, 'Admin: Meldung gelöscht', (string)$t->fetchColumn(), '#' . $id);
            return ['ok' => true, 'msg' => 'Meldung gelöscht.'];
        }
        case 'admin_clear': {
            $target = strtolower((string)($in['target'] ?? ''));
            if (!preg_match(NW_UID, $target)) throw new NwError('Ungültige VRChat-User-ID.');
            $db->prepare('DELETE FROM warn_reports WHERE target = ?')->execute([$target]);
            nw_log($actor, 'Admin: alle Meldungen gelöscht', $target);
            return ['ok' => true, 'msg' => 'Alle Meldungen zu dieser Person gelöscht.'];
        }
        case 'admin_appeal': {
            $id = (int)($in['id'] ?? 0);
            $accept = !empty($in['accept']);
            $t = $db->prepare('SELECT target FROM warn_appeals WHERE id = ?'); $t->execute([$id]);
            $target = (string)$t->fetchColumn();
            if ($target === '') throw new NwError('Einspruch nicht gefunden.');
            $db->prepare('UPDATE warn_appeals SET status = ?, answer = ?, closed = ? WHERE id = ?')->execute([$accept ? 'accepted' : 'rejected', nw_cut((string)($in['answer'] ?? ''), 500), time(), $id]);
            if ($accept) $db->prepare('DELETE FROM warn_reports WHERE target = ?')->execute([$target]);
            nw_log($actor, $accept ? 'Admin: Einspruch angenommen (Meldungen gelöscht)' : 'Admin: Einspruch abgelehnt', $target, '#' . $id);
            return ['ok' => true, 'msg' => $accept ? 'Einspruch angenommen – Meldungen gelöscht.' : 'Einspruch abgelehnt.'];
        }
        case 'admin_settings': {
            if (isset($in['min'])) set_setting('nexuswarn_min', (string)max(1, min(10, (int)$in['min'])));
            if (isset($in['revoked'])) {
                $ids = array_map(fn($x) => preg_replace('/[^0-9a-f]/', '', strtolower(trim($x))), explode(',', (string)$in['revoked']));
                set_setting('nexuswarn_revoked', implode(',', array_slice(array_values(array_unique(array_filter($ids))), 0, 500)));
            }
            nw_log($actor, 'Admin: Einstellungen', '', 'min=' . nw_min());
            return ['ok' => true, 'msg' => 'Gespeichert.', 'min' => nw_min(), 'revoked' => nw_revoked()];
        }
    }
    throw new NwError('Unbekannte Aktion.');
}
