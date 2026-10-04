# NuttyLand – Integrated Retail and Market Management System

Phase 1 prototype for the PRT631 NuttyLand project. It's built with **Laravel 12**, **MySQL** and **Filament 5** (the admin panel).

| Area | What it does | Report reference |
|---|---|---|
| **Customer website** (`/`) | Mobile-first catalogue with search and filters (category, roasting style, organic), product pages with pack sizes, prices, stock, ingredients and allergens, a cart, and customer accounts with order history | FR-01, FR-02, FR-08 |
| **Click & Collect** | The customer picks a market and trading day at checkout, pays through a simulated gateway (approve or decline), and gets an email confirmation. The order then moves through Confirmed → Preparing → Ready → Collected | FR-03, Fig. 4 |
| **Staff screens** (`/staff`) | Sell screen for the stall (product-level sales, cash/EFTPOS) that **works offline** and syncs later. Also Click & Collect pick lists, stock view, transfers, stocktakes, damaged stock, a sales log and alerts | FR-04, FR-05, TC-05 |
| **Inventory** | Stock per product size per location (warehouse and each market). Every change is written to a stock ledger. Owners and staff get an alert when stock falls to its threshold | FR-05, FR-06 |
| **Admin / owner** (`/admin`) | Dashboard (KPIs, daily sales, market comparison, top products). Management of products and sizes, categories, markets and schedule, orders, stock, customers, users and roles, and suppliers (future phase). Includes allergen approval and an audit log | §7.4, §13.1 |
| **Reports** (`/admin/reports`) | Filter by date, market and category. Shows the daily summary, product sales, market comparison, sales by category, order status and low stock, with CSV export | FR-07 |
| **Security** | Role-based access (customer / staff / marketing / owner), hashed passwords, CSRF protection, login rate limiting, audit log of admin changes, deactivated users can't log in | §13 |

> **Sample data:** prices, market days and times, and the four weeks of sales history are illustrative. NuttyLand hasn't supplied its product master list or schedules yet (report §14.1). Payments are simulated.

---

## 1. Install on Windows

You need **PHP 8.2+**, **Composer** and **MySQL 8**. The easiest way to get all three is **[Laragon](https://laragon.org/)** (Full edition). **XAMPP** plus **[Composer](https://getcomposer.org/download/)** also works.

Make sure these PHP extensions are enabled in `php.ini`: `pdo_mysql`, `intl`, `mbstring`, `fileinfo`, `zip`, `gd`. In Laragon, go to Menu → PHP → Extensions. In XAMPP, remove the `;` in front of `extension=intl`, `extension=zip`, `extension=gd`.

```bash
# 1. Open a terminal in this folder
cd "C:\Users\sajid\OneDrive\Desktop\NuttyLand\nuttyland"

# 2. Install PHP packages (takes a few minutes the first time)
composer install

# 3. Create your settings file
copy .env.example .env
php artisan key:generate
```

**4. Create the database.** In HeidiSQL (Laragon) or phpMyAdmin (XAMPP), create an empty database called `nuttyland` with collation `utf8mb4_unicode_ci`. If your MySQL user isn't `root` with an empty password, edit `DB_USERNAME` / `DB_PASSWORD` in `.env`.

```bash
# 5. Create the tables and load the sample data
php artisan migrate --seed
php artisan storage:link

# 6. Start the site
php artisan serve
```

Open **http://127.0.0.1:8000**.

> **Tip:** OneDrive sync can slow down `composer install` and lock files. If you see odd errors, pause OneDrive syncing while installing, or move the project to a folder outside OneDrive (e.g. `C:\dev\nuttyland`).

You only need Node.js if you change the website's styling (`resources/css/app.css` or the Blade views). The compiled CSS is already in `public/build`. After changing styles, run `npm install` and then `npm run build`.

### Demo accounts (password: `password`)

| Email | Role | Goes to |
|---|---|---|
| `owner@nuttyland.test` | Owner / manager | `/admin` (full access) + `/staff` |
| `staff@nuttyland.test` | Market staff | `/staff` |
| `marketing@nuttyland.test` | Marketing | `/admin` (dashboard, reports, products, orders, customers – read-only) |
| `customer@nuttyland.test` | Customer | Website, has one sample order |

To start again with fresh sample data, run `php artisan migrate:fresh --seed`.

Emails (order confirmation, ready-to-collect, low stock) are written to `storage/logs/laravel.log`, because `MAIL_MAILER=log`. To send real emails, set the `MAIL_*` values in `.env`.

---

## 2. Try the main journeys (matches report test cases)

| Test case | How to try it |
|---|---|
| **TC-01 Click & Collect order** | Log in as the customer → add products → Checkout → choose a market day → *Pay* (or *Simulate declined card*) → see the order status page |
| **TC-02 Product-level market sale** | Log in as staff → **Sell** → choose market → tap products/sizes → EFTPOS/Cash → *Complete sale* → check **Sales log** and **Stock** |
| **TC-03 Low-stock alert** | Keep selling one size at a market until it reaches its "alert at" level. The alert then appears on the staff **Today** screen and on the bell icon in the admin |
| **TC-04 Role access** | Try `/admin` as staff or customer (403), and `/staff` as customer (403) |
| **TC-05 Connectivity fallback** | On the Sell screen, turn off Wi-Fi (or Chrome DevTools → Network → *Offline*) → complete sales. The header shows *Offline* and the sales queue on the device. Turn the network back on and they sync automatically. Each sale has a unique ID, so it is never saved twice |

### Automated tests

```bash
php artisan test
```

There are 30 tests with 163 assertions, in `tests/Feature`. They cover TC-01 to TC-05 plus stock transfers and stocktakes, payment idempotency, cancelling and refunding, role access, catalogue search, cart maths, report accuracy, the audit log and allergen re-approval. By default they run on an in-memory SQLite database. If PHP's `pdo_sqlite` extension is missing, enable it, or point `phpunit.xml` at a separate MySQL database.

---

## 3. How the code is organised

```
app/
  Models/                 Eloquent models (Product, ProductVariant, Location, MarketDay, Inventory,
                          StockMovement, Customer, Order, OrderItem, Sale, SaleItem, AuditLog, …)
  Services/
    InventoryService.php  the ONLY place stock changes – ledger + low-stock alerts
    OrderService.php      Click & Collect workflow (place → pay → prepare → ready → collected / cancel)
    SaleService.php       stall sales, idempotent by client UUID (offline sync)
    ReportService.php     all management reports (dashboard, reports page and CSV use the same code)
    Cart.php              session cart
  Http/Controllers/Shop/  customer website
  Http/Controllers/Staff/ staff screens + JSON API for the offline Sell screen
  Filament/               admin panel: Resources (CRUD), Pages/Reports, Widgets (dashboard)
  Notifications/          LowStockAlert, OrderConfirmed, OrderReadyForCollection
database/migrations/      schema (see section 4)
database/seeders/         sample data
resources/views/          Blade templates (shop, staff, auth)
public/js/staff-pos.js    offline-capable Sell screen
public/sw.js              service worker – lets the Sell screen open with no internet
tests/Feature/            automated tests
```

## 4. Data model changes from the draft ERD (Figure 8)

The core entities follow the ERD. A few additions were needed to meet the requirements. It would be worth updating the report's ERD to match:

| Change | Why |
|---|---|
| **`product_variants`** table (size, price, SKU, low-stock threshold) | One product is sold in 100g / 200g / 500g / 1kg packs at different prices. The ERD had one price per product |
| **`locations`** replaces `Market` and includes the **warehouse** (`type` = warehouse / market / store) | Stock lives in the warehouse *and* at markets. This also makes a future permanent store just another location |
| **`market_days`** table | The market schedule. Customers choose a specific trading day for Click & Collect |
| **`stock_movements`** ledger | Every sale, online order, transfer, return, damaged item and stocktake is recorded with who, when and why. `inventories.quantity` is the running total. This supports reconciliation (report §6) and the audit requirement |
| **`sale_items`** (Sale → many items) | One stall transaction usually contains several products, but reports stay product-level |
| **`sales.client_uuid`** | Prevents duplicates when an offline sale is synced twice (TC-05) |
| **`audit_logs`** | Administrative change history (§13.1) |
| **`users.role`** links to **`customers.user_id`** | Customers log in. Staff and owners are users without a customer profile |
| `products.allergen_approved_at/by` | Controlled approval of allergen information (§6 risk, §13.1) |

Online orders take stock from the **warehouse** when payment is confirmed, because Click & Collect orders are packed there and brought to the market. Stall sales take stock from that **market**. Stall sales are always recorded, even if the system count goes negative, because the sale really happened. A negative number flags a count that needs reconciling.

## 5. Not in Phase 1 (per report scope)

These are not built yet: live payments (Stripe can replace the simulated gateway inside `CheckoutController::processPayment`), Australia-wide shipping (the "Home delivery" option is shown as coming soon), the loyalty programme (the `loyalty_points` column is ready), gift baskets, supplier purchase orders (basic supplier records exist), demand forecasting, and native mobile apps (the staff screen can be installed as a web app instead).

## 6. Deploying later (pilot)

For a pilot, use any host that runs PHP 8.2+ and MySQL 8, ideally in the Sydney region for privacy. Examples are Laravel Cloud, Laravel Forge with DigitalOcean or AWS, or cPanel hosting. Before going live:

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Use a strong database password.
- Set up real mail settings.
- Set up a daily `mysqldump` backup and test restoring it.
- Use HTTPS. Browsers require it for the offline Sell screen anywhere except localhost.
