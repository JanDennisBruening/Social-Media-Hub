<?php
/**
 * Zentrale Steckmodul-Registry (ProviderRegistry) & deklarativer Plattform-Adapter für alle 11 Kanäle:
 * Instagram, Facebook, LinkedIn, Threads, TikTok, Pinterest, Bluesky, Mastodon, Telegram, WhatsApp-Kanal, Signal.
 *
 * @package SocialMediaHub\Providers
 */

declare(strict_types=1);

namespace SocialMediaHub\Providers;

use SocialMediaHub\Infrastructure\Security\SignedMediaEndpoint;

class ProviderRegistry
{
    /** @var array<string, ProviderInterface> */
    private array $providers = [];
    private SignedMediaEndpoint $signedMediaEndpoint;

    public function __construct(?SignedMediaEndpoint $signedMediaEndpoint = null)
    {
        $this->signedMediaEndpoint = $signedMediaEndpoint ?? new SignedMediaEndpoint();
        $this->registerDefaultProviders();
    }

    public function register(ProviderInterface $provider): void
    {
        $this->providers[$provider->getPlatformKey()] = $provider;
    }

    public function get(string $platformKey): ?ProviderInterface
    {
        return $this->providers[$platformKey] ?? null;
    }

    /**
     * @return array<string, ProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAllManifests(): array
    {
        $manifests = [];
        foreach ($this->providers as $key => $provider) {
            $manifests[$key] = $provider->getManifest();
        }
        return $manifests;
    }

    /**
     * Zählt Unicode-Grapheme exakt.
     */
    public static function countGraphemes(string $text): int
    {
        if ($text === '') {
            return 0;
        }
        if (function_exists('grapheme_strlen')) {
            $len = grapheme_strlen($text);
            if (is_int($len)) {
                return $len;
            }
        }
        if (preg_match_all('/\X/u', $text, $matches) !== false) {
            return count($matches[0]);
        }
        return mb_strlen($text, 'UTF-8');
    }

    private function registerDefaultProviders(): void
    {
        $specs = self::getDefaultPlatformSpecs();
        foreach ($specs as $key => $spec) {
            $this->register(new ConfigurablePlatformProvider($key, $spec, $this->signedMediaEndpoint));
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getDefaultPlatformSpecs(): array
    {
        return [
            'instagram' => [
                'label'                 => 'Instagram',
                'short'                 => 'IG',
                'api_version'           => 'Meta Graph API v21.0',
                'media_transport'       => 'pull_signed_url',
                'clickable_in_caption'  => false,
                'supports_remote_edit'  => false,
                'supports_read_inbox'   => true,
                'supports_read_metrics' => true,
                'default_char_limit'    => 2200,
                'required_scopes'       => [
                    'business' => ['instagram_basic', 'instagram_content_publish'],
                    'creator'  => ['instagram_basic', 'instagram_content_publish'],
                    'personal' => ['__unsupported_personal_account__'],
                ],
                'formats' => [
                    'feed'     => ['label' => 'Feed · Einzelbild (4:5 / 1:1)', 'media_kind' => 'image', 'min_media' => 1, 'max_media' => 1, 'recommended_rendition' => '4:5'],
                    'carousel' => ['label' => 'Karussell (2–10 Bilder)', 'media_kind' => 'image', 'min_media' => 2, 'max_media' => 10, 'recommended_rendition' => '4:5'],
                    'reel'     => ['label' => 'Reel · 9:16 Hochkantvideo', 'media_kind' => 'video', 'min_media' => 1, 'max_media' => 1, 'recommended_rendition' => '9:16'],
                    'story'    => ['label' => 'Story · 9:16 Vollbild', 'media_kind' => 'image_or_video', 'min_media' => 1, 'max_media' => 1, 'recommended_rendition' => '9:16'],
                ],
            ],
            'facebook' => [
                'label'                 => 'Facebook-Seite',
                'short'                 => 'f',
                'api_version'           => 'Meta Graph Page API v21.0',
                'media_transport'       => 'direct_or_pull',
                'clickable_in_caption'  => true,
                'supports_remote_edit'  => true,
                'supports_read_inbox'   => true,
                'supports_read_metrics' => true,
                'default_char_limit'    => 5000,
                'required_scopes'       => [
                    'page' => ['pages_manage_posts'],
                ],
                'formats' => [
                    'image' => ['label' => 'Seitenbild + Caption', 'media_kind' => 'image', 'min_media' => 1, 'max_media' => 1, 'recommended_rendition' => '1:1'],
                    'link'  => ['label' => 'Link-Beitrag (z. B. YouTube-Verweis)', 'media_kind' => 'none', 'min_media' => 0, 'max_media' => 1, 'recommended_rendition' => '16:9'],
                    'text'  => ['label' => 'Reiner Textbeitrag', 'media_kind' => 'none', 'min_media' => 0, 'max_media' => 0, 'recommended_rendition' => 'none'],
                    'video' => ['label' => 'Seitenvideo', 'media_kind' => 'video', 'min_media' => 1, 'max_media' => 1, 'recommended_rendition' => '9:16'],
                ],
            ],
            'linkedin' => [
                'label'                 => 'LinkedIn',
                'short'                 => 'in',
                'api_version'           => 'LinkedIn Posts API 2026-11',
                'media_transport'       => 'direct_push',
                'clickable_in_caption'  => true,
                'supports_remote_edit'  => false,
                'supports_read_inbox'   => true,
                'supports_read_metrics' => true,
                'default_char_limit'    => 3000,
                'required_scopes'       => [
                    'person'       => ['w_member_social'],
                    'organization' => ['w_organization_social'],
                ],
                'formats' => [
                    'image'      => ['label' => 'Bildbeitrag + Text', 'media_kind' => 'image', 'min_media' => 1, 'max_media' => 1, 'recommended_rendition' => '1:1'],
                    'text'       => ['label' => 'Fachlicher Textbeitrag / Link', 'media_kind' => 'none', 'min_media' => 0, 'max_media' => 0, 'recommended_rendition' => 'none'],
                    'multiimage' => ['label' => 'Mehrere Bilder (2–20)', 'media_kind' => 'image', 'min_media' => 2, 'max_media' => 20, 'recommended_rendition' => '1:1'],
                    'document'   => ['label' => 'PDF / Dokument-Slides', 'media_kind' => 'document', 'min_media' => 1, 'max_media' => 1, 'recommended_rendition' => 'pdf'],
                    'video'      => ['label' => 'Videobeitrag', 'media_kind' => 'video', 'min_media' => 1, 'max_media' => 1, 'recommended_rendition' => '9:16'],
                ],
            ],
            'threads' => [
                'label'                 => 'Threads',
                'short'                 => '@',
                'api_version'           => 'Meta Threads API v1.0',
                'media_transport'       => 'pull_signed_url',
                'clickable_in_caption'  => true,
                'supports_remote_edit'  => false,
                'supports_read_inbox'   => true,
                'supports_read_metrics' => true,
                'default_char_limit'    => 500,
                'required_scopes'       => [
                    'profile' => ['threads_basic', 'threads_content_publish'],
                ],
                'formats' => [
                    'image'    => ['label' => 'Bildbeitrag', 'media_kind' => 'image', 'min_media' => 1, 'max_media' => 1, 'recommended_rendition' => '4:5'],
                    'text'     => ['label' => 'Textbeitrag / Link', 'media_kind' => 'none', 'min_media' => 0, 'max_media' => 0, 'recommended_rendition' => 'none'],
                    'carousel' => ['label' => 'Karussell', 'media_kind' => 'image', 'min_media' => 2, 'max_media' => 10, 'recommended_rendition' => '4:5'],
                ],
            ],
            'tiktok' => [
                'label'                 => 'TikTok',
                'short'                 => '♪',
                'api_version'           => 'TikTok Content Posting API v2',
                'media_transport'       => 'direct_push',
                'clickable_in_caption'  => false,
                'supports_remote_edit'  => false,
                'supports_read_inbox'   => false,
                'supports_read_metrics' => false,
                'default_char_limit'    => 2200,
                'required_scopes'       => [
                    'creator' => ['video.upload'],
                ],
                'formats' => [
                    'video' => ['label' => 'TikTok-Video (9:16)', 'media_kind' => 'video', 'min_media' => 1, 'max_media' => 1, 'recommended_rendition' => '9:16'],
                    'photo' => ['label' => 'Fotopost', 'media_kind' => 'image', 'min_media' => 1, 'max_media' => 10, 'recommended_rendition' => '9:16'],
                ],
            ],
            'pinterest' => [
                'label'                 => 'Pinterest',
                'short'                 => 'P',
                'api_version'           => 'Pinterest API v5',
                'media_transport'       => 'direct_push',
                'clickable_in_caption'  => true,
                'supports_remote_edit'  => false,
                'supports_read_inbox'   => false,
                'supports_read_metrics' => true,
                'default_char_limit'    => 500,
                'required_scopes'       => [
                    'business' => ['boards:read', 'pins:write'],
                ],
                'formats' => [
                    'pin'       => ['label' => 'Pin · 2:3 Vertikalbild', 'media_kind' => 'image', 'min_media' => 1, 'max_media' => 1, 'recommended_rendition' => '2:3'],
                    'video-pin' => ['label' => 'Video-Pin', 'media_kind' => 'video', 'min_media' => 1, 'max_media' => 1, 'recommended_rendition' => '9:16'],
                ],
            ],
            'bluesky' => [
                'label'                 => 'Bluesky',
                'short'                 => 'BS',
                'api_version'           => 'AT Protocol (com.atproto.repo)',
                'media_transport'       => 'direct_push',
                'clickable_in_caption'  => true,
                'supports_remote_edit'  => false,
                'supports_read_inbox'   => false,
                'supports_read_metrics' => false,
                'default_char_limit'    => 300,
                'required_scopes'       => [
                    'atproto' => ['com.atproto.repo.createRecord'],
                ],
                'formats' => [
                    'image' => ['label' => 'Bilderbeitrag (1–4 Bilder + Alt-Text)', 'media_kind' => 'image', 'min_media' => 1, 'max_media' => 4, 'recommended_rendition' => '16:9'],
                    'text'  => ['label' => 'Text + Linkkarte', 'media_kind' => 'none', 'min_media' => 0, 'max_media' => 0, 'recommended_rendition' => 'none'],
                ],
            ],
            'mastodon' => [
                'label'                 => 'Mastodon',
                'short'                 => 'M',
                'api_version'           => 'Mastodon REST API (/api/v2/instance)',
                'media_transport'       => 'direct_push',
                'clickable_in_caption'  => true,
                'supports_remote_edit'  => true,
                'supports_read_inbox'   => false,
                'supports_read_metrics' => false,
                'default_char_limit'    => 500,
                'required_scopes'       => [
                    'instance' => ['write:statuses', 'write:media'],
                ],
                'formats' => [
                    'image' => ['label' => 'Medienbeitrag (1–4 Bilder)', 'media_kind' => 'image', 'min_media' => 1, 'max_media' => 4, 'recommended_rendition' => '16:9'],
                    'text'  => ['label' => 'Textbeitrag', 'media_kind' => 'none', 'min_media' => 0, 'max_media' => 0, 'recommended_rendition' => 'none'],
                    'poll'  => ['label' => 'Umfrage (ohne Bilder)', 'media_kind' => 'none', 'min_media' => 0, 'max_media' => 0, 'recommended_rendition' => 'none'],
                ],
            ],
            'telegram' => [
                'label'                 => 'Telegram-Kanal',
                'short'                 => 'TG',
                'api_version'           => 'Telegram Bot API / Manuell',
                'media_transport'       => 'direct_push',
                'clickable_in_caption'  => true,
                'supports_remote_edit'  => true,
                'supports_read_inbox'   => false,
                'supports_read_metrics' => false,
                'default_char_limit'    => 1024,
                'required_scopes'       => [
                    'channel' => [],
                ],
                'formats' => [
                    'message' => ['label' => 'Kanalnachricht (Bild + Text)', 'media_kind' => 'none', 'min_media' => 0, 'max_media' => 1, 'recommended_rendition' => '1:1'],
                    'album'   => ['label' => 'Mediengruppe (2–10 Bilder)', 'media_kind' => 'image', 'min_media' => 2, 'max_media' => 10, 'recommended_rendition' => '1:1'],
                ],
            ],
            'whatsapp' => [
                'label'                 => 'WhatsApp-Kanal',
                'short'                 => 'WA',
                'api_version'           => 'Manueller Handoff-Adapter (Kein Public Write-API)',
                'media_transport'       => 'manual_download',
                'clickable_in_caption'  => true,
                'supports_remote_edit'  => false,
                'supports_read_inbox'   => false,
                'supports_read_metrics' => false,
                'default_char_limit'    => 2000,
                'required_scopes'       => [
                    'channel' => [],
                ],
                'formats' => [
                    'update' => ['label' => 'Kanal-Update (Bild + kurzer Text + Link)', 'media_kind' => 'none', 'min_media' => 0, 'max_media' => 1, 'recommended_rendition' => '1:1'],
                ],
            ],
            'signal' => [
                'label'                 => 'Signal',
                'short'                 => 'S',
                'api_version'           => 'Manueller Handoff-Adapter',
                'media_transport'       => 'manual_download',
                'clickable_in_caption'  => true,
                'supports_remote_edit'  => false,
                'supports_read_inbox'   => false,
                'supports_read_metrics' => false,
                'default_char_limit'    => 2000,
                'required_scopes'       => [
                    'group' => [],
                ],
                'formats' => [
                    'message' => ['label' => 'Nachricht / Gruppe (Manuell)', 'media_kind' => 'none', 'min_media' => 0, 'max_media' => 1, 'recommended_rendition' => '1:1'],
                ],
            ],
        ];
    }
}
