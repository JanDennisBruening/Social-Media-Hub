/**
 * Social Media Hub v2.0.0 — Generalüberholte, REST-gebundene WordPress-Admin-Applikation
 *
 * Schwerpunkte v2.0.0:
 * - Sauberer Erststart ohne Demo-Müll (wirklich leer und sofort einsatzbereit)
 * - WordPress-Hauptnavigation automatisch reduziert (`body.folded`)
 * - Einfacher 3-Schritt-Beitrags-Assistent (1. Plattformen wählen → 2. Inhalt & Bild → 3. Prüfen & 1-Klick-Veröffentlichen)
 * - Große, aufgeräumte Beitragsliste mit Mehrfachauswahl, Verschieben, Duplizieren & zweistufiger Lösch-Bestätigung
 * - Echte WordPress-REST-Persistenz & native WordPress-Mediathek (`wp.media`)
 */
(() => {
  'use strict';

  const root = document.getElementById('smh-wordpress-admin-root');
  if (!root) return;

  // WordPress-Hauptnavigation auf großen Bildschirmen automatisch reduzieren
  if (window.innerWidth > 960 && !document.body.classList.contains('folded')) {
    document.body.classList.add('folded');
  }

  const boot = window.SMH_BOOT || {};
  const REST_URL = (boot.restUrl || '/wp-json/social-hub/v1').replace(/\/$/, '');
  const NONCE = boot.nonce || '';

  let state = boot.initialState || {
    version: '2.0.0',
    manifests: {},
    brands: [{ id: 'default', name: 'Hauptprofil', color: '#586EE7', description: '' }],
    accounts: [],
    media_assets: [],
    snippets: [],
    post_groups: [],
    revisions: [],
    jobs: [],
    receipts: [],
    automation_rules: [],
    preflight_48h_alerts: [],
    audit_events: [],
    wartungsmodus_active: false,
  };

  // Plattform-Metadaten (korrekte Kurzzeichen & Namen)
  const PLATFORMS = {
    instagram: { label: 'Instagram', short: 'IG', manualDefault: false, hint: 'Feed, Karussell, Reel oder Story' },
    facebook:  { label: 'Facebook-Seite', short: 'FB', manualDefault: false, hint: 'Bild, Link-Beitrag, Text oder Video' },
    linkedin:  { label: 'LinkedIn', short: 'in', manualDefault: false, hint: 'Persönliches Profil oder Unternehmensseite' },
    whatsapp:  { label: 'WhatsApp-Kanal', short: 'WA', manualDefault: true,  hint: 'Manuelle Übergabe mit Kopier- & Bild-Assistent' },
    threads:   { label: 'Threads', short: '@', manualDefault: false, hint: 'Text, Bild oder Karussell' },
    tiktok:    { label: 'TikTok', short: 'TT', manualDefault: false, hint: 'Video oder Fotopost (App-Fertigstellung)' },
    pinterest: { label: 'Pinterest', short: 'P', manualDefault: false, hint: 'Pin (2:3) mit Pinnwand & Ziel-URL' },
    bluesky:   { label: 'Bluesky', short: 'BS', manualDefault: false, hint: 'Kurzbeitrag (300 Zeichen) & bis zu 4 Bilder' },
    mastodon:  { label: 'Mastodon', short: 'M', manualDefault: false, hint: 'Instanz-API mit dynamischen Limits' },
    telegram:  { label: 'Telegram-Kanal', short: 'TG', manualDefault: true,  hint: 'Manuell oder automatisch über Bot-Token' },
    signal:    { label: 'Signal', short: 'S', manualDefault: true,  hint: 'Manuelle Übergabe für Kanal/Gruppe' },
  };

  const ICONS = {
    home: '<path d="m3 10 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
    list: '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
    cal: '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    hand: '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
    img: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
    plug: '<path d="M12 22v-5"/><path d="M9 8V2"/><path d="M15 8V2"/><path d="M18 8v5a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4V8Z"/>',
    cog: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
  };

  const svg = (k) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">${ICONS[k] || ''}</svg>`;
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const countGraphemes = (str) => {
    try { return [...new Intl.Segmenter('de', { granularity: 'grapheme' }).segment(String(str || ''))].length; }
    catch { return Array.from(String(str || '')).length; }
  };

  const formatDate = (iso) => {
    if (!iso) return 'Sofort / kein Termin';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '–';
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(d);
  };

  const toLocalDateTimeInput = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  };

  const platBadge = (platform) => {
    const info = PLATFORMS[platform] || { label: platform, short: String(platform || '?').slice(0, 2).toUpperCase() };
    return `<span class="smh-plat ${esc(platform)}" title="${esc(info.label)}">${esc(info.short)}</span>`;
  };

  const findAccount = (id) => (state.accounts || []).find((a) => a.id === id);
  const findMedia = (id) => (state.media_assets || []).find((m) => m.id === id);
  const findBrand = (id) => (state.brands || []).find((b) => b.id === id);
  const brandName = (id) => findBrand(id)?.name || 'Hauptprofil';

  // Plattformspezifische Zugangsdaten-Hinweise für den Kanal-Dialog
  const PLATFORM_CREDS = {
    instagram: {
      idLabel: 'Instagram Business Account ID (z. B. 17841400…)',
      idPlaceholder: 'Bei IG…-Token optional, bei Meta Graph Token (EAAG…) erforderlich',
      tokenLabel: 'Instagram / Meta Graph Access Token',
      tokenHint: 'Wird beim Speichern und bei „Live prüfen“ echt gegen graph.instagram.com bzw. graph.facebook.com geprüft.',
    },
    facebook: {
      idLabel: 'Facebook Seiten-ID (Page ID)',
      idPlaceholder: 'z. B. 102938475612345',
      tokenLabel: 'Facebook Page Access Token (mit pages_manage_posts)',
      tokenHint: 'Wird live über die Facebook Graph API v21.0 verifiziert.',
    },
    linkedin: {
      idLabel: 'LinkedIn Author-URN (optional – wird bei Live-Prüfung erkannt)',
      idPlaceholder: 'z. B. urn:li:person:… oder urn:li:organization:…',
      tokenLabel: 'LinkedIn OAuth 2.0 Access Token',
      tokenHint: 'Wird live über api.linkedin.com/v2/userinfo geprüft.',
    },
    threads: {
      idLabel: 'Threads User-ID (optional – wird bei Live-Prüfung erkannt)',
      idPlaceholder: 'Optional',
      tokenLabel: 'Meta Threads Access Token',
      tokenHint: 'Wird live gegen graph.threads.net/v1.0/me geprüft.',
    },
    pinterest: {
      idLabel: 'Pinterest Benutzername (optional)',
      idPlaceholder: 'Wird automatisch aus API gelesen',
      tokenLabel: 'Pinterest API v5 OAuth Access Token',
      tokenHint: 'Wird live über api.pinterest.com/v5/user_account geprüft.',
    },
    bluesky: {
      idLabel: 'Bluesky-Handle (Identifier) *',
      idPlaceholder: 'z. B. deinname.bsky.social',
      tokenLabel: 'Bluesky App-Passwort',
      tokenHint: 'Erstelle in Bluesky unter Einstellungen → App-Passwörter ein eigenes App-Passwort. Wird live per ATProto verifiziert.',
    },
    mastodon: {
      idLabel: 'Konto-ID (wird automatisch erkannt)',
      idPlaceholder: 'Optional',
      needsInstance: true,
      tokenLabel: 'Mastodon Application Access Token',
      tokenHint: 'Wird live gegen deine Mastodon-Instanz geprüft und liest das echte Zeichenlimit aus.',
    },
    telegram: {
      idLabel: 'Telegram Kanal-Username oder Chat-ID *',
      idPlaceholder: 'z. B. @mein_kanal oder -1001234567890',
      tokenLabel: 'Telegram Bot-Token (von @BotFather)',
      tokenHint: 'Der Bot muss als Administrator im Kanal eingetragen sein. Wird live über api.telegram.org geprüft.',
    },
    tiktok: {
      idLabel: 'TikTok Open-ID (optional)',
      idPlaceholder: 'Wird automatisch aus API gelesen',
      tokenLabel: 'TikTok Content Posting OAuth Token',
      tokenHint: 'Wird live über open.tiktokapis.com/v2/user/info/ geprüft.',
    },
  };

  function channelStatusInfo(account) {
    const st = account?.capability_diagnosis?.status || account?.connection_state || 'not_configured';
    if (st === 'ready') return { cls: 'ready', label: 'Live verbunden ✓', diagCls: 'ready' };
    if (st === 'manual') return { cls: 'manual', label: 'Manueller Kanal', diagCls: 'warn' };
    if (st === 'handoff') return { cls: 'handoff', label: 'App-Übergabe ✓', diagCls: 'ready' };
    if (st === 'not_configured') return { cls: 'not_configured', label: 'Nicht verbunden', diagCls: '' };
    if (st === 'blocked') return { cls: 'blocked', label: 'Verbindung fehlgeschlagen', diagCls: 'blocked' };
    return { cls: 'unknown', label: 'Noch nicht geprüft', diagCls: 'warn' };
  }

  // UI-Zustand
  let view = 'posts'; // Start direkt auf der großen Beitragsliste bzw. Übersicht
  if (!(state.post_groups || []).length) view = 'dashboard';
  let activeBrand = 'all';
  let activeDetailPostId = null;
  let testingChannelId = null;

  // Filter & Mehrfachauswahl in der Beitragsliste
  let postTab = 'all';
  let postSearch = '';
  let postCampaignFilter = 'all';
  let postPlatformFilter = 'all';
  let postSort = 'newest';
  let selectedPostIds = new Set();

  // Mehrfachauswahl Medien
  let selectedMediaIds = new Set();

  // Kalender
  let calDate = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
  let selectedCalDay = null;

  // 3-Schritt-Beitrags-Assistent (Wizard)
  let wizard = null;
  let wizardPreviewAccountId = null;

  // Aktives Modal (Move, Double-Delete, Quick-Channel, Manual-Confirm, Brand, Snippet)
  let activeModal = null;
  let busy = false;
  let toastTimer = null;

  function showToast(msg) {
    let el = document.getElementById('smh-toast-box');
    if (!el) {
      el = document.createElement('div');
      el.id = 'smh-toast-box';
      document.body.appendChild(el);
    }
    el.innerHTML = `<div class="smh-toast">${esc(msg)}</div>`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.innerHTML = ''; }, 4200);
  }

  async function api(endpoint, method = 'GET', body = null) {
    busy = true;
    try {
      const opts = {
        method,
        headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' },
        credentials: 'same-origin',
      };
      if (body !== null && method !== 'GET') {
        opts.body = JSON.stringify(body);
      }
      const res = await fetch(`${REST_URL}${endpoint}`, opts);
      const data = await res.json();
      if (!res.ok || data?.ok === false) {
        throw new Error(data?.error || data?.message || `Fehler (${res.status})`);
      }
      if (data?.state) {
        state = data.state;
      }
      return data;
    } finally {
      busy = false;
    }
  }

  function filteredPostsBase() {
    return (state.post_groups || []).filter((p) => activeBrand === 'all' || p.brand_id === activeBrand);
  }

  function classifyPostTab(p) {
    if (p.archived) return 'archived';
    const st = p.summary?.composite_state || 'draft';
    if (st === 'completed') return 'published';
    if (st === 'scheduled') return 'scheduled';
    if (st === 'partial_failure' || st === 'partially_completed') return 'open';
    return 'draft';
  }

  function visiblePosts() {
    let list = filteredPostsBase();
    if (postTab === 'all') {
      list = list.filter((p) => !p.archived);
    } else {
      list = list.filter((p) => classifyPostTab(p) === postTab);
    }
    if (postCampaignFilter !== 'all') {
      list = list.filter((p) => (p.campaign || 'Allgemein') === postCampaignFilter);
    }
    if (postPlatformFilter !== 'all') {
      list = list.filter((p) => (p.targets || []).some((tid) => findAccount(tid)?.platform === postPlatformFilter));
    }
    if (postSearch.trim()) {
      const q = postSearch.trim().toLowerCase();
      list = list.filter((p) =>
        `${p.title || ''} ${p.master_text || ''} ${p.campaign || ''} ${p.external_url || ''}`.toLowerCase().includes(q)
      );
    }
    list = list.slice();
    if (postSort === 'scheduled') {
      list.sort((a, b) => new Date(a.scheduled_at || '2999-01-01') - new Date(b.scheduled_at || '2999-01-01'));
    } else if (postSort === 'title') {
      list.sort((a, b) => String(a.title || '').localeCompare(String(b.title || ''), 'de'));
    } else {
      list.sort((a, b) => new Date(b.updated_at || b.created_at || 0) - new Date(a.updated_at || a.created_at || 0));
    }
    return list;
  }

  function openPostDetail(postId) {
    const post = (state.post_groups || []).find((p) => p.id === postId);
    if (!post) return;
    activeDetailPostId = post.id;
    view = 'detail';
    render();
  }

  function openNewPostWizard(existingPost = null, prefillDay = null, initialStep = 1) {
    const defaultBrand = activeBrand !== 'all' ? activeBrand : (state.brands[0]?.id || 'default');
    if (existingPost) {
      wizard = {
        step: initialStep,
        id: existingPost.id,
        brand_id: existingPost.brand_id || defaultBrand,
        title: existingPost.title || '',
        campaign: existingPost.campaign || 'Allgemein',
        master_text: existingPost.master_text || '',
        external_url: existingPost.external_url || '',
        primary_media_id: existingPost.primary_media_id || '',
        scheduled_at: existingPost.scheduled_at || '',
        schedule_mode: existingPost.scheduled_at ? 'scheduled' : 'now',
        approval_status: existingPost.approval_status || 'approved',
        targets: [...(existingPost.targets || [])],
        variants: JSON.parse(JSON.stringify(existingPost.variants || {})),
      };
    } else {
      const brandAccounts = (state.accounts || []).filter((a) => a.brand_id === defaultBrand);
      wizard = {
        step: 1,
        id: null,
        brand_id: defaultBrand,
        title: '',
        campaign: 'Allgemein',
        master_text: '',
        external_url: '',
        primary_media_id: '',
        scheduled_at: prefillDay ? `${prefillDay}T10:00` : '',
        schedule_mode: prefillDay ? 'scheduled' : 'now',
        approval_status: 'approved',
        targets: brandAccounts.map((a) => a.id),
        variants: {},
      };
    }
    syncWizardVariants();
    wizardPreviewAccountId = wizard.targets[0] || null;
    view = 'wizard';
    render();
  }

  function syncWizardVariants() {
    if (!wizard) return;
    for (const accId of wizard.targets) {
      const acc = findAccount(accId);
      if (!acc) continue;
      const manifest = state.manifests?.[acc.platform] || {};
      const formatKeys = Object.keys(manifest.formats || {});
      const existing = wizard.variants[accId] || {};
      const fmt = existing.format_key && manifest.formats?.[existing.format_key]
        ? existing.format_key
        : (formatKeys[0] || 'image');
      wizard.variants[accId] = {
        enabled: existing.enabled !== false,
        format_key: fmt,
        text_override: existing.text_override || '',
        media_ids: wizard.primary_media_id ? [wizard.primary_media_id] : [],
        rendition_key: existing.rendition_key || manifest.formats?.[fmt]?.recommended_rendition || '4:5',
        metadata: existing.metadata || {},
        delivery_status: existing.delivery_status || 'pending',
      };
    }
  }

  function computePreviewForAccount(acc) {
    if (!wizard) return { text: '', graphemes: 0, charLimit: null };
    const prevVar = acc ? (wizard.variants[acc.id] || {}) : {};
    const prevTextBase = (prevVar.text_override || wizard.master_text || '').trim();
    const clickableLink = acc && !['instagram', 'tiktok'].includes(acc.platform) && wizard.external_url && !prevTextBase.includes(wizard.external_url);
    const text = clickableLink ? `${prevTextBase}\n\n🔗 ${wizard.external_url}` : prevTextBase;
    const charLimit = acc ? (state.manifests?.[acc.platform]?.default_char_limit || null) : null;
    return { text, graphemes: countGraphemes(text), charLimit };
  }

  function updateLivePreviewDom() {
    if (!wizard || wizard.step !== 2) return;
    const chosenAccounts = wizard.targets.map(findAccount).filter(Boolean);
    const prevAcc = findAccount(wizardPreviewAccountId) || chosenAccounts[0] || null;
    const info = computePreviewForAccount(prevAcc);
    const masterCount = document.getElementById('wiz-master-count');
    if (masterCount) masterCount.textContent = `${countGraphemes(wizard.master_text)} Zeichen`;
    const body = document.querySelector('.smh-preview-body');
    if (body) body.textContent = info.text || 'Hier erscheint die Vorschau deines Beitragstextes …';
    const prevCount = document.getElementById('wiz-preview-count');
    if (prevCount) prevCount.textContent = `${info.graphemes}${info.charLimit ? ` / ${info.charLimit}` : ''} Zeichen`;
    const prevHint = document.getElementById('wiz-preview-hint');
    if (prevHint) prevHint.textContent = prevAcc?.platform === 'instagram' && wizard.external_url ? 'Hinweis: Link in Bio' : 'Vorschau';
  }

  async function copyTextSafely(text) {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return;
    }
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    ta.remove();
  }

  function openWordPressMediaPicker(onSelect) {
    if (window.wp && window.wp.media) {
      const frame = window.wp.media({
        title: 'Bild oder Video für deinen Beitrag auswählen',
        button: { text: 'Übernehmen' },
        multiple: false,
      });
      frame.on('select', async () => {
        const att = frame.state().get('selection').first().toJSON();
        const saved = await api('/media', 'POST', {
          brand_id: wizard?.brand_id || (activeBrand !== 'all' ? activeBrand : state.brands[0]?.id),
          wp_attachment_id: att.id,
          url: att.url,
          thumbnail_url: att.sizes?.medium?.url || att.sizes?.thumbnail?.url || att.url,
          name: att.filename || att.title || 'Medium',
          mime_type: att.mime || 'image/jpeg',
          width_px: att.width || 1080,
          height_px: att.height || 1080,
          alt_text: att.alt || '',
        });
        if (saved?.media) {
          showToast(`Medium „${saved.media.name}“ übernommen.`);
          onSelect(saved.media);
        }
      });
      frame.open();
      return;
    }
    showToast('WordPress-Mediathek steht in dieser Vorschau nicht zur Verfügung.');
  }

  // Zweistufiges Lösch-Modal (Double Confirmation!)
  function openDoubleDeleteModal(config) {
    activeModal = {
      type: 'delete',
      step: 1,
      confirmedCheck: false,
      title: config.title,
      items: config.items || [],
      warningText: config.warningText || 'Alle zugehörigen Texte, Kanal-Varianten und Termine werden entfernt.',
      onConfirm: config.onConfirm,
    };
    render();
  }

  // Verschieben-Modal (Einzel & Mehrfachauswahl)
  function openMovePostsModal(ids) {
    const existingCampaigns = [...new Set((state.post_groups || []).map((p) => p.campaign || 'Allgemein'))];
    activeModal = {
      type: 'move',
      ids: [...ids],
      existingCampaigns,
    };
    render();
  }

  function openChannelModal(existing = null, returnToWizard = false) {
    activeModal = {
      type: 'channel',
      returnToWizard,
      account: existing || {
        id: '',
        brand_id: wizard?.brand_id || (activeBrand !== 'all' ? activeBrand : state.brands[0]?.id || 'default'),
        platform: 'instagram',
        account_type: 'business',
        handle: '',
        external_account_id: '',
        instance_url: '',
        connection_state: '',
      },
    };
    render();
  }

  function openManualConfirmModal(postId, accountId) {
    const post = (state.post_groups || []).find((p) => p.id === postId);
    const acc = findAccount(accountId);
    if (!post || !acc) return;
    activeModal = {
      type: 'manual-confirm',
      postId,
      accountId,
      postTitle: post.title,
      handle: acc.handle,
      externalUrl: post.variants?.[accountId]?.remote_url || '',
      notes: '',
    };
    render();
  }

  // ===========================================================================
  // ANSICHT 1: ÜBERSICHT
  // ===========================================================================
  function renderDashboard() {
    const posts = filteredPostsBase().filter((p) => !p.archived);
    const accounts = (state.accounts || []).filter((a) => activeBrand === 'all' || a.brand_id === activeBrand);
    const drafts = posts.filter((p) => classifyPostTab(p) === 'draft').length;
    const scheduled = posts.filter((p) => classifyPostTab(p) === 'scheduled').length;
    const published = posts.filter((p) => classifyPostTab(p) === 'published').length;
    const manualOpen = posts.reduce((n, p) => {
      return n + (p.targets || []).filter((tid) => {
        const st = p.variants?.[tid]?.delivery_status;
        const m = p.summary?.per_target?.[tid]?.method;
        return ['manual', 'handoff'].includes(m) && st !== 'manual_confirmed';
      }).length;
    }, 0);

    // Sauberer Willkommenszustand für Erstnutzer
    if (accounts.length === 0 && posts.length === 0) {
      return `
        <div class="smh-empty">
          <div class="smh-empty-icon">✨</div>
          <h3>Willkommen im Social Media Hub</h3>
          <p>Dein Arbeitsbereich ist komplett sauber und bereit für den Start. Lege zuerst deine gewünschten Kanäle an (z. B. Instagram, Facebook, LinkedIn oder WhatsApp-Kanal) und erstelle danach in 3 einfachen Schritten deinen ersten Beitrag.</p>
          <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            <button class="smh-btn primary lg" data-action="open-new-channel">+ Ersten Kanal einrichten</button>
            <button class="smh-btn lg" data-action="new-post">+ Direkt ersten Beitrag starten</button>
          </div>
        </div>
      `;
    }

    return `
      ${(state.preflight_48h_alerts || []).length ? `
        <div class="smh-alert warn">
          <strong>Hinweis zu deinen Kanalverbindungen (48h-Check):</strong>
          ${state.preflight_48h_alerts.map((w) => `${esc(w.handle)}: ${esc(w.issue)}`).join(' · ')}
        </div>
      ` : ''}

      <div class="smh-grid4">
        <div class="smh-card smh-stat-card">
          <div class="smh-stat-label">Entwürfe</div>
          <div class="smh-stat-num">${drafts}</div>
          <span class="smh-pill">In Bearbeitung</span>
        </div>
        <div class="smh-card smh-stat-card">
          <div class="smh-stat-label">Geplant</div>
          <div class="smh-stat-num">${scheduled}</div>
          <span class="smh-pill scheduled">Bereit für Versand</span>
        </div>
        <div class="smh-card smh-stat-card">
          <div class="smh-stat-label">Manuelle Übergaben</div>
          <div class="smh-stat-num">${manualOpen}</div>
          <span class="smh-pill ${manualOpen ? 'manual' : 'ready'}">${manualOpen ? 'Offen (z. B. WhatsApp)' : 'Alles erledigt'}</span>
        </div>
        <div class="smh-card smh-stat-card">
          <div class="smh-stat-label">Veröffentlicht</div>
          <div class="smh-stat-num">${published}</div>
          <span class="smh-pill ready">${accounts.length} Kanäle eingerichtet</span>
        </div>
      </div>

      <div class="smh-grid2">
        <div class="smh-card">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
            <h3 style="margin:0;font-size:15px">Aktuelle Beiträge</h3>
            <button class="smh-btn sm" data-nav="posts">Alle Beiträge öffnen →</button>
          </div>
          ${posts.length ? posts.slice(0, 6).map((p) => `
            <div class="smh-row">
              <div class="smh-post-link-cell" data-open-detail="${esc(p.id)}" style="min-width:0;flex:1">
                <strong style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(p.title)}</strong>
                <div class="smh-hint">${esc(p.campaign || 'Allgemein')} · ${esc(formatDate(p.scheduled_at))}</div>
                <div style="margin-top:5px;display:flex;gap:4px;flex-wrap:wrap">
                  ${(p.targets || []).map((tid) => { const a = findAccount(tid); return a ? platBadge(a.platform) : ''; }).join('')}
                </div>
              </div>
              <div style="display:flex;gap:6px;align-items:center;flex-shrink:0">
                <span class="smh-pill ${esc(p.summary?.composite_state || 'draft')}">${esc(p.summary?.composite_label || 'Entwurf')}</span>
                <button class="smh-btn sm" data-open-detail="${esc(p.id)}">Öffnen</button>
              </div>
            </div>
          `).join('') : `
            <p class="smh-hint" style="margin:14px 0">Noch keine Beiträge erstellt.</p>
            <button class="smh-btn primary" data-action="new-post">+ Ersten Beitrag erstellen</button>
          `}
        </div>

        <div class="smh-card">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
            <h3 style="margin:0;font-size:15px">Deine Kanäle & Verbindungen</h3>
            <button class="smh-btn sm" data-action="open-new-channel">+ Kanal hinzufügen</button>
          </div>
          ${accounts.length ? accounts.map((a) => {
            const stInfo = channelStatusInfo(a);
            return `
              <div class="smh-row">
                <div style="display:flex;align-items:center;gap:10px;min-width:0">
                  ${platBadge(a.platform)}
                  <div style="min-width:0">
                    <strong style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(a.handle)}</strong>
                    <div class="smh-hint">${esc(PLATFORMS[a.platform]?.label || a.platform)}</div>
                  </div>
                </div>
                <span class="smh-pill ${esc(stInfo.cls)}">${esc(stInfo.label)}</span>
              </div>
            `;
          }).join('') : `
            <p class="smh-hint" style="margin:14px 0">Noch keine Social-Media-Kanäle angelegt.</p>
            <button class="smh-btn primary" data-action="open-new-channel">+ Ersten Kanal verbinden</button>
          `}
        </div>
      </div>
    `;
  }

  // ===========================================================================
  // ANSICHT 2: GROSSE BEITRAGSLISTE (MEHRFACHAUSWAHL, VERSCHIEBEN, 2-STUFEN-LÖSCHEN)
  // ===========================================================================
  function renderPostsList() {
    const allBase = filteredPostsBase();
    const list = visiblePosts();
    const campaigns = [...new Set(allBase.map((p) => p.campaign || 'Allgemein'))];

    const counts = {
      all: allBase.filter((p) => !p.archived).length,
      draft: allBase.filter((p) => classifyPostTab(p) === 'draft').length,
      scheduled: allBase.filter((p) => classifyPostTab(p) === 'scheduled').length,
      open: allBase.filter((p) => classifyPostTab(p) === 'open').length,
      published: allBase.filter((p) => classifyPostTab(p) === 'published').length,
      archived: allBase.filter((p) => p.archived).length,
    };

    const allVisibleSelected = list.length > 0 && list.every((p) => selectedPostIds.has(p.id));
    const selectedArr = [...selectedPostIds].filter((id) => (state.post_groups || []).some((p) => p.id === id));

    return `
      <div class="smh-tabs">
        ${[
          ['all', 'Alle'],
          ['draft', 'Entwürfe'],
          ['scheduled', 'Geplant'],
          ['open', 'Offen / Teilweise'],
          ['published', 'Veröffentlicht'],
          ['archived', 'Archiv'],
        ].map(([id, label]) => `
          <button class="smh-tab ${postTab === id ? 'active' : ''}" data-post-tab="${id}">
            ${label} <span style="opacity:.7">(${counts[id]})</span>
          </button>
        `).join('')}
      </div>

      <div class="smh-toolbar">
        <input class="smh-input" id="smh-post-search" style="max-width:290px" placeholder="Beiträge durchsuchen …" value="${esc(postSearch)}">
        <select class="smh-select" id="smh-campaign-filter" style="width:190px">
          <option value="all">Alle Ordner / Kampagnen</option>
          ${campaigns.map((c) => `<option value="${esc(c)}" ${postCampaignFilter === c ? 'selected' : ''}>${esc(c)}</option>`).join('')}
        </select>
        <select class="smh-select" id="smh-platform-filter" style="width:170px">
          <option value="all">Alle Plattformen</option>
          ${Object.entries(PLATFORMS).map(([k, info]) => `<option value="${k}" ${postPlatformFilter === k ? 'selected' : ''}>${esc(info.label)}</option>`).join('')}
        </select>
        <select class="smh-select" id="smh-post-sort" style="width:175px">
          <option value="newest" ${postSort === 'newest' ? 'selected' : ''}>Neueste zuerst</option>
          <option value="scheduled" ${postSort === 'scheduled' ? 'selected' : ''}>Nach Termin</option>
          <option value="title" ${postSort === 'title' ? 'selected' : ''}>Alphabetisch (A–Z)</option>
        </select>
      </div>

      ${selectedArr.length ? `
        <div class="smh-bulkbar">
          <strong>${selectedArr.length} ${selectedArr.length === 1 ? 'Beitrag' : 'Beiträge'} ausgewählt</strong>
          <div class="smh-bulk-actions">
            <button class="smh-btn sm" data-bulk="move">📂 Verschieben (Ordner / Status / Termin)</button>
            <button class="smh-btn sm" data-bulk="duplicate">📄 Duplizieren</button>
            <button class="smh-btn sm" data-bulk="export">⬇️ Auswahl exportieren</button>
            <button class="smh-btn sm danger-solid" data-bulk="delete">🗑️ Löschen</button>
            <button class="smh-btn sm" data-bulk="clear">Abwählen</button>
          </div>
        </div>
      ` : ''}

      ${list.length ? `
        <div class="smh-table-wrap">
          <table class="smh-table">
            <thead>
              <tr>
                <th style="width:40px"><input type="checkbox" id="smh-select-all-posts" ${allVisibleSelected ? 'checked' : ''} aria-label="Alle auswählen"></th>
                <th>Beitrag & Vorschau</th>
                <th>Ordner</th>
                <th>Plattformen</th>
                <th>Termin</th>
                <th>Status</th>
                <th style="text-align:right">Aktionen</th>
              </tr>
            </thead>
            <tbody>
              ${list.map((p) => {
                const checked = selectedPostIds.has(p.id);
                const media = findMedia(p.primary_media_id);
                const thumbHtml = media?.thumbnail_url || media?.url
                  ? `<img src="${esc(media.thumbnail_url || media.url)}" alt="">`
                  : (media?.kind === 'video' ? '🎞️' : (p.external_url ? '🔗' : '✍️'));
                const excerpt = (p.master_text || '').replace(/\s+/g, ' ').slice(0, 95);
                return `
                  <tr class="${checked ? 'selected' : ''}">
                    <td><input type="checkbox" class="smh-post-check" value="${esc(p.id)}" ${checked ? 'checked' : ''}></td>
                    <td style="min-width:280px">
                      <div class="smh-post-link-cell" data-open-detail="${esc(p.id)}" title="Klicken für große Beitrags-Übersicht" style="display:flex;align-items:center;gap:12px">
                        <div class="smh-thumb">${thumbHtml}</div>
                        <div style="min-width:0">
                          <strong style="display:block;font-size:13.5px">${esc(p.title || 'Ohne Titel')}</strong>
                          <div class="smh-hint" style="max-width:360px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(excerpt || 'Noch kein Text eingetragen')}</div>
                        </div>
                      </div>
                    </td>
                    <td><span class="smh-pill">${esc(p.campaign || 'Allgemein')}</span></td>
                    <td>
                      <div style="display:flex;gap:4px;flex-wrap:wrap">
                        ${(p.targets || []).map((tid) => { const a = findAccount(tid); return a ? platBadge(a.platform) : ''; }).join('') || '<span class="smh-hint">Keine</span>'}
                      </div>
                    </td>
                    <td style="font-size:12px;white-space:nowrap">${esc(formatDate(p.scheduled_at))}</td>
                    <td>
                      <span class="smh-pill ${esc(p.archived ? 'draft' : (p.summary?.composite_state || 'draft'))}">
                        ${esc(p.archived ? 'Archiviert' : (p.summary?.composite_label || 'Entwurf'))}
                      </span>
                    </td>
                    <td style="text-align:right;white-space:nowrap">
                      <div style="display:inline-flex;gap:5px">
                        <button class="smh-btn sm" data-open-detail="${esc(p.id)}">Öffnen</button>
                        <button class="smh-btn sm" data-edit-post="${esc(p.id)}" data-step="2">Bearbeiten</button>
                        <button class="smh-btn sm primary" data-edit-post="${esc(p.id)}" data-step="3">Prüfen & Senden</button>
                        <button class="smh-btn sm" data-single-move="${esc(p.id)}" title="In anderen Ordner, Status oder Termin verschieben">Verschieben</button>
                        <button class="smh-btn sm danger" data-single-delete="${esc(p.id)}" title="Löschen">✕</button>
                      </div>
                    </td>
                  </tr>
                `;
              }).join('')}
            </tbody>
          </table>
        </div>
      ` : `
        <div class="smh-empty">
          <div class="smh-empty-icon">📝</div>
          <h3>${allBase.length ? 'Keine Beiträge passen zu diesem Filter' : 'Noch keine Beiträge angelegt'}</h3>
          <p>${allBase.length ? 'Passe Suche oder Ordner-Filter an, um wieder alle Beiträge zu sehen.' : 'Starte in 3 einfachen Schritten: Plattformen auswählen, Text & Bild einfügen, Verbindung prüfen und mit 1 Klick veröffentlichen.'}</p>
          <button class="smh-btn primary lg" data-action="new-post">+ Neuen Beitrag erstellen</button>
        </div>
      `}
    `;
  }

  // ===========================================================================
  // ANSICHT 2B: GROSSE BEITRAGS-DETAILÜBERSICHT (BEIM ANKLICKEN IN DER LISTE)
  // ===========================================================================
  function renderPostDetail() {
    const p = (state.post_groups || []).find((x) => x.id === activeDetailPostId);
    if (!p) {
      view = 'posts';
      return renderPostsList();
    }
    const media = findMedia(p.primary_media_id);
    const targets = (p.targets || []).map((tid) => ({
      acc: findAccount(tid),
      check: p.summary?.per_target?.[tid] || {},
      v: p.variants?.[tid] || {},
    })).filter((item) => item.acc);
    const revisions = (state.revisions || []).filter((r) => r.post_group_id === p.id).slice(-5).reverse();

    return `
      <div class="smh-card" style="margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <button class="smh-btn" data-nav="posts">← Zurück zur Beitragsliste</button>
          <span class="smh-pill ${esc(p.archived ? 'draft' : (p.summary?.composite_state || 'draft'))}">
            ${esc(p.archived ? 'Archiviert' : (p.summary?.composite_label || 'Entwurf'))}
          </span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="smh-btn" data-edit-post="${esc(p.id)}" data-step="2">✏️ Bearbeiten</button>
          <button class="smh-btn primary" data-edit-post="${esc(p.id)}" data-step="3">🚀 Prüfen & Veröffentlichen</button>
          <button class="smh-btn" data-single-move="${esc(p.id)}">📂 Verschieben</button>
          <button class="smh-btn" data-single-duplicate="${esc(p.id)}">📄 Duplizieren</button>
          <button class="smh-btn danger" data-single-delete="${esc(p.id)}">🗑️ Löschen</button>
        </div>
      </div>

      <div class="smh-grid-composer">
        <div class="smh-card">
          <h2 style="margin:0 0 14px;font-size:20px;letter-spacing:-0.3px">${esc(p.title || 'Ohne Titel')}</h2>

          ${media ? `
            <div class="smh-detail-hero">
              ${media.url ? `<img src="${esc(media.url)}" alt="${esc(media.alt_text || media.name || '')}">` : `<div style="padding:48px;font-size:44px">🖼️</div>`}
            </div>
            <div class="smh-hint" style="margin:-10px 0 16px">
              Medium: <strong>${esc(media.name)}</strong> (${media.width_px || 1080}×${media.height_px || 1080}px)${media.alt_text ? ` · Alt-Text: „${esc(media.alt_text)}“` : ''}
            </div>
          ` : ''}

          <div style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:var(--smh-muted);margin-bottom:6px">Beitragstext (Caption & Hashtags)</div>
          <div class="smh-detail-text">${esc(p.master_text || 'Noch kein Text eingetragen.')}</div>

          ${p.external_url ? `
            <div class="smh-alert" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
              <span><strong>Verknüpfter Link:</strong> <a href="${esc(p.external_url)}" target="_blank" rel="noopener">${esc(p.external_url)} ↗</a></span>
            </div>
          ` : ''}

          <div class="smh-meta-strip">
            <div class="smh-meta-box"><small>Ordner / Kampagne</small><strong>${esc(p.campaign || 'Allgemein')}</strong></div>
            <div class="smh-meta-box"><small>Geplanter Termin</small><strong>${esc(formatDate(p.scheduled_at))}</strong></div>
            <div class="smh-meta-box"><small>Zuletzt aktualisiert</small><strong>${esc(formatDate(p.updated_at || p.created_at))}</strong></div>
          </div>
        </div>

        <div style="display:grid;gap:14px">
          <div class="smh-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
              <h3 style="margin:0;font-size:15px">Ausgewählte Kanäle (${targets.length})</h3>
              <button class="smh-btn sm" data-edit-post="${esc(p.id)}" data-step="1">Kanäle ändern</button>
            </div>
            ${targets.length ? targets.map(({ acc, check, v }) => {
              const deliv = v.delivery_status || 'pending';
              const isDone = deliv === 'published' || deliv === 'manual_confirmed';
              const isFailed = ['failed', 'failed_permanent', 'failed_retryable'].includes(deliv);
              const chStatus = channelStatusInfo(acc);
              return `
                <div class="smh-channel-item">
                  <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px">
                    <div style="display:flex;align-items:center;gap:8px;min-width:0">
                      ${platBadge(acc.platform)}
                      <div style="min-width:0">
                        <strong style="display:block;font-size:13px">${esc(PLATFORMS[acc.platform]?.label || acc.platform)}</strong>
                        <span class="smh-hint">${esc(acc.handle)} · Format: ${esc(v.format_key || 'Standard')}</span>
                      </div>
                    </div>
                    <span class="smh-pill ${esc(isDone ? 'published' : (isFailed ? 'blocked' : chStatus.cls))}">
                      ${esc(deliv === 'published' ? 'Veröffentlicht' : (deliv === 'manual_confirmed' ? 'Manuell bestätigt' : (isFailed ? 'Fehlgeschlagen' : chStatus.label)))}
                    </span>
                  </div>

                  ${check.effective_text && check.effective_text !== p.master_text ? `
                    <div style="font-size:12px;white-space:pre-wrap;background:#f7f9fd;border:1px solid var(--smh-line);border-radius:8px;padding:8px 10px;margin-bottom:8px">${esc(check.effective_text)}</div>
                  ` : ''}

                  ${(check.issues || []).map((iss) => `<div class="smh-alert danger" style="padding:7px 10px;margin-bottom:6px">✕ ${esc(iss)}</div>`).join('')}
                  ${(check.advice || []).map((adv) => `<div class="smh-alert warn" style="padding:7px 10px;margin-bottom:6px">ℹ ${esc(adv)}</div>`).join('')}
                  ${v.last_error ? `<div class="smh-alert danger" style="padding:7px 10px;margin-bottom:6px">✕ Letzter Versandfehler: ${esc(v.last_error)}</div>` : ''}
                  ${deliv === 'published' && v.remote_url ? `<div class="smh-alert success" style="padding:7px 10px;margin-bottom:6px">✓ Live · <a href="${esc(v.remote_url)}" target="_blank" rel="noopener">Beitrag öffnen ↗</a></div>` : ''}

                  <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
                    ${['manual', 'handoff'].includes(check.method) ? `
                      <button class="smh-btn sm" data-copy-text="${esc(check.effective_text || p.master_text)}">📋 Text kopieren</button>
                      ${deliv !== 'manual_confirmed'
                        ? `<button class="smh-btn sm success" data-open-manual-confirm="${esc(p.id)}" data-acc="${esc(acc.id)}">✓ Als veröffentlicht bestätigen</button>`
                        : `<button class="smh-btn sm danger" data-revoke-manual="${esc(p.id)}" data-acc="${esc(acc.id)}">Bestätigung widerrufen</button>`
                      }
                    ` : ''}
                    ${isFailed ? `<button class="smh-btn sm primary" data-retry-single="${esc(p.id)}" data-acc="${esc(acc.id)}">↻ Kanal erneut senden</button>` : ''}
                  </div>
                </div>
              `;
            }).join('') : `<p class="smh-hint">Diesem Beitrag sind noch keine Kanäle zugeordnet.</p>`}
          </div>

          ${revisions.length ? `
            <div class="smh-card">
              <h3 style="margin:0 0 10px;font-size:14px">Dokumentierte Stände (Revisionen)</h3>
              ${revisions.map((r) => `
                <div class="smh-row" style="padding:8px 0">
                  <span style="font-size:12px"><strong>Revision #${r.revision_number}</strong> (${esc(r.reason)})</span>
                  <span class="smh-hint">${esc(formatDate(r.created_at))}</span>
                </div>
              `).join('')}
            </div>
          ` : ''}
        </div>
      </div>
    `;
  }

  // ===========================================================================
  // ANSICHT 3: EINFACHER 3-SCHRITT-BEITRAGS-ASSISTENT (WIZARD)
  // ===========================================================================
  function renderWizard() {
    if (!wizard) {
      openNewPostWizard();
      return '';
    }
    syncWizardVariants();

    const step = wizard.step;
    const brandAccounts = (state.accounts || []).filter((a) => !wizard.brand_id || a.brand_id === wizard.brand_id);
    const chosenAccounts = wizard.targets.map(findAccount).filter(Boolean);
    const snippets = (state.snippets || []).filter((s) => !wizard.brand_id || s.brand_id === wizard.brand_id);
    const existingCampaigns = [...new Set(['Allgemein', ...(state.post_groups || []).map((p) => p.campaign || 'Allgemein')])];
    const selectedMedia = findMedia(wizard.primary_media_id);

    const stepperHtml = `
      <div class="smh-stepper">
        <button class="smh-step ${step === 1 ? 'active' : (step > 1 ? 'done' : '')}" data-wiz-step="1">
          <span class="smh-step-num">${step > 1 ? '✓' : '1'}</span>
          <div><strong>1. Plattformen wählen</strong><small>${chosenAccounts.length} Kanal/Kanäle gewählt</small></div>
        </button>
        <button class="smh-step ${step === 2 ? 'active' : (step > 2 ? 'done' : '')}" data-wiz-step="2">
          <span class="smh-step-num">${step > 2 ? '✓' : '2'}</span>
          <div><strong>2. Inhalt & Bild gestalten</strong><small>Text, Hashtags, Bild & Termin</small></div>
        </button>
        <button class="smh-step ${step === 3 ? 'active' : ''}" data-wiz-step="3">
          <span class="smh-step-num">3</span>
          <div><strong>3. Prüfen & 1-Klick-Veröffentlichen</strong><small>Verbindungen checken & starten</small></div>
        </button>
      </div>
    `;

    // SCHRITT 1: Plattformen & Formate auswählen
    if (step === 1) {
      return `
        ${stepperHtml}
        <div class="smh-card">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
            <div>
              <h3 style="margin:0 0 4px">Auf welchen Plattformen soll dieser Beitrag erscheinen?</h3>
              <p class="smh-hint" style="margin:0">Wähle einfach die gewünschten Kanäle aus und lege bei Bedarf direkt das Format fest.</p>
            </div>
            <button class="smh-btn" data-action="open-new-channel-wizard">+ Weiteren Kanal anlegen</button>
          </div>

          ${brandAccounts.length ? `
            <div class="smh-channel-pick-grid">
              ${brandAccounts.map((a) => {
                const chosen = wizard.targets.includes(a.id);
                const manifest = state.manifests?.[a.platform] || {};
                const formats = Object.entries(manifest.formats || {});
                const v = wizard.variants[a.id] || {};
                const stInfo = channelStatusInfo(a);
                return `
                  <div class="smh-channel-card ${chosen ? 'chosen' : ''}">
                    <label style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;cursor:pointer">
                      <div style="display:flex;align-items:center;gap:10px">
                        <input type="checkbox" data-wiz-toggle-target="${esc(a.id)}" ${chosen ? 'checked' : ''} style="width:17px;height:17px;accent-color:var(--smh-accent)">
                        ${platBadge(a.platform)}
                        <div>
                          <strong style="display:block;font-size:13.5px">${esc(PLATFORMS[a.platform]?.label || a.platform)}</strong>
                          <span class="smh-hint">${esc(a.handle)}</span>
                        </div>
                      </div>
                      <span class="smh-pill ${esc(stInfo.cls)}">${esc(stInfo.label)}</span>
                    </label>
                    ${chosen && formats.length ? `
                      <div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--smh-line)">
                        <label class="smh-hint" style="display:block;margin-bottom:4px;font-weight:700">Format auf diesem Kanal:</label>
                        <select class="smh-select" data-wiz-format="${esc(a.id)}">
                          ${formats.map(([fk, fspec]) => `<option value="${esc(fk)}" ${v.format_key === fk ? 'selected' : ''}>${esc(fspec.label)}</option>`).join('')}
                        </select>
                      </div>
                    ` : ''}
                  </div>
                `;
              }).join('')}
            </div>
          ` : `
            <div class="smh-empty" style="margin:12px auto">
              <div class="smh-empty-icon">🔌</div>
              <h3>Zuerst mindestens einen Kanal anlegen</h3>
              <p>Wähle mit einem Klick aus, welche Plattformen du bespielen möchtest (z. B. Instagram, Facebook, LinkedIn oder WhatsApp-Kanal).</p>
              <button class="smh-btn primary lg" data-action="open-new-channel-wizard">+ Ersten Kanal jetzt anlegen</button>
            </div>
          `}

          <div class="smh-wizard-footer">
            <button class="smh-btn" data-nav="posts">Abbrechen</button>
            <button class="smh-btn primary lg" data-wiz-step="2" ${chosenAccounts.length ? '' : 'disabled'}>Weiter zu Inhalt & Bild →</button>
          </div>
        </div>
      `;
    }

    // SCHRITT 2: Inhalt, Bild, Hashtags, Link & Termin gestalten
    if (step === 2) {
      const prevAcc = findAccount(wizardPreviewAccountId) || chosenAccounts[0] || null;
      const prevVar = prevAcc ? (wizard.variants[prevAcc.id] || {}) : {};
      const prevTextBase = (prevVar.text_override || wizard.master_text || '').trim();
      const clickableLink = prevAcc && !['instagram', 'tiktok'].includes(prevAcc.platform) && wizard.external_url && !prevTextBase.includes(wizard.external_url);
      const prevFinalText = clickableLink ? `${prevTextBase}\n\n🔗 ${wizard.external_url}` : prevTextBase;
      const graphemes = countGraphemes(prevFinalText);
      const charLimit = prevAcc ? (state.manifests?.[prevAcc.platform]?.default_char_limit || null) : null;

      return `
        ${stepperHtml}
        <div class="smh-grid-composer">
          <div class="smh-card">
            <div class="smh-field">
              <label for="wiz-text">Beitragstext (Caption & Hashtags)</label>
              <textarea id="wiz-text" class="smh-textarea" placeholder="Schreibe deinen Text für den Beitrag …">${esc(wizard.master_text)}</textarea>
              <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:6px">
                <span class="smh-hint" id="wiz-master-count">${countGraphemes(wizard.master_text)} Zeichen</span>
                ${snippets.length ? `
                  <div style="display:flex;gap:5px;flex-wrap:wrap">
                    ${snippets.map((s) => `<button type="button" class="smh-btn sm" data-wiz-insert-snippet="${esc(s.id)}">+ ${esc(s.title)}</button>`).join('')}
                  </div>
                ` : ''}
              </div>
            </div>

            <div class="smh-field">
              <label>Bild oder Video hinzufügen</label>
              <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <button type="button" class="smh-btn primary" data-wiz-pick-media="1">🖼️ Aus WordPress-Mediathek wählen / hochladen</button>
                ${(state.media_assets || []).length ? `
                  <select class="smh-select" id="wiz-media-select" style="width:240px">
                    <option value="">Ohne Medium</option>
                    ${state.media_assets.map((m) => `<option value="${esc(m.id)}" ${wizard.primary_media_id === m.id ? 'selected' : ''}>${esc(m.name)}</option>`).join('')}
                  </select>
                ` : ''}
                ${wizard.primary_media_id ? `<button type="button" class="smh-btn sm danger" data-wiz-clear-media="1">Bild entfernen</button>` : ''}
              </div>
              ${selectedMedia ? `
                <div style="margin-top:8px;display:flex;gap:12px;align-items:center;background:#f6f8fd;padding:10px;border-radius:10px;border:1px solid var(--smh-line)">
                  <div class="smh-thumb">${selectedMedia.thumbnail_url || selectedMedia.url ? `<img src="${esc(selectedMedia.thumbnail_url || selectedMedia.url)}" alt="">` : '🖼️'}</div>
                  <div style="font-size:12px">
                    <strong>${esc(selectedMedia.name)}</strong>
                    <div class="smh-hint">${selectedMedia.width_px || 1080}×${selectedMedia.height_px || 1080}px · Alt-Text: ${esc(selectedMedia.alt_text || 'Noch nicht gepflegt')}</div>
                  </div>
                </div>
              ` : ''}
            </div>

            <div class="smh-field">
              <label for="wiz-link">Optionaler Link (z. B. neues YouTube-Video oder Webseite)</label>
              <input id="wiz-link" type="url" class="smh-input" value="${esc(wizard.external_url)}" placeholder="https://www.youtube.com/watch?v=... oder https://janbruening.de/...">
              <span class="smh-hint">Wird auf klickbaren Kanälen (Facebook, LinkedIn, WhatsApp, Bluesky, Mastodon) automatisch übernommen. Auf Instagram erscheint ein Hinweis für „Link in der Bio“.</span>
            </div>

            <div class="smh-grid2">
              <div class="smh-field">
                <label for="wiz-title">Bezeichnung für deine Beitragsliste (optional)</label>
                <input id="wiz-title" class="smh-input" value="${esc(wizard.title)}" placeholder="Leer lassen = automatisch aus erster Zeile">
              </div>
              <div class="smh-field">
                <label for="wiz-campaign">Ordner / Kampagne</label>
                <input id="wiz-campaign" list="smh-campaign-datalist" class="smh-input" value="${esc(wizard.campaign)}" placeholder="z. B. Allgemein, YouTube-Hinweise">
                <datalist id="smh-campaign-datalist">${existingCampaigns.map((c) => `<option value="${esc(c)}">`).join('')}</datalist>
              </div>
            </div>

            <div class="smh-grid2">
              <div class="smh-field">
                <label for="wiz-schedule-mode">Wann soll veröffentlicht werden?</label>
                <select id="wiz-schedule-mode" class="smh-select">
                  <option value="now" ${wizard.schedule_mode === 'now' ? 'selected' : ''}>Sofort in Schritt 3 mit 1 Klick veröffentlichen</option>
                  <option value="scheduled" ${wizard.schedule_mode === 'scheduled' ? 'selected' : ''}>Für einen bestimmten Termin einplanen</option>
                </select>
              </div>
              ${wizard.schedule_mode === 'scheduled' ? `
                <div class="smh-field">
                  <label for="wiz-date">Datum & Uhrzeit (Europe/Berlin)</label>
                  <input id="wiz-date" type="datetime-local" class="smh-input" value="${esc(toLocalDateTimeInput(wizard.scheduled_at))}">
                </div>
              ` : `<div></div>`}
            </div>

            <details style="margin-top:8px;border:1px solid var(--smh-line);border-radius:11px;padding:12px;background:#fafbfe" ${chosenAccounts.some((a) => ['pinterest', 'tiktok'].includes(a.platform)) ? 'open' : ''}>
              <summary style="cursor:pointer;font-weight:700;font-size:13px">Feinanpassung & Pflichtfelder je Plattform (optional)</summary>
              <div style="margin-top:12px;display:grid;gap:14px">
                ${chosenAccounts.map((a) => {
                  const v = wizard.variants[a.id] || { metadata: {} };
                  return `
                    <div style="background:#fff;border:1px solid var(--smh-line);border-radius:10px;padding:12px">
                      <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                        ${platBadge(a.platform)} <strong>${esc(PLATFORMS[a.platform]?.label || a.platform)} · ${esc(a.handle)}</strong>
                      </div>
                      <textarea class="smh-textarea" style="min-height:64px" data-wiz-override="${esc(a.id)}" placeholder="Leer lassen = gemeinsamer Text oben wird verwendet">${esc(v.text_override || '')}</textarea>
                      ${a.platform === 'pinterest' ? `
                        <div class="smh-grid2" style="margin-top:8px">
                          <input class="smh-input" data-wiz-meta="board" data-acc="${esc(a.id)}" placeholder="Pinterest-Pinnwand (Board) *" value="${esc(v.metadata?.board || '')}">
                          <input class="smh-input" data-wiz-meta="title" data-acc="${esc(a.id)}" placeholder="Pin-Titel *" value="${esc(v.metadata?.title || '')}">
                        </div>
                      ` : ''}
                      ${a.platform === 'tiktok' ? `
                        <div class="smh-grid2" style="margin-top:8px;align-items:center">
                          <select class="smh-select" data-wiz-meta="privacy" data-acc="${esc(a.id)}">
                            <option value="">Sichtbarkeit wählen *</option>
                            <option value="public" ${v.metadata?.privacy === 'public' ? 'selected' : ''}>Öffentlich</option>
                            <option value="friends" ${v.metadata?.privacy === 'friends' ? 'selected' : ''}>Freunde</option>
                            <option value="private" ${v.metadata?.privacy === 'private' ? 'selected' : ''}>Privat</option>
                          </select>
                          <label style="font-size:12px;display:flex;align-items:center;gap:6px">
                            <input type="checkbox" data-wiz-meta="consent" data-acc="${esc(a.id)}" ${v.metadata?.consent ? 'checked' : ''}> Ausdrückliche TikTok-Freigabe *
                          </label>
                        </div>
                      ` : ''}
                    </div>
                  `;
                }).join('')}
              </div>
            </details>

            <div class="smh-wizard-footer">
              <button class="smh-btn" data-wiz-step="1">← Zurück zu Plattformen</button>
              <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button class="smh-btn" data-wiz-save="draft">Als Entwurf speichern</button>
                <button class="smh-btn primary lg" data-wiz-save="step3">Weiter zur Prüfung & Veröffentlichung →</button>
              </div>
            </div>
          </div>

          <!-- Rechte Live-Vorschau -->
          <div class="smh-card" style="position:sticky;top:110px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
              <strong style="font-size:13.5px">Live-Vorschau</strong>
              <select class="smh-select" id="wiz-preview-select" style="width:190px">
                ${chosenAccounts.map((a) => `<option value="${esc(a.id)}" ${prevAcc?.id === a.id ? 'selected' : ''}>${esc(PLATFORMS[a.platform]?.label || a.platform)}</option>`).join('')}
              </select>
            </div>
            <div class="smh-preview-card">
              <div style="padding:12px 14px;border-bottom:1px solid var(--smh-line);display:flex;align-items:center;gap:9px">
                ${prevAcc ? platBadge(prevAcc.platform) : ''}
                <div>
                  <strong style="display:block;font-size:12.5px">${esc(prevAcc?.handle || 'Kanal wählen')}</strong>
                  <span class="smh-hint">${esc(prevVar.format_key || 'Beitrag')}</span>
                </div>
              </div>
              ${selectedMedia ? `
                <div class="smh-preview-media">
                  ${selectedMedia.url ? `<img src="${esc(selectedMedia.url)}" alt="">` : `<span style="font-size:42px">🖼️</span>`}
                </div>
              ` : ''}
              <div class="smh-preview-body">${esc(prevFinalText || 'Hier erscheint die Vorschau deines Beitragstextes …')}</div>
              <div style="padding:10px 14px;border-top:1px solid var(--smh-line);font-size:11.5px;color:var(--smh-muted);display:flex;justify-content:space-between">
                <span id="wiz-preview-count">${graphemes}${charLimit ? ` / ${charLimit}` : ''} Zeichen</span>
                <span id="wiz-preview-hint">${prevAcc?.platform === 'instagram' && wizard.external_url ? 'Hinweis: Link in Bio' : 'Vorschau'}</span>
              </div>
            </div>
          </div>
        </div>
      `;
    }

    // SCHRITT 3: Finale Prüfung, Verbindungs-Check & 1-Klick-Veröffentlichung
    const savedPost = wizard.id ? (state.post_groups || []).find((p) => p.id === wizard.id) : null;
    const summary = savedPost?.summary || { ready: 0, manual: 0, handoff: 0, blocked: 0, per_target: {} };

    return `
      ${stepperHtml}
      <div class="smh-card" style="margin-bottom:18px">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px">
          <div>
            <h3 style="margin:0 0 4px">${esc(savedPost?.title || wizard.title || 'Neuer Beitrag')}</h3>
            <p class="smh-hint" style="margin:0">
              Ordner: <strong>${esc(wizard.campaign || 'Allgemein')}</strong> ·
              ${wizard.schedule_mode === 'scheduled' && wizard.scheduled_at ? `Geplant für: <strong>${esc(formatDate(wizard.scheduled_at))}</strong>` : '<strong>Bereit für sofortige Veröffentlichung</strong>'}
            </p>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <span class="smh-pill ready">${summary.ready || 0} automatisch bereit</span>
            <span class="smh-pill manual">${(summary.manual || 0) + (summary.handoff || 0)} manuell / App</span>
            ${summary.blocked ? `<span class="smh-pill blocked">${summary.blocked} prüfen</span>` : ''}
          </div>
        </div>
      </div>

      <div class="smh-grid3">
        ${chosenAccounts.map((acc) => {
          const check = summary.per_target?.[acc.id] || { key: 'unknown', label: 'Noch nicht gespeichert', issues: [], advice: [], effective_text: wizard.master_text };
          const v = savedPost?.variants?.[acc.id] || wizard.variants[acc.id] || {};
          const deliv = v.delivery_status || 'pending';
          const isDone = deliv === 'published' || deliv === 'manual_confirmed';
          const isFailed = ['failed', 'failed_permanent', 'failed_retryable'].includes(deliv);
          return `
            <div class="smh-card">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <div style="display:flex;align-items:center;gap:8px">
                  ${platBadge(acc.platform)}
                  <div>
                    <strong style="display:block;font-size:13px">${esc(PLATFORMS[acc.platform]?.label || acc.platform)}</strong>
                    <span class="smh-hint">${esc(acc.handle)}</span>
                  </div>
                </div>
                <span class="smh-pill ${esc(isDone ? 'published' : (isFailed ? 'blocked' : check.key))}">
                  ${esc(deliv === 'published' ? 'Veröffentlicht' : (deliv === 'manual_confirmed' ? 'Manuell bestätigt' : (isFailed ? 'Fehlgeschlagen' : check.label)))}
                </span>
              </div>

              ${selectedMedia?.thumbnail_url || selectedMedia?.url ? `
                <div class="smh-preview-media" style="border-radius:10px;min-height:130px;max-height:170px;margin-bottom:10px">
                  <img src="${esc(selectedMedia.thumbnail_url || selectedMedia.url)}" alt="">
                </div>
              ` : ''}

              <div style="font-size:12px;white-space:pre-wrap;background:#f7f9fd;padding:10px;border-radius:9px;border:1px solid var(--smh-line);max-height:120px;overflow:auto;margin-bottom:10px">
                ${esc(check.effective_text || wizard.master_text || 'Kein Text')}
              </div>

              ${(check.issues || []).map((iss) => `<div class="smh-alert danger" style="padding:8px 10px;margin-bottom:6px">✕ ${esc(iss)}</div>`).join('')}
              ${(check.advice || []).map((adv) => `<div class="smh-alert warn" style="padding:8px 10px;margin-bottom:6px">ℹ ${esc(adv)}</div>`).join('')}
              ${!(check.issues || []).length && !isDone && !isFailed && ['ready', 'manual', 'handoff'].includes(check.key) ? `<div class="smh-alert success" style="padding:8px 10px;margin-bottom:6px">✓ ${check.key === 'ready' ? 'Live-Verbindung & Format sind bereit.' : 'Bereit für manuelle Übergabe / App-Abschluss.'}</div>` : ''}
              ${['unknown', 'not_configured', 'blocked'].includes(check.key) && !isDone ? `
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
                  <button class="smh-btn sm" data-test-channel="${esc(acc.id)}" ${testingChannelId === acc.id ? 'disabled' : ''}>${testingChannelId === acc.id ? '⏳ Prüfe live …' : '🔌 Verbindung live prüfen'}</button>
                  <button class="smh-btn sm" data-edit-channel="${esc(acc.id)}">Zugangsdaten bearbeiten</button>
                </div>
              ` : ''}

              ${deliv === 'published' && v.remote_url ? `
                <div class="smh-alert success" style="padding:8px 10px">✓ Live veröffentlicht · <a href="${esc(v.remote_url)}" target="_blank" rel="noopener">Beitrag ansehen ↗</a></div>
              ` : ''}

              ${isFailed && savedPost ? `
                <button class="smh-btn sm primary" data-retry-single="${esc(savedPost.id)}" data-acc="${esc(acc.id)}">↻ Nur diesen Kanal erneut senden</button>
              ` : ''}

              ${['manual', 'handoff'].includes(check.method) && savedPost ? `
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
                  <button class="smh-btn sm" data-copy-text="${esc(check.effective_text || wizard.master_text)}">📋 Text kopieren</button>
                  ${deliv !== 'manual_confirmed'
                    ? `<button class="smh-btn sm success" data-open-manual-confirm="${esc(savedPost.id)}" data-acc="${esc(acc.id)}">✓ Als veröffentlicht bestätigen</button>`
                    : `<button class="smh-btn sm danger" data-revoke-manual="${esc(savedPost.id)}" data-acc="${esc(acc.id)}">Bestätigung widerrufen</button>`
                  }
                </div>
              ` : ''}
            </div>
          `;
        }).join('')}
      </div>

      <div class="smh-card" style="margin-top:18px">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
          <button class="smh-btn" data-wiz-step="2">← Zurück zum Bearbeiten</button>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button class="smh-btn" data-nav="posts">Zur Beitragsliste</button>
            ${wizard.schedule_mode === 'scheduled' && wizard.scheduled_at ? `
              <button class="smh-btn primary lg" data-wiz-dispatch="schedule">📅 Mit 1 Klick für Termin einplanen</button>
            ` : `
              <button class="smh-btn success lg" data-wiz-dispatch="publish_now">🚀 Jetzt mit 1 Klick veröffentlichen</button>
            `}
          </div>
        </div>
      </div>
    `;
  }

  // ===========================================================================
  // ANSICHT 4: KALENDER
  // ===========================================================================
  function renderCalendar() {
    const y = calDate.getFullYear();
    const m = calDate.getMonth();
    const firstWeekday = (new Date(y, m, 1).getDay() + 6) % 7;
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const prevDays = new Date(y, m, 0).getDate();
    const monthLabel = new Intl.DateTimeFormat('de-DE', { month: 'long', year: 'numeric' }).format(calDate);
    const todayKey = new Date().toISOString().slice(0, 10);

    const posts = filteredPostsBase().filter((p) => !p.archived && p.scheduled_at);
    const keyOf = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

    let cells = '';
    for (let i = 0; i < 42; i++) {
      const n = i - firstWeekday + 1;
      const inMonth = n >= 1 && n <= daysInMonth;
      const dt = new Date(y, m, n);
      const dk = keyOf(dt);
      const dayPosts = posts.filter((p) => String(p.scheduled_at || '').slice(0, 10) === dk);
      cells += `
        <button class="smh-cal-day ${inMonth ? '' : 'outside'} ${dk === todayKey ? 'today' : ''}" data-cal-day="${dk}">
          <strong style="font-size:12px">${inMonth ? n : (n < 1 ? prevDays + n : n - daysInMonth)}</strong>
          ${dayPosts.slice(0, 3).map((p) => `<span class="smh-cal-chip">${esc(p.title)}</span>`).join('')}
          ${dayPosts.length > 3 ? `<span class="smh-hint">+${dayPosts.length - 3} weitere</span>` : ''}
        </button>
      `;
    }

    const activeDayPosts = selectedCalDay ? posts.filter((p) => String(p.scheduled_at || '').slice(0, 10) === selectedCalDay) : [];

    return `
      <div class="smh-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
          <h3 style="margin:0;text-transform:capitalize">${esc(monthLabel)}</h3>
          <div style="display:flex;gap:6px">
            <button class="smh-btn sm" data-cal-nav="-1">‹ Zurück</button>
            <button class="smh-btn sm" data-cal-nav="0">Heute</button>
            <button class="smh-btn sm" data-cal-nav="1">Weiter ›</button>
          </div>
        </div>
        <div class="smh-cal-grid">
          ${['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'].map((d) => `<div class="smh-cal-head">${d}</div>`).join('')}
          ${cells}
        </div>
      </div>

      ${selectedCalDay ? `
        <div class="smh-card" style="margin-top:16px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <h3 style="margin:0">Geplant am ${esc(selectedCalDay)}</h3>
            <button class="smh-btn primary sm" data-new-post-day="${esc(selectedCalDay)}">+ Beitrag für diesen Tag erstellen</button>
          </div>
          ${activeDayPosts.length ? activeDayPosts.map((p) => `
            <div class="smh-row">
              <div class="smh-post-link-cell" data-open-detail="${esc(p.id)}" style="flex:1">
                <strong>${esc(p.title)}</strong>
                <div class="smh-hint">${esc(formatDate(p.scheduled_at))} · ${esc(p.campaign || 'Allgemein')}</div>
              </div>
              <div style="display:flex;gap:6px">
                <button class="smh-btn sm" data-open-detail="${esc(p.id)}">Öffnen</button>
                <button class="smh-btn sm" data-edit-post="${esc(p.id)}" data-step="2">Bearbeiten</button>
                <button class="smh-btn sm" data-single-move="${esc(p.id)}">Termin verschieben</button>
              </div>
            </div>
          `).join('') : `<p class="smh-hint">An diesem Tag ist noch kein Beitrag geplant.</p>`}
        </div>
      ` : ''}
    `;
  }

  // ===========================================================================
  // ANSICHT 5: MANUELLE ÜBERGABEN (WHATSAPP-KANAL, SIGNAL, TIKTOK-APP)
  // ===========================================================================
  function renderManualHandoffs() {
    const items = [];
    for (const p of filteredPostsBase().filter((x) => !x.archived)) {
      for (const tid of (p.targets || [])) {
        const a = findAccount(tid);
        const check = p.summary?.per_target?.[tid];
        if (!a || !['manual', 'handoff'].includes(check?.method || '')) continue;
        items.push({ p, a, check, v: p.variants?.[tid] || {} });
      }
    }

    return `
      <div class="smh-alert warn">
        <strong>Grundsatz:</strong> „Text kopiert“ gilt noch nicht als veröffentlicht. Bestätige einen Kanal erst, nachdem du den Beitrag tatsächlich in WhatsApp, Signal, Telegram oder der TikTok-App gepostet hast.
      </div>
      ${items.length ? `
        <div class="smh-card">
          ${items.map(({ p, a, check, v }) => {
            const done = v.delivery_status === 'manual_confirmed';
            const media = findMedia(p.primary_media_id);
            return `
              <div class="smh-row">
                <div style="min-width:0">
                  <div style="display:flex;align-items:center;gap:8px">
                    ${platBadge(a.platform)}
                    <strong>${esc(a.handle)}</strong>
                    <span class="smh-pill ${done ? 'published' : 'manual'}">${done ? 'Veröffentlicht bestätigt' : 'Offen'}</span>
                  </div>
                  <div style="margin-top:4px;font-weight:600">${esc(p.title)}</div>
                  <div class="smh-hint">${esc(formatDate(p.scheduled_at))}${v.remote_url ? ` · <a href="${esc(v.remote_url)}" target="_blank" rel="noopener">Nachweis-Link ↗</a>` : ''}</div>
                </div>
                <div style="display:flex;gap:7px;flex-wrap:wrap;flex-shrink:0">
                  <button class="smh-btn sm" data-copy-text="${esc(check.effective_text || p.master_text)}">📋 Text kopieren</button>
                  ${media?.url ? `<a class="smh-btn sm" href="${esc(media.url)}" download target="_blank" rel="noopener">⬇️ Bild laden</a>` : ''}
                  ${!done
                    ? `<button class="smh-btn sm success" data-open-manual-confirm="${esc(p.id)}" data-acc="${esc(a.id)}">✓ Veröffentlicht</button>`
                    : `<button class="smh-btn sm danger" data-revoke-manual="${esc(p.id)}" data-acc="${esc(a.id)}">Widerrufen</button>`
                  }
                </div>
              </div>
            `;
          }).join('')}
        </div>
      ` : `
        <div class="smh-empty">
          <div class="smh-empty-icon">✓</div>
          <h3>Keine offenen manuellen Übergaben</h3>
          <p>Sobald du einen Beitrag für einen WhatsApp-Kanal, Signal, Telegram (manuell) oder TikTok erstellst, erscheint er hier als einfache Schritt-für-Schritt-Checkliste.</p>
        </div>
      `}
    `;
  }

  // ===========================================================================
  // ANSICHT 6: MEDIEN & TEXTBAUSTEINE
  // ===========================================================================
  function renderMediaAndSnippets() {
    const media = (state.media_assets || []).filter((m) => activeBrand === 'all' || m.brand_id === activeBrand);
    const snippets = (state.snippets || []).filter((s) => activeBrand === 'all' || s.brand_id === activeBrand);
    const selMedia = [...selectedMediaIds].filter((id) => media.some((m) => m.id === id));

    return `
      <div class="smh-grid2">
        <div class="smh-card">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
            <h3 style="margin:0">Medienbibliothek (${media.length})</h3>
            <button class="smh-btn primary sm" data-action="upload-wp-media">+ Aus Mediathek / Hochladen</button>
          </div>
          ${selMedia.length ? `
            <div class="smh-bulkbar">
              <strong>${selMedia.length} Medien ausgewählt</strong>
              <button class="smh-btn sm danger-solid" data-bulk-media="delete">🗑️ Auswahl löschen</button>
            </div>
          ` : ''}
          ${media.length ? media.map((m) => `
            <div class="smh-row">
              <div style="display:flex;align-items:center;gap:10px;min-width:0">
                <input type="checkbox" class="smh-media-check" value="${esc(m.id)}" ${selectedMediaIds.has(m.id) ? 'checked' : ''}>
                <div class="smh-thumb">${m.thumbnail_url || m.url ? `<img src="${esc(m.thumbnail_url || m.url)}" alt="">` : '🖼️'}</div>
                <div style="min-width:0">
                  <strong style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(m.name)}</strong>
                  <div class="smh-hint">${m.width_px || 1080}×${m.height_px || 1080}px · Alt: ${esc(m.alt_text || '–')}</div>
                </div>
              </div>
              <button class="smh-btn sm danger" data-delete-media="${esc(m.id)}">✕</button>
            </div>
          `).join('') : `<p class="smh-hint">Noch keine Medien im Social Hub hinterlegt. Nutze den Button oben, um Bilder direkt aus der WordPress-Mediathek hinzuzufügen.</p>`}
        </div>

        <div class="smh-card">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
            <h3 style="margin:0">Hashtag- & Textbausteine (${snippets.length})</h3>
            <button class="smh-btn primary sm" data-action="open-new-snippet">+ Baustein anlegen</button>
          </div>
          ${snippets.length ? snippets.map((s) => `
            <div class="smh-row">
              <div style="min-width:0">
                <strong>${esc(s.title)}</strong>
                <div class="smh-hint" style="white-space:pre-wrap">${esc(s.content)}</div>
              </div>
              <button class="smh-btn sm danger" data-delete-snippet="${esc(s.id)}">✕</button>
            </div>
          `).join('') : `<p class="smh-hint">Speichere hier wiederkehrende Hashtag-Blöcke oder Schlusszeilen (z. B. YouTube-Hinweise), um sie beim Schreiben mit 1 Klick einzufügen.</p>`}
        </div>
      </div>
    `;
  }

  // ===========================================================================
  // ANSICHT 7: KANÄLE & MARKEN
  // ===========================================================================
  function renderChannelsAndBrands() {
    const accounts = (state.accounts || []).filter((a) => activeBrand === 'all' || a.brand_id === activeBrand);
    const brands = state.brands || [];

    return `
      <div class="smh-card" style="margin-bottom:18px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
          <div>
            <h3 style="margin:0 0 3px">Verbundene & manuelle Kanäle (${accounts.length})</h3>
            <p class="smh-hint" style="margin:0">Zugangsschlüssel (Tokens) werden serverseitig verschlüsselt im Tresor gespeichert.</p>
          </div>
          <button class="smh-btn primary" data-action="open-new-channel">+ Kanal hinzufügen</button>
        </div>

        ${accounts.length ? accounts.map((a) => {
          const stInfo = channelStatusInfo(a);
          const isTesting = testingChannelId === a.id;
          const diagMsg = a.last_check_message || a.capability_diagnosis?.reason || 'Noch nicht geprüft.';
          const httpMeta = [
            a.last_check_http_code ? `HTTP ${a.last_check_http_code}` : '',
            a.last_check_endpoint ? a.last_check_endpoint : '',
            a.last_checked_at ? `Geprüft: ${formatDate(a.last_checked_at)}` : '',
          ].filter(Boolean).join(' · ');
          return `
            <div class="smh-channel-item">
              <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                <div style="display:flex;align-items:center;gap:12px;min-width:0">
                  ${platBadge(a.platform)}
                  <div style="min-width:0">
                    <strong>${esc(a.handle)}</strong>
                    <span class="smh-hint" style="margin-left:6px">(${esc(PLATFORMS[a.platform]?.label || a.platform)}${brands.length > 1 ? ` · ${esc(brandName(a.brand_id))}` : ''})</span>
                    <div class="smh-hint">
                      Tresor: <strong>${esc(a.security_redaction?.has_access_token ? (a.security_redaction.token_hint || 'Schlüssel gespeichert') : 'Kein API-Schlüssel hinterlegt')}</strong>
                      ${a.external_account_id ? ` · Konto-ID: <code>${esc(a.external_account_id)}</code>` : ''}
                      ${a.instance_url ? ` · Instanz: ${esc(a.instance_url)}` : ''}
                    </div>
                  </div>
                </div>
                <div style="display:flex;gap:6px;align-items:center;flex-shrink:0;flex-wrap:wrap">
                  <span class="smh-pill ${esc(stInfo.cls)}">${esc(stInfo.label)}</span>
                  <button class="smh-btn sm primary" data-test-channel="${esc(a.id)}" ${isTesting ? 'disabled' : ''}>${isTesting ? '⏳ Prüfe live …' : '🔌 Live prüfen'}</button>
                  <button class="smh-btn sm" data-edit-channel="${esc(a.id)}">Zugangsdaten</button>
                  <button class="smh-btn sm danger" data-delete-channel="${esc(a.id)}">✕</button>
                </div>
              </div>
              <div class="smh-diag-box ${esc(stInfo.diagCls)}">
                <strong>${stInfo.cls === 'ready' ? '✓ Echt verifiziert:' : (stInfo.cls === 'blocked' ? '✕ Live-Prüfung fehlgeschlagen:' : (stInfo.cls === 'manual' ? 'ℹ Manueller Modus:' : 'ℹ Status:'))}</strong>
                ${esc(diagMsg)}
                ${httpMeta ? `<div style="margin-top:4px;opacity:.82;font-size:11px">${esc(httpMeta)}</div>` : ''}
              </div>
            </div>
          `;
        }).join('') : `
          <div class="smh-empty" style="margin:10px auto">
            <div class="smh-empty-icon">🔌</div>
            <h3>Noch keine Kanäle eingerichtet</h3>
            <p>Füge deine Social-Media-Konten oder deinen WhatsApp-Kanal hinzu, um Beiträge zu erstellen.</p>
            <button class="smh-btn primary" data-action="open-new-channel">+ Ersten Kanal anlegen</button>
          </div>
        `}
      </div>

      <div class="smh-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
          <div>
            <h3 style="margin:0 0 3px">Marken / Arbeitsbereiche (${brands.length})</h3>
            <p class="smh-hint" style="margin:0">Bei nur einer Marke bleibt die Oberfläche schlank. Erst ab 2 Marken erscheint oben ein Marken-Umschalter.</p>
          </div>
          <button class="smh-btn" data-action="open-new-brand">+ Weitere Marke anlegen</button>
        </div>
        ${brands.map((b) => `
          <div class="smh-row">
            <div style="display:flex;align-items:center;gap:10px">
              <span style="width:14px;height:14px;border-radius:4px;background:${esc(b.color || '#586EE7')};display:inline-block"></span>
              <div>
                <strong>${esc(b.name)}</strong>
                <div class="smh-hint">${esc(b.description || 'Keine Beschreibung')}</div>
              </div>
            </div>
            <div style="display:flex;gap:6px">
              <button class="smh-btn sm" data-edit-brand="${esc(b.id)}">Umbenennen</button>
              ${brands.length > 1 ? `<button class="smh-btn sm danger" data-delete-brand="${esc(b.id)}">✕</button>` : ''}
            </div>
          </div>
        `).join('')}
      </div>
    `;
  }

  // ===========================================================================
  // ANSICHT 8: EINSTELLUNGEN, AUTOMATION & KOMPLETT-EXPORT
  // ===========================================================================
  function renderSettings() {
    return `
      <div class="smh-grid2">
        <div class="smh-card">
          <h3 style="margin-top:0">Komplett-Export & Backup</h3>
          <p class="smh-hint">Exportiert alle Beiträge, Texte, Medien-Manifeste, Kanaleinstellungen (ohne geheime Zugangsdaten) und Nachweise als Datei zum Sichern oder Umziehen.</p>
          <div style="display:flex;gap:10px;flex-wrap:wrap;margin:14px 0">
            <button class="smh-btn primary" data-action="download-json-export">⬇️ Komplett-Backup (.json)</button>
            <button class="smh-btn" data-action="download-csv-export">📊 Beitragsarchiv (.csv)</button>
            <button class="smh-btn" data-action="trigger-import-json">⬆️ Backup importieren</button>
            <input type="file" id="smh-import-file" accept=".json,application/json" hidden>
          </div>

          <hr style="border:0;border-top:1px solid var(--smh-line);margin:18px 0">
          <h3 style="margin:0 0 6px;color:var(--smh-danger)">Arbeitsbereich leeren</h3>
          <p class="smh-hint">Entfernt alle Beiträge, Kanäle, Medien-Einträge und Bausteine und setzt den Hub auf einen komplett leeren Zustand zurück (mit zweifacher Sicherheitsabfrage).</p>
          <button class="smh-btn danger" data-action="reset-workspace">🗑️ Alle Daten im Hub löschen</button>
        </div>

        <div class="smh-card">
          <h3 style="margin-top:0">Automatisierung & Systemstatus</h3>
          <div class="smh-alert ${state.wartungsmodus_active ? 'success' : ''}" style="margin-bottom:14px">
            <strong>Wartungsmodus-Schutz:</strong> ${state.wartungsmodus_active ? 'JDB Wartungsmodus ist aktiv – Social Media Hub arbeitet im Backend und bei signierten Medien-Abrufen störungsfrei.' : 'Öffentliche Website aktiv · REST-API und Token-Tresor bereit.'}
          </div>
          ${(state.automation_rules || []).map((r) => `
            <div class="smh-row">
              <div>
                <strong>${esc(r.name)}</strong>
                <div class="smh-hint">${esc(r.detail)}</div>
              </div>
              <input type="checkbox" data-toggle-rule="${esc(r.id)}" ${r.enabled ? 'checked' : ''} style="width:18px;height:18px;accent-color:var(--smh-accent)">
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }

  // ===========================================================================
  // MODALS (ZWEISTUFIGES LÖSCHEN, VERSCHIEBEN, KANAL, MANUELLER NACHWEIS, MARKE, BAUSTEIN)
  // ===========================================================================
  function renderModal() {
    if (!activeModal) return '';

    // 1. ZWEISTUFIGE LÖSCH-SICHERHEITSABFRAGE
    if (activeModal.type === 'delete') {
      const m = activeModal;
      return `
        <div class="smh-overlay" data-close-modal="overlay">
          <div class="smh-modal">
            <div class="smh-modal-head">
              <h3>${m.step === 1 ? esc(m.title) : '⚠️ Zweite Sicherheitsabfrage'}</h3>
              <button class="smh-btn sm" data-close-modal="btn">✕</button>
            </div>
            <div class="smh-modal-body">
              ${m.step === 1 ? `
                <p style="margin-top:0">${esc(m.warningText)}</p>
                ${m.items.length ? `
                  <ul style="background:#f7f9fd;border:1px solid var(--smh-line);border-radius:10px;padding:12px 12px 12px 28px;margin:10px 0;font-size:13px;max-height:180px;overflow:auto">
                    ${m.items.map((lbl) => `<li>${esc(lbl)}</li>`).join('')}
                  </ul>
                ` : ''}
              ` : `
                <div class="smh-alert danger">
                  <strong>Bist du dir wirklich sicher?</strong><br>
                  Das endgültige Löschen kann nicht rückgängig gemacht werden. Alle ausgewählten Einträge werden dauerhaft entfernt.
                </div>
                <label style="display:flex;align-items:center;gap:9px;font-size:13px;font-weight:600;cursor:pointer;margin-top:12px">
                  <input type="checkbox" id="smh-delete-final-check" ${m.confirmedCheck ? 'checked' : ''} style="width:17px;height:17px;accent-color:var(--smh-danger)">
                  Ja, ich bin mir sicher und möchte endgültig löschen.
                </label>
              `}
            </div>
            <div class="smh-modal-foot">
              <button class="smh-btn" data-close-modal="btn">Abbrechen</button>
              ${m.step === 1
                ? `<button class="smh-btn danger" data-modal-delete-next="1">Weiter zum endgültigen Löschen →</button>`
                : `<button class="smh-btn danger-solid" data-modal-delete-confirm="1" ${m.confirmedCheck ? '' : 'disabled'}>Endgültig löschen</button>`
              }
            </div>
          </div>
        </div>
      `;
    }

    // 2. VERSCHIEBEN-MODAL (ORDNER, STATUS, TERMIN, MARKE)
    if (activeModal.type === 'move') {
      const m = activeModal;
      return `
        <div class="smh-overlay" data-close-modal="overlay">
          <div class="smh-modal">
            <div class="smh-modal-head">
              <h3>📂 ${m.ids.length === 1 ? 'Beitrag verschieben' : `${m.ids.length} Beiträge verschieben`}</h3>
              <button class="smh-btn sm" data-close-modal="btn">✕</button>
            </div>
            <div class="smh-modal-body">
              <div class="smh-field">
                <label>In Ordner / Kampagne verschieben (optional)</label>
                <input id="smh-move-campaign" list="smh-move-camp-list" class="smh-input" placeholder="Leer lassen = nicht ändern (oder neuen Namen eingeben)">
                <datalist id="smh-move-camp-list">${m.existingCampaigns.map((c) => `<option value="${esc(c)}">`).join('')}</datalist>
              </div>
              <div class="smh-field">
                <label>Status / Bereich ändern</label>
                <select id="smh-move-workflow" class="smh-select">
                  <option value="__keep__">— Nicht ändern —</option>
                  <option value="draft">Zu Entwürfen verschieben</option>
                  <option value="review">In Prüfung verschieben</option>
                  <option value="approved">Als freigegeben markieren</option>
                  <option value="archived">Ins Archiv verschieben</option>
                </select>
              </div>
              <div class="smh-field">
                <label>Termin verschieben (optional)</label>
                <input id="smh-move-date" type="datetime-local" class="smh-input">
                <label style="display:flex;align-items:center;gap:7px;font-weight:500;font-size:12px;margin-top:4px;cursor:pointer">
                  <input type="checkbox" id="smh-move-clear-date"> Bestehenden Termin entfernen (ohne festen Termin)
                </label>
              </div>
              ${(state.brands || []).length > 1 ? `
                <div class="smh-field">
                  <label>Zu anderer Marke verschieben</label>
                  <select id="smh-move-brand" class="smh-select">
                    <option value="">— Marke nicht ändern —</option>
                    ${state.brands.map((b) => `<option value="${esc(b.id)}">${esc(b.name)}</option>`).join('')}
                  </select>
                </div>
              ` : ''}
            </div>
            <div class="smh-modal-foot">
              <button class="smh-btn" data-close-modal="btn">Abbrechen</button>
              <button class="smh-btn primary" data-modal-move-save="1">Jetzt verschieben</button>
            </div>
          </div>
        </div>
      `;
    }

    // 3. KANAL ANLEGEN / BEARBEITEN
    if (activeModal.type === 'channel') {
      const a = activeModal.account;
      const isManual = ['whatsapp', 'signal'].includes(a.platform);
      const cred = PLATFORM_CREDS[a.platform] || {
        idLabel: 'Externe Konto-ID (optional)',
        idPlaceholder: '',
        tokenLabel: 'API-Schlüssel / Access Token',
        tokenHint: 'Wird verschlüsselt gespeichert und live gegen die Plattform-API geprüft.',
      };
      return `
        <div class="smh-overlay" data-close-modal="overlay">
          <div class="smh-modal">
            <div class="smh-modal-head">
              <h3>${a.id ? 'Kanal & Zugangsdaten bearbeiten' : 'Neuen Kanal anlegen'}</h3>
              <button class="smh-btn sm" data-close-modal="btn">✕</button>
            </div>
            <div class="smh-modal-body">
              <div class="smh-field">
                <label>Plattform *</label>
                <select id="smh-ch-platform" class="smh-select">
                  ${Object.entries(PLATFORMS).map(([k, info]) => `<option value="${k}" ${a.platform === k ? 'selected' : ''}>${esc(info.label)} — ${esc(info.hint)}</option>`).join('')}
                </select>
              </div>
              <div class="smh-field">
                <label>Anzeigename / Handle im Social Hub *</label>
                <input id="smh-ch-handle" class="smh-input" value="${esc(a.handle || '')}" placeholder="z. B. @janbruening oder WhatsApp-Kanal Südwestpfalz">
              </div>
              ${a.platform === 'linkedin' ? `
                <div class="smh-field">
                  <label>LinkedIn-Kontotyp</label>
                  <select id="smh-ch-type" class="smh-select">
                    <option value="person" ${a.account_type === 'person' ? 'selected' : ''}>Persönliches Profil</option>
                    <option value="organization" ${a.account_type === 'organization' ? 'selected' : ''}>Unternehmensseite</option>
                  </select>
                </div>
              ` : ''}
              ${!isManual ? `
                <div class="smh-field">
                  <label>Betriebsart</label>
                  <select id="smh-ch-mode" class="smh-select">
                    <option value="" ${a.connection_state !== 'manual' ? 'selected' : ''}>Echte API-Verbindung (mit Live-Prüfung)</option>
                    <option value="manual" ${a.connection_state === 'manual' ? 'selected' : ''}>Vorerst als manuellen Übergabe-Kanal führen (ohne API-Schlüssel)</option>
                  </select>
                </div>
                ${cred.needsInstance ? `
                  <div class="smh-field">
                    <label>Mastodon Instanz-URL *</label>
                    <input id="smh-ch-instance" type="url" class="smh-input" value="${esc(a.instance_url || '')}" placeholder="https://mastodon.social">
                  </div>
                ` : ''}
                <div class="smh-field">
                  <label>${esc(cred.idLabel)}</label>
                  <input id="smh-ch-ext-id" class="smh-input" value="${esc(a.external_account_id || '')}" placeholder="${esc(cred.idPlaceholder)}">
                </div>
                <div class="smh-field">
                  <label>${esc(cred.tokenLabel)}</label>
                  <input id="smh-ch-token" type="password" class="smh-input" autocomplete="new-password" placeholder="${a.security_redaction?.has_access_token ? 'Gespeichert (' + esc(a.security_redaction.token_hint) + ') – leer lassen zum Behalten' : 'Echten API-Token / Schlüssel einfügen (oder leer lassen)'}">
                  <span class="smh-hint">${esc(cred.tokenHint)}</span>
                  ${a.security_redaction?.has_access_token ? `
                    <label style="display:flex;align-items:center;gap:7px;font-weight:500;font-size:12px;margin-top:4px;cursor:pointer;color:var(--smh-danger)">
                      <input type="checkbox" id="smh-ch-clear-token"> Gespeicherten API-Schlüssel entfernen
                    </label>
                  ` : ''}
                </div>
                <div class="smh-alert" style="margin-bottom:0">
                  <strong>Ehrlicher Live-Check:</strong> Ein Kanal wird erst dann als <em>„Live verbunden ✓“</em> markiert, wenn die Plattform-API den Schlüssel wirklich mit HTTP 200 bestätigt hat.
                </div>
              ` : `<div class="smh-alert warn">WhatsApp-Kanäle und Signal werden über unseren einfachen Kopier- & Bestätigungs-Assistenten gepflegt.</div>`}
              ${(state.brands || []).length > 1 ? `
                <div class="smh-field" style="margin-top:14px">
                  <label>Marke</label>
                  <select id="smh-ch-brand" class="smh-select">
                    ${state.brands.map((b) => `<option value="${esc(b.id)}" ${a.brand_id === b.id ? 'selected' : ''}>${esc(b.name)}</option>`).join('')}
                  </select>
                </div>
              ` : ''}
            </div>
            <div class="smh-modal-foot">
              <button class="smh-btn" data-close-modal="btn">Abbrechen</button>
              <button class="smh-btn primary" data-modal-channel-save="1">${isManual ? 'Kanal speichern' : 'Speichern & live prüfen'}</button>
            </div>
          </div>
        </div>
      `;
    }

    // 4. MANUELLEN NACHWEIS BESTÄTIGEN
    if (activeModal.type === 'manual-confirm') {
      const m = activeModal;
      return `
        <div class="smh-overlay" data-close-modal="overlay">
          <div class="smh-modal">
            <div class="smh-modal-head">
              <h3>Veröffentlichung dokumentieren</h3>
              <button class="smh-btn sm" data-close-modal="btn">✕</button>
            </div>
            <div class="smh-modal-body">
              <p style="margin-top:0"><strong>${esc(m.postTitle)}</strong> · ${esc(m.handle)}</p>
              <div class="smh-field">
                <label>Link zum veröffentlichten Beitrag (optional)</label>
                <input id="smh-mc-url" type="url" class="smh-input" value="${esc(m.externalUrl)}" placeholder="https://...">
              </div>
              <div class="smh-field">
                <label>Kurze Notiz (optional)</label>
                <textarea id="smh-mc-notes" class="smh-textarea" style="min-height:70px" placeholder="z. B. Im Kanal mit Umfrage veröffentlicht">${esc(m.notes)}</textarea>
              </div>
            </div>
            <div class="smh-modal-foot">
              <button class="smh-btn" data-close-modal="btn">Abbrechen</button>
              <button class="smh-btn success" data-modal-mc-save="1">✓ Als tatsächlich veröffentlicht bestätigen</button>
            </div>
          </div>
        </div>
      `;
    }

    // 5. MARKE / BAUSTEIN
    if (activeModal.type === 'brand') {
      const b = activeModal.brand;
      return `
        <div class="smh-overlay" data-close-modal="overlay">
          <div class="smh-modal">
            <div class="smh-modal-head">
              <h3>${b.id ? 'Marke bearbeiten' : 'Neue Marke anlegen'}</h3>
              <button class="smh-btn sm" data-close-modal="btn">✕</button>
            </div>
            <div class="smh-modal-body">
              <div class="smh-field"><label>Name der Marke *</label><input id="smh-br-name" class="smh-input" value="${esc(b.name || '')}" placeholder="z. B. Jan Design Digital"></div>
              <div class="smh-field"><label>Kurzbeschreibung</label><input id="smh-br-desc" class="smh-input" value="${esc(b.description || '')}"></div>
              <div class="smh-field"><label>Farbe</label><input id="smh-br-color" type="color" value="${esc(b.color || '#586EE7')}" style="width:60px;height:36px;border:1px solid var(--smh-line);border-radius:8px"></div>
            </div>
            <div class="smh-modal-foot">
              <button class="smh-btn" data-close-modal="btn">Abbrechen</button>
              <button class="smh-btn primary" data-modal-brand-save="1">Speichern</button>
            </div>
          </div>
        </div>
      `;
    }

    if (activeModal.type === 'snippet') {
      return `
        <div class="smh-overlay" data-close-modal="overlay">
          <div class="smh-modal">
            <div class="smh-modal-head">
              <h3>Neuer Hashtag- / Textbaustein</h3>
              <button class="smh-btn sm" data-close-modal="btn">✕</button>
            </div>
            <div class="smh-modal-body">
              <div class="smh-field"><label>Bezeichnung *</label><input id="smh-sn-title" class="smh-input" placeholder="z. B. Standard-Hashtags Webdesign"></div>
              <div class="smh-field"><label>Inhalt *</label><textarea id="smh-sn-content" class="smh-textarea" placeholder="#Webdesign #Digitalität ..."></textarea></div>
            </div>
            <div class="smh-modal-foot">
              <button class="smh-btn" data-close-modal="btn">Abbrechen</button>
              <button class="smh-btn primary" data-modal-snippet-save="1">Baustein speichern</button>
            </div>
          </div>
        </div>
      `;
    }

    return '';
  }

  // ===========================================================================
  // HAUPT-RENDER
  // ===========================================================================
  function currentTopTitle() {
    const map = {
      dashboard: ['Übersicht', 'Alles Wichtige auf einen Blick'],
      posts: ['Beiträge', 'Erstellen, verwalten, verschieben und veröffentlichen'],
      detail: ['Beitrags-Übersicht', 'Große Detailansicht & Kanal-Status'],
      wizard: [wizard?.id ? 'Beitrag bearbeiten' : 'Neuer Beitrag', `Schritt ${wizard?.step || 1} von 3`],
      calendar: ['Kalender', 'Geplante Beiträge nach Datum'],
      manual: ['Manuelle Übergaben', 'WhatsApp-Kanal, Signal & App-Fertigstellung'],
      media: ['Medien & Bausteine', 'Bilder, Videos und Hashtag-Vorlagen'],
      channels: ['Kanäle & Marken', 'Echte Live-Verbindungen und Arbeitsbereiche'],
      settings: ['Einstellungen & Export', 'Backup, Umzug und Automatisierung'],
    };
    return map[view] || ['Social Media Hub', ''];
  }

  function render() {
    const [title, subtitle] = currentTopTitle();
    const totalPosts = filteredPostsBase().filter((p) => !p.archived).length;
    const manualOpen = filteredPostsBase().filter((p) => !p.archived).reduce((n, p) => {
      return n + (p.targets || []).filter((tid) => {
        const m = p.summary?.per_target?.[tid]?.method;
        return ['manual', 'handoff'].includes(m) && p.variants?.[tid]?.delivery_status !== 'manual_confirmed';
      }).length;
    }, 0);

    const viewHtml = {
      dashboard: renderDashboard,
      posts: renderPostsList,
      detail: renderPostDetail,
      wizard: renderWizard,
      calendar: renderCalendar,
      manual: renderManualHandoffs,
      media: renderMediaAndSnippets,
      channels: renderChannelsAndBrands,
      settings: renderSettings,
    }[view]?.() || renderDashboard();

    root.innerHTML = `
      <div class="smh-shell">
        <aside class="smh-sidebar">
          <div class="smh-identity">
            <div class="smh-brand-logo">S</div>
            <div>
              <strong>Social Hub</strong>
              <small>Version ${esc(state.version || '2.0.0')}</small>
            </div>
          </div>

          <div class="smh-sidebar-cta">
            <button class="smh-btn primary" data-action="new-post">+ Neuer Beitrag</button>
          </div>

          <nav class="smh-nav">
            <div class="smh-nav-group">Arbeitsbereich</div>
            <button class="smh-nav-btn ${view === 'dashboard' ? 'active' : ''}" data-nav="dashboard">
              <span class="smh-nav-left">${svg('home')} Übersicht</span>
            </button>
            <button class="smh-nav-btn ${view === 'posts' || view === 'detail' ? 'active' : ''}" data-nav="posts">
              <span class="smh-nav-left">${svg('list')} Beiträge</span>
              ${totalPosts ? `<span class="smh-nav-badge">${totalPosts}</span>` : ''}
            </button>
            <button class="smh-nav-btn ${view === 'calendar' ? 'active' : ''}" data-nav="calendar">
              <span class="smh-nav-left">${svg('cal')} Kalender</span>
            </button>
            <button class="smh-nav-btn ${view === 'manual' ? 'active' : ''}" data-nav="manual">
              <span class="smh-nav-left">${svg('hand')} Manuelle Übergaben</span>
              ${manualOpen ? `<span class="smh-nav-badge warn">${manualOpen}</span>` : ''}
            </button>

            <div class="smh-nav-group">Bibliothek & Konten</div>
            <button class="smh-nav-btn ${view === 'media' ? 'active' : ''}" data-nav="media">
              <span class="smh-nav-left">${svg('img')} Medien & Bausteine</span>
            </button>
            <button class="smh-nav-btn ${view === 'channels' ? 'active' : ''}" data-nav="channels">
              <span class="smh-nav-left">${svg('plug')} Kanäle & Marken</span>
            </button>

            <div class="smh-nav-group">System</div>
            <button class="smh-nav-btn ${view === 'settings' ? 'active' : ''}" data-nav="settings">
              <span class="smh-nav-left">${svg('cog')} Einstellungen & Export</span>
            </button>
          </nav>

          <div class="smh-sidefooter">
            <span class="smh-dot-online"></span> Bereit · Token-Tresor aktiv
          </div>
        </aside>

        <div class="smh-main">
          <header class="smh-topbar">
            <div class="smh-top-title">
              <h2>${esc(title)}</h2>
              <span class="smh-top-subtitle">${esc(subtitle)}</span>
            </div>
            <div class="smh-toptools">
              ${(state.brands || []).length > 1 ? `
                <select class="smh-select" id="smh-brand-select" style="width:190px">
                  <option value="all" ${activeBrand === 'all' ? 'selected' : ''}>Alle Marken (${state.brands.length})</option>
                  ${state.brands.map((b) => `<option value="${esc(b.id)}" ${activeBrand === b.id ? 'selected' : ''}>${esc(b.name)}</option>`).join('')}
                </select>
              ` : ''}
              ${view !== 'wizard' ? `<button class="smh-btn primary" data-action="new-post">+ Neuer Beitrag</button>` : ''}
            </div>
          </header>
          <main class="smh-content">${viewHtml}</main>
        </div>
      </div>
      ${renderModal()}
    `;
  }

  // Hilfsfunktion: Aktuellen Wizard-Entwurf über REST speichern
  async function persistWizardDraft() {
    if (!wizard) return null;
    syncWizardVariants();
    const payload = {
      id: wizard.id || undefined,
      brand_id: wizard.brand_id,
      title: wizard.title,
      campaign: wizard.campaign || 'Allgemein',
      master_text: wizard.master_text,
      external_url: wizard.external_url,
      primary_media_id: wizard.primary_media_id || null,
      primary_type: findMedia(wizard.primary_media_id)?.kind || (wizard.primary_media_id ? 'image' : 'text'),
      scheduled_at: wizard.schedule_mode === 'scheduled' && wizard.scheduled_at ? new Date(wizard.scheduled_at).toISOString() : null,
      approval_status: wizard.approval_status || 'approved',
      targets: wizard.targets,
      variants: wizard.variants,
    };
    const res = await api('/post-groups', 'POST', payload);
    if (res?.post_group?.id) {
      wizard.id = res.post_group.id;
      wizard.title = res.post_group.title;
    }
    return res;
  }

  function downloadFile(filename, content, mime) {
    const blob = new Blob([content], { type: mime });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }

  // ===========================================================================
  // EVENT-HANDLING (KLICKS, EINGABEN, ÄNDERUNGEN)
  // ===========================================================================
  root.addEventListener('click', async (e) => {
    try {
      // Modal schließen
      if (e.target.dataset.closeModal === 'overlay' || e.target.closest('[data-close-modal="btn"]')) {
        activeModal = null;
        render();
        return;
      }

      // Navigation
      const navBtn = e.target.closest('[data-nav]');
      if (navBtn) {
        view = navBtn.dataset.nav;
        render();
        return;
      }

      // Große Beitrags-Detailansicht öffnen
      const openDetailEl = e.target.closest('[data-open-detail]');
      if (openDetailEl) {
        openPostDetail(openDetailEl.dataset.openDetail);
        return;
      }

      // Beitrag bearbeiten / öffnen
      const editPostBtn = e.target.closest('[data-edit-post]');
      if (editPostBtn) {
        const post = (state.post_groups || []).find((p) => p.id === editPostBtn.dataset.editPost);
        if (post) openNewPostWizard(post, null, Number(editPostBtn.dataset.step || 2));
        return;
      }

      // Neuer Beitrag an Kalendertag
      const dayPostBtn = e.target.closest('[data-new-post-day]');
      if (dayPostBtn) {
        openNewPostWizard(null, dayPostBtn.dataset.newPostDay, 1);
        return;
      }

      // Allgemeine Aktionen
      const actBtn = e.target.closest('[data-action]');
      if (actBtn) {
        const act = actBtn.dataset.action;
        if (act === 'new-post') { openNewPostWizard(); return; }
        if (act === 'open-new-channel') { openChannelModal(null, false); return; }
        if (act === 'open-new-channel-wizard') { openChannelModal(null, true); return; }
        if (act === 'open-new-brand') { activeModal = { type: 'brand', brand: { id: '', name: '', description: '', color: '#586EE7' } }; render(); return; }
        if (act === 'open-new-snippet') { activeModal = { type: 'snippet' }; render(); return; }
        if (act === 'upload-wp-media') { openWordPressMediaPicker(() => render()); return; }
        if (act === 'download-json-export') {
          const res = await api('/export?format=json');
          downloadFile(res.filename || 'social-media-hub-backup.json', JSON.stringify(res.payload, null, 2), 'application/json');
          showToast('Komplett-Backup (.json) heruntergeladen.');
          return;
        }
        if (act === 'download-csv-export') {
          const res = await api('/export?format=csv');
          downloadFile(res.filename || 'social-media-hub-archiv.csv', res.content || '', 'text/csv;charset=utf-8');
          showToast('Beitragsarchiv (.csv) heruntergeladen.');
          return;
        }
        if (act === 'trigger-import-json') {
          document.getElementById('smh-import-file')?.click();
          return;
        }
        if (act === 'reset-workspace') {
          openDoubleDeleteModal({
            title: 'Gesamten Social Media Hub leeren?',
            items: ['Alle Beiträge & Entwürfe', 'Alle eingerichteten Kanäle', 'Alle Medien-Einträge & Textbausteine'],
            warningText: 'Möchtest du den Arbeitsbereich komplett auf den leeren Ausgangszustand zurücksetzen?',
            onConfirm: async () => {
              await api('/reset', 'POST', {});
              selectedPostIds.clear();
              selectedMediaIds.clear();
              wizard = null;
              view = 'dashboard';
              showToast('Arbeitsbereich wurde komplett geleert.');
            },
          });
          return;
        }
      }

      // Beitragsliste: Tabs
      const tabBtn = e.target.closest('[data-post-tab]');
      if (tabBtn) {
        postTab = tabBtn.dataset.postTab;
        selectedPostIds.clear();
        render();
        return;
      }

      // Einzel-Duplizieren (z. B. aus der Detailansicht)
      const singleDup = e.target.closest('[data-single-duplicate]');
      if (singleDup) {
        await api('/post-groups/bulk', 'POST', { action: 'duplicate', ids: [singleDup.dataset.singleDuplicate] });
        showToast('Beitrag als neuen Entwurf dupliziert.');
        view = 'posts';
        render();
        return;
      }

      // Einzel-Verschieben
      const singleMove = e.target.closest('[data-single-move]');
      if (singleMove) {
        openMovePostsModal([singleMove.dataset.singleMove]);
        return;
      }

      // Einzel-Löschen (2-stufig!)
      const singleDel = e.target.closest('[data-single-delete]');
      if (singleDel) {
        const pid = singleDel.dataset.singleDelete;
        const post = (state.post_groups || []).find((p) => p.id === pid);
        openDoubleDeleteModal({
          title: 'Beitrag wirklich löschen?',
          items: [post?.title || pid],
          onConfirm: async () => {
            await api('/post-groups/bulk', 'POST', { action: 'delete', ids: [pid] });
            selectedPostIds.delete(pid);
            if (activeDetailPostId === pid) {
              activeDetailPostId = null;
              view = 'posts';
            }
            showToast('Beitrag wurde endgültig gelöscht.');
          },
        });
        return;
      }

      // Mehrfachauswahl-Aktionen
      const bulkBtn = e.target.closest('[data-bulk]');
      if (bulkBtn) {
        const ids = [...selectedPostIds];
        const act = bulkBtn.dataset.bulk;
        if (act === 'clear') { selectedPostIds.clear(); render(); return; }
        if (act === 'move') { openMovePostsModal(ids); return; }
        if (act === 'duplicate') {
          await api('/post-groups/bulk', 'POST', { action: 'duplicate', ids });
          selectedPostIds.clear();
          showToast(`${ids.length} Beitrag/Beiträge dupliziert.`);
          render();
          return;
        }
        if (act === 'export') {
          const picked = (state.post_groups || []).filter((p) => selectedPostIds.has(p.id));
          downloadFile(`social-hub-auswahl-${picked.length}.json`, JSON.stringify({ exported_at: new Date().toISOString(), post_groups: picked }, null, 2), 'application/json');
          showToast(`${picked.length} ausgewählte Beiträge exportiert.`);
          return;
        }
        if (act === 'delete') {
          const names = (state.post_groups || []).filter((p) => selectedPostIds.has(p.id)).map((p) => p.title || p.id);
          openDoubleDeleteModal({
            title: `${ids.length} ausgewählte Beiträge löschen?`,
            items: names,
            onConfirm: async () => {
              await api('/post-groups/bulk', 'POST', { action: 'delete', ids });
              selectedPostIds.clear();
              showToast(`${ids.length} Beiträge endgültig gelöscht.`);
            },
          });
          return;
        }
      }

      // Wizard-Schritte
      const wizStepBtn = e.target.closest('[data-wiz-step]');
      if (wizStepBtn && wizard) {
        const nextStep = Number(wizStepBtn.dataset.wizStep);
        if (nextStep > 1 && wizard.targets.length === 0) {
          showToast('Bitte wähle zuerst mindestens einen Kanal aus.');
          return;
        }
        if (nextStep === 3) {
          await persistWizardDraft();
        }
        wizard.step = nextStep;
        render();
        return;
      }

      // Wizard speichern
      const wizSaveBtn = e.target.closest('[data-wiz-save]');
      if (wizSaveBtn && wizard) {
        await persistWizardDraft();
        if (wizSaveBtn.dataset.wizSave === 'step3') {
          wizard.step = 3;
          showToast('Entwurf gespeichert – bereit für die finale Prüfung.');
        } else {
          showToast('Beitrag als Entwurf gespeichert.');
          view = 'posts';
        }
        render();
        return;
      }

      // Wizard 1-Klick-Veröffentlichung / Planung
      const wizDispatch = e.target.closest('[data-wiz-dispatch]');
      if (wizDispatch && wizard) {
        await persistWizardDraft();
        const mode = wizDispatch.dataset.wizDispatch;
        const res = await api(`/post-groups/${wizard.id}/dispatch`, 'POST', { mode });
        showToast(mode === 'schedule'
          ? `Eingeplant: ${res.queued_or_published} API-Ziel(e), ${res.manual_prepared + res.handoff_prepared} manuell.`
          : `Ausgeführt: ${res.queued_or_published} veröffentlicht, ${res.manual_prepared + res.handoff_prepared} manuell offen.`);
        render();
        return;
      }

      // Einzel-Retry für fehlgeschlagenen Kanal (Idempotenz)
      const retryBtn = e.target.closest('[data-retry-single]');
      if (retryBtn) {
        await api(`/post-groups/${retryBtn.dataset.retrySingle}/dispatch`, 'POST', {
          mode: 'publish_now',
          only_account_ids: [retryBtn.dataset.acc],
        });
        showToast('Einzel-Wiederholung ausgeführt (bereits veröffentlichte Kanäle blieben unberührt).');
        render();
        return;
      }

      // Wizard: WP-Mediathek öffnen
      if (e.target.closest('[data-wiz-pick-media]')) {
        openWordPressMediaPicker((media) => {
          wizard.primary_media_id = media.id;
          syncWizardVariants();
          render();
        });
        return;
      }
      if (e.target.closest('[data-wiz-clear-media]') && wizard) {
        wizard.primary_media_id = '';
        syncWizardVariants();
        render();
        return;
      }

      // Wizard: Baustein einfügen
      const snipBtn = e.target.closest('[data-wiz-insert-snippet]');
      if (snipBtn && wizard) {
        const s = (state.snippets || []).find((x) => x.id === snipBtn.dataset.wizInsertSnippet);
        if (s) {
          wizard.master_text = wizard.master_text.trim() ? `${wizard.master_text.trim()}\n\n${s.content}` : s.content;
          render();
        }
        return;
      }

      // Text kopieren (WhatsApp / Manuell)
      const copyBtn = e.target.closest('[data-copy-text]');
      if (copyBtn) {
        await copyTextSafely(copyBtn.dataset.copyText || '');
        showToast('Text kopiert – bitte nach dem Posten auf „Veröffentlicht“ klicken.');
        return;
      }

      // Manuellen Nachweis öffnen / widerrufen
      const mcOpen = e.target.closest('[data-open-manual-confirm]');
      if (mcOpen) {
        openManualConfirmModal(mcOpen.dataset.openManualConfirm, mcOpen.dataset.acc);
        return;
      }
      const mcRevoke = e.target.closest('[data-revoke-manual]');
      if (mcRevoke) {
        await api(`/post-groups/${mcRevoke.dataset.revokeManual}/manual-receipt`, 'POST', {
          account_id: mcRevoke.dataset.acc,
          action: 'revoke',
        });
        showToast('Bestätigung wurde zurückgenommen.');
        render();
        return;
      }

      // Kalender-Navigation & Tag
      const calNav = e.target.closest('[data-cal-nav]');
      if (calNav) {
        const delta = Number(calNav.dataset.calNav);
        calDate = delta === 0 ? new Date(new Date().getFullYear(), new Date().getMonth(), 1) : new Date(calDate.getFullYear(), calDate.getMonth() + delta, 1);
        render();
        return;
      }
      const calDay = e.target.closest('[data-cal-day]');
      if (calDay) {
        selectedCalDay = calDay.dataset.calDay;
        render();
        return;
      }

      // Medien / Bausteine / Kanäle / Marken bearbeiten & löschen (2-stufig!)
      const delMedia = e.target.closest('[data-delete-media]');
      if (delMedia) {
        const m = findMedia(delMedia.dataset.deleteMedia);
        openDoubleDeleteModal({
          title: 'Medium aus dem Social Hub entfernen?',
          items: [m?.name || delMedia.dataset.deleteMedia],
          warningText: 'Das Medium wird aus der Social-Hub-Liste entfernt (die Originaldatei in deiner WordPress-Mediathek bleibt unberührt).',
          onConfirm: async () => {
            await api('/media/delete', 'POST', { ids: [delMedia.dataset.deleteMedia] });
            showToast('Medium entfernt.');
          },
        });
        return;
      }
      if (e.target.closest('[data-bulk-media="delete"]')) {
        const ids = [...selectedMediaIds];
        openDoubleDeleteModal({
          title: `${ids.length} Medien entfernen?`,
          items: ids.map((id) => findMedia(id)?.name || id),
          onConfirm: async () => {
            await api('/media/delete', 'POST', { ids });
            selectedMediaIds.clear();
            showToast(`${ids.length} Medien entfernt.`);
          },
        });
        return;
      }
      const delSnip = e.target.closest('[data-delete-snippet]');
      if (delSnip) {
        await api(`/snippets/${delSnip.dataset.deleteSnippet}`, 'DELETE');
        showToast('Textbaustein entfernt.');
        render();
        return;
      }
      const editCh = e.target.closest('[data-edit-channel]');
      if (editCh) {
        openChannelModal(findAccount(editCh.dataset.editChannel), false);
        return;
      }
      const testCh = e.target.closest('[data-test-channel]');
      if (testCh) {
        const cid = testCh.dataset.testChannel;
        testingChannelId = cid;
        render();
        try {
          const res = await api(`/accounts/${cid}/test`, 'POST', {});
          const v = res.verification || {};
          showToast(v.ok ? `✓ ${v.message || 'Live-Prüfung erfolgreich!'}` : `✕ ${v.message || res.diagnosis?.reason || 'Verbindung fehlgeschlagen.'}`);
        } finally {
          testingChannelId = null;
          render();
        }
        return;
      }
      const delCh = e.target.closest('[data-delete-channel]');
      if (delCh) {
        const acc = findAccount(delCh.dataset.deleteChannel);
        openDoubleDeleteModal({
          title: 'Kanal wirklich entfernen?',
          items: [acc?.handle || delCh.dataset.deleteChannel],
          warningText: 'Der Kanal und sein gespeicherter Token werden aus dem Social Hub entfernt.',
          onConfirm: async () => {
            await api(`/accounts/${delCh.dataset.deleteChannel}`, 'DELETE');
            showToast('Kanal entfernt.');
          },
        });
        return;
      }
      const editBr = e.target.closest('[data-edit-brand]');
      if (editBr) {
        activeModal = { type: 'brand', brand: { ...findBrand(editBr.dataset.editBrand) } };
        render();
        return;
      }
      const delBr = e.target.closest('[data-delete-brand]');
      if (delBr) {
        const br = findBrand(delBr.dataset.deleteBrand);
        openDoubleDeleteModal({
          title: 'Marke löschen?',
          items: [br?.name || delBr.dataset.deleteBrand],
          warningText: 'Zugeordnete Beiträge und Kanäle werden automatisch in deine Hauptmarke verschoben.',
          onConfirm: async () => {
            await api(`/brands/${delBr.dataset.deleteBrand}`, 'DELETE');
            if (activeBrand === delBr.dataset.deleteBrand) activeBrand = 'all';
            showToast('Marke entfernt.');
          },
        });
        return;
      }

      // Modal-Bestätigungen
      if (e.target.closest('[data-modal-delete-next]') && activeModal?.type === 'delete') {
        activeModal.step = 2;
        activeModal.confirmedCheck = false;
        render();
        return;
      }
      if (e.target.closest('[data-modal-delete-confirm]') && activeModal?.type === 'delete' && activeModal.confirmedCheck) {
        const fn = activeModal.onConfirm;
        activeModal = null;
        await fn();
        render();
        return;
      }
      if (e.target.closest('[data-modal-move-save]') && activeModal?.type === 'move') {
        const move = {};
        const camp = document.getElementById('smh-move-campaign')?.value.trim();
        const wf = document.getElementById('smh-move-workflow')?.value;
        const dt = document.getElementById('smh-move-date')?.value;
        const clearDate = document.getElementById('smh-move-clear-date')?.checked;
        const br = document.getElementById('smh-move-brand')?.value;
        if (camp) move.campaign = camp;
        if (wf && wf !== '__keep__') move.workflow_target = wf;
        if (clearDate) move.scheduled_at = null;
        else if (dt) move.scheduled_at = new Date(dt).toISOString();
        if (br) move.brand_id = br;
        if (Object.keys(move).length === 0) {
          showToast('Bitte wähle mindestens einen neuen Ordner, Status oder Termin.');
          return;
        }
        const ids = activeModal.ids;
        activeModal = null;
        await api('/post-groups/bulk', 'POST', { action: 'move', ids, move });
        selectedPostIds.clear();
        showToast(`${ids.length} Beitrag/Beiträge verschoben.`);
        render();
        return;
      }
      if (e.target.closest('[data-modal-channel-save]') && activeModal?.type === 'channel') {
        const platform = document.getElementById('smh-ch-platform')?.value || 'instagram';
        const handle = document.getElementById('smh-ch-handle')?.value.trim() || '';
        if (!handle) { showToast('Bitte gib einen Namen oder ein Handle für den Kanal ein.'); return; }
        const payload = {
          id: activeModal.account.id || undefined,
          brand_id: document.getElementById('smh-ch-brand')?.value || activeModal.account.brand_id,
          platform,
          account_type: document.getElementById('smh-ch-type')?.value || undefined,
          handle,
          external_account_id: document.getElementById('smh-ch-ext-id')?.value.trim() || '',
          instance_url: document.getElementById('smh-ch-instance')?.value.trim() || '',
          access_token: document.getElementById('smh-ch-token')?.value.trim() || '',
          clear_token: Boolean(document.getElementById('smh-ch-clear-token')?.checked),
          connection_state: document.getElementById('smh-ch-mode')?.value || '',
        };
        const saveBtn = e.target.closest('[data-modal-channel-save]');
        if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = '⏳ Speichere & prüfe live …'; }
        const returnToWiz = activeModal.returnToWizard;
        const res = await api('/accounts', 'POST', payload);
        activeModal = null;
        if (returnToWiz && wizard && res?.account_id) {
          if (!wizard.targets.includes(res.account_id)) wizard.targets.push(res.account_id);
          syncWizardVariants();
        }
        if (res?.verification) {
          showToast(res.verification.ok
            ? `✓ ${res.verification.message}`
            : `✕ Gespeichert, aber Live-Check fehlgeschlagen: ${res.verification.message}`);
        } else {
          showToast(`Kanal „${handle}“ gespeichert.`);
        }
        render();
        return;
      }
      if (e.target.closest('[data-modal-mc-save]') && activeModal?.type === 'manual-confirm') {
        const url = document.getElementById('smh-mc-url')?.value.trim() || '';
        const notes = document.getElementById('smh-mc-notes')?.value.trim() || '';
        const { postId, accountId } = activeModal;
        activeModal = null;
        await api(`/post-groups/${postId}/manual-receipt`, 'POST', {
          account_id: accountId,
          action: 'confirm',
          external_url: url,
          notes,
        });
        showToast('Manuelle Veröffentlichung dokumentiert.');
        render();
        return;
      }
      if (e.target.closest('[data-modal-brand-save]') && activeModal?.type === 'brand') {
        const name = document.getElementById('smh-br-name')?.value.trim();
        if (!name) { showToast('Bitte einen Markennamen eingeben.'); return; }
        await api('/brands', 'POST', {
          id: activeModal.brand.id || undefined,
          name,
          description: document.getElementById('smh-br-desc')?.value.trim() || '',
          color: document.getElementById('smh-br-color')?.value || '#586EE7',
        });
        activeModal = null;
        showToast(`Marke „${name}“ gespeichert.`);
        render();
        return;
      }
      if (e.target.closest('[data-modal-snippet-save]') && activeModal?.type === 'snippet') {
        const title = document.getElementById('smh-sn-title')?.value.trim();
        const content = document.getElementById('smh-sn-content')?.value.trim();
        if (!title || !content) { showToast('Bitte Bezeichnung und Inhalt ausfüllen.'); return; }
        await api('/snippets', 'POST', {
          brand_id: activeBrand !== 'all' ? activeBrand : state.brands[0]?.id,
          title,
          content,
        });
        activeModal = null;
        showToast('Textbaustein gespeichert.');
        render();
        return;
      }
    } catch (err) {
      showToast(err?.message || 'Aktion konnte nicht ausgeführt werden.');
    }
  });

  root.addEventListener('change', async (e) => {
    const t = e.target;
    try {
      if (t.id === 'smh-brand-select') { activeBrand = t.value; selectedPostIds.clear(); render(); return; }
      if (t.id === 'smh-campaign-filter') { postCampaignFilter = t.value; render(); return; }
      if (t.id === 'smh-platform-filter') { postPlatformFilter = t.value; render(); return; }
      if (t.id === 'smh-post-sort') { postSort = t.value; render(); return; }

      if (t.id === 'smh-select-all-posts') {
        const list = visiblePosts();
        if (t.checked) list.forEach((p) => selectedPostIds.add(p.id));
        else list.forEach((p) => selectedPostIds.delete(p.id));
        render();
        return;
      }
      if (t.classList.contains('smh-post-check')) {
        if (t.checked) selectedPostIds.add(t.value);
        else selectedPostIds.delete(t.value);
        render();
        return;
      }
      if (t.classList.contains('smh-media-check')) {
        if (t.checked) selectedMediaIds.add(t.value);
        else selectedMediaIds.delete(t.value);
        render();
        return;
      }
      if (t.id === 'smh-delete-final-check' && activeModal?.type === 'delete') {
        activeModal.confirmedCheck = t.checked;
        render();
        return;
      }
      if (t.id === 'smh-ch-platform' && activeModal?.type === 'channel') {
        activeModal.account.platform = t.value;
        activeModal.account.handle = document.getElementById('smh-ch-handle')?.value || '';
        render();
        return;
      }

      // Wizard-Änderungen
      if (t.dataset.wizToggleTarget && wizard) {
        const id = t.dataset.wizToggleTarget;
        wizard.targets = t.checked ? [...new Set([...wizard.targets, id])] : wizard.targets.filter((x) => x !== id);
        syncWizardVariants();
        render();
        return;
      }
      if (t.dataset.wizFormat && wizard) {
        const id = t.dataset.wizFormat;
        wizard.variants[id] = wizard.variants[id] || {};
        wizard.variants[id].format_key = t.value;
        render();
        return;
      }
      if (t.id === 'wiz-media-select' && wizard) {
        wizard.primary_media_id = t.value;
        syncWizardVariants();
        render();
        return;
      }
      if (t.id === 'wiz-schedule-mode' && wizard) {
        wizard.schedule_mode = t.value;
        render();
        return;
      }
      if (t.id === 'wiz-date' && wizard) {
        wizard.scheduled_at = t.value;
        return;
      }
      if (t.id === 'wiz-preview-select') {
        wizardPreviewAccountId = t.value;
        render();
        return;
      }
      if (t.dataset.wizMeta && wizard) {
        const accId = t.dataset.acc;
        wizard.variants[accId] = wizard.variants[accId] || { metadata: {} };
        wizard.variants[accId].metadata = wizard.variants[accId].metadata || {};
        wizard.variants[accId].metadata[t.dataset.wizMeta] = t.type === 'checkbox' ? t.checked : t.value;
        return;
      }
      if (t.dataset.toggleRule) {
        await api('/automations', 'POST', { id: t.dataset.toggleRule, enabled: t.checked });
        showToast('Automationsregel aktualisiert.');
        return;
      }
      if (t.id === 'smh-import-file' && t.files?.[0]) {
        const txt = await t.files[0].text();
        const parsed = JSON.parse(txt);
        const res = await api('/import', 'POST', parsed);
        showToast(`Import erfolgreich: ${res.imported_posts} Beiträge übernommen.`);
        render();
      }
    } catch (err) {
      showToast(err?.message || 'Änderung fehlgeschlagen.');
    }
  });

  root.addEventListener('input', (e) => {
    const t = e.target;
    if (t.id === 'smh-post-search') {
      postSearch = t.value;
      const pos = t.selectionStart;
      render();
      const el = document.getElementById('smh-post-search');
      if (el) { el.focus(); el.setSelectionRange(pos, pos); }
      return;
    }
    if (!wizard) return;
    if (t.id === 'wiz-text') {
      wizard.master_text = t.value;
      updateLivePreviewDom();
      return;
    }
    if (t.id === 'wiz-link') {
      wizard.external_url = t.value;
      updateLivePreviewDom();
      return;
    }
    if (t.id === 'wiz-title') { wizard.title = t.value; return; }
    if (t.id === 'wiz-campaign') { wizard.campaign = t.value; return; }
    if (t.dataset.wizOverride) {
      wizard.variants[t.dataset.wizOverride] = wizard.variants[t.dataset.wizOverride] || {};
      wizard.variants[t.dataset.wizOverride].text_override = t.value;
      updateLivePreviewDom();
      return;
    }
    if (t.dataset.wizMeta && t.type !== 'checkbox') {
      const accId = t.dataset.acc;
      wizard.variants[accId] = wizard.variants[accId] || { metadata: {} };
      wizard.variants[accId].metadata = wizard.variants[accId].metadata || {};
      wizard.variants[accId].metadata[t.dataset.wizMeta] = t.value;
    }
  });

  render();
})();
