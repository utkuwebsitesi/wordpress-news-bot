# Database Health and Repair

WordPress News Bot 0.3.4 validates the physical schema instead of trusting only the saved schema version. The inspection reads table, column, index, engine, charset and collation metadata; it does not read or export news, WordPress posts, users, credentials or API keys.

The source writer uses the WordPress-prefixed `wpnb_sources` table. A new source insert writes `name`, `feed_url`, `canonical_hash`, `allowed_domains`, `category_id`, `active`, `updated_at`, `last_checked_at`, `last_result`, `last_error` and `created_at`. The remaining required source columns use schema defaults. `canonical_hash_unique(canonical_hash)` is the concurrency boundary for duplicate sources.

Version 0.3.0 did not define `canonical_hash`, `last_checked_at` or `last_result`. If a prior `dbDelta` upgrade did not physically add them, 0.3.3 could pass the HTTP/feed test and fail at the duplicate lookup or insert stage. The repository history does not use `canonical_url`, `allowed_host` or `is_active`; the canonical fields are `feed_url`, `allowed_domains` and `active`.

Repair is additive and journaled. Missing tables and safe missing columns are added, duplicate data is checked before a unique index is created, and the physical schema is inspected again before the schema version is updated. Existing type, engine or collation mismatches are reported for review instead of being changed automatically. Production repair code contains no `DROP TABLE` or `TRUNCATE`, and MySQL DDL is not treated as transaction-roll-backable.

Version 0.3.0 through 0.3.3 appended only WordPress charset/collation metadata to `CREATE TABLE` statements and did not specify a storage engine. A server whose `default_storage_engine` was MyISAM therefore created plugin tables as MyISAM. Version 0.3.4 forced InnoDB for new tables and detected legacy engine mismatches; version 0.3.5 adds the explicit, confirmed conversion path for existing tables.

Engine conversion first records a non-autoloaded, metadata-only WordPress option so progress does not depend on the initially MyISAM migration journal. It verifies InnoDB support and ALTER privilege, records row counts and table sizes, converts the trusted plugin tables one at a time, and checks engine, row count and extended checksum after every ALTER. Once the migration journal itself is InnoDB, progress is also written there. The first failure stops subsequent tables, and a later run resumes by skipping tables already verified as InnoDB.
