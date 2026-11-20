<?php
require __DIR__ . '/../../inc/core/pages.php';
require __DIR__ . '/../../inc/modules/nexusacct.php';
set_setting('vrc_api_base', 'http://127.0.0.1:8792/api/1');
site_db()->exec("INSERT OR IGNORE INTO roles (id, name, is_system) VALUES (1, 'Administrator', 1)");
site_db()->exec("INSERT OR IGNORE INTO users (id, username, password_hash, role_id) VALUES (1, 'svc', 'x', 1)");
vrc_save_account(1, ['id' => 'usr_00000000-0000-4000-8000-000000000001', 'displayName' => 'Service'], ['auth' => 'mock']);
// kein Dienst-Konto wählen: die Webseite nimmt automatisch das VRChat-Konto eines Administrators
set_setting('nexus_service_uid', '0');

echo "ok\n";
