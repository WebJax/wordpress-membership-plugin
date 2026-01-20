# Test Guide - Kritiske Funktionaliteter

## Setup

1. **Aktiver pluginet igen** (hvis allerede aktiveret, deaktiver og genaktiver for at køre database migration)
2. Gå til **Indstillinger** → **Membership Settings**
3. Konfigurer følgende:
   - Automatic Renewal Products: Vælg et WooCommerce produkt
   - Manual Renewal Products: Vælg et WooCommerce produkt
   - Member Role: Vælg f.eks. "Subscriber" eller opret en custom rolle
   - Remove Role on Expiration: Markér hvis rollen skal fjernes ved udløb

## Test 1: Token-baserede Fornyelseslinks

### Trin:
1. Gå til **Memberships** → **Membership Manager**
2. Klik på et eksisterende medlemskab (eller opret et nyt)
3. Kopier URL'en fra browserens adressefelt og tilføj `&show_token=1`
4. Du kan også finde token'et direkte i databasen:
   ```sql
   SELECT renewal_token FROM wp_membership_subscriptions WHERE id = [MEMBERSHIP_ID];
   ```
5. Åbn URL'en: `https://yoursite.com/membership-renewal/{token}/`
6. **Forventet resultat:**
   - Du bliver redirected til checkout
   - Det konfigurerede manual renewal produkt er i kurven

### Test fra "Min Konto":
1. Log ind som en bruger med et medlemskab
2. Gå til **Min Konto** → **Membership**
3. Klik på "Renew Membership" knappen
4. Verificer at du kommer til checkout med produktet i kurven

## Test 2: Automatisk Ordre-oprettelse

### Setup testscenarie:
1. Opret et medlemskab med:
   - `renewal_type = 'automatic'`
   - `end_date` = i dag (eller i morgen for at teste i morgen)
   - `status = 'active'`

### Manuel trigger (uden at vente på cron):
```bash
# Fra kommandolinjen
wp cron event run membership_renewal_cron

# Eller via WP-CLI i terminalen
cd /Users/jacobthygesen/Sites/dianalund
wp cron event run membership_renewal_cron --path=/Users/jacobthygesen/Sites/dianalund
```

### Verificering:
1. Tjek **WooCommerce** → **Orders**
2. Der skulle være en ny ordre med:
   - Status: "Pending payment" eller "Processing"
   - Order note: "Automatic renewal order for membership subscription ID: X"
   - Meta: `_membership_subscription_id` og `_is_membership_renewal`
3. Tjek logfilen: `wp-content/plugins/wordpress-membership-plugin/logs/membership.log`

## Test 3: Roles & Capabilities

### Test activation:
1. Opret en testbruger (eller brug eksisterende uden medlemskab)
2. Verificer brugerens rolle før (sandsynligvis "Subscriber")
3. Opret et medlemskab for brugeren med `status = 'active'`
4. Refresh brugerens profil i admin
5. **Forventet resultat:** Brugeren har nu den konfigurerede medlem-rolle

### Test expiration:
1. Find et aktivt medlemskab
2. Sæt `end_date` til en dato i fortiden
3. Kør cron manuelt: `wp cron event run membership_renewal_cron`
4. Tjek brugerprofilen
5. **Forventet resultat:** Hvis "Remove Role on Expiration" er aktiveret, skulle medlem-rollen være fjernet

### Verificer user meta:
```bash
wp user meta get [USER_ID] has_active_membership
```

## Test 4: Fejlhåndtering for Automatiske Betalinger

### Simuler fejlet betaling:
1. Opret et automatisk medlemskab der udløber i dag
2. Sørg for at brugeren IKKE har en gemt betalingsmetode
3. Kør cron: `wp cron event run membership_renewal_cron`
4. **Forventet resultat:**
   - En ordre er oprettet med status "Pending"
   - Medlemskabets status er ændret til "pending-cancel"
   - Brugeren har modtaget en email med link til at betale
   - Admin har modtaget en notifikation

### Verificer emails:
Tjek din email (eller brug en email-catching plugin som WP Mail Logging)
- **Bruger email:** "Action Required: Membership Renewal Failed"
- **Admin email:** "Failed Membership Renewal - Admin Notification"

### Verificer log:
```bash
tail -f wp-content/plugins/wordpress-membership-plugin/logs/membership.log
```

Du skulle se entries som:
```
[2026-01-01 10:00:00] [INFO] - Processing automatic renewal for subscription ID: X on expiration date
[2026-01-01 10:00:01] [INFO] - Created renewal order #Y for subscription ID: X
[2026-01-01 10:00:02] [WARNING] - No saved payment methods for user Z. Manual payment required for order #Y
[2026-01-01 10:00:03] [ERROR] - Failed automatic renewal for subscription ID: X. Reason: no_payment_method. Status set to pending-cancel.
```

## Test 5: Email Reminders med Token Links

### Test 30-dages påmindelse:
1. Opret et medlemskab med `end_date` = 30 dage fra i dag
2. Kør cron
3. Tjek email
4. **Forventet resultat:** Email med unikt renewal link

### Verificer token i email:
Email'en skulle indeholde et link som:
```
https://yoursite.com/membership-renewal/[64-tegns-hex-string]/
```

## Test 6: Generate Missing Tokens (Migration)

### For eksisterende medlemskaber uden tokens:
1. Gå til **Memberships** → **Migration**
2. Klik på **Generate Missing Tokens**
3. **Forventet resultat:** Success-besked med antal genererede tokens
4. Verificer i databasen:
   ```sql
   SELECT id, user_id, renewal_token 
   FROM wp_membership_subscriptions 
   WHERE renewal_token IS NOT NULL AND renewal_token != '';
   ```

## Debugging Tips

### Tjek cron schedule:
```bash
wp cron event list
```

### Manuelt køre cron:
```bash
wp cron event run membership_renewal_cron
```

### Se alle medlemskaber der udløber snart:
```sql
SELECT * FROM wp_membership_subscriptions 
WHERE status = 'active' 
AND DATEDIFF(end_date, NOW()) <= 30 
ORDER BY end_date ASC;
```

### Se brugerroller:
```bash
wp user get [USER_ID] --field=roles
```

### Clear log fil (hvis den bliver for stor):
```bash
> wp-content/plugins/wordpress-membership-plugin/logs/membership.log
```

## Forventede Resultater - Checkliste

- [ ] Token-links redirecter korrekt til checkout med produkt
- [ ] Automatiske ordrer oprettes på forfaldsdagen
- [ ] Roller tildeles ved activation
- [ ] Roller fjernes ved expiration (hvis konfigureret)
- [ ] Fejlede betalinger sender email til bruger og admin
- [ ] Status ændres til "pending-cancel" ved fejl
- [ ] Påmindelser sendes 30, 14, 7, og 1 dag før udløb
- [ ] Log-filen indeholder detaljerede entries
- [ ] Migration af tokens fungerer

## Support

Hvis noget ikke virker som forventet:

1. Tjek `logs/membership.log` for fejl
2. Verificer WooCommerce er aktivt
3. Verificer produkter er konfigureret i indstillinger
4. Test med WP_DEBUG aktiveret i wp-config.php:
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   define( 'WP_DEBUG_DISPLAY', false );
   ```
5. Tjek WordPress debug.log filen
