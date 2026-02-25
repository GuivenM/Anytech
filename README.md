# 📶 ANYTECH Hotspot — WiFi Compliance SaaS for Benin

<p align="center">
  <img src="https://img.shields.io/badge/version-2.0.0-blue?style=for-the-badge" />
  <img src="https://img.shields.io/badge/stack-PHP%20%7C%20MySQL%20%7C%20MikroTik-informational?style=for-the-badge" />
  <img src="https://img.shields.io/badge/payment-FedaPay-green?style=for-the-badge" />
  <img src="https://img.shields.io/badge/built%20with-AI%20%2B%20domain%20expertise-blueviolet?style=for-the-badge" />
</p>

> **A real-world SaaS born from a real-world problem** — built at the intersection of networking expertise, local regulatory knowledge, and AI-assisted development.

---

## 🌍 The Problem I Identified in the Field

In Benin (and across West Africa), operators of public WiFi hotspots — cafés, hotels, agencies, cybercafés — are **legally required** to collect and store user identification data** before granting internet access. This includes:

- Full name
- Phone number
- National ID number (CNI, Passport, or CIP)
- Connection timestamp, MAC address, and IP address

The regulation exists for security and traceability purposes, and non-compliance exposes business owners to legal risk. Yet **no accessible, affordable, and locally adapted tool existed** for these small operators to comply.

I saw this gap firsthand. I built ANYTECH Hotspot to fill it.

---

## 💡 What ANYTECH Hotspot Does

ANYTECH is a **multi-tenant SaaS platform** that allows WiFi hotspot owners in Benin to:

- Register on the platform and manage one or multiple hotspot sites
- Integrate a lightweight JavaScript snippet into their **MikroTik** hotspot login page
- Automatically **capture and store user identity data** at connection time
- View, filter, and export connection logs from a clean web dashboard
- Renew their monthly subscription using local payment via **FedaPay** (Mobile Money / Card)

Every connection is logged with: name, surname, ID type & number, phone, MAC address, IP address, user agent, and timestamp.

---

## 🏗️ Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                        CLIENT SIDE                          │
│                                                             │
│  MikroTik Router  ──►  Hotspot Login Page                   │
│                         + anytech-integration.js            │
│                         (auto-injected identity form)       │
└──────────────────────────────┬──────────────────────────────┘
                               │  POST /api-register.php
                               │  Header: X-Hotspot-Code
                               ▼
┌─────────────────────────────────────────────────────────────┐
│                       ANYTECH SERVER                        │
│                                                             │
│  ┌──────────────┐    ┌───────────────┐    ┌─────────────┐  │
│  │  Auth Layer  │    │  REST API     │    │  Admin      │  │
│  │  (Sessions)  │    │  api-register │    │  Panel      │  │
│  │              │    │  api-logs     │    │             │  │
│  └──────────────┘    └───────┬───────┘    └─────────────┘  │
│                              │                             │
│                    ┌─────────▼─────────┐                   │
│                    │   MySQL Database   │                   │
│                    │   proprietaires   │                   │
│                    │   routeurs        │                   │
│                    │   wifi_users      │                   │
│                    └───────────────────┘                   │
└─────────────────────────────────────────────────────────────┘
                               │
                               │  FedaPay Callback
                               ▼
                    ┌──────────────────┐
                    │  Payment Gateway │
                    │  FedaPay (Live)  │
                    │  Mobile Money /  │
                    │  Card            │
                    └──────────────────┘
```

---

## 🔌 MikroTik Integration — The Networking Layer

This is where my networking knowledge was essential.

### How it works

MikroTik routers power most hotspots in West Africa. Their login page (served via the Hotspot feature) accepts **custom HTML/JS injection**. ANYTECH exploits this by providing each operator a **pre-configured JavaScript file** that:

1. Injects identity fields (name, surname, ID type, ID number, phone) into the existing MikroTik login form
2. Intercepts form submission
3. POSTs the user data to the ANYTECH API **before** the router authenticates the session
4. Lets the login proceed normally — the user experience is uninterrupted

```
User fills MikroTik login page
         │
         ▼
anytech-integration.js intercepts submit
         │
         ├──► POST to /api-register.php (identity saved)
         │
         └──► Original MikroTik form.submit() proceeds
                    │
                    ▼
              User gets internet access ✅
              Identity logged in DB ✅
```

### MikroTik Session Logging (`.rsc` script)

A RouterOS script (`mikrotik-anytech-logs.rsc`) runs on a scheduler on the router itself. It:
- Iterates over all active hotspot sessions
- Collects MAC, IP, uptime, bytes in/out, login time
- Sends the batch as JSON to `/api-logs.php`
- Logs success or failure to the RouterOS system log

```routeros
# Scheduled script — runs every N minutes on the router
/tool fetch url=$apiUrl http-method=post \
  http-header-field=("Content-Type: application/json,X-Hotspot-Code: " . $hotspotCode) \
  http-data=$payload output=none mode=https check-certificate=no
```

This dual approach means ANYTECH captures **both** the user identity at login AND ongoing session telemetry from the router itself.

---

## 🧱 Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8+ (procedural + PDO) |
| Database | MySQL (utf8mb4) |
| Frontend | Vanilla HTML/CSS/JS (no framework) |
| Payment | FedaPay API (live — Mobile Money & card) |
| Router integration | MikroTik RouterOS scripting (`.rsc`) |
| Hotspot injection | Vanilla JavaScript (IIFE module) |
| Hosting | Shared hosting (PHP/MySQL compatible) |
| Auth | PHP sessions with CSRF protection |
| Cron | PHP cron script for expiration checks |

---

## ✨ Key Features

### For Hotspot Operators (SaaS Users)
- 🔐 Secure account registration & login
- 📡 Add and manage multiple hotspot sites
- 📊 Dashboard with monthly stats: active sessions, unique users, expiring sites
- ⏰ Expiration alerts with one-click renewal
- 💳 Credit-based billing via FedaPay (1 credit = 1 site × 1 month)
- 📥 Download a pre-configured JS integration script per site
- 📤 Export user logs as CSV

### For Compliance
- ✅ Captures: name, surname, ID type (CNI/Passport/CIP), ID number, phone, email (optional), voucher code, MAC address, IP address, user agent, timestamp
- ✅ All data stored per site, per operator, with timestamps
- ✅ Operator-level data isolation — each owner only sees their own users
- ✅ Supports Beninese ID types (CNI, CIP, Passeport)

### For Admins
- 👤 Admin panel with operator management
- 📋 Full view of all registered sites and users
- 📦 CSV export of all users
- 🔄 Account status management (actif / essai / suspendu)

---

## 🛠️ How I Built This with AI

This project is a concrete example of **AI as a force multiplier for domain experts**.

I brought:
- **Networking knowledge** — understanding of MikroTik, hotspot architecture, RouterOS scripting, and how to intercept login flows without breaking them
- **Field insight** — direct observation of the compliance gap in Benin's WiFi market
- **Product thinking** — defining the credit model, multi-tenant structure, and operator UX
- **Prompting discipline** — knowing how to break complex problems into well-scoped AI tasks, validate outputs, and iterate

AI (Claude by Anthropic) helped with:
- Scaffolding the PHP architecture and PDO patterns
- Writing the MikroTik `.rsc` script in RouterOS syntax
- Building the FedaPay integration flow
- Designing the JS integration module (IIFE pattern, form interception)
- Generating SQL queries for multi-tenant stats
- Writing this README

The result: a production-grade SaaS built in a fraction of the time it would have taken alone — without sacrificing code quality, security, or business logic correctness.

> The AI didn't know what Benin's hotspot compliance rules were. I did. The AI didn't know how MikroTik's login page injection works. I did. The combination is what made this possible.

---

## 📁 Project Structure

```
/
├── config.php                    # DB, app, FedaPay, security config (gitignored in prod)
├── auth-check.php                # Session validation middleware
├── auth-layout.php               # Auth pages layout (login/register)
├── layout.php                    # Main app layout (sidebar, header)
│
├── index.php                     # Landing / redirect
├── login.php                     # Login page
├── login-process.php             # Login handler
├── register.php                  # Registration form
├── register-process.php          # Registration handler
├── logout.php                    # Session destroy
│
├── dashboard.php                 # Main dashboard
├── sites.php                     # Site list
├── site-detail.php               # Single site: logs, stats
├── add-site.php                  # Add a new hotspot site
├── renew-site.php                # Renew site subscription
├── profile.php                   # Operator profile
├── profile-process.php           # Profile update handler
│
├── credits.php                   # Credits overview
├── buy-credits.php               # Purchase flow
├── process-payment.php           # FedaPay payment init
├── fedapay-callback.php          # FedaPay webhook handler
├── payment-success.php           # Post-payment confirmation
│
├── api-register.php              # REST API: user identity registration
├── api-logs.php                  # REST API: MikroTik session logs
├── download-integration-script.php  # Generates per-site JS integration file
├── download-mikrotik-logs.php    # Export logs as CSV
│
├── admin.php                     # Admin dashboard
├── admin-login.php               # Admin login
├── admin-auth.php                # Admin session check
├── admin-logout.php              # Admin session destroy
├── users.php                     # Admin: all operators
├── export-users.php              # Admin: CSV export
│
├── mikrotik-anytech-logs.rsc     # RouterOS script for session telemetry
├── cron-check-expirations.php    # Cron: deactivate expired sites
├── sessions.php                  # DB helper
├── 404.php                       # Error page
├── account-suspended.php         # Suspended account page
└── app.css                       # Global styles
```

---

### MikroTik Setup
1. Upload `mikrotik-anytech-logs.rsc` to the router
2. Replace `RTR-2025-XXX` with the site's unique code from the dashboard
3. Create a scheduler: `/system scheduler add interval=5m on-event="..."`
4. Download the integration JS from the dashboard and add it to your hotspot HTML login page

---

## 💰 Business Model

| Package | Credits | Price (FCFA) | Notes |
|---|---|---|---|
| 1 crédit | 1 | 2 000 | 1 site × 1 month |
| Bundle 6 | 6 | 10 000 | Bonus included |
| Custom | Variable | Negotiated | For resellers |

Payment processed via **FedaPay** — supports MTN Mobile Money, Moov Money, and card payments, which are the dominant payment methods in Benin.

---

## 🔒 Security Notes

- All user inputs sanitized with `htmlspecialchars` and `PDO` prepared statements
- CSRF token system implemented
- Session timeout: 24h
- Admin panel on separate auth flow
- Operator data isolation enforced at query level (`WHERE proprietaire_id = :id`)
- API authenticated via `X-Hotspot-Code` header (unique per site)

---

## 👨‍💻 Author

Built by **[Pambou Myveck Jean-Paul Guiven]** — Network engineer & developer based in Cotonou, Benin.

- Identified a regulatory compliance gap in the local WiFi market
- Designed and shipped a full SaaS to address it
- Combined MikroTik networking expertise with AI-assisted development
- Integrated local payment infrastructure (FedaPay)

> *"Tools are meant to be used."*

---

## 📄 License

This project is proprietary. All rights reserved. Source code is shared for portfolio and demonstration purposes only.
