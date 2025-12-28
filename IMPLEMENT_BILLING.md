# Implementation Plan: Optional Billing Feature

## Overview

This document outlines the atomic steps to implement a Stripe-based billing system with subscription tiers (Free, Monthly, Annual, Lifetime) in the `optional-billing` branch.

## Prerequisites

- Stripe test account with API keys
- Existing `users` table with `user_id` column
- PHP environment with PDO support
- Composer for Stripe PHP SDK

---

## Phase 1: Stripe Setup (Test Mode)

### Step 1.1: Create Stripe Test Account & Get API Keys
**Atomic Task:** Set up Stripe test account and obtain test API keys

- [ ] Sign up for Stripe account (if not already done)
- [ ] Navigate to Stripe Dashboard → Developers → API Keys
- [ ] Copy **Publishable Key** (starts with `pk_test_`)
- [ ] Copy **Secret Key** (starts with `sk_test_`)
- [ ] Add keys to `classes/Config.php` (NOT in version control, similar to database credentials)

**Deliverable:** Test API keys stored in Config.php

---

### Step 1.2: Create Stripe Products & Prices
**Atomic Task:** Set up subscription products in Stripe Dashboard

Create the following products in Stripe Dashboard (Test Mode):

1. **Monthly Subscription**
   - Product name: "Monthly Pro"
   - Price: $9.99/month (or your chosen amount)
   - Recurring billing: Monthly
   - Copy Price ID (starts with `price_`)

2. **Annual Subscription**
   - Product name: "Annual Pro"
   - Price: $99/year (or your chosen amount)
   - Recurring billing: Yearly
   - Copy Price ID (starts with `price_`)

3. **Lifetime (One-time payment)**
   - Product name: "Lifetime Pro"
   - Price: $299 (or your chosen amount)
   - One-time payment
   - Copy Price ID (starts with `price_`)

**Deliverable:**
- Product IDs and Price IDs documented
- Screenshot or note of Stripe product configuration

---

### Step 1.3: Configure Stripe Webhook Endpoint
**Atomic Task:** Set up webhook endpoint URL in Stripe

- [ ] In Stripe Dashboard → Developers → Webhooks
- [ ] Add endpoint: `https://yourdomain.com/webhooks/stripe` (placeholder URL for now)
- [ ] Select events to listen to:
  - `customer.subscription.created`
  - `customer.subscription.updated`
  - `customer.subscription.deleted`
  - `invoice.payment_succeeded`
  - `invoice.payment_failed`
- [ ] Copy **Webhook Signing Secret** (starts with `whsec_`)
- [ ] Add webhook secret to `classes/Config.php`

**Deliverable:** Webhook signing secret stored in Config.php

---

## Phase 2: Database Schema - MVP Tables

### Step 2.1: Create Branch & Directory Structure
**Atomic Task:** Create git branch and database schema directory

```bash
git checkout -b optional-billing
mkdir -p db_schemas/02_billing
```

**Deliverable:** Branch `optional-billing` created with `db_schemas/02_billing/` directory

---

### Step 2.2: Create `subscription_plans` Table (MVP)
**Atomic Task:** Create minimal subscription plans table

Create file: `db_schemas/02_billing/01_create_subscription_plans.sql`

```sql
CREATE TABLE subscription_plans (
  subscription_plan_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(32) NOT NULL,
  name VARCHAR(64) NOT NULL,
  is_pro TINYINT(1) NOT NULL DEFAULT 0,
  created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (subscription_plan_id),
  UNIQUE KEY uq_plan_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert basic plans
INSERT INTO subscription_plans (code, name, is_pro)
VALUES
  ('FREE', 'Free', 0),
  ('MONTHLY', 'Monthly', 1),
  ('ANNUAL', 'Annual', 1),
  ('LIFETIME', 'Lifetime', 1);
```

**Deliverable:** MVP `subscription_plans` table with 4 basic plans

---

### Step 2.3: Create `stripe_customers` Table (MVP)
**Atomic Task:** Create minimal Stripe customer mapping table

Create file: `db_schemas/02_billing/02_create_stripe_customers.sql`

```sql
CREATE TABLE stripe_customers (
  stripe_customer_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  stripe_customer_ref VARCHAR(64) NOT NULL,
  livemode TINYINT(1) NOT NULL DEFAULT 0,
  created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (stripe_customer_id),
  UNIQUE KEY uq_stripe_customer_ref (stripe_customer_ref),
  UNIQUE KEY uq_user_livemode (user_id, livemode),
  CONSTRAINT fk_stripe_customers_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Deliverable:** MVP `stripe_customers` table

---

### Step 2.4: Create `stripe_subscriptions` Table (MVP)
**Atomic Task:** Create minimal subscriptions tracking table

Create file: `db_schemas/02_billing/03_create_stripe_subscriptions.sql`

```sql
CREATE TABLE stripe_subscriptions (
  stripe_subscription_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  subscription_plan_id BIGINT UNSIGNED NOT NULL,
  stripe_subscription_ref VARCHAR(64) NOT NULL,
  stripe_customer_ref VARCHAR(64) NOT NULL,
  status VARCHAR(32) NOT NULL,
  current_period_end_utc DATETIME(6) NULL,
  livemode TINYINT(1) NOT NULL DEFAULT 0,
  created_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (stripe_subscription_id),
  UNIQUE KEY uq_stripe_subscription_ref (stripe_subscription_ref),
  KEY idx_user_status (user_id, status),
  CONSTRAINT fk_stripe_subscriptions_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE,
  CONSTRAINT fk_stripe_subscriptions_plan
    FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(subscription_plan_id)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Deliverable:** MVP `stripe_subscriptions` table

---

### Step 2.5: Create `stripe_webhook_events` Table (MVP)
**Atomic Task:** Create webhook event logging table

Create file: `db_schemas/02_billing/04_create_stripe_webhook_events.sql`

```sql
CREATE TABLE stripe_webhook_events (
  stripe_event_id VARCHAR(64) NOT NULL,
  type VARCHAR(128) NOT NULL,
  livemode TINYINT(1) NOT NULL DEFAULT 0,
  received_at_utc DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  payload_json JSON NOT NULL,
  processed_at_utc DATETIME(6) NULL,
  process_status VARCHAR(16) NOT NULL DEFAULT 'new',
  PRIMARY KEY (stripe_event_id),
  KEY idx_type_status (type, process_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Deliverable:** MVP `stripe_webhook_events` table

---

## Phase 3: MVP Code - Stripe Integration

### Step 3.1: Install Stripe PHP SDK
**Atomic Task:** Add Stripe SDK via Composer

```bash
cd /home/thunderrabbit/work/rob/new-DH-user-site
composer require stripe/stripe-php
```

**Deliverable:** Stripe PHP SDK installed, `composer.json` and `composer.lock` updated

---

### Step 3.2: Create Stripe Configuration Class
**Atomic Task:** Create configuration wrapper for Stripe

Create file: `classes/Billing/StripeConfig.php`

```php
<?php
namespace Billing;

class StripeConfig
{
    public readonly string $secret_key;
    public readonly string $publishable_key;
    public readonly string $webhook_secret;
    public readonly bool $livemode;

    public function __construct(\Config $config)
    {
        // Load from Config.php (not in version control)
        $this->secret_key = $config->stripe_secret_key ?? '';
        $this->publishable_key = $config->stripe_publishable_key ?? '';
        $this->webhook_secret = $config->stripe_webhook_secret ?? '';
        $this->livemode = $config->stripe_livemode ?? false;

        if (empty($this->secret_key)) {
            throw new \Exception('Stripe secret key not configured in Config.php');
        }
    }
}
```

**Deliverable:** `StripeConfig` class created

---

### Step 3.3: Create Stripe Customer Manager (MVP)
**Atomic Task:** Create class to manage Stripe customers

Create file: `classes/Billing/StripeCustomerManager.php`

```php
<?php
namespace Billing;

class StripeCustomerManager
{
    public function __construct(
        private \PDO $pdo,
        private StripeConfig $config
    ) {
        \Stripe\Stripe::setApiKey($this->config->secret_key);
    }

    /**
     * Get or create Stripe customer for user
     */
    public function getOrCreateCustomer(int $user_id, string $email): string
    {
        // Check if customer exists in DB
        $existing = $this->getCustomerRef($user_id);
        if ($existing) {
            return $existing;
        }

        // Create new Stripe customer
        $customer = \Stripe\Customer::create([
            'email' => $email,
            'metadata' => ['user_id' => $user_id]
        ]);

        // Store in DB
        $stmt = $this->pdo->prepare(
            "INSERT INTO stripe_customers (user_id, stripe_customer_ref, livemode)
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$user_id, $customer->id, $this->config->livemode ? 1 : 0]);

        return $customer->id;
    }

    private function getCustomerRef(int $user_id): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT stripe_customer_ref FROM stripe_customers
             WHERE user_id = ? AND livemode = ? LIMIT 1"
        );
        $stmt->execute([$user_id, $this->config->livemode ? 1 : 0]);
        $result = $stmt->fetch();
        return $result ? $result['stripe_customer_ref'] : null;
    }
}
```

**Deliverable:** `StripeCustomerManager` class created

---

### Step 3.4: Create Subscription Manager (MVP)
**Atomic Task:** Create class to manage subscriptions

Create file: `classes/Billing/SubscriptionManager.php`

```php
<?php
namespace Billing;

class SubscriptionManager
{
    public function __construct(
        private \PDO $pdo,
        private StripeConfig $config
    ) {
        \Stripe\Stripe::setApiKey($this->config->secret_key);
    }

    /**
     * Create checkout session for subscription
     */
    public function createCheckoutSession(
        string $customer_id,
        string $price_id,
        string $success_url,
        string $cancel_url
    ): string {
        $session = \Stripe\Checkout\Session::create([
            'customer' => $customer_id,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $price_id,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
        ]);

        return $session->url;
    }

    /**
     * Store subscription in database
     */
    public function storeSubscription(array $subscription_data): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO stripe_subscriptions
             (user_id, subscription_plan_id, stripe_subscription_ref, stripe_customer_ref,
              status, current_period_end_utc, livemode)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
             status = VALUES(status),
             current_period_end_utc = VALUES(current_period_end_utc),
             updated_at_utc = CURRENT_TIMESTAMP(6)"
        );
        $stmt->execute([
            $subscription_data['user_id'],
            $subscription_data['subscription_plan_id'],
            $subscription_data['stripe_subscription_ref'],
            $subscription_data['stripe_customer_ref'],
            $subscription_data['status'],
            $subscription_data['current_period_end_utc'],
            $this->config->livemode ? 1 : 0
        ]);
    }
}
```

**Deliverable:** `SubscriptionManager` class created

---

### Step 3.5: Create Webhook Handler (MVP)
**Atomic Task:** Create webhook endpoint to receive Stripe events

Create file: `wwwroot/webhooks/stripe.php`

```php
<?php
require_once __DIR__ . '/../../prepend.php';

use Billing\StripeConfig;

$config = new StripeConfig();
\Stripe\Stripe::setApiKey($config->secret_key);

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $config->webhook_secret);
} catch (\Exception $e) {
    http_response_code(400);
    exit();
}

// Log event
$stmt = $pdo->prepare(
    "INSERT INTO stripe_webhook_events (stripe_event_id, type, livemode, payload_json)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE stripe_event_id = stripe_event_id"
);
$stmt->execute([
    $event->id,
    $event->type,
    $event->livemode ? 1 : 0,
    json_encode($event->data->object)
]);

// Handle event types
switch ($event->type) {
    case 'customer.subscription.created':
    case 'customer.subscription.updated':
        // Update subscription in database
        $subscription = $event->data->object;
        // TODO: Call SubscriptionManager->storeSubscription()
        break;

    case 'customer.subscription.deleted':
        // Mark subscription as canceled
        break;
}

http_response_code(200);
```

**Deliverable:** Basic webhook handler created

---

## Phase 4: User-Facing Pages

### Step 4.1: Create Registration Page
**Atomic Task:** Create user registration page with default Free plan

Create file: `wwwroot/register/index.php`

```php
<?php
require_once __DIR__ . '/../../prepend.php';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validate inputs
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } else {
        // Create user
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)"
        );

        try {
            $stmt->execute([$username, $email, $password_hash]);
            $user_id = $pdo->lastInsertId();

            // Assign FREE plan by default
            // (No Stripe customer needed for free tier)

            header('Location: /login');
            exit;
        } catch (\PDOException $e) {
            $error = 'Username or email already exists';
        }
    }
}

// Render registration form
$template = new \Template('register.tpl.php');
$template->error = $error ?? '';
echo $template->render();
```

Create template: `templates/register.tpl.php`

```html
<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="register-container">
        <h1>Create Account</h1>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" required>
            </div>

            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>Password:</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit">Register (Free Account)</button>
        </form>

        <p>Already have an account? <a href="/login">Login</a></p>
    </div>
</body>
</html>
```

**Deliverable:** Registration page with Free tier default

---

### Step 4.2: Create Upgrade/Pricing Page
**Atomic Task:** Create subscription upgrade page with pricing tiers

Create file: `wwwroot/upgrade/index.php`

```php
<?php
require_once __DIR__ . '/../../prepend.php';

// Require login
if (!$isLoggedIn->isLoggedIn()) {
    header('Location: /login');
    exit;
}

$user_id = $isLoggedIn->loggedInID();

// Get subscription plans from database
$stmt = $pdo->query("SELECT * FROM subscription_plans ORDER BY FIELD(code, 'FREE', 'MONTHLY', 'ANNUAL', 'LIFETIME')");
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle plan selection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_code = $_POST['plan'] ?? '';

    if ($plan_code === 'FREE') {
        // Downgrade to free (cancel subscription)
        // TODO: Cancel Stripe subscription
        header('Location: /profile');
        exit;
    }

    // Get user email
    $stmt = $pdo->prepare("SELECT email FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    // Create or get Stripe customer
    $customerManager = new \Billing\StripeCustomerManager($pdo, new \Billing\StripeConfig());
    $customer_id = $customerManager->getOrCreateCustomer($user_id, $user['email']);

    // Get price ID for selected plan
    $stmt = $pdo->prepare("SELECT stripe_price_ref FROM subscription_plans WHERE code = ?");
    $stmt->execute([$plan_code]);
    $plan = $stmt->fetch();

    // Create checkout session
    $subscriptionManager = new \Billing\SubscriptionManager($pdo, new \Billing\StripeConfig());
    $checkout_url = $subscriptionManager->createCheckoutSession(
        $customer_id,
        $plan['stripe_price_ref'],
        'https://yourdomain.com/upgrade/success',
        'https://yourdomain.com/upgrade'
    );

    header('Location: ' . $checkout_url);
    exit;
}

$template = new \Template('upgrade.tpl.php');
$template->plans = $plans;
echo $template->render();
```

Create template: `templates/upgrade.tpl.php`

```html
<!DOCTYPE html>
<html>
<head>
    <title>Upgrade Your Plan</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <div class="pricing-container">
        <h1>Choose Your Plan</h1>

        <div class="pricing-grid">
            <?php foreach ($plans as $plan): ?>
                <div class="pricing-card <?= $plan['is_pro'] ? 'pro' : 'free' ?>">
                    <h2><?= htmlspecialchars($plan['name']) ?></h2>

                    <?php if ($plan['code'] === 'FREE'): ?>
                        <div class="price">$0</div>
                        <p>Basic features</p>
                    <?php elseif ($plan['code'] === 'MONTHLY'): ?>
                        <div class="price">$9.99<span>/month</span></div>
                        <p>All Pro features</p>
                    <?php elseif ($plan['code'] === 'ANNUAL'): ?>
                        <div class="price">$99<span>/year</span></div>
                        <p>Save 17%</p>
                    <?php elseif ($plan['code'] === 'LIFETIME'): ?>
                        <div class="price">$299<span>one-time</span></div>
                        <p>Pay once, use forever</p>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="plan" value="<?= $plan['code'] ?>">
                        <button type="submit" class="select-plan-btn">
                            <?= $plan['code'] === 'FREE' ? 'Current Plan' : 'Select Plan' ?>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
```

**Deliverable:** Upgrade page with 4 pricing tiers

---

### Step 4.3: Create Success/Cancel Pages
**Atomic Task:** Create post-checkout pages

Create file: `wwwroot/upgrade/success.php`

```php
<?php
require_once __DIR__ . '/../../prepend.php';

if (!$isLoggedIn->isLoggedIn()) {
    header('Location: /login');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Subscription Successful</title>
</head>
<body>
    <h1>Welcome to Pro! 🎉</h1>
    <p>Your subscription has been activated.</p>
    <a href="/profile">Go to Profile</a>
</body>
</html>
```

**Deliverable:** Success and cancel pages created

---

## Phase 5: Authorization System

### Step 5.1: Create `IsAuthorized` Class
**Atomic Task:** Create authorization checker class (similar to `IsLoggedIn`)

Create file: `classes/Auth/IsAuthorized.php`

```php
<?php
namespace Auth;

class IsAuthorized
{
    private bool $is_pro = false;
    private ?string $current_plan = null;

    public function __construct(
        private \PDO $pdo,
        private int $user_id
    ) {
        if ($user_id > 0) {
            $this->checkProStatus();
        }
    }

    private function checkProStatus(): void
    {
        $stmt = $this->pdo->prepare("
            SELECT
                sp.code,
                sp.is_pro,
                ss.status,
                ss.current_period_end_utc
            FROM stripe_subscriptions ss
            JOIN subscription_plans sp ON ss.subscription_plan_id = sp.subscription_plan_id
            WHERE ss.user_id = ?
              AND ss.status IN ('active', 'trialing')
              AND (ss.current_period_end_utc IS NULL OR ss.current_period_end_utc > NOW())
            ORDER BY sp.is_pro DESC, ss.created_at_utc DESC
            LIMIT 1
        ");
        $stmt->execute([$this->user_id]);
        $result = $stmt->fetch();

        if ($result) {
            $this->is_pro = (bool)$result['is_pro'];
            $this->current_plan = $result['code'];
        } else {
            // Default to FREE plan
            $this->is_pro = false;
            $this->current_plan = 'FREE';
        }
    }

    public function isPro(): bool
    {
        return $this->is_pro;
    }

    public function getCurrentPlan(): string
    {
        return $this->current_plan ?? 'FREE';
    }

    public function requirePro(): void
    {
        if (!$this->isPro()) {
            header('Location: /upgrade');
            exit;
        }
    }
}
```

**Deliverable:** `IsAuthorized` class created

---

### Step 5.2: Integrate `IsAuthorized` into Application
**Atomic Task:** Add authorization to prepend.php

Edit `prepend.php` to instantiate `IsAuthorized`:

```php
// After IsLoggedIn instantiation
$isLoggedIn = new \Auth\IsLoggedIn($pdo, $config);
$isLoggedIn->checkLogin($mla_request);

// Add IsAuthorized
$isAuthorized = new \Auth\IsAuthorized($pdo, $isLoggedIn->loggedInID());
```

**Deliverable:** `IsAuthorized` available globally as `$isAuthorized`

---

## Phase 6: Enhanced Tables & Features

### Step 6.1: Enhance `subscription_plans` Table
**Atomic Task:** Add Stripe product/price references to plans table

Create migration: `db_schemas/02_billing/05_append_subscription_plans_stripe_refs.sql`

```sql
ALTER TABLE subscription_plans
ADD COLUMN trial_days SMALLINT UNSIGNED NULL AFTER is_pro,
ADD COLUMN duration_days SMALLINT UNSIGNED NULL AFTER trial_days,
ADD COLUMN stripe_product_ref VARCHAR(64) NULL AFTER duration_days,
ADD COLUMN stripe_price_ref VARCHAR(64) NULL AFTER stripe_product_ref,
ADD COLUMN stripe_livemode TINYINT(1) NOT NULL DEFAULT 0 AFTER stripe_price_ref;

-- Update with actual Stripe price IDs from Step 1.2
UPDATE subscription_plans SET stripe_price_ref = 'price_XXXMONTHLY' WHERE code = 'MONTHLY';
UPDATE subscription_plans SET stripe_price_ref = 'price_XXXANNUAL' WHERE code = 'ANNUAL';
UPDATE subscription_plans SET stripe_price_ref = 'price_XXXLIFETIME' WHERE code = 'LIFETIME';
```

**Deliverable:** Enhanced `subscription_plans` table with Stripe references

---

### Step 6.2: Enhance `stripe_subscriptions` Table
**Atomic Task:** Add additional tracking fields

Create migration: `db_schemas/02_billing/06_append_stripe_subscriptions_fields.sql`

```sql
ALTER TABLE stripe_subscriptions
ADD COLUMN stripe_price_ref VARCHAR(64) NULL AFTER stripe_customer_ref,
ADD COLUMN cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
ADD COLUMN current_period_start_utc DATETIME(6) NULL AFTER cancel_at_period_end,
ADD COLUMN canceled_at_utc DATETIME(6) NULL AFTER current_period_end_utc,
ADD COLUMN ended_at_utc DATETIME(6) NULL AFTER canceled_at_utc;

ALTER TABLE stripe_subscriptions
ADD INDEX idx_user_period_end (user_id, current_period_end_utc);
```

**Deliverable:** Enhanced `stripe_subscriptions` table

---

### Step 6.3: Create `stripe_invoices` Table
**Atomic Task:** Add invoice tracking for support/reporting

Create file: `db_schemas/02_billing/07_create_stripe_invoices.sql`

```sql
CREATE TABLE stripe_invoices (
  stripe_invoice_ref VARCHAR(64) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  stripe_subscription_ref VARCHAR(64) NULL,
  status VARCHAR(32) NULL,
  amount_due BIGINT NULL,
  amount_paid BIGINT NULL,
  currency CHAR(3) NULL,
  stripe_hosted_invoice_url TEXT NULL,
  invoice_pdf_url TEXT NULL,
  created_utc DATETIME(6) NULL,
  paid_utc DATETIME(6) NULL,
  livemode TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (stripe_invoice_ref),
  KEY idx_user_invoice (user_id, created_utc),
  CONSTRAINT fk_stripe_invoices_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Deliverable:** `stripe_invoices` table created

---

### Step 6.4: Enhance Webhook Handler
**Atomic Task:** Add complete webhook event handling

Update `wwwroot/webhooks/stripe.php` to handle all events:

- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.payment_succeeded`
- `invoice.payment_failed`

**Deliverable:** Complete webhook handler with all event types

---

### Step 6.5: Add Subscription Management Page
**Atomic Task:** Create page for users to manage their subscription

Create file: `wwwroot/profile/subscription.php`

Features:
- View current plan
- View billing history
- Cancel subscription
- Reactivate subscription
- Download invoices

**Deliverable:** Subscription management page

---

## Phase 7: Testing & Validation

### Step 7.1: Test Stripe Integration (Test Mode)
**Atomic Task:** Verify Stripe checkout flow

- [ ] Register new user
- [ ] Navigate to upgrade page
- [ ] Select Monthly plan
- [ ] Complete checkout with test card: `4242 4242 4242 4242`
- [ ] Verify subscription created in Stripe Dashboard
- [ ] Verify subscription stored in local database
- [ ] Verify webhook received and processed

**Deliverable:** Successful test checkout

---

### Step 7.2: Test Authorization Gating
**Atomic Task:** Verify Pro feature gating works

- [ ] Create test page requiring Pro access
- [ ] Access as Free user → should redirect to upgrade
- [ ] Access as Pro user → should allow access
- [ ] Cancel subscription → verify access revoked

**Deliverable:** Authorization system working correctly

---

### Step 7.3: Test Webhook Idempotency
**Atomic Task:** Verify duplicate webhooks handled correctly

- [ ] Manually trigger same webhook event twice
- [ ] Verify only one database record created
- [ ] Verify no errors in webhook logs

**Deliverable:** Idempotent webhook handling

---

## Phase 8: Documentation & Deployment

### Step 8.1: Update ConfigSample.php
**Atomic Task:** Add Stripe configuration properties to ConfigSample.php

Update `classes/ConfigSample.php` to include:

```php
// Stripe Configuration (Test Mode)
public $stripe_secret_key = '';  // sk_test_... from Stripe Dashboard
public $stripe_publishable_key = '';  // pk_test_... from Stripe Dashboard
public $stripe_webhook_secret = '';  // whsec_... from Stripe Webhooks
public $stripe_livemode = false;  // Set to true for production
```

**Note:** Developers should copy `ConfigSample.php` to `Config.php` and fill in their Stripe keys (Config.php is gitignored).

**Deliverable:** ConfigSample.php updated with Stripe properties

---

### Step 8.2: Migration Files Follow Naming Convention
**Atomic Task:** Ensure migration files follow the numeric + action prefix naming pattern

The system automatically runs migrations via `DBExistaroo`:
- Migrations in `db_schemas/02_billing/` will be auto-detected
- Files use numeric prefixes for ordering: `01_`, `02_`, etc.
- Action prefixes indicate operation type: `create_`, `append_`, `drop_`, etc.
- The system tracks applied migrations in `applied_DB_versions` table
- Migrations run automatically on first page load after deployment

**File naming convention:**
- ✅ `01_create_subscription_plans.sql`
- ✅ `02_create_stripe_customers.sql`
- ✅ `03_create_stripe_subscriptions.sql`
- ✅ `04_create_stripe_webhook_events.sql`
- ✅ `05_append_subscription_plans_stripe_refs.sql` (ALTER TABLE)
- ✅ `06_append_stripe_subscriptions_fields.sql` (ALTER TABLE)
- ✅ `07_create_stripe_invoices.sql`

---

### Step 8.3: Update README
**Atomic Task:** Document billing feature in README

Add section to README.md:
- How to set up Stripe
- How to configure environment variables
- How to run migrations
- How to test billing locally

**Deliverable:** Updated README

---

## Summary Checklist

- [ ] Phase 1: Stripe setup complete (test mode)
- [ ] Phase 2: MVP database tables created
- [ ] Phase 3: MVP Stripe integration code written
- [ ] Phase 4: User-facing pages created (register, upgrade)
- [ ] Phase 5: Authorization system implemented
- [ ] Phase 6: Enhanced tables and features added
- [ ] Phase 7: Testing completed
- [ ] Phase 8: Documentation and deployment ready

---

## Additional Considerations

### Security
- Never commit Stripe API keys to version control
- Use environment variables or secure config files
- Validate webhook signatures
- Sanitize all user inputs

### Production Deployment
- Switch to live Stripe keys
- Update webhook endpoint URL
- Test with real payment methods
- Set up monitoring for failed payments

### Future Enhancements
- Add trial period support
- Implement proration for plan changes
- Add usage-based billing
- Create admin dashboard for subscription management
- Add email notifications for billing events
