<?php
/**
 * Zentrale Repository-Schicht für Marken, Konten, Medien, Snippets, Beitragsverbünde,
 * Varianten, Revisions-Snapshots, Queue-Jobs, Manuelle Nachweise und Audit-Events.
 *
 * @package SocialMediaHub\Infrastructure\Database
 */

declare(strict_types=1);

namespace SocialMediaHub\Infrastructure\Database;

use SocialMediaHub\Infrastructure\Security\TokenSafe;

class Repositories
{
    /** @var array<string, mixed> */
    private array $state;
    private TokenSafe $tokenSafe;

    public function __construct(?TokenSafe $tokenSafe = null, ?array $initialState = null)
    {
        $this->tokenSafe = $tokenSafe ?? new TokenSafe();
        $this->state = $initialState ?? $this->loadPersistedOrDefaultState();
    }

    /**
     * @return array<string, mixed>
     */
    public function loadPersistedOrDefaultState(): array
    {
        if (function_exists('get_option')) {
            $saved = get_option('smh_repository_state', null);
            if (is_array($saved) && !empty($saved['brands'])) {
                return $saved;
            }
        }
        return $this->createDefaultSeedState();
    }

    public function persist(): void
    {
        if (function_exists('update_option')) {
            update_option('smh_repository_state', $this->state, false);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function &getStateRef(): array
    {
        return $this->state;
    }

    public function resetToSeed(): void
    {
        $this->state = $this->createDefaultSeedState();
        $this->persist();
    }

    /**
     * @return array<string, mixed>
     */
    public function createDefaultSeedState(): array
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $tomorrow10 = gmdate('Y-m-d\T10:00:00\Z', time() + 86400);
        $inTwoDays14 = gmdate('Y-m-d\T14:00:00\Z', time() + 2 * 86400);
        $inThreeDays17 = gmdate('Y-m-d\T17:00:00\Z', time() + 3 * 86400);

        return [
            'schema_version' => 1,
            'brands' => [
                [
                    'id'                => 'jan',
                    'slug'              => 'jan-design-digital',
                    'name'              => 'Jan Design Digital',
                    'color'             => '#586EE7',
                    'description'       => 'Personenmarke · Gestaltung, klare Systeme & Digitalität',
                    'require_approval'  => false,
                    'allowed_user_ids'  => [1, 2],
                    'default_utm'       => ['utm_source' => '{platform}', 'utm_medium' => 'social'],
                ],
                [
                    'id'                => 'nui',
                    'slug'              => 'nui',
                    'name'              => 'NUI',
                    'color'             => '#15876E',
                    'description'       => 'Digitalbegleiter · Neue Perspektiven & KI im Alltag',
                    'require_approval'  => true,
                    'allowed_user_ids'  => [1, 2],
                    'default_utm'       => ['utm_source' => '{platform}', 'utm_medium' => 'social'],
                ],
                [
                    'id'                => 'demo_client',
                    'slug'              => 'kundenprojekt-demo',
                    'name'              => 'Kundenprojekt (Mandant)',
                    'color'             => '#8263A7',
                    'description'       => 'Getrennter Kundenbereich mit Mandanten-Freigaberolle',
                    'require_approval'  => true,
                    'allowed_user_ids'  => [99],
                    'default_utm'       => ['utm_source' => '{platform}', 'utm_medium' => 'social'],
                ],
            ],
            'accounts' => [
                [
                    'id'                => 'acc_ig_jan',
                    'brand_id'          => 'jan',
                    'platform'          => 'instagram',
                    'account_type'      => 'business',
                    'handle'            => '@jan.design.digital',
                    'auth_mode'         => 'direct_credentials',
                    'connection_state'  => 'connected',
                    'granted_scopes'    => ['instagram_basic', 'instagram_content_publish'],
                    'encrypted_secrets' => $this->tokenSafe->encryptSecrets(['access_token' => 'ig_live_token_9821']),
                    'token_expires_at'  => gmdate('Y-m-d\TH:i:s\Z', time() + 30 * 86400),
                    'last_checked_at'   => $now,
                ],
                [
                    'id'                => 'acc_li_person',
                    'brand_id'          => 'jan',
                    'platform'          => 'linkedin',
                    'account_type'      => 'person',
                    'handle'            => 'Jan Dennis Brüning (Profil)',
                    'auth_mode'         => 'direct_credentials',
                    'connection_state'  => 'connected',
                    'granted_scopes'    => ['w_member_social'],
                    'encrypted_secrets' => $this->tokenSafe->encryptSecrets(['access_token' => 'li_person_token_4410', 'refresh_token' => 'li_ref_4410']),
                    'token_expires_at'  => gmdate('Y-m-d\TH:i:s\Z', time() + 45 * 86400),
                    'last_checked_at'   => $now,
                ],
                [
                    'id'                => 'acc_li_company',
                    'brand_id'          => 'jan',
                    'platform'          => 'linkedin',
                    'account_type'      => 'organization',
                    'handle'            => 'Jan Design Digital (Unternehmensseite)',
                    'auth_mode'         => 'direct_credentials',
                    'connection_state'  => 'expired',
                    'granted_scopes'    => [],
                    'encrypted_secrets' => '',
                    'token_expires_at'  => gmdate('Y-m-d\TH:i:s\Z', time() - 86400),
                    'last_checked_at'   => $now,
                ],
                [
                    'id'                => 'acc_fb_jan',
                    'brand_id'          => 'jan',
                    'platform'          => 'facebook',
                    'account_type'      => 'page',
                    'handle'            => 'Jan Design Digital (Facebook-Seite)',
                    'auth_mode'         => 'direct_credentials',
                    'connection_state'  => 'connected',
                    'granted_scopes'    => ['pages_manage_posts', 'pages_read_engagement'],
                    'encrypted_secrets' => $this->tokenSafe->encryptSecrets(['access_token' => 'fb_page_token_7712']),
                    'token_expires_at'  => gmdate('Y-m-d\TH:i:s\Z', time() + 60 * 86400),
                    'last_checked_at'   => $now,
                ],
                [
                    'id'                => 'acc_wa_jan',
                    'brand_id'          => 'jan',
                    'platform'          => 'whatsapp',
                    'account_type'      => 'channel',
                    'handle'            => 'Digital-Tipps Südwestpfalz (WhatsApp-Kanal)',
                    'auth_mode'         => 'manual_handoff',
                    'connection_state'  => 'manual',
                    'granted_scopes'    => [],
                    'encrypted_secrets' => '',
                    'token_expires_at'  => null,
                    'last_checked_at'   => $now,
                ],
                [
                    'id'                => 'acc_tiktok_jan',
                    'brand_id'          => 'jan',
                    'platform'          => 'tiktok',
                    'account_type'      => 'creator',
                    'handle'            => '@jan.design.digital (TikTok)',
                    'auth_mode'         => 'direct_credentials',
                    'connection_state'  => 'handoff_only',
                    'granted_scopes'    => ['video.upload'],
                    'encrypted_secrets' => $this->tokenSafe->encryptSecrets(['access_token' => 'tt_upload_token_3109']),
                    'token_expires_at'  => gmdate('Y-m-d\TH:i:s\Z', time() + 14 * 86400),
                    'last_checked_at'   => $now,
                ],
                [
                    'id'                => 'acc_pin_jan',
                    'brand_id'          => 'jan',
                    'platform'          => 'pinterest',
                    'account_type'      => 'business',
                    'handle'            => '@jandesigndigital (Pinterest)',
                    'auth_mode'         => 'direct_credentials',
                    'connection_state'  => 'connected',
                    'granted_scopes'    => ['boards:read', 'pins:write'],
                    'encrypted_secrets' => $this->tokenSafe->encryptSecrets(['access_token' => 'pin_v5_token_5501']),
                    'token_expires_at'  => gmdate('Y-m-d\TH:i:s\Z', time() + 30 * 86400),
                    'last_checked_at'   => $now,
                ],
                [
                    'id'                => 'acc_bsky_jan',
                    'brand_id'          => 'jan',
                    'platform'          => 'bluesky',
                    'account_type'      => 'atproto',
                    'handle'            => '@jandesigndigital.bsky.social',
                    'auth_mode'         => 'direct_credentials',
                    'connection_state'  => 'connected',
                    'granted_scopes'    => ['com.atproto.repo.createRecord'],
                    'encrypted_secrets' => $this->tokenSafe->encryptSecrets(['access_token' => 'bsky_app_pwd_6612']),
                    'token_expires_at'  => null,
                    'last_checked_at'   => $now,
                ],
                [
                    'id'                => 'acc_masto_jan',
                    'brand_id'          => 'jan',
                    'platform'          => 'mastodon',
                    'account_type'      => 'instance',
                    'handle'            => '@jan@mastodon.social',
                    'auth_mode'         => 'direct_credentials',
                    'connection_state'  => 'connected',
                    'granted_scopes'    => ['write:statuses', 'write:media'],
                    'instance_limits'   => ['max_chars' => 500, 'max_media' => 4],
                    'encrypted_secrets' => $this->tokenSafe->encryptSecrets(['access_token' => 'masto_tok_1190']),
                    'token_expires_at'  => null,
                    'last_checked_at'   => $now,
                ],
                [
                    'id'                => 'acc_ig_nui',
                    'brand_id'          => 'nui',
                    'platform'          => 'instagram',
                    'account_type'      => 'business',
                    'handle'            => '@nui.digital',
                    'auth_mode'         => 'direct_credentials',
                    'connection_state'  => 'connected',
                    'granted_scopes'    => ['instagram_basic', 'instagram_content_publish'],
                    'encrypted_secrets' => $this->tokenSafe->encryptSecrets(['access_token' => 'ig_nui_tok_2201']),
                    'token_expires_at'  => gmdate('Y-m-d\TH:i:s\Z', time() + 25 * 86400),
                    'last_checked_at'   => $now,
                ],
                [
                    'id'                => 'acc_threads_nui',
                    'brand_id'          => 'nui',
                    'platform'          => 'threads',
                    'account_type'      => 'profile',
                    'handle'            => '@nui.digital (Threads)',
                    'auth_mode'         => 'direct_credentials',
                    'connection_state'  => 'connected',
                    'granted_scopes'    => ['threads_basic', 'threads_content_publish'],
                    'encrypted_secrets' => $this->tokenSafe->encryptSecrets(['access_token' => 'th_nui_tok_2202']),
                    'token_expires_at'  => gmdate('Y-m-d\TH:i:s\Z', time() + 25 * 86400),
                    'last_checked_at'   => $now,
                ],
                [
                    'id'                => 'acc_tg_nui',
                    'brand_id'          => 'nui',
                    'platform'          => 'telegram',
                    'account_type'      => 'channel',
                    'handle'            => 'NUI Neuigkeiten (Telegram-Kanal)',
                    'auth_mode'         => 'manual_handoff',
                    'connection_state'  => 'manual',
                    'granted_scopes'    => [],
                    'encrypted_secrets' => '',
                    'token_expires_at'  => null,
                    'last_checked_at'   => $now,
                ],
            ],
            'media_assets' => [
                [
                    'id'               => 'med_keyvisual',
                    'brand_id'         => 'jan',
                    'name'             => 'Leitidee-Keyvisual.jpg',
                    'kind'             => 'image',
                    'mime_type'        => 'image/jpeg',
                    'emoji'            => '✨',
                    'width_px'         => 2400,
                    'height_px'        => 3000,
                    'duration_sec'     => 0,
                    'alt_text'         => 'Schreibtisch mit Skizzenbuch, Farbpalette und Laptop im warmen Morgenlicht – Symbolbild für digitale Gestaltung.',
                    'copyright_notice' => 'Eigenes Werk · Jan Design Digital',
                    'renditions'       => [
                        '4:5'  => ['width' => 1080, 'height' => 1350, 'ready' => true],
                        '1:1'  => ['width' => 1080, 'height' => 1080, 'ready' => true],
                        '9:16' => ['width' => 1080, 'height' => 1920, 'ready' => true],
                        '2:3'  => ['width' => 1000, 'height' => 1500, 'ready' => true],
                        '16:9' => ['width' => 1920, 'height' => 1080, 'ready' => true],
                    ],
                ],
                [
                    'id'               => 'med_yt_teaser',
                    'brand_id'         => 'jan',
                    'name'             => 'YouTube-Folge-Teaserbild.jpg',
                    'kind'             => 'image',
                    'mime_type'        => 'image/jpeg',
                    'emoji'            => '▶️',
                    'width_px'         => 1080,
                    'height_px'        => 1350,
                    'duration_sec'     => 0,
                    'alt_text'         => 'Vorschaubild zur neuen YouTube-Folge über klare digitale Systeme.',
                    'copyright_notice' => 'Eigenes Werk · Jan Design Digital',
                    'renditions'       => [
                        '4:5'  => ['width' => 1080, 'height' => 1350, 'ready' => true],
                        '1:1'  => ['width' => 1080, 'height' => 1080, 'ready' => true],
                        '16:9' => ['width' => 1920, 'height' => 1080, 'ready' => true],
                    ],
                ],
                [
                    'id'               => 'med_reel_916',
                    'brand_id'         => 'jan',
                    'name'             => 'Impuls-Reel-9x16.mp4',
                    'kind'             => 'video',
                    'mime_type'        => 'video/mp4',
                    'emoji'            => '🎞️',
                    'width_px'         => 1080,
                    'height_px'        => 1920,
                    'duration_sec'     => 38,
                    'alt_text'         => 'Kurzvideo mit drei Impulsen für digitale Gestaltung.',
                    'copyright_notice' => 'Eigenes Werk · Jan Design Digital',
                    'renditions'       => [
                        '9:16'  => ['width' => 1080, 'height' => 1920, 'ready' => true],
                        'cover' => ['width' => 1080, 'height' => 1920, 'ready' => true],
                    ],
                ],
                [
                    'id'               => 'med_pdf_guide',
                    'brand_id'         => 'jan',
                    'name'             => 'Leitfaden-Kurzfassung.pdf',
                    'kind'             => 'document',
                    'mime_type'        => 'application/pdf',
                    'emoji'            => '📑',
                    'width_px'         => 1080,
                    'height_px'        => 1080,
                    'duration_sec'     => 0,
                    'alt_text'         => 'PDF-Leitfaden mit 6 kompakten Folien zur Zugangssicherheit.',
                    'copyright_notice' => 'Eigenes Werk · Jan Design Digital',
                    'renditions'       => [
                        'pdf' => ['pages' => 6, 'ready' => true],
                    ],
                ],
                [
                    'id'               => 'med_nui_intro',
                    'brand_id'         => 'nui',
                    'name'             => 'NUI-Vorstellung-Motiv.jpg',
                    'kind'             => 'image',
                    'mime_type'        => 'image/jpeg',
                    'emoji'            => '🤖',
                    'width_px'         => 1080,
                    'height_px'        => 1350,
                    'duration_sec'     => 0,
                    'alt_text'         => 'Illustration des digitalen Begleiters NUI.',
                    'copyright_notice' => 'NUI Digital',
                    'renditions'       => [
                        '4:5' => ['width' => 1080, 'height' => 1350, 'ready' => true],
                        '1:1' => ['width' => 1080, 'height' => 1080, 'ready' => true],
                    ],
                ],
            ],
            'snippets' => [
                [
                    'id'           => 'snip_hashtags_jan',
                    'brand_id'     => 'jan',
                    'title'        => 'Klassiker-Hashtags · Gestaltung & Digitalität',
                    'snippet_type' => 'hashtags',
                    'content'      => '#DigitaleGestaltung #Webdesign #KlareSysteme #Südwestpfalz #JanDesignDigital',
                ],
                [
                    'id'           => 'snip_youtube_cta',
                    'brand_id'     => 'jan',
                    'title'        => 'Hinweis-Baustein · Neues YouTube-Video',
                    'snippet_type' => 'cta',
                    'content'      => '▶️ Das komplette Video findest du jetzt auf meinem YouTube-Kanal – schau gern rein!',
                ],
                [
                    'id'           => 'snip_hashtags_nui',
                    'brand_id'     => 'nui',
                    'title'        => 'NUI Basis-Hashtags',
                    'snippet_type' => 'hashtags',
                    'content'      => '#NUI #Digitalbegleiter #KünstlicheIntelligenz #DigitaleZukunft',
                ],
            ],
            'post_groups' => [
                [
                    'id'                  => 'pg_classic_1',
                    'brand_id'            => 'jan',
                    'campaign'            => 'Herbst-Impuls · Digitale Gestaltung',
                    'title'               => 'Die Welt von heute zusammen gestalten',
                    'master_text'         => "Gestaltung beginnt mit einer Frage: Was können wir heute sinnvoll verändern? 🌱\n\nDesign, Technik und menschlicher Blick gehören zusammen.\n\n#DigitaleGestaltung #Webdesign #KlareSysteme",
                    'primary_type'        => 'image',
                    'primary_media_id'    => 'med_keyvisual',
                    'external_url'        => 'https://jandesigndigital.de/impulse',
                    'approval_status'     => 'approved',
                    'approved_by'         => 1,
                    'approved_at'         => $now,
                    'scheduled_at'        => $tomorrow10,
                    'timezone'            => 'Europe/Berlin',
                    'current_revision_id' => 'rev_pg_classic_1_v1',
                    'created_at'          => $now,
                    'updated_at'          => $now,
                    'targets'             => ['acc_ig_jan', 'acc_li_person', 'acc_fb_jan', 'acc_wa_jan'],
                    'variants'            => [
                        'acc_ig_jan' => [
                            'enabled'         => true,
                            'format_key'      => 'feed',
                            'text_override'   => '',
                            'media_ids'       => ['med_keyvisual'],
                            'rendition_key'   => '4:5',
                            'metadata'        => ['bio_link_hint' => true],
                            'delivery_status' => 'pending',
                        ],
                        'acc_li_person' => [
                            'enabled'         => true,
                            'format_key'      => 'image',
                            'text_override'   => "Die Welt von heute zusammen gestalten: Warum fachliches Gestaltungsurteil im digitalen Wandel noch wichtiger wird.\n\n#DigitaleGestaltung #Strategie #Webdesign",
                            'media_ids'       => ['med_keyvisual'],
                            'rendition_key'   => '1:1',
                            'metadata'        => ['identity' => 'person'],
                            'delivery_status' => 'pending',
                        ],
                        'acc_fb_jan' => [
                            'enabled'         => true,
                            'format_key'      => 'image',
                            'text_override'   => '',
                            'media_ids'       => ['med_keyvisual'],
                            'rendition_key'   => '1:1',
                            'metadata'        => [],
                            'delivery_status' => 'pending',
                        ],
                        'acc_wa_jan' => [
                            'enabled'         => true,
                            'format_key'      => 'update',
                            'text_override'   => '',
                            'media_ids'       => ['med_keyvisual'],
                            'rendition_key'   => '1:1',
                            'metadata'        => [],
                            'delivery_status' => 'manual_pending',
                        ],
                    ],
                ],
                [
                    'id'                  => 'pg_youtube_hint',
                    'brand_id'            => 'jan',
                    'campaign'            => 'YouTube-Begleitkommunikation',
                    'title'               => 'Neues YouTube-Video: Warum einfache Systeme länger halten',
                    'master_text'         => "Neues Video auf YouTube! ▶️ Ich zeige dir in 8 Minuten, warum schlanke, unabhängige Systeme im Alltag zuverlässiger funktionieren als überladene Alles-Könner.\n\n#YouTubeUpdate #DigitaleSysteme #Webdesign",
                    'primary_type'        => 'image',
                    'primary_media_id'    => 'med_yt_teaser',
                    'external_url'        => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    'approval_status'     => 'approved',
                    'approved_by'         => 1,
                    'approved_at'         => $now,
                    'scheduled_at'        => $inTwoDays14,
                    'timezone'            => 'Europe/Berlin',
                    'current_revision_id' => 'rev_pg_youtube_hint_v1',
                    'created_at'          => $now,
                    'updated_at'          => $now,
                    'targets'             => ['acc_ig_jan', 'acc_fb_jan', 'acc_li_person', 'acc_wa_jan', 'acc_bsky_jan'],
                    'variants'            => [
                        'acc_ig_jan' => [
                            'enabled'         => true,
                            'format_key'      => 'feed',
                            'text_override'   => '',
                            'media_ids'       => ['med_yt_teaser'],
                            'rendition_key'   => '4:5',
                            'metadata'        => ['bio_link_hint' => true],
                            'delivery_status' => 'pending',
                        ],
                        'acc_fb_jan' => [
                            'enabled'         => true,
                            'format_key'      => 'link',
                            'text_override'   => '',
                            'media_ids'       => ['med_yt_teaser'],
                            'rendition_key'   => '16:9',
                            'metadata'        => ['link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                            'delivery_status' => 'pending',
                        ],
                        'acc_li_person' => [
                            'enabled'         => true,
                            'format_key'      => 'image',
                            'text_override'   => '',
                            'media_ids'       => ['med_yt_teaser'],
                            'rendition_key'   => '16:9',
                            'metadata'        => ['identity' => 'person', 'link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                            'delivery_status' => 'pending',
                        ],
                        'acc_wa_jan' => [
                            'enabled'         => true,
                            'format_key'      => 'update',
                            'text_override'   => '',
                            'media_ids'       => ['med_yt_teaser'],
                            'rendition_key'   => '1:1',
                            'metadata'        => ['link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                            'delivery_status' => 'manual_pending',
                        ],
                        'acc_bsky_jan' => [
                            'enabled'         => true,
                            'format_key'      => 'image',
                            'text_override'   => '',
                            'media_ids'       => ['med_yt_teaser'],
                            'rendition_key'   => '16:9',
                            'metadata'        => ['link' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                            'delivery_status' => 'pending',
                        ],
                    ],
                ],
                [
                    'id'                  => 'pg_nui_hello',
                    'brand_id'            => 'nui',
                    'campaign'            => 'NUI Launch',
                    'title'               => 'Hallo, ich bin NUI – dein digitaler Begleiter',
                    'master_text'         => "Hallo! 👋 Ich bin NUI – dein digitaler Begleiter. Gemeinsam entdecken wir neue digitale Möglichkeiten Schritt für Schritt.\n\n#NUI #Digitalbegleiter #KI",
                    'primary_type'        => 'image',
                    'primary_media_id'    => 'med_nui_intro',
                    'external_url'        => 'https://nui.digital',
                    'approval_status'     => 'pending',
                    'approved_by'         => null,
                    'approved_at'         => null,
                    'scheduled_at'        => $inThreeDays17,
                    'timezone'            => 'Europe/Berlin',
                    'current_revision_id' => null,
                    'created_at'          => $now,
                    'updated_at'          => $now,
                    'targets'             => ['acc_ig_nui', 'acc_threads_nui', 'acc_tg_nui'],
                    'variants'            => [
                        'acc_ig_nui' => [
                            'enabled'         => true,
                            'format_key'      => 'feed',
                            'text_override'   => '',
                            'media_ids'       => ['med_nui_intro'],
                            'rendition_key'   => '4:5',
                            'metadata'        => ['bio_link_hint' => true],
                            'delivery_status' => 'pending',
                        ],
                        'acc_threads_nui' => [
                            'enabled'         => true,
                            'format_key'      => 'image',
                            'text_override'   => '',
                            'media_ids'       => ['med_nui_intro'],
                            'rendition_key'   => '4:5',
                            'metadata'        => [],
                            'delivery_status' => 'pending',
                        ],
                        'acc_tg_nui' => [
                            'enabled'         => true,
                            'format_key'      => 'message',
                            'text_override'   => '',
                            'media_ids'       => ['med_nui_intro'],
                            'rendition_key'   => '1:1',
                            'metadata'        => [],
                            'delivery_status' => 'manual_pending',
                        ],
                    ],
                ],
            ],
            'revisions'    => [],
            'jobs'         => [],
            'receipts'     => [],
            'interactions' => [
                [
                    'id'         => 'int_1',
                    'account_id' => 'acc_ig_jan',
                    'brand_id'   => 'jan',
                    'author'     => 'Anna Beispiel',
                    'text'       => 'Sehr klarer Beitrag! Wo finde ich den Link zum neuen YouTube-Video?',
                    'type'       => 'Kommentar (API)',
                    'resolved'   => false,
                    'note'       => '',
                    'created_at' => $now,
                ],
                [
                    'id'         => 'int_2',
                    'account_id' => 'acc_threads_nui',
                    'brand_id'   => 'nui',
                    'author'     => 'Digitalfan',
                    'text'       => 'Wann gibt es die nächste Folge mit NUI?',
                    'type'       => 'Antwort (API)',
                    'resolved'   => false,
                    'note'       => 'Hinweis auf Freitags-Post vorbereiten.',
                    'created_at' => $now,
                ],
            ],
            'automation_rules' => [
                [
                    'id'      => 'rule_auto_queue_on_approval',
                    'name'    => 'Automatisch nach Freigabe in die Versand-Warteschlange einreihen',
                    'detail'  => 'Sobald ein Beitragsverbund freigegeben wird, werden alle grünen API-Ziele zum geplanten Termin eingereiht.',
                    'enabled' => true,
                ],
                [
                    'id'      => 'rule_48h_token_preflight',
                    'name'    => '48-Stunden-Vorab-Check der Kanalverbindungen',
                    'detail'  => 'Prüft 48 Stunden vor geplanten Beiträgen alle OAuth-Zugänge und verlängert Refresh-Tokens automatisch.',
                    'enabled' => true,
                ],
                [
                    'id'      => 'rule_retry_transient_errors',
                    'name'    => 'Automatische Wiederholung bei Plattform-Überlastung (Max. 3× mit Backoff)',
                    'detail'  => 'Wiederholt nur vorübergehende Fehler (HTTP 429 / 5xx). Stoppt sofort bei abgelaufenen Zugängen (401/403).',
                    'enabled' => true,
                ],
                [
                    'id'      => 'rule_manual_handoff_reminder',
                    'name'    => 'Erinnerung für manuelle Kanäle (WhatsApp / Signal / App-Abschluss)',
                    'detail'  => 'Erzeugt bei Fälligkeit einen direkten Hinweis mit Link zum mobilen Handoff-Assistenten (S10).',
                    'enabled' => true,
                ],
            ],
            'audit_events' => [
                [
                    'id'            => 'aud_init',
                    'brand_id'      => 'jan',
                    'post_group_id' => null,
                    'account_id'    => null,
                    'event_type'    => 'system.initialized',
                    'title'         => 'Social Media Hub v1.0 initialisiert',
                    'detail'        => 'Eigenständige Datenbank- und Provider-Architektur aktiv.',
                    'actor_id'      => 1,
                    'created_at'    => $now,
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getBrands(): array
    {
        return array_values($this->state['brands'] ?? []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBrand(string $brandId): ?array
    {
        foreach ($this->state['brands'] ?? [] as $brand) {
            if (($brand['id'] ?? '') === $brandId) {
                return $brand;
            }
        }
        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAccounts(?string $brandId = null): array
    {
        $accounts = array_values($this->state['accounts'] ?? []);
        if ($brandId !== null && $brandId !== '' && $brandId !== 'all') {
            $accounts = array_values(array_filter(
                $accounts,
                static fn(array $a): bool => ($a['brand_id'] ?? '') === $brandId
            ));
        }
        return $accounts;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAccount(string $accountId): ?array
    {
        foreach ($this->state['accounts'] ?? [] as $account) {
            if (($account['id'] ?? '') === $accountId) {
                return $account;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $updates
     */
    public function updateAccount(string $accountId, array $updates): ?array
    {
        foreach ($this->state['accounts'] as $idx => $account) {
            if (($account['id'] ?? '') === $accountId) {
                $this->state['accounts'][$idx] = array_merge($account, $updates, [
                    'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
                ]);
                $this->persist();
                return $this->state['accounts'][$idx];
            }
        }
        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getMediaAssets(?string $brandId = null): array
    {
        $media = array_values($this->state['media_assets'] ?? []);
        if ($brandId !== null && $brandId !== '' && $brandId !== 'all') {
            $media = array_values(array_filter(
                $media,
                static fn(array $m): bool => ($m['brand_id'] ?? '') === $brandId
            ));
        }
        return $media;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findMediaAsset(string $mediaId): ?array
    {
        foreach ($this->state['media_assets'] ?? [] as $asset) {
            if (($asset['id'] ?? '') === $mediaId) {
                return $asset;
            }
        }
        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSnippets(?string $brandId = null): array
    {
        $snippets = array_values($this->state['snippets'] ?? []);
        if ($brandId !== null && $brandId !== '' && $brandId !== 'all') {
            $snippets = array_values(array_filter(
                $snippets,
                static fn(array $s): bool => ($s['brand_id'] ?? '') === $brandId
            ));
        }
        return $snippets;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPostGroups(?string $brandId = null): array
    {
        $groups = array_values($this->state['post_groups'] ?? []);
        if ($brandId !== null && $brandId !== '' && $brandId !== 'all') {
            $groups = array_values(array_filter(
                $groups,
                static fn(array $g): bool => ($g['brand_id'] ?? '') === $brandId
            ));
        }
        return $groups;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPostGroup(string $postGroupId): ?array
    {
        foreach ($this->state['post_groups'] ?? [] as $group) {
            if (($group['id'] ?? '') === $postGroupId) {
                return $group;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $postGroup
     * @return array<string, mixed>
     */
    public function savePostGroup(array $postGroup): array
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        if (empty($postGroup['id'])) {
            $postGroup['id'] = 'pg_' . bin2hex(random_bytes(5));
            $postGroup['created_at'] = $now;
        }
        $postGroup['updated_at'] = $now;

        foreach ($this->state['post_groups'] as $idx => $existing) {
            if (($existing['id'] ?? '') === $postGroup['id']) {
                $this->state['post_groups'][$idx] = $postGroup;
                $this->persist();
                return $postGroup;
            }
        }

        array_unshift($this->state['post_groups'], $postGroup);
        $this->persist();
        return $postGroup;
    }

    /**
     * @param array<string, mixed> $revision
     * @return array<string, mixed>
     */
    public function addRevision(array $revision): array
    {
        $this->state['revisions'][] = $revision;
        $this->persist();
        return $revision;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRevisions(?string $postGroupId = null): array
    {
        $revisions = array_values($this->state['revisions'] ?? []);
        if ($postGroupId !== null && $postGroupId !== '') {
            $revisions = array_values(array_filter(
                $revisions,
                static fn(array $r): bool => ($r['post_group_id'] ?? '') === $postGroupId
            ));
        }
        return $revisions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getJobs(?string $postGroupId = null): array
    {
        $jobs = array_values($this->state['jobs'] ?? []);
        if ($postGroupId !== null && $postGroupId !== '') {
            $jobs = array_values(array_filter(
                $jobs,
                static fn(array $j): bool => ($j['post_group_id'] ?? '') === $postGroupId
            ));
        }
        return $jobs;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findJobByIdempotencyKey(string $idempotencyKey): ?array
    {
        foreach ($this->state['jobs'] ?? [] as $job) {
            if (($job['idempotency_key'] ?? '') === $idempotencyKey) {
                return $job;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    public function saveJob(array $job): array
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        if (empty($job['id'])) {
            $job['id'] = 'job_' . bin2hex(random_bytes(5));
            $job['created_at'] = $now;
        }
        $job['updated_at'] = $now;

        foreach ($this->state['jobs'] as $idx => $existing) {
            if (($existing['id'] ?? '') === $job['id']) {
                $this->state['jobs'][$idx] = $job;
                $this->persist();
                return $job;
            }
        }

        $this->state['jobs'][] = $job;
        $this->persist();
        return $job;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getReceipts(?string $postGroupId = null): array
    {
        $receipts = array_values($this->state['receipts'] ?? []);
        if ($postGroupId !== null && $postGroupId !== '') {
            $receipts = array_values(array_filter(
                $receipts,
                static fn(array $r): bool => ($r['post_group_id'] ?? '') === $postGroupId
            ));
        }
        return $receipts;
    }

    /**
     * @param array<string, mixed> $receipt
     * @return array<string, mixed>
     */
    public function saveReceipt(array $receipt): array
    {
        if (empty($receipt['id'])) {
            $receipt['id'] = 'rec_' . bin2hex(random_bytes(5));
        }
        foreach ($this->state['receipts'] as $idx => $existing) {
            if (($existing['id'] ?? '') === $receipt['id']) {
                $this->state['receipts'][$idx] = $receipt;
                $this->persist();
                return $receipt;
            }
        }
        array_unshift($this->state['receipts'], $receipt);
        $this->persist();
        return $receipt;
    }

    public function recordAudit(
        string $eventType,
        string $title,
        string $detail = '',
        ?string $brandId = null,
        ?string $postGroupId = null,
        ?string $accountId = null,
        int $actorId = 1
    ): array {
        $entry = [
            'id'            => 'aud_' . bin2hex(random_bytes(5)),
            'brand_id'      => $brandId,
            'post_group_id' => $postGroupId,
            'account_id'    => $accountId,
            'event_type'    => $eventType,
            'title'         => $title,
            'detail'        => $detail,
            'actor_id'      => $actorId,
            'created_at'    => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        array_unshift($this->state['audit_events'], $entry);
        $this->state['audit_events'] = array_slice($this->state['audit_events'], 0, 500);
        $this->persist();
        return $entry;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAuditEvents(int $limit = 100): array
    {
        return array_slice(array_values($this->state['audit_events'] ?? []), 0, $limit);
    }
}
