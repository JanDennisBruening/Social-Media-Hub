(() => {
  'use strict';

  const root = document.getElementById('smh-wordpress-admin-root');
  if (!root) return;

  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));

  const boot = window.SMH_BOOT || {};
  let state = boot.initialState || {
    version: '1.0.0',
    brands: [],
    accounts: [],
    media_assets: [],
    snippets: [],
    post_groups: [],
    revisions: [],
    jobs: [],
    receipts: [],
    preflight_48h_alerts: [],
    audit_events: []
  };

  let activeView = 'dashboard';
  let activeBrand = 'all';
  let activePostId = state.post_groups[0]?.id || null;

  const navItems = [
    { group: 'Arbeitsbereich', items: [
      ['dashboard', 'Übersicht (S01)'],
      ['composer', 'Beitragsstudio (S02)'],
      ['studio', 'Plattformstudio & Preflight (S03)'],
      ['queue', 'Publishing-Queue & Retry (S04)'],
      ['manual', 'Manuelle Übergaben (S05 / S10)'],
      ['calendar', 'Redaktionskalender (S06)'],
      ['media', 'Medien & Zuschnitte (S07)']
    ]},
    { group: 'Verwaltung', items: [
      ['brands', 'Marken, Kanäle & Rechte (S08)'],
      ['inbox', 'Interaktionen & Analysen (S11)']
    ]},
    { group: 'System & Export', items: [
      ['settings', 'System, Queue & Komplett-Export (S09)']
    ]}
  ];

  const brandName = (brandId) => state.brands.find((b) => b.id === brandId)?.name || brandId;
  const findAccount = (accId) => state.accounts.find((a) => a.id === accId);
  const findMedia = (medId) => state.media_assets.find((m) => m.id === medId);

  const filteredPosts = () => state.post_groups.filter(
    (p) => activeBrand === 'all' || p.brand_id === activeBrand
  );

  function renderDashboard() {
    const posts = filteredPosts();
    const openManual = posts.reduce((n, p) => n + (p.summary?.manual || 0) + (p.summary?.handoff || 0), 0);
    const blockedCount = posts.reduce((n, p) => n + (p.summary?.blocked || 0), 0);
    const alerts48h = state.preflight_48h_alerts || [];

    return `
      <div class="smh-pagehead">
        <div>
          <div class="smh-eyebrow">ARBEITSBEREICH · MEHRMARKEN-COCKPIT (S01)</div>
          <h1>Alles im Blick. Für jede Marke.</h1>
          <p>Klassische Social-Media-Beiträge (Bild, Caption, Hashtags, Link/YouTube-Verweis) sicher gestalten, prüfen, ausspielen und dokumentieren.</p>
        </div>
        <button class="smh-btn primary" data-nav="composer">+ Neuen Beitragsverbund anlegen</button>
      </div>

      ${alerts48h.length ? `
        <div class="smh-alert warn">
          <strong>48-Stunden-Vorab-Check der Verbindungen:</strong>
          ${alerts48h.map((w) => `${esc(w.handle)} (${esc(w.post_title)}): ${esc(w.issue)}`).join(' · ')}
        </div>
      ` : ''}

      <div class="smh-grid4">
        <div class="smh-card">
          <div class="smh-eyebrow">BEITRAGSVERBÜNDE</div>
          <div class="smh-stat-num">${posts.length}</div>
          <span class="smh-pill scheduled">Unabhängige Zielvarianten aktiv</span>
        </div>
        <div class="smh-card">
          <div class="smh-eyebrow">AKTION ERFORDERLICH</div>
          <div class="smh-stat-num">${blockedCount}</div>
          <span class="smh-pill ${blockedCount ? 'blocked' : 'ready'}">${blockedCount ? 'Fehlende Pflichtfelder / Token' : 'Alle Ziele geprüft'}</span>
        </div>
        <div class="smh-card">
          <div class="smh-eyebrow">MANUELLE ÜBERGABEN</div>
          <div class="smh-stat-num">${openManual}</div>
          <span class="smh-pill manual">WhatsApp-Kanal / App-Abschluss</span>
        </div>
        <div class="smh-card">
          <div class="smh-eyebrow">KANÄLE & MARKEN</div>
          <div class="smh-stat-num">${state.accounts.length} · ${state.brands.length}</div>
          <span class="smh-pill ready">Eigenständige WP-Tabellen aktiv</span>
        </div>
      </div>

      <div class="smh-grid2">
        <div class="smh-card">
          <h3>Aktuelle Beitragsverbünde & YouTube-/Kampagnen-Hinweise</h3>
          ${posts.map((p) => `
            <div class="smh-row">
              <div>
                <strong>${esc(p.title)}</strong>
                <div style="font-size:12px;color:var(--smh-muted)">
                  ${esc(brandName(p.brand_id))} · ${esc(p.campaign || 'Standard')}
                  ${p.external_url ? ` · 🔗 <a href="${esc(p.external_url)}" target="_blank" rel="noopener">${esc(p.external_url)}</a>` : ''}
                </div>
                <div style="margin-top:6px;display:flex;gap:5px;flex-wrap:wrap">
                  ${(p.targets || []).map((tid) => {
                    const a = findAccount(tid);
                    return a ? `<span class="smh-plat ${esc(a.platform)}">${esc(a.platform.toUpperCase().slice(0, 2))}</span>` : '';
                  }).join('')}
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:8px">
                <span class="smh-pill ${esc(p.summary?.composite_state || 'ready')}">${esc(p.summary?.composite_label || 'Bereit')}</span>
                <button class="smh-btn" data-open-studio="${esc(p.id)}">Plattformstudio →</button>
              </div>
            </div>
          `).join('')}
        </div>

        <div class="smh-card">
          <h3>Kanal- & Verbindungsgesundheit (Dynamischer Capability-Check)</h3>
          ${state.accounts.slice(0, 8).map((a) => `
            <div class="smh-row">
              <div>
                <span class="smh-plat ${esc(a.platform)}">${esc(a.platform.toUpperCase().slice(0, 2))}</span>
                <strong style="margin-left:6px">${esc(a.handle)}</strong>
                <div style="font-size:11px;color:var(--smh-muted)">${esc(a.capability_diagnosis?.reason || '')}</div>
              </div>
              <span class="smh-pill ${esc(a.capability_diagnosis?.status || 'ready')}">${esc(a.capability_diagnosis?.status || 'ready')}</span>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }

  function renderStudio() {
    const post = state.post_groups.find((p) => p.id === activePostId) || state.post_groups[0];
    if (!post) return `<div class="smh-card">Kein Beitragsverbund ausgewählt.</div>`;
    const summary = post.summary || { ready: 0, manual: 0, handoff: 0, blocked: 0, per_target: {} };

    return `
      <div class="smh-pagehead">
        <div>
          <div class="smh-eyebrow">STUFE 2 · PLATTFORMSTUDIO & VORPRÜFUNG (S03)</div>
          <h1>${esc(post.title)}</h1>
          <p>Marke: <strong>${esc(brandName(post.brand_id))}</strong> · Revision: <code>${esc(post.current_revision_id || 'Entwurf')}</code> ${post.external_url ? `· Verweis-Link: <code>${esc(post.external_url)}</code>` : ''}</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <span class="smh-pill ready">${summary.ready || 0} API-bereit</span>
          <span class="smh-pill manual">${(summary.manual || 0) + (summary.handoff || 0)} Manuell / App-Handoff</span>
          <span class="smh-pill ${summary.blocked ? 'blocked' : 'ready'}">${summary.blocked || 0} Blockiert</span>
        </div>
      </div>

      <div class="smh-grid3">
        ${(post.targets || []).map((accId) => {
          const acc = findAccount(accId);
          if (!acc) return '';
          const v = post.variants?.[accId] || {};
          const check = summary.per_target?.[accId] || { key: 'ready', label: 'API-bereit', issues: [], advice: [], effective_text: post.master_text, grapheme_count: 0 };
          const media = (v.media_ids || []).map(findMedia).filter(Boolean);
          return `
            <div class="smh-card">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <div>
                  <span class="smh-plat ${esc(acc.platform)}">${esc(acc.platform.toUpperCase().slice(0, 2))}</span>
                  <strong style="margin-left:6px">${esc(acc.handle)}</strong>
                </div>
                <span class="smh-pill ${esc(check.key)}">${esc(check.label)}</span>
              </div>

              <div style="font-size:12px;color:var(--smh-muted);margin-bottom:8px">
                Format: <strong>${esc(v.format_key || 'Standard')}</strong> · Zuschnitt: <strong>${esc(v.rendition_key || '4:5')}</strong>
              </div>

              <div class="smh-preview-box">
                <div style="font-size:12px;font-weight:700;margin-bottom:6px">
                  ${media.length ? media.map((m) => `${esc(m.emoji || '🖼️')} ${esc(m.name)} (${esc(v.rendition_key || '4:5')})`).join(', ') : 'Kein Medium ausgewählt'}
                </div>
                <div style="font-size:12px;white-space:pre-wrap">${esc(check.effective_text || post.master_text)}</div>
                <div style="font-size:11px;color:var(--smh-muted);margin-top:8px">
                  ${check.grapheme_count || 0} ${check.char_limit ? `/ ${check.char_limit}` : ''} Grapheme · Zustellstatus: <strong>${esc(v.delivery_status || 'pending')}</strong>
                </div>
              </div>

              ${(check.issues || []).map((iss) => `<div class="smh-alert danger" style="padding:8px 10px;margin-bottom:6px">✕ ${esc(iss)}</div>`).join('')}
              ${(check.advice || []).map((adv) => `<div class="smh-alert warn" style="padding:8px 10px;margin-bottom:6px">ℹ ${esc(adv)}</div>`).join('')}
              ${!(check.issues || []).length && !(check.advice || []).length ? `<div class="smh-alert success" style="padding:8px 10px;margin-bottom:6px">✓ Format, Zuschnitt, Alt-Text und Kanalrechte gültig.</div>` : ''}
            </div>
          `;
        }).join('')}
      </div>
    `;
  }

  function renderSettingsAndExport() {
    return `
      <div class="smh-pagehead">
        <div>
          <div class="smh-eyebrow">SYSTEM, SICHERHEIT & KOMPLETT-EXPORT (S09)</div>
          <h1>Eigenständiger Betrieb, Verschlüsselter Tresor & Backup-Export</h1>
          <p>Unabhängig von WordPress-Blogbeiträgen. Alle Beiträge, Bilder-Manifeste, Varianten und Nachweise lassen sich jederzeit komplett auslagern.</p>
        </div>
      </div>

      <div class="smh-grid2">
        <div class="smh-card">
          <h3>1. Komplett-Export & Umzugs-Paket (Bereich 1)</h3>
          <p style="font-size:13px;color:var(--smh-muted)">
            Exportiert alle Marken, Kanalkonfigurationen (<strong>ohne geheime Tokens</strong>), Textbausteine, Medien-Manifeste (inkl. Zuschnitten & Alt-Texten), Beitragsverbünde, Revisions-Snapshots und Nachweise.
          </p>
          <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
            <button class="smh-btn primary" data-action="export-json">⬇️ Komplett-Backup als JSON herunterladen</button>
            <button class="smh-btn" data-action="export-csv">📊 Archiv-Tabelle als CSV exportieren</button>
          </div>
          <pre id="smh-export-preview" style="margin-top:14px;background:#f4f6fb;padding:12px;border-radius:10px;font-size:11px;max-height:240px;overflow:auto">Klicke auf einen Export-Button, um das portable Paket zu erzeugen.</pre>
        </div>

        <div class="smh-card">
          <h3>2. Sicherheits-, Queue- & Abgleich-Status (Bereiche A–D)</h3>
          <div class="smh-row">
            <div><strong>Verschlüsselter Token-Safe</strong><div style="font-size:12px;color:var(--smh-muted)">Libsodium (sodium_crypto_secretbox) / AES-256-GCM Server-Tresor</div></div>
            <span class="smh-pill ready">Aktiv & geschützt</span>
          </div>
          <div class="smh-row">
            <div><strong>Signierte Medien-Abhol-Links (Instagram Pull-API)</strong><div style="font-size:12px;color:var(--smh-muted)">Kurzlebige HMAC-SHA256-URLs (15 Min. gültig) für Meta-Container</div></div>
            <span class="smh-pill ready">Aktiv</span>
          </div>
          <div class="smh-row">
            <div><strong>Idempotente Queue & Einzel-Retry</strong><div style="font-size:12px;color:var(--smh-muted)">Verhindert Doppel-Posts auf bereits erfolgreichen Kanälen bei Teilversagen</div></div>
            <span class="smh-pill ready">Aktiv</span>
          </div>
          <div class="smh-row">
            <div><strong>48-Stunden-Vorab-Check & Auto-Backoff</strong><div style="font-size:12px;color:var(--smh-muted)">Prüft Token-Ablauf vor Fälligkeit; 3× Backoff (60s/300s/1800s) bei HTTP 429</div></div>
            <span class="smh-pill ready">Aktiv</span>
          </div>
        </div>
      </div>
    `;
  }

  function renderSimpleTableView(title, eyebrow, subtitle, rowsHtml) {
    return `
      <div class="smh-pagehead">
        <div>
          <div class="smh-eyebrow">${esc(eyebrow)}</div>
          <h1>${esc(title)}</h1>
          <p>${esc(subtitle)}</p>
        </div>
      </div>
      <div class="smh-card">${rowsHtml}</div>
    `;
  }

  function renderCurrentView() {
    switch (activeView) {
      case 'dashboard':
        return renderDashboard();
      case 'studio':
      case 'composer':
        return renderStudio();
      case 'settings':
        return renderSettingsAndExport();
      case 'media':
        return renderSimpleTableView(
          'Medienbibliothek, Format-Renditions & Alt-Texte',
          'CONTENT-BIBLIOTHEK (S07)',
          'Jedes Originalbild hält passgenaue Zuschnitte (4:5, 1:1, 9:16, 2:3, 16:9) und Barrierefreiheits-Alt-Texte bereit.',
          state.media_assets.map((m) => `
            <div class="smh-row">
              <div>
                <strong>${esc(m.emoji || '🖼️')} ${esc(m.name)}</strong> (${esc(brandName(m.brand_id))})
                <div style="font-size:12px;color:var(--smh-muted)">Alt-Text: ${esc(m.alt_text || 'Fehlt')}</div>
              </div>
              <div style="display:flex;gap:6px;flex-wrap:wrap">
                ${Object.keys(m.renditions || {}).map((r) => `<span class="smh-pill ready">${esc(r)} ✓</span>`).join('')}
              </div>
            </div>
          `).join('')
        );
      case 'manual':
        return renderSimpleTableView(
          'Manuelle Übergaben & Smartphone-Handoff',
          'EXTERNE VERÖFFENTLICHUNG & NACHWEISE (S05 / S10)',
          'Grundsatz: „Text kopiert ≠ Veröffentlicht“. Erst die ausdrückliche Bestätigung mit Zeitstempel erzeugt den Nachweis.',
          filteredPosts().flatMap((p) => (p.targets || []).map((tid) => {
            const a = findAccount(tid);
            const check = p.summary?.per_target?.[tid];
            if (!a || !['manual', 'handoff'].includes(check?.method || '')) return '';
            return `
              <div class="smh-row">
                <div>
                  <span class="smh-plat ${esc(a.platform)}">${esc(a.platform.toUpperCase().slice(0, 2))}</span>
                  <strong style="margin-left:6px">${esc(a.handle)}</strong> · ${esc(p.title)}
                  <div style="font-size:12px;color:var(--smh-muted)">${esc(check.label)} · Status: ${esc(p.variants?.[tid]?.delivery_status || 'offen')}</div>
                </div>
                <span class="smh-pill manual">Bereit zur Übergabe</span>
              </div>
            `;
          })).join('') || '<p>Keine offenen manuellen Übergaben.</p>'
        );
      default:
        return renderDashboard();
    }
  }

  function render() {
    root.innerHTML = `
      <div class="smh-shell">
        <aside class="smh-sidebar">
          <div class="smh-identity">
            <div class="smh-brand-logo">S</div>
            <div>
              <strong>Social Hub</strong>
              <small>WordPress Plugin · v${esc(state.version || '1.0.0')}</small>
            </div>
          </div>
          <nav class="smh-nav">
            ${navItems.map((g) => `
              <div class="smh-nav-group">${esc(g.group)}</div>
              ${g.items.map(([id, label]) => `
                <button class="smh-nav-btn ${activeView === id ? 'active' : ''}" data-nav="${esc(id)}">
                  <span>${esc(label)}</span>
                </button>
              `).join('')}
            `).join('')}
          </nav>
          <div class="smh-sidefooter">
            <span class="smh-dot-online"></span> Eigenständiges WP-Plugin<br>
            10 Custom Tables · TokenSafe aktiv
          </div>
        </aside>
        <div class="smh-main">
          <header class="smh-topbar">
            <div class="smh-crumb">Social Media Hub › <strong>${esc(activeView.toUpperCase())}</strong></div>
            <div class="smh-toptools">
              <select class="smh-select" id="smh-brand-select" style="width:220px">
                <option value="all">Alle Marken (${state.brands.length})</option>
                ${state.brands.map((b) => `<option value="${esc(b.id)}" ${activeBrand === b.id ? 'selected' : ''}>${esc(b.name)}</option>`).join('')}
              </select>
              <button class="smh-btn primary" data-nav="studio">Plattformstudio (S03)</button>
            </div>
          </header>
          <main class="smh-content">${renderCurrentView()}</main>
        </div>
      </div>
    `;
  }

  root.addEventListener('click', (e) => {
    const navBtn = e.target.closest('[data-nav]');
    if (navBtn) {
      activeView = navBtn.dataset.nav;
      render();
      return;
    }
    const studioBtn = e.target.closest('[data-open-studio]');
    if (studioBtn) {
      activePostId = studioBtn.dataset.openStudio;
      activeView = 'studio';
      render();
      return;
    }
    const actionBtn = e.target.closest('[data-action]');
    if (actionBtn) {
      const pre = document.getElementById('smh-export-preview');
      if (actionBtn.dataset.action === 'export-json' && pre) {
        const exportPayload = {
          export_format: 'social-media-hub-portable-backup',
          export_version: '1.0.0',
          secrets_included: false,
          exported_at: new Date().toISOString(),
          counts: {
            brands: state.brands.length,
            accounts: state.accounts.length,
            media_assets: state.media_assets.length,
            snippets: state.snippets.length,
            post_groups: state.post_groups.length
          },
          data: state
        };
        pre.textContent = JSON.stringify(exportPayload, null, 2);
      } else if (actionBtn.dataset.action === 'export-csv' && pre) {
        const rows = ['Beitrags-ID;Marke;Titel;Zielkonto;Format;Status;Externer Link'];
        for (const p of state.post_groups) {
          for (const tid of (p.targets || [])) {
            const a = findAccount(tid);
            const v = p.variants?.[tid] || {};
            rows.push(`"${p.id}";"${brandName(p.brand_id)}";"${p.title}";"${a?.handle || tid}";"${v.format_key || ''}";"${v.delivery_status || 'pending'}";"${p.external_url || ''}"`);
          }
        }
        pre.textContent = rows.join('\n');
      }
    }
  });

  root.addEventListener('change', (e) => {
    if (e.target.id === 'smh-brand-select') {
      activeBrand = e.target.value;
      render();
    }
  });

  render();
})();
