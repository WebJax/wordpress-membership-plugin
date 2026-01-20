# Kritiske Funktionaliteter - Implementeret

## Oversigt

Dette dokument beskriver de kritiske funktionaliteter der er blevet implementeret i WordPress Membership Plugin.

## Migration Features

### Statusændringsdatoer fra WooCommerce Subscriptions ✅

**Ny funktionalitet:** Ved migration fra WooCommerce Subscriptions parser systemet nu automatisk order notes for at hente historiske statusændringsdatoer.

#### Hvad der hentes:
- **`paused_date`** - Dato for hvornår subscription blev sat on-hold
- **`status_changed_date`** - Dato for seneste statusændring

#### Hvordan det fungerer:
1. Under migration læser systemet alle order notes fra hver subscription
2. Parser note-indhold for statusændringer (sprog-uafhængig matching)
3. Finder dato for "to on-hold" statusændring → sætter `paused_date`
4. Finder seneste statusændring generelt → sætter `status_changed_date`
5. Gemmer datoerne sammen med de migrerede medlemskabsdata

#### Log output:
```
Found paused_date from order notes for subscription #123: 2025-11-15 14:23:45
Found status_changed_date from order notes for subscription #123: 2025-12-20 09:12:03
Migrated subscription for user ID: 42 with renewal type: automatic (with status dates from order notes)
```

#### Fordele:
- ✅ Bevarer historisk information fra WooCommerce Subscriptions
- ✅ Robuste regex-baserede matches der håndterer variationer i note-tekst
- ✅ Fejlhåndtering - fortsætter selvom parsing fejler
- ✅ Detaljeret logging af fundne datoer

---

## 1. Automatisk Ordre-oprettelse ✅

### Funktionalitet
Systemet opretter automatisk WooCommerce ordrer på forfaldsdagen for medlemskaber med `renewal_type = 'automatic'`.

### Implementering
- **Fil:** `includes/class-membership-renewals.php`
- **Funktion:** `create_renewal_order()`
- **Tidspunkt:** Køres dagligt via cron job når et automatisk medlemskab når forfaldsdagen (day 0)

### Hvordan det virker
1. Cron job'et `membership_renewal_cron` køres dagligt
2. `process_membership_renewals()` identificerer medlemskaber der udløber i dag og har `renewal_type = 'automatic'`
3. `create_renewal_order()` opretter en ny WooCommerce ordre med det konfigurerede produkt
4. Systemet forsøger automatisk betaling hvis kunden har en gemt betalingsmetode
5. Hvis betaling fejler, sættes medlemskabet til `pending-cancel` og der sendes notifikationer

### Konfiguration
Automatiske fornyelsesprodukter skal konfigureres i **Indstillinger** → **Membership Settings** → **Automatic Renewal Products**.

## 2. Token-baserede Fornyelseslinks ✅

### Funktionalitet
Hver medlemskab får et unikt, sikkert token der genererer et fornyelseslink som automatisk tilføjer produktet til kurven og redirecter til checkout.

### Implementering
- **Database:** Ny kolonne `renewal_token` i `wp_membership_subscriptions` tabellen
- **Funktioner:**
  - `generate_renewal_token()` - Genererer et 64-tegn hex token
  - `get_renewal_link()` - Returnerer det unikke fornyelseslink
  - `handle_renewal_token()` - Håndterer når linket tilgås
  - `register_renewal_endpoint()` - Registrerer URL endpoint

### URL Format
```
https://yoursite.com/membership-renewal/{token}/
```

### Hvordan det virker
1. Ved oprettelse af medlemskab genereres et unikt token
2. Token gemmes i databasen sammen med medlemskabet
3. I emails og på "Min Konto" siden bruges `get_renewal_link()` til at generere linket
4. Når brugeren klikker på linket:
   - Systemet validerer token'et
   - Finder det tilhørende medlemskab
   - Tømmer kurven
   - Tilføjer fornyelsesproduktet
   - Redirecter til checkout

### Migration
For eksisterende medlemskaber uden tokens, brug **Migration** → **Generate Missing Tokens** knappen.

## 3. Roles & Capabilities Integration ✅

### Funktionalitet
Automatisk tildeling og fjernelse af WordPress roller baseret på medlemskabsstatus.

### Implementering
- **Fil:** `includes/class-membership-roles.php`
- **Hooks:**
  - `membership_manager_subscription_activated` - Kaldes når medlemskab aktiveres
  - `membership_manager_subscription_expired` - Kaldes når medlemskab udløber
  - `membership_manager_status_changed` - Kaldes ved statusændringer

### Hvordan det virker
1. Når et medlemskab aktiveres, tildeles den konfigurerede rolle til brugeren
2. Når et medlemskab udløber, fjernes rollen (hvis konfigureret)
3. Brugeren får WordPress' default rolle hvis de ikke har andre roller

### Konfiguration
I **Indstillinger** → **Membership Settings** → **User Roles & Capabilities**:
- **Member Role:** Vælg hvilken WordPress rolle medlemmer skal have
- **Remove Role on Expiration:** Checkbox om rollen skal fjernes ved udløb

### Hooks for udviklere
```php
// Custom action når medlemskab aktiveres
add_action( 'membership_manager_after_activation', function( $user_id, $subscription_id ) {
    // Din custom kode
}, 10, 2 );

// Custom action når medlemskab udløber
add_action( 'membership_manager_after_expiration', function( $user_id, $subscription_id ) {
    // Din custom kode
}, 10, 2 );
```

## 4. Fejlhåndtering for Automatiske Fornyelser ✅

### Funktionalitet
Omfattende error handling når automatiske betalinger fejler.

### Implementering
- **Fil:** `includes/class-membership-renewals.php`
- **Funktioner:**
  - `process_automatic_payment()` - Forsøger automatisk betaling
  - `handle_failed_automatic_renewal()` - Håndterer fejlede fornyelser
  - `send_payment_required_email()` - Email til kunde
  - `send_failed_renewal_email()` - Email ved fejl
  - `notify_admin_failed_renewal()` - Admin notifikation

### Hvordan det virker
1. Når en ordre oprettes, tjekker systemet for gemte betalingsmetoder
2. Hvis betalingsmetode findes, forsøges automatisk betaling
3. Hvis betaling fejler eller ingen betalingsmetode findes:
   - Medlemskabet sættes til status `pending-cancel`
   - Kunde får email med link til at betale ordren
   - Admin får notifikation med detaljer
   - Fejlen logges i membership.log

### Email Notifikationer
- **Til kunde:** Link til at betale den oprettede ordre
- **Til admin:** Detaljer om bruger, ordre, og fejlårsag

### Retry Logik
Næste dag vil cron job'et forsøge igen hvis medlemskabet stadig er i `pending-cancel` status.

## Database Ændringer

### Opdateret Schema
```sql
CREATE TABLE wp_membership_subscriptions (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    start_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
    end_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
    status varchar(20) DEFAULT '' NOT NULL,
    renewal_type varchar(20) DEFAULT 'manual' NOT NULL,
    renewal_token varchar(64) DEFAULT '' NOT NULL,
    PRIMARY KEY  (id),
    KEY user_id (user_id),
    KEY renewal_token (renewal_token)
);
```

## Migration Steps

Når pluginet opdateres til denne version:

1. **Deaktiver pluginet**
2. **Aktiver pluginet igen** - Dette kører `activate()` funktionen som opdaterer database-skemaet
3. Gå til **Memberships** → **Migration**
4. Klik på **Generate Missing Tokens** for at generere tokens til eksisterende medlemskaber
5. Gå til **Indstillinger** → **Membership Settings**
6. Konfigurer:
   - Automatic Renewal Products
   - Manual Renewal Products
   - Member Role
   - Remove Role on Expiration
7. Gem indstillinger

## Testing

### Test Automatisk Fornyelse
```bash
# Simuler cron job manuelt
wp cron event run membership_renewal_cron
```

### Test Token Link
1. Find et medlemskab i admin
2. Hent renewal_token fra databasen
3. Besøg: `https://yoursite.com/membership-renewal/{token}/`
4. Verificer at produktet tilføjes til kurv og redirect til checkout sker

### Test Roles
1. Opret et nyt medlemskab for en testbruger
2. Verificer at rollen tildeles
3. Sæt medlemskabet til expired manuelt
4. Kør expiration processen
5. Verificer at rollen fjernes (hvis konfigureret)

## Logging

Alle handlinger logges i: `wp-content/plugins/wordpress-membership-plugin/logs/membership.log`

Log niveauer:
- **INFO:** Normal operation
- **WARNING:** Advarsler der ikke forhindrer funktion
- **ERROR:** Kritiske fejl

## Sikkerhed

- Renewal tokens er 64-tegn hex strenge (256 bits entropi)
- Alle admin actions bruger WordPress nonce verification
- Database queries bruger prepared statements
- User input saniteres med WordPress funktioner

## Hooks Reference

### Actions
```php
// Når medlemskab aktiveres
do_action( 'membership_manager_subscription_activated', $user_id, $subscription_id );

// Når medlemskab udløber
do_action( 'membership_manager_subscription_expired', $user_id, $subscription_id );

// Når medlemskabsstatus ændres
do_action( 'membership_manager_status_changed', $subscription_id, $old_status, $new_status );

// Efter activation (efter rolle er tildelt)
do_action( 'membership_manager_after_activation', $user_id, $subscription_id );

// Efter expiration (efter rolle er fjernet)
do_action( 'membership_manager_after_expiration', $user_id, $subscription_id );

// Når automatisk fornyelse fejler
do_action( 'membership_manager_failed_renewal', $subscription, $order, $reason );

// Når automatisk betaling skal proceseres
do_action( 'membership_manager_process_renewal_payment', $order, $subscription );
```

## Support & Fejlfinding

Hvis du støder på problemer:

1. Tjek `logs/membership.log` for fejlmeddelelser
2. Verificer at WooCommerce er aktivt og konfigureret
3. Verificer at cron jobs kører: `wp cron event list`
4. Tjek at produkter er konfigureret i indstillinger
5. Verificer database-skema med: `DESCRIBE wp_membership_subscriptions;`
