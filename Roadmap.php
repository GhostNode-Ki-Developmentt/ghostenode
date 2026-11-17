<?php
declare(strict_types=1);

/**
 * Nexus 3.x Roadmap – GhostNode KI Development
 * Läuft innerhalb des bestehenden GhostNode-Webprojekts.
 * Zusätzlich mit dem angefragten SHA-512-Passwortschutz.
 */
require_once __DIR__ . '/inc/core/common.php';
auth_require_setup();

const ROADMAP_PASSWORD_SHA512 = '8af0d7279882a570a1f446fc368d40db612ad550c8a9b88f5ef145679462f8b88b730e531e7453063cb1c74fea4e7d6af1c0ac6b375a85b6e18ce9d7fb9e573a';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['roadmap_csrf'])) {
    $_SESSION['roadmap_csrf'] = bin2hex(random_bytes(32));
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['roadmap_login'])) {
    if (!hash_equals($_SESSION['roadmap_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Ungültige Anfrage. Bitte lade die Seite neu.';
    } elseif (!empty($_SESSION['roadmap_lock_until']) && time() < (int)$_SESSION['roadmap_lock_until']) {
        $error = 'Zu viele Fehlversuche. Bitte später erneut versuchen.';
    } elseif (hash_equals(ROADMAP_PASSWORD_SHA512, hash('sha512', (string)($_POST['password'] ?? '')))) {
        session_regenerate_id(true);
        $_SESSION['roadmap_authenticated'] = true;
        $_SESSION['roadmap_failures'] = 0;
        $_SESSION['roadmap_lock_until'] = 0;
        $_SESSION['roadmap_csrf'] = bin2hex(random_bytes(32));
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    } else {
        $_SESSION['roadmap_failures'] = (int)($_SESSION['roadmap_failures'] ?? 0) + 1;
        if ($_SESSION['roadmap_failures'] >= 5) {
            $_SESSION['roadmap_failures'] = 0;
            $_SESSION['roadmap_lock_until'] = time() + 900;
        }
        $error = 'Passwort ist nicht korrekt.';
    }
}
if (isset($_POST['roadmap_logout']) && hash_equals($_SESSION['roadmap_csrf'], (string)($_POST['csrf'] ?? ''))) {
    unset($_SESSION['roadmap_authenticated']);
    session_regenerate_id(true);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
if (empty($_SESSION['roadmap_authenticated'])):
?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nexus Roadmap – Geschützt</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:#080c14;color:#edf4ff;font:15px system-ui}.box{width:min(100%,420px);padding:30px;border:1px solid #2d4662;border-radius:20px;background:#111b29;box-shadow:0 22px 70px #0008}.eyebrow{color:#70e0dc;font-size:11px;letter-spacing:.15em;font-weight:800}.box h1{margin:10px 0}.box p{color:#aebed1}input{width:100%;padding:12px;border:1px solid #34465e;border-radius:9px;background:#0b1420;color:white;font:inherit}button{width:100%;margin-top:14px;padding:12px;border:0;border-radius:9px;background:#55b9ff;color:#06111c;font:inherit;font-weight:800;cursor:pointer}.error{color:#ff9a9a;margin-top:12px}
</style></head><body><form class="box" method="post"><div class="eyebrow">GHOSTNODE KI DEVELOPMENT</div><h1>Nexus 3.x Roadmap</h1><p>Dieser Bereich ist zusätzlich passwortgeschützt.</p><label for="password">Passwort</label><input id="password" name="password" type="password" autocomplete="current-password" required autofocus><input type="hidden" name="csrf" value="<?= e($_SESSION['roadmap_csrf']) ?>"><button name="roadmap_login" value="1">Anmelden</button><?php if ($error !== ''): ?><div class="error"><?= e($error) ?></div><?php endif; ?></form></body></html>
<?php
exit;
endif;

$modules = [
'Nexus Core & Stabilität'=>['Fehler seit Version 2.5.0 beheben','Codebasis überarbeiten','Performance-Optimierungen','Fehlerbehandlung verbessern','Zentrale Konfiguration und Einstellungen','Protokollierung verbessern','Logout mit vollständiger Session-Bereinigung','Automatisierte UI-Tests mit Playwright','CI- und Regressionstests','Englische Dokumentation','Changelog','Bestehende Änderungen vor Implementierung prüfen'],
'Heartbeat & Tracking'=>['Pulsoid-Integration','HypeRate-Integration','OSC-Heartbeat','SteamVR-HUD','Heartbeat auf der Startseite','Heartbeat-Verlaufsdiagramm','Stream-Deck-Heartbeat','Chatbox-Integration','Gemeinsame Heartbeat-Datenquelle','NSFW-Toy-Heartbeat-Integration'],
'VRChat Companion'=>['Friends Bar verbessern','Navigations-Shortcuts','Home-Shortcuts','Spielerinformationen erweitern','VRChat-Status anzeigen','Weltinformationen anzeigen','Crasher-Warnliste','Ripper-Warnliste','Troll-Warnliste','Lokale Nutzernotizen','Community-bestätigte Meldungen','VRChat-API-Integration verbessern'],
'GhostNode Community'=>['Community-Profile','Zuordnung über VRChat User ID','Profilbilder und Banner','Profilbeschreibungen','Interessen','Pronomen','Sprachen','Persönliche Links','Pride-Profilthemen','Optionaler 18+-Profilbereich','Privatsphäre-Einstellungen','VRChat-Freundesliste als Kontaktgrundlage','Online-/Offline-Status','Benachrichtigungen','Nutzer blockieren und melden'],
'Community-Gruppen'=>['VRChat-Gruppenintegration','Zuordnung über VRChat Group ID','Gruppenchats','Gruppenrollen','Gruppenberechtigungen','Gruppenankündigungen','Gruppeninformationen','Mitgliederverwaltung'],
'Nexus Messenger'=>['Private Direktnachrichten','Gruppennachrichten','Nachrichtenverlauf','Bilder und Medien teilen','GIF-Unterstützung','Nachrichtenreaktionen','Nachrichtenfavoriten','Angeheftete Nachrichten','Desktop-Benachrichtigungen','Android-Benachrichtigungen','Offline-Nachrichten zustellen','Nachrichten an VRChat-Nutzer ohne Nexus senden','Nachrichten nach späterer Nexus-Registrierung abrufen','Boob-Funktion im Chat','Heartbeat-Integration im Chat','Signal Protocol für Einzelchats','Geräte-Schlüsselverwaltung','Multi-Device-Unterstützung','Sicherheitsnummern zur Identitätsprüfung','Matrix für Gruppenkommunikation','Megolm-Gruppenverschlüsselung','Gruppenschlüssel bei Mitgliederänderungen erneuern'],
'Nexus Voice'=>['Private Sprachgespräche','Gruppen-Sprachchats','Voice-Räume für VRChat-Gruppen','Opus-Audiocodec','WebRTC','SFU-Server','Mikrofonsteuerung','Push-to-Talk','Individuelle Lautstärkeregelung','Geräuschunterdrückung','Echo-Unterdrückung','Aktiven Sprecher anzeigen','Verschlüsselte Sprachübertragung'],
'Nexus Permission & Key System'=>['NXK1-Lizenzformat','Public Key im Nexus-Client','Private Key bleibt beim Herausgeber','Signierte Lizenz-Keys','Bindung an VRChat User ID','Identitätsprüfung über authentifizierte VRChat-API','Ausstellungs- und Ablaufdatum','Multiaccount-Lizenztyp','Mehrere gebundene Accounts verwalten','Lizenzverwaltung','Admin-Berechtigung','NSFW-Berechtigung','Toys-Berechtigung','Remote-Berechtigung','Individuelle Funktionsrechte','Zentrale Rechteprüfung für Module','Fest hinterlegte Admin-VRChat-UID','Admin-Identität über VRChat-API prüfen','Admin-PC-Registrierung','Admin-Zugriff bei fremder Identität verweigern','Admin-Funktion auf fremdem PC bei nicht autorisierter Identität entfernen','Berechtigungsänderungen protokollieren'],
'GhostNode Online Services'=>['Zentrales GhostNode-Backend','Online-Dienste standardmäßig deaktiviert','Aktivierung nur nach Opt-in','Discord-Authentifizierung/Account-Verknüpfung','Online-Lizenzprüfung','Community-API','Profildatenbank','Gruppendatenbank','Messenger-Infrastruktur','Voice-Infrastruktur','Online-Berechtigungsverwaltung','Sitzungsverwaltung','Online-Zugriffe widerrufen'],
'Discord-Integration'=>['GhostNode Discord-Bot','Discord-Login','Discord- und Nexus-Accounts verknüpfen','Community-Ankündigungen','Benachrichtigungen','Gruppeninformationen','Discord-Community-Brücke'],
'Fediverse & ActivityPub'=>['ActivityPub','WebFinger','ActivityStreams','Föderierte Profile','Föderierte Beiträge','Antworten','Likes','Boosts','Anderen Fediverse-Nutzern folgen','Föderierte Community-News'],
'Nexus Pulse – News & Discovery'=>['Offizielle VRChat-News','Developer-Updates','Patchnotes','Creator-Economy-News','Sicherheitsmeldungen','VRChat-Events','r/VRChat','r/VRchatERP','Neueste Reddit-Beiträge','Beliebte Reddit-Beiträge','Kommentare anzeigen','Medienvorschau','Favoriten','Originalbeiträge auf Reddit öffnen','Separate 18+-Freigabe für r/VRchatERP'],
'Android & Mobile'=>['Push-Benachrichtigungen','Homescreen-Widget','Community-Benachrichtigungen','Messenger','Voice Chat','Gemeinsame Codebasis'],
'Stream Deck'=>['Stream-Deck-Funktionen verbessern','Heartbeat-Anzeige','Chatbox-Steuerung','Community-Status','Erweiterte Nexus-Aktionen'],
'Nexus Navigation & VRChat-Bereiche'=>['Dashboard','Welten','Gruppen','Personen','Avatare','Kalender','Zeitachse','Medienbibliothek','Inventar','Werkzeuge','Einstellungen','Gruppenmitglieder anzeigen','Mitglieder-Avatarbilder und Benutzernamen','Online-Status anzeigen','Region/Sprachregion anzeigen, sofern verfügbar','Gruppenmitgliederprofile öffnen','Mitgliederanzahl anzeigen','Mitgliederliste aktualisieren','VRChat-Profildekorationen anzeigen, sofern über die API verfügbar','Profilbanner und Hintergründe darstellen, sofern verfügbar','Profilrahmen und dekorative Elemente darstellen, sofern verfügbar','Profilabzeichen und Badges anzeigen','Unterstützer- und Eventdekorationen anzeigen, sofern verfügbar','Dekorationen in Personen- und Freundesansichten darstellen'],
'Design, Website & Lokalisierung'=>['Nexus-Interface überarbeiten','GhostNode-Website-Layout','Navigation verbessern','Einheitliche Benutzeroberfläche','Deutsch','Englisch','Französisch','Japanisch','Alle sichtbaren Texte lokalisieren','Einheitliche Dokumentation']
];
$principles = ['Lokale VRChat-Funktionen bleiben kostenlos.','Online-Dienste sind standardmäßig deaktiviert und benötigen Opt-in.','VRChat User ID ist Grundlage der Identitätszuordnung.','Desktop und Android bleiben Teil derselben Nexus-Anwendung.','Lokale Funktionen sollen unabhängig von GhostNode Online funktionieren.','Berechtigungen werden zentral über NXK1 verwaltet.'];
$releases = [
'3.0.0 – Core & Foundation'=>['Stabilisierung und Bugfixes','Heartbeat-Erweiterungen','UI-Tests und Lokalisierung','VRChat-Funktionen','NXK1 und Admin-Sicherheit'],
'3.1.0 – Community'=>['GhostNode-Backend','Community-Profile','VRChat-Gruppen','Freunde und Kontakte','Discord-Authentifizierung'],
'3.2.0 – Messenger'=>['Signal-Einzelchats','Matrix-Gruppenchats','Nachrichtenhistorie','Android-Benachrichtigungen','Medien und Reaktionen'],
'3.3.0 – Voice & Integrationen'=>['WebRTC und Opus','Voice-Räume','Discord-Bot','Fediverse'],
'3.4.0 – Pulse & Discovery'=>['VRChat-News','Reddit-Integration','Community-Discovery'],
'3.x+ – Further Development'=>['Weitere Mobile-Funktionen','Weitere Stream-Deck-Funktionen','Weitere Integrationen']
];
$technical = ['Lokale Funktionen bleiben kostenlos.','Online-Dienste sind standardmäßig deaktiviert.','Online-Dienste benötigen ausdrückliche Aktivierung.','VRChat-Identität ist primäre Identitätszuordnung.','Lokale Lizenzprüfung funktioniert ohne Internet.','Private Signaturschlüssel bleiben außerhalb des Clients.','Online-Berechtigungen werden serverseitig geprüft.','Keine Secrets in Git.','Backups vor produktiven Änderungen.','Modulare Architektur.','Desktop- und Android-Kompatibilität.'];
$total = array_sum(array_map('count', $modules));
?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nexus 3.x Roadmap · GhostNode</title><link rel="stylesheet" href="assets/css/hud.css">
<style>
.roadmap-wrap{max-width:1160px;margin:28px auto 65px;padding:0 20px}.roadmap-hero{padding:34px;border:1px solid #2d4662;border-radius:22px;background:linear-gradient(125deg,#17304b,#111a29 65%,#292044)}.roadmap-hero .eyebrow{color:#70e0dc;font-size:11px;letter-spacing:.16em;font-weight:800}.roadmap-hero h1{font-size:clamp(40px,7vw,66px);margin:12px 0;line-height:1}.roadmap-hero p,.rm-muted{color:#aebed1}.rm-pills{display:flex;gap:8px;flex-wrap:wrap}.rm-pill,.rm-tag{border:1px solid #ffffff24;border-radius:99px;padding:4px 10px;font-size:11px}.rm-stats,.rm-grid,.rm-releases{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin:16px 0 30px}.rm-card,.rm-module,.rm-release{border:1px solid var(--line,#34465e);border-radius:14px;background:var(--panel,#111b29);padding:15px}.rm-card strong{font-size:26px;display:block}.rm-section{margin:30px 0}.rm-section h2{margin-bottom:6px}.rm-principles,.rm-tech{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}.rm-principles div,.rm-tech div{padding:11px;border:1px solid #ffffff12;border-radius:9px}.rm-toolbar{display:flex;gap:8px;flex-wrap:wrap;margin:15px 0}.rm-toolbar input,.rm-toolbar select,.rm-toolbar button{padding:10px;border:1px solid var(--line,#34465e);border-radius:8px;background:var(--panel,#111b29);color:inherit}.rm-toolbar input{flex:1;min-width:200px}.rm-toolbar button{cursor:pointer}.rm-module{padding:0;margin:10px 0;overflow:hidden}.rm-module summary{display:flex;align-items:center;gap:12px;cursor:pointer;list-style:none;padding:15px}.rm-module summary::-webkit-details-marker{display:none}.rm-num{background:#55b9ff20;color:#55b9ff;border-radius:9px;padding:8px;font-weight:bold}.rm-title{flex:1}.rm-title small{display:block;color:#9bacc1}.rm-body{padding:14px;border-top:1px solid #ffffff12}.rm-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}.rm-feature{display:flex;gap:8px;padding:8px;border:1px solid #ffffff10;border-radius:8px;font-size:13px}.rm-feature input{accent-color:#55b9ff;margin-top:4px}.rm-feature.done span{text-decoration:line-through;opacity:.55}.rm-count{font-size:12px;color:#9bacc1}.rm-releases{grid-template-columns:repeat(2,1fr)}.rm-release h3{margin:0 0 8px}.rm-release ul{padding-left:18px;margin:0}.rm-logout{float:right}@media(max-width:700px){.roadmap-wrap{padding:0 12px}.roadmap-hero{padding:23px}.rm-stats{gap:7px}.rm-stats .rm-card{padding:10px}.rm-stats strong{font-size:21px}.rm-principles,.rm-tech,.rm-list,.rm-releases{grid-template-columns:1fr}.rm-tag{display:none}.rm-module summary{padding:12px}}
</style></head><body>
<div class="roadmap-wrap">
<form method="post" class="rm-logout"><input type="hidden" name="csrf" value="<?= e($_SESSION['roadmap_csrf']) ?>"><button name="roadmap_logout" value="1">Abmelden</button></form>
<header class="roadmap-hero"><div class="eyebrow">GHOSTNODE KI DEVELOPMENT · DEVELOPMENT ROADMAP</div><h1>NEXUS 3</h1><p>The next generation of VRChat Companion.<br>Eine eigenständige VRChat-Companion-Anwendung mit lokalen Funktionen, Community, Kommunikation und Integrationen.</p><div class="rm-pills"><span class="rm-pill">Version generation 3.x</span><span class="rm-pill">Desktop & Android</span><span class="rm-pill">GhostNode Online · Opt-in</span></div></header>
<div class="rm-stats"><div class="rm-card"><strong><?= count($modules) ?></strong><span class="rm-muted">Funktionsbereiche</span></div><div class="rm-card"><strong><?= $total ?></strong><span class="rm-muted">Feature-Punkte</span></div><div class="rm-card"><strong>3.x</strong><span class="rm-muted">Mehrere Releases</span></div></div>
<section class="rm-section"><h2>Produktprinzipien</h2><p class="rm-muted">Grundsätze für die gesamte Nexus-3-Generation.</p><div class="rm-card rm-principles"><?php foreach($principles as $p): ?><div>✓ <?= e($p) ?></div><?php endforeach; ?></div></section>
<section class="rm-section"><h2>Alle Funktionen</h2><p class="rm-muted">Alle bisher gesammelten Auflistungen. Häkchen werden lokal im Browser gespeichert.</p>
<div class="rm-toolbar"><input id="rmSearch" type="search" placeholder="Funktionen durchsuchen …"><select id="rmFilter"><option value="all">Alle Bereiche</option><?php foreach($modules as $name=>$items): ?><option value="<?= e($name) ?>"><?= e($name) ?></option><?php endforeach; ?></select><button type="button" id="rmExpand">Alle öffnen</button><button type="button" id="rmCollapse">Alle schließen</button><button type="button" id="rmReset">Fortschritt zurücksetzen</button></div>
<?php $n=1; foreach($modules as $name=>$items): ?><details class="rm-module" data-module="<?= e($name) ?>"><summary><span class="rm-num"><?= sprintf('%02d',$n++) ?></span><span class="rm-title"><strong><?= e($name) ?></strong><small><?= count($items) ?> Feature-Punkte</small></span><span class="rm-tag">NEXUS 3.x</span><span class="rm-count"></span><span>⌄</span></summary><div class="rm-body"><div class="rm-list"><?php foreach($items as $i=>$item): $key=substr(sha1($name.'|'.$item.'|'.$i),0,16); ?><label class="rm-feature"><input type="checkbox" data-key="<?= e($key) ?>"><span><?= e($item) ?></span></label><?php endforeach; ?></div></div></details><?php endforeach; ?></section>
<section class="rm-section"><h2>Vorgeschlagene Release-Aufteilung</h2><p class="rm-muted">Flexible Orientierung – nicht alle Funktionen müssen in 3.0.0 erscheinen.</p><div class="rm-releases"><?php foreach($releases as $name=>$items): ?><article class="rm-release"><h3><?= e($name) ?></h3><ul><?php foreach($items as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></article><?php endforeach; ?></div></section>
<section class="rm-section"><h2>Technische Leitlinien</h2><div class="rm-card rm-tech"><?php foreach($technical as $t): ?><div>✓ <?= e($t) ?></div><?php endforeach; ?></div></section>
</div>
<script>
(()=>{const key='ghostnode-nexus-roadmap-progress-v1';let state={};try{state=JSON.parse(localStorage.getItem(key)||'{}')}catch(e){}const boxes=[...document.querySelectorAll('.rm-feature input')];function counts(){document.querySelectorAll('.rm-module').forEach(m=>{const a=[...m.querySelectorAll('input')];m.querySelector('.rm-count').textContent=a.filter(x=>x.checked).length+'/'+a.length})}boxes.forEach(b=>{b.checked=!!state[b.dataset.key];b.closest('.rm-feature').classList.toggle('done',b.checked);b.addEventListener('change',()=>{state[b.dataset.key]=b.checked;localStorage.setItem(key,JSON.stringify(state));b.closest('.rm-feature').classList.toggle('done',b.checked);counts()})});counts();document.getElementById('rmExpand').onclick=()=>document.querySelectorAll('.rm-module').forEach(x=>x.open=true);document.getElementById('rmCollapse').onclick=()=>document.querySelectorAll('.rm-module').forEach(x=>x.open=false);document.getElementById('rmReset').onclick=()=>{if(!confirm('Alle Roadmap-Häkchen zurücksetzen?'))return;state={};boxes.forEach(b=>{b.checked=false;b.closest('.rm-feature').classList.remove('done')});localStorage.removeItem(key);counts()};function filter(){const q=document.getElementById('rmSearch').value.toLocaleLowerCase('de'),v=document.getElementById('rmFilter').value;document.querySelectorAll('.rm-module').forEach(m=>{const visible=(v==='all'||m.dataset.module===v)&&m.textContent.toLocaleLowerCase('de').includes(q);m.style.display=visible?'':'none';if(q&&visible)m.open=true})}document.getElementById('rmSearch').addEventListener('input',filter);document.getElementById('rmFilter').addEventListener('change',filter)})();
</script></body></html>
