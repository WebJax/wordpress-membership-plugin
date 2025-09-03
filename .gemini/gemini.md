# Projekt: WordPress Membership Plugin

---

## Mål

Udvikle et **WordPress plugin** der håndterer **årlige medlemskaber** for en velgørende forening. Pluginnet skal integreres med **WooCommerce** for checkout-flow og betaling.

## Arkitektur & Teknologier

* **Platform:** WordPress & WooCommerce.
* **Database:** En ny, dedikeret MySQL-tabel for medlemskaber.
* **Sprog:** PHP, JavaScript, HTML, CSS.
* **Struktur:** Følg den skitserede mappestruktur med separate klasser for kernefunktionalitet, administration og e-mails.

## Arbejdsinstruktioner

### Fase 1: Grundlæggende Funktionalitet (Fokus på backend)

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

## Tekniske retningslinjer & Værktøjer

* **Shell:** Brug Shell-værktøjet til at køre `wp-cli` kommandoer, f.eks. til at opdatere en database-tabel efter en vellykket migrering eller til at simulere cron-jobs.
* **FileSystem:** Brug FileSystem til at skrive og redigere alle plugin-filer.
* **WebFetch:** Brug WebFetch til at slå op i WordPress Codex eller WooCommerce dokumentation for specifikke funktioner.
