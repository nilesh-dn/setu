# Anvesha – AI Lead Intelligence for local businesses

Anvesha finds businesses on **Google Maps**, audits their **website** and **Google Business Profile**, writes a persuasive **AI audit report** as a branded PDF, and sends it to the owner by **Email and WhatsApp**. It tracks every lead's journey: found → audited → report → contacted → opened → replied → won.

Built for **shared hosting** (cPanel / Hostinger / any PHP host): plain PHP 8 + MySQL, no framework, no Node, no Composer needed on the server. The PDF (dompdf) and email (PHPMailer) libraries are bundled in `app/vendor`.

## Features
- **Search** – keyword + location, top 10/20/30/40/50/100 or any number. Uses Google Places API (New, up to 60 results) or SerpAPI (100+). Saves name, category, address, phone, website, rating, reviews and Maps link.
- **Contact enrichment** – if Maps has no phone number, it is taken from the website (tel:, WhatsApp links, text). Emails are always looked for on the website and contact page.
- **Initiate Process** – for each selected lead: a website audit (~25 SEO, speed, mobile, schema, tracking and conversion checks, with optional PageSpeed) plus a Google Maps audit (rating, reviews vs competitors, rank, photos, hours, website, phone), then the AI report and the PDF.
- **AI reports** – the report focuses on weak points and pitches your services. It includes ROI maths: "just *n* customers a year pays for our fee", with the lead value estimated from the industry and city. Your logo, company info, colours and fonts (you can upload TTF fonts) appear on the cover and every page.
- **No website?** – the report pitches a website, and your **website proposal PDF** (uploaded in Settings) is attached with the Maps audit.
- **Prompts & Messages** – a default prompt or message for each type, with as many saved versions as you like and one marked *In use*. Types: report with website, report without website, WhatsApp, WhatsApp without website, Email, Email without website. The default messages contain **no fees**, and the first message carries the PDF.
- **Outreach** – SMTP email with the PDF attached and open tracking. WhatsApp runs in one of three modes: Manual (wa.me, free), the official WhatsApp Cloud API (template with a document header), or any other provider's HTTP API. There is a sending gap, a daily limit, and a "skip already contacted" option.
- **Reply tracking** – IMAP inbox scan (no php-imap extension needed). WhatsApp Cloud webhook for delivered, read and replies. Manual "mark replied". PDF view tracking.
- **AI Connection** – Anthropic Claude, OpenAI ChatGPT, OpenRouter, Google Gemini, Groq, DeepSeek or any OpenAI-compatible API. Each has step-by-step instructions and a test button.
- **Updates tab** – the "Go live" guide, checks GitHub automatically every 6 hours, and offers one-click **Update now**. It backs up changed files, never touches `config.php` / `storage/` / data, and then migrates the database.
- Configurable **currency** (default INR ₹), timezone, team users, and CSV export.

## Install (shared hosting)
1. Upload the files to `public_html` or a sub-domain folder (Download ZIP from GitHub → File Manager → Extract), or use cPanel → Git Version Control.
2. Create a MySQL database and user (cPanel → MySQL Database Wizard, ALL PRIVILEGES).
3. Open `https://your-domain/install.php`, enter the DB details and create the admin login.
4. Background jobs run automatically – no cron setup needed. Anvesha adds a server cron itself when the host allows it, otherwise its built-in scheduler keeps running and restarts on any visit. (Optional extra reliability: a cron job every minute with `php /path/to/cron.php`.)
5. Connect AI (AI Connection tab), the Google Places / SerpAPI key, SMTP/IMAP and WhatsApp (Settings).

Requirements: PHP 8.0+ with pdo_mysql, curl, mbstring, dom, gd, openssl, and zip (for updates). Also MySQL 5.7+ or MariaDB 10.3+, and HTTPS.

Each step is a separate short job, so strict `max_execution_time` limits are fine.

## Releasing an update
1. Commit your changes to the update branch (default `main`, configurable in Updates).
2. Bump `VERSION` and `version.json` (`{"version":"1.0.1","date":"…","notes":["…"]}`).
3. For database changes add `app/migrations/002_xxx.php` returning an array of SQL statements. It runs automatically after an update.
Installed sites show a **New** badge on Updates and can update with one click. For a private repository, add a read-only GitHub token in the Updates tab.

## Structure
```
index.php        app entry (routes ?r=page)      install.php  installer
cron.php         background queue + replies       report.php   public PDF link (tracked)
track.php        email open pixel                 webhook.php  WhatsApp Cloud API webhook
app/lib          DB, Settings, Maps, Scraper, Auditor, AI, Report, Mailer, WhatsApp, Queue, Outreach, Updater, Migrator, Seed
app/pages        dashboard, campaigns, campaign, leads, lead, reports, prompts, settings, ai, updates, api, login
app/views        layout + shared partials          app/migrations  database schema
assets/          css, js, images, uploaded logo    storage/  reports, uploads, fonts, logs, backups (private)
```

## Notes
- Google Places API returns a maximum of 60 results per query. Use SerpAPI for more.
- WhatsApp Cloud API needs an approved template for the first (business-initiated) message. Manual mode needs no approval.
- Send responsibly: keep the gaps and daily limits sensible, and honour opt-out requests. Follow anti-spam and data-protection rules (e.g. India's DPDP Act and IT rules, GDPR for EU contacts).
