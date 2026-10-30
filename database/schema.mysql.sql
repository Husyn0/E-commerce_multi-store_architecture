-- E-commerce multi-store initial schema.
-- Target: MySQL 8.0.16+ (CHECK constraints and STORED generated columns).
-- This is a fresh-install schema, not a migration or upgrade script.
-- Tenant hard deletion is intentionally unsupported; use suspension.

CREATE DATABASE IF NOT EXISTS ecommerce_multistore
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE ecommerce_multistore;

CREATE TABLE IF NOT EXISTS tenants (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name VARCHAR(160) NOT NULL,
  subdomain VARCHAR(63) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  custom_domain VARCHAR(253) CHARACTER SET ascii COLLATE ascii_general_ci NULL,
  status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  default_currency CHAR(3) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  default_locale VARCHAR(5) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tenants_subdomain (subdomain),
  UNIQUE KEY uq_tenants_custom_domain (custom_domain),
  UNIQUE KEY uq_tenants_id_currency (id, default_currency),
  CONSTRAINT chk_tenants_currency CHECK (default_currency REGEXP '^[A-Z]{3}$')
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tenant_settings (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  theme_config JSON NOT NULL,
  payment_driver ENUM('demo', 'stripe') NOT NULL DEFAULT 'demo',
  verification_driver ENUM('demo', 'real') NOT NULL DEFAULT 'demo',
  human_check_driver ENUM('demo', 'turnstile', 'hcaptcha') NOT NULL DEFAULT 'demo',
  email_provider ENUM('log', 'smtp', 'postmark', 'ses') NOT NULL DEFAULT 'log',
  sms_provider ENUM('log', 'twilio') NOT NULL DEFAULT 'log',
  stripe_account_id VARCHAR(255) NULL,
  require_email_verification BOOLEAN NOT NULL DEFAULT TRUE,
  require_phone_verification BOOLEAN NOT NULL DEFAULT FALSE,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tenant_settings_tenant (tenant_id),
  UNIQUE KEY uq_tenant_settings_id_tenant (tenant_id, id),
  UNIQUE KEY uq_tenant_settings_stripe_account (stripe_account_id),
  CONSTRAINT fk_tenant_settings_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  email VARCHAR(254) NOT NULL,
  email_verified_at TIMESTAMP NULL,
  phone VARCHAR(32) NULL,
  phone_verified_at TIMESTAMP NULL,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NULL,
  role ENUM('customer', 'admin') NOT NULL,
  status ENUM('active', 'suspended', 'deleted') NOT NULL DEFAULT 'active',
  last_login_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_tenant_id_id (tenant_id, id),
  UNIQUE KEY uq_users_tenant_email (tenant_id, email),
  KEY ix_users_tenant_role_status (tenant_id, role, status),
  CONSTRAINT chk_users_reserved_id CHECK (id <> '00000000-0000-0000-0000-000000000000'),
  CONSTRAINT fk_users_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_sessions (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  session_type ENUM('admin_refresh', 'storefront') NOT NULL,
  token_hash BINARY(32) NOT NULL,
  user_agent VARCHAR(512) NULL,
  ip_address VARCHAR(45) NULL,
  expires_at TIMESTAMP NOT NULL,
  last_used_at TIMESTAMP NULL,
  revoked_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_sessions_tenant_id_id (tenant_id, id),
  UNIQUE KEY uq_user_sessions_token (tenant_id, token_hash),
  KEY ix_user_sessions_tenant_user_expires (tenant_id, user_id, expires_at),
  KEY ix_user_sessions_tenant_type_user (tenant_id, session_type, user_id),
  KEY ix_user_sessions_tenant_type_last_used (tenant_id, session_type, last_used_at),
  CONSTRAINT fk_user_sessions_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_user_sessions_user
    FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS verification_codes (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  identifier VARCHAR(254) NOT NULL,
  channel ENUM('email', 'sms') NOT NULL,
  purpose ENUM('verify_email', 'verify_phone', 'login_2fa', 'password_reset', 'guest_order_lookup') NOT NULL,
  code_hash BINARY(32) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  used_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_verification_codes_tenant_id_id (tenant_id, id),
  KEY ix_verification_codes_identifier (tenant_id, identifier, purpose, expires_at),
  KEY ix_verification_codes_user_purpose (tenant_id, user_id, purpose),
  CONSTRAINT chk_verification_attempts CHECK (max_attempts > 0 AND attempts <= max_attempts),
  CONSTRAINT fk_verification_codes_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_verification_codes_user
    FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS human_verifications (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  session_token_hash BINARY(32) NOT NULL,
  provider ENUM('demo', 'turnstile', 'hcaptcha') NOT NULL,
  action ENUM('register', 'checkout') NOT NULL,
  passed BOOLEAN NOT NULL,
  ip_address VARCHAR(45) NULL,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_human_verifications_tenant_id_id (tenant_id, id),
  UNIQUE KEY uq_human_verifications_token (tenant_id, session_token_hash),
  KEY ix_human_verifications_expires (tenant_id, expires_at),
  CONSTRAINT fk_human_verifications_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payment_methods (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  provider ENUM('demo', 'stripe') NOT NULL,
  provider_customer_id VARCHAR(255) NOT NULL,
  provider_payment_method_id VARCHAR(255) NOT NULL,
  card_brand VARCHAR(40) NULL,
  card_last4 CHAR(4) NULL,
  card_exp_month TINYINT UNSIGNED NULL,
  card_exp_year SMALLINT UNSIGNED NULL,
  is_default BOOLEAN NOT NULL DEFAULT FALSE,
  default_provider_guard TINYINT
    GENERATED ALWAYS AS (CASE WHEN is_default THEN 1 ELSE NULL END) STORED,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_methods_tenant_id_id (tenant_id, id),
  UNIQUE KEY uq_payment_methods_default (tenant_id, user_id, provider, default_provider_guard),
  KEY ix_payment_methods_user (tenant_id, user_id),
  KEY ix_payment_methods_user_provider_default (tenant_id, user_id, provider, is_default),
  CONSTRAINT chk_payment_card_exp_month CHECK (card_exp_month IS NULL OR card_exp_month BETWEEN 1 AND 12),
  CONSTRAINT fk_payment_methods_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_payment_methods_user
    FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  parent_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL,
  icon VARCHAR(32) NULL,
  image_url VARCHAR(2048) NULL,
  position INT NOT NULL DEFAULT 0,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_tenant_id_id (tenant_id, id),
  UNIQUE KEY uq_categories_tenant_slug (tenant_id, slug),
  KEY ix_categories_parent_active (tenant_id, parent_id, is_active),
  CONSTRAINT fk_categories_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_categories_parent
    FOREIGN KEY (tenant_id, parent_id) REFERENCES categories (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  category_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  title VARCHAR(240) NOT NULL,
  slug VARCHAR(240) NOT NULL,
  description TEXT NOT NULL,
  sku VARCHAR(100) NULL,
  price_cents BIGINT NOT NULL,
  compare_at_price_cents BIGINT NULL,
  currency CHAR(3) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  weight_grams INT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  published_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_tenant_id_id (tenant_id, id),
  UNIQUE KEY uq_products_tenant_slug (tenant_id, slug),
  UNIQUE KEY uq_products_tenant_sku (tenant_id, sku),
  KEY ix_products_catalog (tenant_id, category_id, is_active, published_at),
  CONSTRAINT chk_products_reserved_id CHECK (id <> '00000000-0000-0000-0000-000000000000'),
  CONSTRAINT chk_products_price CHECK (price_cents >= 0),
  CONSTRAINT chk_products_compare_price CHECK (compare_at_price_cents IS NULL OR compare_at_price_cents > price_cents),
  CONSTRAINT chk_products_stock CHECK (stock >= 0),
  CONSTRAINT chk_products_weight CHECK (weight_grams IS NULL OR weight_grams >= 0),
  CONSTRAINT chk_products_currency CHECK (currency REGEXP '^[A-Z]{3}$'),
  CONSTRAINT fk_products_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_products_category
    FOREIGN KEY (tenant_id, category_id) REFERENCES categories (tenant_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_products_tenant_currency
    FOREIGN KEY (tenant_id, currency) REFERENCES tenants (id, default_currency)
      ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_images (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  product_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  url VARCHAR(2048) NOT NULL,
  alt_text VARCHAR(500) NULL,
  media_type ENUM('image', 'video') NOT NULL DEFAULT 'image',
  position INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_images_tenant_id_id (tenant_id, id),
  KEY ix_product_images_product_position (tenant_id, product_id, position),
  CONSTRAINT fk_product_images_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_product_images_product
    FOREIGN KEY (tenant_id, product_id) REFERENCES products (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_variants (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  product_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  sku VARCHAR(100) NOT NULL,
  name VARCHAR(160) NOT NULL,
  attributes JSON NOT NULL,
  price_cents BIGINT NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_product_variants_tenant_id_id (tenant_id, id),
  UNIQUE KEY uq_product_variants_tenant_sku (tenant_id, sku),
  KEY ix_product_variants_product (tenant_id, product_id),
  CONSTRAINT chk_product_variants_reserved_id CHECK (id <> '00000000-0000-0000-0000-000000000000'),
  CONSTRAINT chk_product_variants_price CHECK (price_cents >= 0),
  CONSTRAINT chk_product_variants_stock CHECK (stock >= 0),
  CONSTRAINT fk_product_variants_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_product_variants_product
    FOREIGN KEY (tenant_id, product_id) REFERENCES products (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS carts (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  session_token_hash BINARY(32) NULL,
  currency CHAR(3) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_carts_tenant_id_id (tenant_id, id),
  UNIQUE KEY uq_carts_session_token (tenant_id, session_token_hash),
  KEY ix_carts_user (tenant_id, user_id),
  KEY ix_carts_token (tenant_id, session_token_hash),
  CONSTRAINT chk_carts_currency CHECK (currency REGEXP '^[A-Z]{3}$'),
  CONSTRAINT fk_carts_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_carts_user
    FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cart_items (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  cart_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  product_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  variant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  variant_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin
    GENERATED ALWAYS AS (COALESCE(variant_id, '00000000-0000-0000-0000-000000000000')) STORED NOT NULL,
  quantity INT NOT NULL,
  unit_price_cents BIGINT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cart_items_tenant_id_id (tenant_id, id),
  UNIQUE KEY uq_cart_items_line (tenant_id, cart_id, product_id, variant_key),
  KEY ix_cart_items_cart (tenant_id, cart_id),
  CONSTRAINT chk_cart_items_quantity CHECK (quantity > 0),
  CONSTRAINT chk_cart_items_price CHECK (unit_price_cents >= 0),
  CONSTRAINT fk_cart_items_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_cart_items_cart
    FOREIGN KEY (tenant_id, cart_id) REFERENCES carts (tenant_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_items_product
    FOREIGN KEY (tenant_id, product_id) REFERENCES products (tenant_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_cart_items_variant
    FOREIGN KEY (tenant_id, variant_id) REFERENCES product_variants (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS addresses (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  label VARCHAR(100) NULL,
  recipient_name VARCHAR(160) NOT NULL,
  phone VARCHAR(32) NULL,
  line1 VARCHAR(255) NOT NULL,
  line2 VARCHAR(255) NULL,
  city VARCHAR(120) NOT NULL,
  state VARCHAR(120) NULL,
  postal_code VARCHAR(32) NOT NULL,
  country_code CHAR(2) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  is_default_shipping BOOLEAN NOT NULL DEFAULT FALSE,
  is_default_billing BOOLEAN NOT NULL DEFAULT FALSE,
  default_shipping_guard TINYINT
    GENERATED ALWAYS AS (CASE WHEN is_default_shipping THEN 1 ELSE NULL END) STORED,
  default_billing_guard TINYINT
    GENERATED ALWAYS AS (CASE WHEN is_default_billing THEN 1 ELSE NULL END) STORED,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_addresses_tenant_id_id (tenant_id, id),
  UNIQUE KEY uq_addresses_default_shipping (tenant_id, user_id, default_shipping_guard),
  UNIQUE KEY uq_addresses_default_billing (tenant_id, user_id, default_billing_guard),
  KEY ix_addresses_user (tenant_id, user_id),
  CONSTRAINT fk_addresses_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_addresses_user
    FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  order_number CHAR(26) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
  payment_status ENUM('requires_payment', 'processing', 'succeeded', 'failed', 'refunded') NOT NULL DEFAULT 'requires_payment',
  payment_provider ENUM('demo', 'stripe', 'cash_on_delivery') NOT NULL,
  payment_intent_id VARCHAR(255) NULL,
  checkout_idempotency_key VARCHAR(255) NOT NULL,
  checkout_request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  checkout_idempotency_expires_at TIMESTAMP NOT NULL,
  subtotal_cents BIGINT NOT NULL,
  tax_cents BIGINT NOT NULL DEFAULT 0,
  shipping_cents BIGINT NOT NULL DEFAULT 0,
  discount_cents BIGINT NOT NULL DEFAULT 0,
  total_cents BIGINT NOT NULL,
  currency CHAR(3) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  shipping_address_snapshot JSON NOT NULL,
  billing_address_snapshot JSON NOT NULL,
  customer_email VARCHAR(254) NOT NULL,
  customer_phone VARCHAR(32) NULL,
  notes TEXT NULL,
  paid_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_tenant_id_id (tenant_id, id),
  UNIQUE KEY uq_orders_order_number (tenant_id, order_number),
  UNIQUE KEY uq_orders_payment_intent (tenant_id, payment_provider, payment_intent_id),
  KEY ix_orders_status_created (tenant_id, status, created_at),
  KEY ix_orders_user (tenant_id, user_id),
  CONSTRAINT chk_orders_ulid CHECK (order_number REGEXP '^[0-9A-HJKMNP-TV-Z]{26}$'),
  CONSTRAINT chk_orders_currency CHECK (currency REGEXP '^[A-Z]{3}$'),
  CONSTRAINT chk_orders_amounts CHECK (
    subtotal_cents >= 0 AND tax_cents >= 0 AND shipping_cents >= 0 AND
    discount_cents >= 0 AND discount_cents <= subtotal_cents AND
    total_cents = subtotal_cents + tax_cents + shipping_cents - discount_cents
  ),
  CONSTRAINT fk_orders_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_orders_user
    FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  product_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  variant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  product_snapshot JSON NOT NULL,
  quantity INT NOT NULL,
  unit_price_cents BIGINT NOT NULL,
  total_cents BIGINT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_order_items_tenant_id_id (tenant_id, id),
  KEY ix_order_items_order (tenant_id, order_id),
  CONSTRAINT chk_order_items_values CHECK (
    quantity > 0 AND unit_price_cents >= 0 AND
    total_cents = quantity * unit_price_cents
  ),
  CONSTRAINT fk_order_items_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_order_items_order
    FOREIGN KEY (tenant_id, order_id) REFERENCES orders (tenant_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_order_items_product
    FOREIGN KEY (tenant_id, product_id) REFERENCES products (tenant_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_order_items_variant
    FOREIGN KEY (tenant_id, variant_id) REFERENCES product_variants (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS refunds (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  provider_refund_id VARCHAR(255) NULL,
  amount_cents BIGINT NOT NULL,
  reason VARCHAR(500) NULL,
  status ENUM('pending', 'succeeded', 'failed') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_refunds_tenant_id_id (tenant_id, id),
  UNIQUE KEY uq_refunds_provider_id (tenant_id, provider_refund_id),
  KEY ix_refunds_order (tenant_id, order_id),
  CONSTRAINT chk_refunds_amount CHECK (amount_cents > 0),
  CONSTRAINT chk_refunds_succeeded_provider_id CHECK (status <> 'succeeded' OR provider_refund_id IS NOT NULL),
  CONSTRAINT fk_refunds_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_refunds_order
    FOREIGN KEY (tenant_id, order_id) REFERENCES orders (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payment_webhook_events (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  provider ENUM('demo', 'stripe') NOT NULL,
  provider_event_id VARCHAR(255) NOT NULL,
  event_type VARCHAR(120) NOT NULL,
  payload JSON NULL,
  suspended_at_receipt BOOLEAN NOT NULL DEFAULT FALSE,
  status ENUM('pending', 'processing', 'processed', 'failed') NOT NULL DEFAULT 'pending',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  locked_at TIMESTAMP NULL,
  processed_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_webhook_events_tenant_id_id (tenant_id, id),
  UNIQUE KEY uq_webhook_events_dedup (tenant_id, provider, provider_event_id),
  KEY ix_webhook_events_tenant_status_created (tenant_id, status, created_at),
  KEY ix_webhook_events_status_created (status, created_at),
  KEY ix_webhook_events_status_locked (status, locked_at),
  CONSTRAINT fk_webhook_events_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS checkout_idempotency_records (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  idempotency_key VARCHAR(255) NOT NULL,
  request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  principal_type ENUM('user', 'guest') NOT NULL,
  principal_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  guest_cart_token_hash BINARY(32) NULL,
  order_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_checkout_idempotency_tenant_id_id (tenant_id, id),
  UNIQUE KEY uq_checkout_idempotency_key (tenant_id, idempotency_key),
  KEY ix_checkout_idempotency_expires (tenant_id, expires_at),
  CONSTRAINT chk_checkout_idempotency_principal CHECK (
    (principal_type = 'user' AND principal_id IS NOT NULL AND guest_cart_token_hash IS NULL) OR
    (principal_type = 'guest' AND principal_id IS NULL AND guest_cart_token_hash IS NOT NULL)
  ),
  CONSTRAINT fk_checkout_idempotency_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_checkout_idempotency_user
    FOREIGN KEY (tenant_id, principal_id) REFERENCES users (tenant_id, id) ON DELETE RESTRICT,
  CONSTRAINT fk_checkout_idempotency_order
    FOREIGN KEY (tenant_id, order_id) REFERENCES orders (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  tenant_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  actor_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(100) NOT NULL,
  entity_id CHAR(36) NOT NULL,
  source ENUM('web', 'cli', 'webhook') NOT NULL,
  host_type ENUM('admin', 'storefront') NULL,
  metadata JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_audit_logs_tenant_id_id (tenant_id, id),
  KEY ix_audit_logs_tenant_created (tenant_id, created_at),
  KEY ix_audit_logs_tenant_source_created (tenant_id, source, created_at),
  KEY ix_audit_logs_entity (tenant_id, entity_type, entity_id),
  KEY ix_audit_logs_actor (tenant_id, actor_id, created_at),
  CONSTRAINT chk_audit_logs_source_host CHECK (
    (source = 'web' AND host_type IS NOT NULL) OR
    (source IN ('cli', 'webhook') AND host_type IS NULL)
  ),
  CONSTRAINT fk_audit_logs_tenant
    FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
  CONSTRAINT fk_audit_logs_actor
    FOREIGN KEY (tenant_id, actor_id) REFERENCES users (tenant_id, id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- After import, check the installed server and schema:
-- SELECT VERSION();
-- SHOW TABLES;
