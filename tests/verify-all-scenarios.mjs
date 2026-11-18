import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';

const pluginRoot = path.resolve(import.meta.dirname, '..');

const results = [];
function test(name, fn) {
  const detail = fn();
  results.push({ name, passed: true, detail: detail || 'OK' });
}

test('1. Plugin-Struktur, Bootstrap, 10 Custom Tables & JS-Syntax', () => {
  const requiredFiles = [
    'social-media-hub.php',
    'uninstall.php',
    'includes/Core/Plugin.php',
    'includes/Core/Activator.php',
    'includes/Core/EventBus.php',
    'includes/Infrastructure/Database/Schema.php',
    'includes/Infrastructure/Database/Repositories.php',
    'includes/Infrastructure/Security/TokenSafe.php',
    'includes/Infrastructure/Security/Capabilities.php',
    'includes/Infrastructure/Security/SignedMediaEndpoint.php',
    'includes/Infrastructure/Queue/PublishQueueWorker.php',
    'includes/Infrastructure/Export/BackupExporter.php',
    'includes/Providers/ProviderInterface.php',
    'includes/Providers/ProviderRegistry.php',
    'includes/Providers/ConfigurablePlatformProvider.php',
    'includes/Domain/PreflightService.php',
    'includes/Domain/PublicationService.php',
    'includes/Rest/RestController.php',
    'includes/Admin/AdminPage.php',
    'assets/css/admin-app.css',
    'assets/js/admin-app.js',
  ];
  for (const rel of requiredFiles) {
    assert.ok(fs.existsSync(path.join(pluginRoot, rel)), `Pflichtdatei fehlt: ${rel}`);
  }
  const schemaPhp = fs.readFileSync(path.join(pluginRoot, 'includes/Infrastructure/Database/Schema.php'), 'utf8');
  for (const tbl of ['smh_brands','smh_accounts','smh_media_assets','smh_snippets','smh_post_groups','smh_variants','smh_revisions','smh_jobs','smh_receipts','smh_audit_events']) {
    assert.ok(schemaPhp.includes(`CREATE TABLE {$` + `prefix}${tbl}`), `Tabellendefinition fehlt: ${tbl}`);
  }
  execFileSync(process.execPath, ['--check', path.join(pluginRoot, 'assets/js/admin-app.js')]);
  return `Alle ${requiredFiles.length} Plugin-Dateien, 10 Custom-Table-Definitionen und JS-Syntax gültig.`;
});

console.log(`Verifikation erfolgreich: ${results.length} Suiten bestanden.`);
