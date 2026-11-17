<?php
declare(strict_types=1);

/**
 * Nexus 3.x / 4.x Roadmap – GhostNode KI Development
 * Läuft innerhalb des bestehenden GhostNode-Webprojekts.
 * Zusätzlich mit dem angefragten SHA-512-Passwortschutz.
 */
require_once __DIR__ . '/inc/core/common.php';
require_once __DIR__ . '/inc/core/pages.php'; // Lädt auch das bestehende Auth-System der Website.
auth_require_setup();

const ROADMAP_PASSWORD_SHA512 = '8af0d7279882a570a1f446fc368d40db612ad550c8a9b88f5ef145679462f8b88b730e531e7453063cb1c74fea4e7d6af1c0ac6b375a85b6e18ce9d7fb9e573a';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['roadmap_csrf'])) {
    $_SESSION['roadmap_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$edit_requested = isset($_GET['edit']) && $_GET['edit'] === '1';

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
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?edit=1');
        exit;
    } else {
        $_SESSION['roadmap_failures'] = (int)($_SESSION['roadmap_failures'] ?? 0) + 1;
        if ($_SESSION['roadmap_failures'] >= 5) {
            $_SESSION['roadmap_failures'] = 0;
            $_SESSION['roadmap_lock_until'] = time() + 900;
        }
        $error = 'Passwort ist nicht korrekt.';
        $edit_requested = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['roadmap_logout'])) {
    if (hash_equals($_SESSION['roadmap_csrf'], (string)($_POST['csrf'] ?? ''))) {
        unset($_SESSION['roadmap_authenticated']);
        session_regenerate_id(true);
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$edit_mode = $edit_requested && !empty($_SESSION['roadmap_authenticated']);
$show_edit_login = $edit_requested && !$edit_mode;

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
'3.x+ – Further Development'=>['Weitere Mobile-Funktionen','Weitere Stream-Deck-Funktionen','Weitere Integrationen'],
'4.0.0 – UI & Navigation'=>['Neues Tab-System für die Startseite: Funktionen thematisch gruppieren und die bisherige Kachelansicht übersichtlicher machen','Tabs für Übersicht, Medien, Verknüpfungen, Gruppe, Systemwerkzeuge, Marktplatz und Sonstiges','Aktiven Tab optisch hervorheben und optional den zuletzt geöffneten Tab merken','Startseiten-Kacheln modernisieren: einheitliche Größen, Abstände, Icons und Beschriftungen','Responsive Darstellung für unterschiedliche Fenstergrößen und Android'],
'4.1.0 – Settings & Persistence'=>['Checkboxen und Toggles im Nexus-Design überarbeiten: klare Zustände, bessere Kontraste und dezente Animationen','Einheitliche Checkbox-Komponenten für die gesamte Anwendung','UI-Auswahlzustände und persönliche Oberflächenoptionen in einer versionierten JSON-Konfiguration speichern','JSON-Konfiguration für Tabs, sichtbare Bereiche und ausgewählte Kacheloptionen verwenden','Import und Export der Oberflächenkonfiguration','Konfigurationsversion und Migration älterer Einstellungen sicherstellen']
];
$technical = ['Lokale Funktionen bleiben kostenlos.','Online-Dienste sind standardmäßig deaktiviert.','Online-Dienste benötigen ausdrückliche Aktivierung.','VRChat-Identität ist primäre Identitätszuordnung.','Lokale Lizenzprüfung funktioniert ohne Internet.','Private Signaturschlüssel bleiben außerhalb des Clients.','Online-Berechtigungen werden serverseitig geprüft.','Keine Secrets in Git.','Backups vor produktiven Änderungen.','Modulare Architektur.','Desktop- und Android-Kompatibilität.'];
$total = array_sum(array_map('count', $modules));
?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nexus 3.x / 4.x Roadmap · GhostNode</title><link rel="stylesheet" href="assets/css/hud.css"><link rel="stylesheet" href="assets/css/index.css"><link rel="stylesheet" href="assets/css/format.css"><link rel="stylesheet" href="assets/css/widgets.css">
<style>
.roadmap-wrap{width:100%;max-width:980px;margin:0 auto;padding:0;position:relative;z-index:2}
.roadmap-hero{padding:24px 0 18px;margin:24px 0 18px;border-bottom:2px solid var(--border-color);background:transparent}
.roadmap-hero .eyebrow{display:flex;align-items:center;gap:8px;color:var(--accent-cyan);font-size:.7rem;letter-spacing:3px;font-weight:700;text-transform:uppercase}
.roadmap-hero .eyebrow:before{content:"";width:6px;height:6px;background:var(--accent-cyan);box-shadow:0 0 10px var(--accent-cyan)}
.roadmap-hero h1{font-size:2.4rem;letter-spacing:5px;margin:10px 0}
.roadmap-hero p,.rm-muted{color:var(--text-muted);line-height:1.55}
.rm-pills{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
.rm-pill,.rm-tag{display:inline-block;padding:3px 8px;border:1px solid var(--border-color);color:var(--accent-cyan);font-size:.65rem;letter-spacing:1px;text-transform:uppercase}
.rm-stats,.rm-releases{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:16px 0 30px}
.rm-card,.rm-module,.rm-release{background:var(--panel-bg);border:1px solid var(--border-color);clip-path:var(--cut-sm);backdrop-filter:blur(8px)}
.rm-card{padding:16px}
.rm-card strong{display:block;color:var(--accent-gold);font-size:1.65rem}
.rm-section{margin:30px 0}
.rm-section h2{color:var(--accent-cyan);font-size:.9rem;letter-spacing:2px;text-transform:uppercase;border-bottom:1px solid var(--border-color);padding-bottom:8px;margin-bottom:8px}
.rm-principles,.rm-tech{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
.rm-principles div,.rm-tech div{padding:10px;border:1px solid rgba(56,213,230,.14);background:rgba(56,213,230,.035);font-size:.82rem}
.rm-toolbar{display:flex;gap:8px;flex-wrap:wrap;margin:15px 0}
.rm-toolbar input,.rm-toolbar select{width:auto;min-width:0;flex:1 1 180px}
.rm-toolbar button{font-family:inherit}
.rm-module{padding:0;margin:10px 0;overflow:hidden}
.rm-module summary{display:flex;align-items:center;gap:12px;cursor:pointer;list-style:none;padding:14px}
.rm-module summary::-webkit-details-marker{display:none}
.rm-module summary:hover{background:var(--panel-hover)}
.rm-num{background:rgba(56,213,230,.1);border:1px solid var(--border-color);color:var(--accent-cyan);padding:7px 9px;font-weight:800}
.rm-title{flex:1;min-width:0}
.rm-title strong{color:#fff;letter-spacing:.4px}
.rm-title small{display:block;color:var(--text-muted);font-size:.68rem;margin-top:3px}
.rm-body{padding:14px;border-top:1px solid var(--border-color)}
.rm-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}
.rm-feature{display:flex;align-items:flex-start;gap:8px;padding:8px;border:1px solid rgba(56,213,230,.12);background:rgba(4,6,9,.35);font-size:.78rem;line-height:1.4;cursor:pointer}
.rm-feature input{flex:0 0 auto;margin-top:3px}
.rm-feature.done span{text-decoration:line-through;opacity:.55}
.rm-count{font-size:.7rem;color:var(--text-muted);white-space:nowrap}
.rm-releases{grid-template-columns:repeat(2,minmax(0,1fr))}
.rm-release{padding:15px}
.rm-release h3{margin:0 0 9px;color:var(--accent-gold);font-size:.85rem;letter-spacing:1px}
.rm-release ul{padding-left:18px;margin:0;color:var(--text-muted);font-size:.8rem;line-height:1.7}
.rm-logout{display:flex;justify-content:flex-end;margin:12px 0}
.rm-edit-login{margin:12px 0 22px;padding:16px;border:1px solid var(--accent-gold);background:rgba(243,194,98,.06);clip-path:var(--cut-sm)}
.rm-edit-login strong{display:block;color:var(--accent-gold);letter-spacing:1px;text-transform:uppercase;font-size:.8rem}
.rm-edit-login p{margin:7px 0}
.rm-edit-login label{display:block;margin:10px 0 5px;color:var(--text-muted);font-size:.72rem;text-transform:uppercase}
.rm-edit-login input[type=password]{max-width:420px}
.rm-login-error{color:var(--accent-red);font-size:.82rem}
.rm-logout button{width:auto}
@media(max-width:700px){.roadmap-wrap{padding:0}.roadmap-hero h1{font-size:1.8rem;letter-spacing:3px}.rm-stats{gap:7px}.rm-stats .rm-card{padding:10px}.rm-stats strong{font-size:1.3rem}.rm-principles,.rm-tech,.rm-list,.rm-releases{grid-template-columns:1fr}.rm-tag{display:none}.rm-module summary{padding:11px}.rm-toolbar input,.rm-toolbar select{flex-basis:100%}}</style></head><body>
<div class="wrapper idx roadmap-wrap">
<div class="rm-logout">
<?php if ($edit_mode): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['roadmap_csrf']) ?>"><button class="btn btn-sm btn-danger" name="roadmap_logout" value="1">Bearbeiten beenden</button></form>
<?php else: ?><a class="btn btn-sm" href="?edit=1">✎ Bearbeiten</a><?php endif; ?>
</div>
<?php if ($show_edit_login): ?><form class="rm-edit-login" method="post"><strong>Bearbeitungsmodus freischalten</strong><p class="rm-muted">Die Roadmap bleibt öffentlich lesbar. Nur Änderungen benötigen das Passwort.</p><label for="roadmapPassword">Passwort</label><input id="roadmapPassword" name="password" type="password" autocomplete="current-password" required autofocus><input type="hidden" name="csrf" value="<?= e($_SESSION['roadmap_csrf']) ?>"><button class="btn btn-sm btn-gold" name="roadmap_login" value="1">Bearbeiten freischalten</button><?php if ($error !== ''): ?><p class="rm-login-error"><?= e($error) ?></p><?php endif; ?></form><?php endif; ?>
<header class="roadmap-hero"><div class="eyebrow">GHOSTNODE KI DEVELOPMENT · DEVELOPMENT ROADMAP</div><h1>NEXUS 3 / 4</h1><p>The next generation of VRChat Companion.<br>Eine eigenständige VRChat-Companion-Anwendung mit lokalen Funktionen, Community, Kommunikation und Integrationen.</p><div class="rm-pills"><span class="rm-pill">Version generation 3.x / 4.x</span><span class="rm-pill">Desktop & Android</span><span class="rm-pill">GhostNode Online · Opt-in</span></div></header>
<div class="rm-stats"><div class="rm-card"><strong><?= count($modules) ?></strong><span class="rm-muted">Funktionsbereiche</span></div><div class="rm-card"><strong><?= $total ?></strong><span class="rm-muted">Feature-Punkte</span></div><div class="rm-card"><strong>3.x / 4.x</strong><span class="rm-muted">Mehrere Release-Generationen</span></div></div>
<section class="rm-section"><h2>Produktprinzipien</h2><p class="rm-muted">Grundsätze für die gesamte Nexus-3-Generation.</p><div class="rm-card rm-principles"><?php foreach($principles as $p): ?><div>✓ <?= e($p) ?></div><?php endforeach; ?></div></section>
<section class="rm-section"><h2>Alle Funktionen</h2><p class="rm-muted">Alle bisher gesammelten Auflistungen. <?php echo $edit_mode ? "Bearbeitungsmodus aktiv – Fortschritt wird lokal im Browser gespeichert." : "Öffentliche Ansicht – zum Ändern bitte den Bearbeitungsmodus freischalten."; ?></p>
<div class="rm-toolbar"><input id="rmSearch" type="search" placeholder="Funktionen durchsuchen …"><select id="rmFilter"><option value="all">Alle Bereiche</option><?php foreach($modules as $name=>$items): ?><option value="<?= e($name) ?>"><?= e($name) ?></option><?php endforeach; ?></select><button type="button" id="rmExpand">Alle öffnen</button><button type="button" id="rmCollapse">Alle schließen</button><?php if ($edit_mode): ?><button type="button" id="rmReset">Fortschritt zurücksetzen</button><?php endif; ?></div>
<?php $n=1; foreach($modules as $name=>$items): ?><details class="rm-module" data-module="<?= e($name) ?>"><summary><span class="rm-num"><?= sprintf('%02d',$n++) ?></span><span class="rm-title"><strong><?= e($name) ?></strong><small><?= count($items) ?> Feature-Punkte</small></span><span class="rm-tag">NEXUS 3.x</span><span class="rm-count"></span><span>⌄</span></summary><div class="rm-body"><div class="rm-list"><?php foreach($items as $i=>$item): $key=substr(sha1($name.'|'.$item.'|'.$i),0,16); ?><label class="rm-feature"><input type="checkbox" data-key="<?= e($key) ?>" <?= $edit_mode ? "" : "disabled" ?>><span><?= e($item) ?></span></label><?php endforeach; ?></div></div></details><?php endforeach; ?></section>
<section class="rm-section"><h2>Vorgeschlagene Release-Aufteilung</h2><p class="rm-muted">Flexible Orientierung – nicht alle Funktionen müssen in 3.0.0 erscheinen.</p><div class="rm-releases"><?php foreach($releases as $name=>$items): ?><article class="rm-release"><h3><?= e($name) ?></h3><ul><?php foreach($items as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></article><?php endforeach; ?></div></section>
<section class="rm-section"><h2>Technische Leitlinien</h2><div class="rm-card rm-tech"><?php foreach($technical as $t): ?><div>✓ <?= e($t) ?></div><?php endforeach; ?></div></section>
</div>
<script>
(()=>{const key='ghostnode-nexus-roadmap-progress-v1';let state={};try{state=JSON.parse(localStorage.getItem(key)||'{}')}catch(e){}const boxes=[...document.querySelectorAll('.rm-feature input')];function counts(){document.querySelectorAll('.rm-module').forEach(m=>{const a=[...m.querySelectorAll('input')];m.querySelector('.rm-count').textContent=a.filter(x=>x.checked).length+'/'+a.length})}boxes.forEach(b=>{b.checked=!!state[b.dataset.key];b.closest('.rm-feature').classList.toggle('done',b.checked);if(!b.disabled)b.addEventListener('change',()=>{state[b.dataset.key]=b.checked;localStorage.setItem(key,JSON.stringify(state));b.closest('.rm-feature').classList.toggle('done',b.checked);counts()})});counts();document.getElementById('rmExpand').onclick=()=>document.querySelectorAll('.rm-module').forEach(x=>x.open=true);document.getElementById('rmCollapse').onclick=()=>document.querySelectorAll('.rm-module').forEach(x=>x.open=false);const resetButton=document.getElementById('rmReset');if(resetButton)resetButton.onclick=()=>{if(!confirm('Alle Roadmap-Häkchen zurücksetzen?'))return;state={};boxes.forEach(b=>{b.checked=false;b.closest('.rm-feature').classList.remove('done')});localStorage.removeItem(key);counts()};function filter(){const q=document.getElementById('rmSearch').value.toLocaleLowerCase('de'),v=document.getElementById('rmFilter').value;document.querySelectorAll('.rm-module').forEach(m=>{const visible=(v==='all'||m.dataset.module===v)&&m.textContent.toLocaleLowerCase('de').includes(q);m.style.display=visible?'':'none';if(q&&visible)m.open=true})}document.getElementById('rmSearch').addEventListener('input',filter);document.getElementById('rmFilter').addEventListener('change',filter)})();
</script></body></html>