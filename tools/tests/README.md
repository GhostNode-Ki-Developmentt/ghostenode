# Tests für die Nexus-Schnittstellen (nexusacct.php, nexuscomm.php)

Nur mit Testdaten – kein echtes VRChat-Konto. Ablauf (Node 22 + PHP 8 mit sodium und pdo_sqlite):

```bash
# 1. VRChat-Mock aus dem App-Repo (ghostnode-labs/vrc_nexus): apps/vrc-nexus/tests/ui/mock-vrchat.js
node mock-vrchat.js 8792 &
# 2. Testkopie der Webseite einrichten (Dienst-Konto zeigt auf den Mock) – NIE auf dem echten Server ausführen
php tools/tests/nexus_test_setup.php
# 3. Test-Schlüssel erzeugen und Server mit zusätzlichem Test-Schlüssel starten
node tools/tests/nexus_test_keys.js > /tmp/nexus-keys.json
NEXUS_WARN_TEST_PUBKEY=$(node -e "console.log(require('/tmp/nexus-keys.json').pub)") php -S 127.0.0.1:8902 &
# 4. Test (erwartet keys.json im angegebenen Ordner)
cp /tmp/nexus-keys.json /tmp/keys.json && node tools/tests/nexus_community_test.js /tmp
```
