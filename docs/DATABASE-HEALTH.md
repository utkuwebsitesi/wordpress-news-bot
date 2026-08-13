# Database Health and Repair

WordPress News Bot 0.3.4 validates the physical schema instead of trusting only the saved schema version. The inspection reads table, column, index, engine, charset and collation metadata; it does not read or export news, WordPress posts, users, credentials or API keys.

The source writer uses the WordPress-prefixed `wpnb_sources` table. A new source insert writes `name`, `feed_url`, `canonical_hash`, `allowed_domains`, `category_id`, `active`, `updated_at`, `last_checked_at`, `last_result`, `last_error` and `created_at`. The remaining required source columns use schema defaults. `canonical_hash_unique(canonical_hash)` is the concurrency boundary for duplicate sources.

Version 0.3.0 did not define `canonical_hash`, `last_checked_at` or `last_result`. If a prior `dbDelta` upgrade did not physically add them, 0.3.3 could pass the HTTP/feed test and fail at the duplicate lookup or insert stage. The repository history does not use `canonical_url`, `allowed_host` or `is_active`; the canonical fields are `feed_url`, `allowed_domains` and `active`.

Repair is additive and journaled. Missing tables and safe missing columns are added, duplicate data is checked before a unique index is created, and the physical schema is inspected again before the schema version is updated. Existing type, engine or collation mismatches are reported for review instead of being changed automatically. Production repair code contains no `DROP TABLE` or `TRUNCATE`, and MySQL DDL is not treated as transaction-roll-backable.
