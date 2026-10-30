-- Staging schema; install with MediaWiki schema updater in integration phase.
CREATE TABLE /*_*/map_geo_feed_approval (
 ga_page_id INT UNSIGNED NOT NULL PRIMARY KEY,
 ga_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 ga_revision_id INT UNSIGNED NOT NULL,
 ga_actor_id INT UNSIGNED NOT NULL,
 ga_updated_at BINARY(14) NOT NULL
);
CREATE TABLE /*_*/map_geo_feed_audit (
 gaa_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 gaa_page_id INT UNSIGNED NOT NULL,
 gaa_actor_id INT UNSIGNED NOT NULL,
 gaa_action VARCHAR(24) NOT NULL,
 gaa_prior_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 gaa_new_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 gaa_revision_id INT UNSIGNED NOT NULL,
 gaa_reason VARCHAR(255) NOT NULL,
 gaa_created_at BINARY(14) NOT NULL,
 INDEX gaa_page_time (gaa_page_id, gaa_created_at)
);
CREATE TABLE /*_*/map_geo_feed_state (
 gs_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 gs_epoch BIGINT UNSIGNED NOT NULL DEFAULT 0,
 gs_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 gs_last_success BINARY(14) NULL
);
INSERT INTO /*_*/map_geo_feed_state (gs_id, gs_epoch) VALUES (1, 0);
