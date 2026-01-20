CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "username" varchar not null,
  "surname" varchar,
  "pending_email" varchar,
  "email_otp_hash" varchar,
  "email_otp_expires_at" datetime,
  "two_factor_enabled" tinyint(1) not null default '0',
  "two_factor_secret" text
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE UNIQUE INDEX "users_username_unique" on "users"("username");
CREATE TABLE IF NOT EXISTS "permissions"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "permissions_name_guard_name_unique" on "permissions"(
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "roles"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "guard_name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "roles_name_guard_name_unique" on "roles"(
  "name",
  "guard_name"
);
CREATE TABLE IF NOT EXISTS "model_has_permissions"(
  "permission_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  primary key("permission_id", "model_id", "model_type")
);
CREATE INDEX "model_has_permissions_model_id_model_type_index" on "model_has_permissions"(
  "model_id",
  "model_type"
);
CREATE TABLE IF NOT EXISTS "model_has_roles"(
  "role_id" integer not null,
  "model_type" varchar not null,
  "model_id" integer not null,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("role_id", "model_id", "model_type")
);
CREATE INDEX "model_has_roles_model_id_model_type_index" on "model_has_roles"(
  "model_id",
  "model_type"
);
CREATE TABLE IF NOT EXISTS "role_has_permissions"(
  "permission_id" integer not null,
  "role_id" integer not null,
  foreign key("permission_id") references "permissions"("id") on delete cascade,
  foreign key("role_id") references "roles"("id") on delete cascade,
  primary key("permission_id", "role_id")
);
CREATE TABLE IF NOT EXISTS "portal_settings"(
  "id" integer primary key autoincrement not null,
  "key" varchar not null,
  "value" text,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "portal_settings_key_unique" on "portal_settings"("key");
CREATE TABLE IF NOT EXISTS "assets"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "type" varchar not null default 'Apartment',
  "address" text,
  "notes" text,
  "purchase_date" date,
  "purchase_price" numeric,
  "currency" varchar not null default 'EUR',
  "owner_entity" varchar,
  "ownership_percentage" numeric not null default '100',
  "title_deed" tinyint(1) not null default '0',
  "title_deed_number" varchar,
  "title_deed_date" date,
  "lawyer_notary" varchar,
  "financed" tinyint(1) not null default '0',
  "lender" varchar,
  "loan_amount" numeric,
  "interest_rate" numeric,
  "loan_start_date" date,
  "loan_end_date" date,
  "monthly_payment" numeric,
  "size_sqm" numeric,
  "land_sqm" numeric,
  "bedrooms" integer,
  "bathrooms" integer,
  "parking" tinyint(1) not null default '0',
  "year_built" integer,
  "status" varchar not null default 'Vacant',
  "estimated_annual_expenses" numeric,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE IF NOT EXISTS "asset_tags"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "asset_tags_name_unique" on "asset_tags"("name");
CREATE TABLE IF NOT EXISTS "asset_asset_tag"(
  "id" integer primary key autoincrement not null,
  "asset_id" integer not null,
  "asset_tag_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("asset_id") references "assets"("id") on delete cascade,
  foreign key("asset_tag_id") references "asset_tags"("id") on delete cascade
);
CREATE UNIQUE INDEX "asset_asset_tag_asset_id_asset_tag_id_unique" on "asset_asset_tag"(
  "asset_id",
  "asset_tag_id"
);
CREATE TABLE IF NOT EXISTS "asset_rentals"(
  "id" integer primary key autoincrement not null,
  "asset_id" integer not null,
  "year" integer not null,
  "month" integer not null,
  "amount" numeric not null default '0',
  "currency" varchar not null default 'EUR',
  "channel" varchar,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "agreement_start_date" date,
  "agreement_end_date" date,
  "tenant_name" varchar,
  "rent_type" varchar,
  "is_active" tinyint(1) not null default '1',
  foreign key("asset_id") references "assets"("id") on delete cascade
);
CREATE UNIQUE INDEX "asset_rentals_asset_id_year_month_unique" on "asset_rentals"(
  "asset_id",
  "year",
  "month"
);
CREATE INDEX "asset_rentals_year_month_index" on "asset_rentals"(
  "year",
  "month"
);
CREATE TABLE IF NOT EXISTS "smtp_settings"(
  "id" integer primary key autoincrement not null,
  "enabled" tinyint(1) not null default '0',
  "host" varchar,
  "port" integer,
  "encryption" varchar,
  "username" varchar,
  "password" text,
  "from_address" varchar,
  "from_name" varchar,
  "last_tested_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE TABLE IF NOT EXISTS "asset_documents"(
  "id" integer primary key autoincrement not null,
  "asset_id" integer not null,
  "original_name" varchar not null,
  "file_path" varchar not null,
  "mime" varchar,
  "size" integer,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("asset_id") references "assets"("id") on delete cascade
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2026_01_19_065625_add_profile_and_2fa_fields_to_users_table',1);
INSERT INTO migrations VALUES(5,'2026_01_19_070516_create_permission_tables',1);
INSERT INTO migrations VALUES(6,'2026_01_19_072449_create_portal_settings_table',1);
INSERT INTO migrations VALUES(7,'2026_01_19_090000_create_assets_table',1);
INSERT INTO migrations VALUES(8,'2026_01_19_090010_create_asset_tags_table',1);
INSERT INTO migrations VALUES(9,'2026_01_19_090020_create_asset_asset_tag_table',1);
INSERT INTO migrations VALUES(10,'2026_01_19_090030_create_asset_rentals_table',1);
INSERT INTO migrations VALUES(11,'2026_01_19_100000_create_smtp_settings_table',1);
INSERT INTO migrations VALUES(12,'2026_01_19_120010_create_asset_documents_table',1);
INSERT INTO migrations VALUES(13,'2026_01_19_121540_add_agreement_start_date_to_asset_rentals_table',1);
INSERT INTO migrations VALUES(14,'2026_01_19_131500_add_missing_rental_fields_to_asset_rentals_table',2);
INSERT INTO migrations VALUES(15,'2026_01_19_140000_add_rental_agreement_fields_to_asset_rentals_table',3);
