# New branch optional-billing

## Subscription + Pro capability (shared across future sites)

We will use **Stripe as the billing provider**. Stripe is the source of truth for paid subscription state; the app stores a local mirror for gating features and for debugging.

### What we store locally

1. Plan definitions (your business concepts): Free, Trial, Monthly, Annual, Lifetime
2. Stripe mappings (customer, subscription, price/product references)
3. Webhook event log (idempotency + audit)
4. Optional invoice ledger for support/reporting

---

### Table: `subscription_plans`

Stores the available plan types.

Columns:

* `subscription_plan_id` BIGINT UNSIGNED AUTO_INCREMENT
* `code` VARCHAR(32) NOT NULL UNIQUE

  * Suggested codes: `FREE`, `TRIAL`, `MONTHLY`, `ANNUAL`, `LIFETIME`
* `name` VARCHAR(64) NOT NULL
* `is_pro` TINYINT(1) NOT NULL DEFAULT 0
* `trial_days` SMALLINT UNSIGNED NULL
* `duration_days` SMALLINT UNSIGNED NULL
* `created_at_utc` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
* `updated_at_utc` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)

Stripe mapping (nullable for FREE / manual lifetime):

* `stripe_product_ref` VARCHAR(64) NULL
* `stripe_price_ref` VARCHAR(64) NULL
* `stripe_livemode` TINYINT(1) NOT NULL DEFAULT 0

Notes:

* You can keep pricing entirely in Stripe; no need to store cents/currency locally unless you want.

---

### Table: `stripe_customers`

Maps users to Stripe customers.

Columns:

* `stripe_customer_id` BIGINT UNSIGNED AUTO_INCREMENT
* `user_id` BIGINT UNSIGNED NOT NULL
* `stripe_customer_ref` VARCHAR(64) NOT NULL
* `livemode` TINYINT(1) NOT NULL
* `created_at_utc` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)

Indexes/constraints:

* UNIQUE (`stripe_customer_ref`)
* UNIQUE (`user_id`, `livemode`)

---

### Table: `stripe_subscriptions`

Local mirror of Stripe subscription state for feature gating.

Columns:

* `stripe_subscription_id` BIGINT UNSIGNED AUTO_INCREMENT
* `user_id` BIGINT UNSIGNED NOT NULL
* `subscription_plan_id` BIGINT UNSIGNED NOT NULL

Stripe refs:

* `stripe_subscription_ref` VARCHAR(64) NOT NULL
* `stripe_customer_ref` VARCHAR(64) NOT NULL
* `stripe_price_ref` VARCHAR(64) NULL

Status:

* `status` VARCHAR(32) NOT NULL

  * Store the Stripe status string (e.g., `trialing`, `active`, `past_due`, `canceled`, `incomplete`, `unpaid`)
* `cancel_at_period_end` TINYINT(1) NOT NULL DEFAULT 0

Periods (UTC is fine here because it comes from Stripe):

* `current_period_start_utc` DATETIME(6) NULL
* `current_period_end_utc` DATETIME(6) NULL
* `canceled_at_utc` DATETIME(6) NULL
* `ended_at_utc` DATETIME(6) NULL

Env + timestamps:

* `livemode` TINYINT(1) NOT NULL
* `created_at_utc` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
* `updated_at_utc` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)

Indexes/constraints:

* UNIQUE (`stripe_subscription_ref`)
* INDEX (`user_id`, `status`)
* INDEX (`user_id`, `current_period_end_utc`)

---

### Table: `stripe_webhook_events`

Stores Stripe events for idempotency and debugging.

Columns:

* `stripe_event_id` VARCHAR(64) NOT NULL
* `type` VARCHAR(128) NOT NULL
* `livemode` TINYINT(1) NOT NULL
* `received_at_utc` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
* `payload_json` JSON NOT NULL
* `processed_at_utc` DATETIME(6) NULL
* `process_status` VARCHAR(16) NOT NULL DEFAULT 'new'  -- new/ok/error
* `error_message` TEXT NULL

Constraints:

* PRIMARY KEY (`stripe_event_id`)

---

### Optional table: `stripe_invoices`

Useful for support (“what did I pay?”). Not required for gating.

Columns:

* `stripe_invoice_ref` VARCHAR(64) NOT NULL
* `user_id` BIGINT UNSIGNED NOT NULL
* `stripe_subscription_ref` VARCHAR(64) NULL
* `status` VARCHAR(32) NULL
* `amount_due` BIGINT NULL
* `amount_paid` BIGINT NULL
* `currency` CHAR(3) NULL
* `stripe_hosted_invoice_url` TEXT NULL
* `invoice_pdf_url` TEXT NULL
* `created_utc` DATETIME(6) NULL
* `paid_utc` DATETIME(6) NULL
* `livemode` TINYINT(1) NOT NULL

Constraints:

* PRIMARY KEY (`stripe_invoice_ref`)

---

### Pro gating rule (Stripe-backed)

A user is considered **Pro** if they have:

* an active Stripe subscription row whose `status` is in (`trialing`, `active`)

  * optionally treat `past_due` as Pro during a grace window if you want
* and `current_period_end_utc` is NULL or in the future
* and the linked `subscription_plans.is_pro = 1`

Lifetime:

* Can be implemented as either:

  1. A Stripe one-time purchase + you create a local “entitlement” record, OR
  2. A manual admin grant (no Stripe)

---

### SQL (drop-in example)

```sql
CREATE TABLE subscription_plans (
  subscription_plan_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(64) NOT NULL,
  is_pro TINYINT(1) NOT NULL DEFAULT 0,
  trial_days SMALLINT UNSIGNED NULL,
  duration_days SMALLINT UNSIGNED NULL,
  stripe_product_ref VARCHAR(64) NULL,
  stripe_price_ref VARCHAR(64) NULL,
  stripe_livemode TINYINT(1) NOT NULL DEFAULT 0,
  created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (subscription_plan_id),
  UNIQUE KEY uq_plan_code (code)
) ENGINE=InnoDB;

CREATE TABLE stripe_customers (
  stripe_customer_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  stripe_customer_ref VARCHAR(64) NOT NULL,
  livemode TINYINT(1) NOT NULL,
  created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (stripe_customer_id),
  UNIQUE KEY uq_stripe_customer_ref (stripe_customer_ref),
  UNIQUE KEY uq_user_livemode (user_id, livemode),
  CONSTRAINT fk_stripe_customers_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE stripe_subscriptions (
  stripe_subscription_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  subscription_plan_id BIGINT UNSIGNED NOT NULL,

  stripe_subscription_ref VARCHAR(64) NOT NULL,
  stripe_customer_ref VARCHAR(64) NOT NULL,
  stripe_price_ref VARCHAR(64) NULL,

  status VARCHAR(32) NOT NULL,
  cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,

  current_period_start_utc DATETIME(6) NULL,
  current_period_end_utc DATETIME(6) NULL,
  canceled_at_utc DATETIME(6) NULL,
  ended_at_utc DATETIME(6) NULL,

  livemode TINYINT(1) NOT NULL,
  created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),

  PRIMARY KEY (stripe_subscription_id),
  UNIQUE KEY uq_stripe_subscription_ref (stripe_subscription_ref),
  KEY idx_user_status (user_id, status),
  KEY idx_user_period_end (user_id, current_period_end_utc),
  KEY idx_plan (subscription_plan_id),

  CONSTRAINT fk_stripe_subscriptions_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE,
  CONSTRAINT fk_stripe_subscriptions_plan
    FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(subscription_plan_id)
    ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE stripe_webhook_events (
  stripe_event_id VARCHAR(64) NOT NULL,
  type VARCHAR(128) NOT NULL,
  livemode TINYINT(1) NOT NULL,
  received_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  payload_json JSON NOT NULL,
  processed_at_utc DATETIME(6) NULL,
  process_status VARCHAR(16) NOT NULL DEFAULT 'new',
  error_message TEXT NULL,
  PRIMARY KEY (stripe_event_id)
) ENGINE=InnoDB;

CREATE TABLE stripe_invoices (
  stripe_invoice_ref VARCHAR(64) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  stripe_subscription_ref VARCHAR(64) NULL,
  status VARCHAR(32) NULL,
  amount_due BIGINT NULL,
  amount_paid BIGINT NULL,
  currency CHAR(3) NULL,
  stripe_hosted_invoice_url TEXT NULL,
  invoice_pdf_url TEXT NULL,
  created_utc DATETIME(6) NULL,
  paid_utc DATETIME(6) NULL,
  livemode TINYINT(1) NOT NULL,
  PRIMARY KEY (stripe_invoice_ref),
  KEY idx_user_invoice (user_id, created_utc),
  CONSTRAINT fk_stripe_invoices_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO subscription_plans (code, name, is_pro, trial_days, duration_days)
VALUES
  ('FREE', 'Free', 0, NULL, NULL),
  ('TRIAL', 'Free Trial', 1, 14, 14),
  ('MONTHLY', 'Monthly', 1, NULL, 30),
  ('ANNUAL', 'Annual', 1, NULL, 365),
  ('LIFETIME', 'Lifetime', 1, NULL, NULL);
```

