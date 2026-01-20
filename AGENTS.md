# Projekt: WordPress Membership Plugin

---

## Mål

Udvikle et **WordPress plugin** der håndterer **årlige medlemskaber** for en velgørende forening. Pluginnet skal integreres med **WooCommerce** for checkout-flow og betaling.

## Arkitektur & Teknologier

* **Platform:** WordPress & WooCommerce.
* **Database:** En ny, dedikeret MySQL-tabel for medlemskaber.
* **Sprog:** PHP, JavaScript, HTML, CSS.
* **Struktur:** Følg den skitserede mappestruktur med separate klasser for kernefunktionalitet, administration og e-mails.

---

## ✅ IMPLEMENTERINGSSTATUS

### ✅ Fase 1: Grundlæggende Funktionalitet - **KOMPLET**
- ✅ Database-tabel oprettet med opdateret schema (inkl. renewal_token)
- ✅ WooCommerce integration via hook
- ✅ Admin interface med oversigt og filtrering
- ✅ Dagligt cron job implementeret

### ✅ Fase 2: Fornyelses- og E-mailsystem - **KOMPLET**
- ✅ Automatisk ordre-oprettelse på forfaldsdagen
- ✅ Token-baserede unikke renewal links
- ✅ E-mailsystem med merge tags
- ✅ Fejlhåndtering for automatiske betalinger

### ✅ Fase 3: Frontend og Brugeroplevelse - **KOMPLET**
- ✅ WooCommerce My Account integration
- ✅ Admin UI med filtrering
- ✅ Migration interface
- ✅ Shortcodes implementeret

### ✅ Ekstra Implementeret:
- ✅ **Roles & Capabilities Integration** - Automatisk tildeling/fjernelse af WordPress roller
- ✅ **Omfattende Fejlhåndtering** - Email notifikationer til bruger og admin ved fejl
- ✅ **Token-baserede Renewal Links** - Sikre, unikke links per medlemskab
- ✅ **Automatisk Betalingsprocessering** - Forsøg på automatisk betaling med gemte payment methods
- ✅ **Logging System** - Detaljeret logging af alle handlinger

---

## Arbejdsinstruktioner

### Fase 1: Grundlæggende Funktionalitet ✅ KOMPLET

1.  **Database:** Opret den specificerede `wp_membership_subscriptions` tabel i databasen. Brug den præcise SQL-kommando som angivet i planen.
2.  **Plugin-fil:** Opret `membership-manager.php` som hovedplugin-filen. Inkluder nødvendige header-oplysninger og "autoload" af de vigtigste klasser fra `includes/` mappen.
3.  **WooCommerce Integration:** Implementer den beskrevne `woocommerce_order_status_completed` hook. Skriv PHP-funktionen `create_membership_subscription` til at oprette en ny række i den nye databasetabel. Funktionen skal hente `user_id`, `product_id`, og sætte `next_renewal_date` til et år fra nu.
4.  **Admin Interface:** Implementer `admin/` mappen. Opret en simpel oversigtside (`memberships-list.php`) der viser data fra `wp_membership_subscriptions` tabellen. Brug WordPress' `add_menu_page` og `add_submenu_page` funktioner for at oprette et menupunkt for plugin'et.
5.  **Cron Job:** Implementer `membership_daily_check` og `process_membership_renewals` funktionerne. Registrer et dagligt cron-job der kører `process_membership_renewals`. Funktionen skal:
    * Identificere medlemskaber med `renewal_type = 'automatic'` der nærmer sig fornyelsesdatoen.
    * Identificere medlemskaber med `renewal_type = 'manual'` der nærmer sig udløbsdatoen.

### Fase 2: Fornyelses- og E-mailsystem (Fokus på automatisering)

1.  **Automatisk Fornyelse:** Udvid `process_membership_renewals` til at håndtere automatiske fornyelser. På forfaldsdagen skal der oprettes en ny WooCommerce-ordre. Brug den specificerede `create_renewal_order` funktion.
2.  **Manuel Fornyelse:** Implementer logikken for manuelle fornyelser. Lav en funktion der kan generere et unikt link, der leder brugeren til en side der tilføjer produktet til kurven og omdirigerer til checkout.
3.  **E-mailsystem:** Opret `templates/emails/` mappen. Byg et simpelt e-mailsystem i PHP, der kan sende de skitserede påmindelser (30, 14, 7, 1 dag før). Anvend **`wp_mail`** eller en lignende funktion. Brug **merge tags** (`[user_name]`, `[renewal_link]`) til at tilpasse indholdet.

### Fase 3: Frontend og Brugeroplevelse (Fokus på UI/UX)

1.  **WooCommerce My Account:** Implementer en ny fane i brugerens "Min Konto" sektion der viser deres medlemskabsstatus. Denne side skal vise **udløbsdato**, **status**, og en **"Forny Medlemskab" knap** for manuelle medlemskaber.
2.  **Admin UI:** Forbedr admin-grænsefladen. Inkluder filtrering efter `status` og `next_renewal_date`. Opret en individuel administrationsside (`membership-edit.php`) hvor man kan **pause, genoptage, ændre dato og slette** et medlemskab.
3.  **Migration Interface:** Opret en simpel side i admin-panelet der viser en knap til at starte migration af **eksisterende WooCommerce Subscriptions** ved hjælp af `migrate_woocommerce_subscription` funktionen.

---

## 🎯 KRITISKE FUNKTIONER - IMPLEMENTERET (Januar 2026)

### 1. Automatisk Ordre-oprettelse ✅
**Hvad:** Systemet opretter automatisk WooCommerce ordrer på forfaldsdagen for automatiske medlemskaber.

**Implementering:**
- Klasse: `Membership_Renewals::create_renewal_order()`
- Trigger: Dagligt cron job når `end_date` nås og `renewal_type = 'automatic'`
- Duplikatkontrol: Tjekker om ordre allerede er oprettet i dag

**Features:**
- Automatisk payment processing med gemte payment methods
- Fejlhåndtering hvis betaling fejler
- Order notes og meta data til sporing
- Integration med WooCommerce payment gateways

### 2. Token-baserede Renewal Links ✅
**Hvad:** Hver medlemskab har et unikt, sikkert token til fornyelseslinks.

**Implementering:**
- Database: Ny `renewal_token` kolonne (64 hex chars)
- URL format: `https://yoursite.com/membership-renewal/{token}/`
- Funktioner: `generate_renewal_token()`, `get_renewal_link()`, `handle_renewal_token()`

**Features:**
- Automatisk kurv-tømning
- Tilføjer korrekt renewal produkt
- Redirect til checkout
- Token regeneration mulig
- Migration tool til eksisterende medlemskaber

### 3. Roles & Capabilities Integration ✅
**Hvad:** Automatisk håndtering af WordPress roller baseret på medlemskabsstatus.

**Implementering:**
- Klasse: `Membership_Roles`
- Hooks: `membership_manager_subscription_activated`, `membership_manager_subscription_expired`

**Features:**
- Tildel rolle ved activation
- Fjern rolle ved expiration (valgfrit)
- Konfigurerbar gennem Settings
- Hooks for custom udvidelser
- User meta tracking (`has_active_membership`)

### 4. Omfattende Fejlhåndtering ✅
**Hvad:** Robust error handling for automatiske fornyelser.

**Implementering:**
- Funktioner: `handle_failed_automatic_renewal()`, `send_failed_renewal_email()`, `notify_admin_failed_renewal()`

**Features:**
- Status ændring til `pending-cancel` ved fejl
- Email til kunde med payment link
- Admin notifikation med detaljer
- Detaljeret logging
- Custom hooks: `membership_manager_failed_renewal`

**Fejlscenarier håndteret:**
- Ingen gemt payment method
- Betalingsgateway fejl
- WooCommerce ordre oprettelse fejl
- Produkter ikke fundet/konfigureret

### 5. Hooks & Udvidelighed 🔧

**Tilgængelige Action Hooks:**
```php
// Core membership events
do_action( 'membership_manager_subscription_activated', $user_id, $subscription_id );
do_action( 'membership_manager_subscription_expired', $user_id, $subscription_id );
do_action( 'membership_manager_status_changed', $subscription_id, $old_status, $new_status );

// Role events
do_action( 'membership_manager_after_activation', $user_id, $subscription_id );
do_action( 'membership_manager_after_expiration', $user_id, $subscription_id );

// Renewal events
do_action( 'membership_manager_failed_renewal', $subscription, $order, $reason );
do_action( 'membership_manager_process_renewal_payment', $order, $subscription );
```

---

## 📚 DOKUMENTATION

Se følgende filer for detaljer:
- **CRITICAL-FEATURES.md** - Detaljeret dokumentation af alle kritiske features
- **TEST-GUIDE.md** - Guide til test af alle funktioner
- **README.md** - Generel plugin dokumentation

---

## 🔄 NÆSTE SKRIDT (Fremtidige Forbedringer)

### Medium Prioritet:
- [ ] Pause/Genoptag medlemskab UI i admin
- [ ] Udvidet settings-side (email afsender, emner, test email)
- [ ] Bedre fejlhåndtering UI (vis fejl i admin dashboard)

### Lav Prioritet:
- [ ] Rapporter og statistik dashboard
- [ ] Bulk actions (masseændring af status)
- [ ] Export funktionalitet (CSV)
- [ ] Integration med flere payment gateways
- [ ] Automated testing suite

---

## Tekniske retningslinjer & Værktøjer

* **Shell:** Brug Shell-værktøjet til at køre `wp-cli` kommandoer, f.eks. til at opdatere en database-tabel efter en vellykket migrering eller til at simulere cron-jobs.
* **FileSystem:** Brug FileSystem til at skrive og redigere alle plugin-filer.
* **WebFetch:** Brug WebFetch til at slå op i WordPress Codex eller WooCommerce dokumentation for specifikke funktioner.
