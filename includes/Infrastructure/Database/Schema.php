<?php
/**
 * Eigenständiges Datenbank-Schema (10 indizierte Custom Tables) für den Social Media Hub.
 *
 * @package SocialMediaHub\Infrastructure\Database
 */

declare(strict_types=1);

namespace SocialMediaHub\Infrastructure\Database;

class Schema
{
    /**
     * Liefert die `dbDelta`-kompatiblen `CREATE TABLE`-Statements für alle 10 Tabellen.
     *
     * @return array<string, string>
     */
    public static function getTableDefinitions(string $prefix = 'wp_', string $charsetCollate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci'): array
    {
        return [
            'smh_brands' => "CREATE TABLE {$prefix}smh_brands (
                id varchar(64) NOT NULL,
                slug varchar(120) NOT NULL,
                name varchar(190) NOT NULL,
                color varchar(16) NOT NULL DEFAULT '#586EE7',
                description text NULL,
                require_approval tinyint(1) NOT NULL DEFAULT 0,
                allowed_user_ids_json longtext NULL,
                default_utm_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY idx_slug (slug)
            ) {$charsetCollate};",

            'smh_accounts' => "CREATE TABLE {$prefix}smh_accounts (
                id varchar(64) NOT NULL,
                brand_id varchar(64) NOT NULL,
                platform varchar(40) NOT NULL,
                account_type varchar(60) NOT NULL DEFAULT 'standard',
                handle varchar(190) NOT NULL,
                external_account_id varchar(190) NULL,
                auth_mode varchar(40) NOT NULL DEFAULT 'direct_credentials',
                connection_state varchar(40) NOT NULL DEFAULT 'unverified',
                granted_scopes_json longtext NULL,
                capabilities_json longtext NULL,
                encrypted_secrets longtext NULL,
                token_expires_at datetime NULL,
                last_checked_at datetime NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY idx_brand_platform (brand_id, platform),
                KEY idx_connection_expires (connection_state, token_expires_at)
            ) {$charsetCollate};",

            'smh_media_assets' => "CREATE TABLE {$prefix}smh_media_assets (
                id varchar(64) NOT NULL,
                brand_id varchar(64) NOT NULL,
                wp_attachment_id bigint(20) unsigned NULL,
                name varchar(255) NOT NULL,
                kind varchar(32) NOT NULL DEFAULT 'image',
                mime_type varchar(100) NOT NULL DEFAULT 'image/jpeg',
                file_path text NULL,
                width_px int unsigned NOT NULL DEFAULT 1080,
                height_px int unsigned NOT NULL DEFAULT 1080,
                duration_sec int unsigned NOT NULL DEFAULT 0,
                alt_text text NULL,
                copyright_notice varchar(255) NULL,
                renditions_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY idx_brand_kind (brand_id, kind)
            ) {$charsetCollate};",

            'smh_snippets' => "CREATE TABLE {$prefix}smh_snippets (
                id varchar(64) NOT NULL,
                brand_id varchar(64) NOT NULL,
                title varchar(190) NOT NULL,
                snippet_type varchar(32) NOT NULL DEFAULT 'hashtags',
                content text NOT NULL,
                platforms_json longtext NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY idx_brand_type (brand_id, snippet_type)
            ) {$charsetCollate};",

            'smh_post_groups' => "CREATE TABLE {$prefix}smh_post_groups (
                id varchar(64) NOT NULL,
                brand_id varchar(64) NOT NULL,
                campaign varchar(190) NULL,
                title varchar(255) NOT NULL,
                master_text longtext NOT NULL,
                primary_type varchar(32) NOT NULL DEFAULT 'image',
                primary_media_id varchar(64) NULL,
                external_url text NULL,
                utm_config_json longtext NULL,
                approval_status varchar(32) NOT NULL DEFAULT 'none',
                approved_by bigint(20) unsigned NULL,
                approved_at datetime NULL,
                scheduled_at datetime NULL,
                timezone varchar(64) NOT NULL DEFAULT 'Europe/Berlin',
                current_revision_id varchar(64) NULL,
                created_by bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY idx_brand_scheduled (brand_id, scheduled_at),
                KEY idx_approval (approval_status)
            ) {$charsetCollate};",

            'smh_variants' => "CREATE TABLE {$prefix}smh_variants (
                id varchar(64) NOT NULL,
                post_group_id varchar(64) NOT NULL,
                account_id varchar(64) NOT NULL,
                enabled tinyint(1) NOT NULL DEFAULT 1,
                format_key varchar(40) NOT NULL DEFAULT 'feed',
                text_override longtext NULL,
                media_ids_json longtext NULL,
                rendition_key varchar(32) NOT NULL DEFAULT '4:5',
                metadata_json longtext NULL,
                preflight_state varchar(32) NOT NULL DEFAULT 'unknown',
                preflight_report_json longtext NULL,
                delivery_status varchar(40) NOT NULL DEFAULT 'pending',
                scheduled_override_at datetime NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY idx_post_account (post_group_id, account_id),
                KEY idx_account_status (account_id, delivery_status)
            ) {$charsetCollate};",

            'smh_revisions' => "CREATE TABLE {$prefix}smh_revisions (
                id varchar(64) NOT NULL,
                post_group_id varchar(64) NOT NULL,
                revision_number int unsigned NOT NULL DEFAULT 1,
                reason varchar(64) NOT NULL DEFAULT 'approval',
                snapshot_hash char(64) NOT NULL,
                snapshot_json longtext NOT NULL,
                created_by bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY idx_post_rev (post_group_id, revision_number),
                KEY idx_hash (snapshot_hash)
            ) {$charsetCollate};",

            'smh_jobs' => "CREATE TABLE {$prefix}smh_jobs (
                id varchar(64) NOT NULL,
                post_group_id varchar(64) NOT NULL,
                variant_id varchar(64) NOT NULL,
                account_id varchar(64) NOT NULL,
                revision_id varchar(64) NOT NULL,
                idempotency_key varchar(128) NOT NULL,
                job_stage varchar(40) NOT NULL DEFAULT 'queued',
                job_status varchar(40) NOT NULL DEFAULT 'queued',
                attempt_count int unsigned NOT NULL DEFAULT 0,
                max_attempts int unsigned NOT NULL DEFAULT 3,
                next_attempt_at datetime NULL,
                remote_container_id varchar(190) NULL,
                remote_post_id varchar(190) NULL,
                remote_url text NULL,
                last_http_code int NULL,
                last_error_code varchar(80) NULL,
                last_error_message text NULL,
                completed_at datetime NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY idx_idempotency (idempotency_key),
                KEY idx_status_next (job_status, next_attempt_at),
                KEY idx_post_account (post_group_id, account_id)
            ) {$charsetCollate};",

            'smh_receipts' => "CREATE TABLE {$prefix}smh_receipts (
                id varchar(64) NOT NULL,
                post_group_id varchar(64) NOT NULL,
                variant_id varchar(64) NOT NULL,
                account_id varchar(64) NOT NULL,
                revision_id varchar(64) NULL,
                receipt_state varchar(32) NOT NULL DEFAULT 'confirmed',
                external_url text NULL,
                notes text NULL,
                confirmed_by bigint(20) unsigned NOT NULL DEFAULT 1,
                confirmed_at datetime NOT NULL,
                revoked_at datetime NULL,
                PRIMARY KEY  (id),
                KEY idx_post_account (post_group_id, account_id)
            ) {$charsetCollate};",

            'smh_audit_events' => "CREATE TABLE {$prefix}smh_audit_events (
                id varchar(64) NOT NULL,
                brand_id varchar(64) NULL,
                post_group_id varchar(64) NULL,
                account_id varchar(64) NULL,
                event_type varchar(80) NOT NULL,
                title varchar(255) NOT NULL,
                detail text NULL,
                actor_id bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY idx_brand_created (brand_id, created_at),
                KEY idx_post_created (post_group_id, created_at)
            ) {$charsetCollate};",
        ];
    }
}
