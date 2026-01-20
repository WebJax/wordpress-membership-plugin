# Staging Mode / Test Mode

## Oversigt

Staging Mode forhindrer at dit test-site sender emails eller kører automatiske fornyelser. Dette er ideelt til udviklings-, test- og staging-miljøer.

## Hvad Staging Mode Gør

Når aktiveret, forhindrer Staging Mode:

1. **Automatiske fornyelser** - Cron job kører ikke
2. **Email-udsendelse** - Ingen emails sendes (velkommen, påmindelser, etc.)
3. **Fakturering** - Ingen WooCommerce ordrer oprettes automatisk

## Hvordan Aktiveres

### Metode 1: Via wp-config.php (Anbefalet)

Tilføj følgende linje til din `wp-config.php` fil (før `/* That's all, stop editing! */`):

```php
define( 'MEMBERSHIP_STAGING_MODE', true );
```

### Metode 2: Via theme functions.php

Alternativt kan du tilføje det i dit themes `functions.php`:

```php
if ( ! defined( 'MEMBERSHIP_STAGING_MODE' ) ) {
    define( 'MEMBERSHIP_STAGING_MODE', true );
}
```

## Hvordan Deaktiveres

For at slå Staging Mode fra igen:

1. Fjern eller kommenter linjen ud i wp-config.php:
```php
// define( 'MEMBERSHIP_STAGING_MODE', true );
```

2. Eller sæt den til `false`:
```php
define( 'MEMBERSHIP_STAGING_MODE', false );
```

## Admin Notice

Når Staging Mode er aktiv, vil du se en gul advarsel øverst på alle admin-sider:

```
⚠️ STAGING MODE ACTIVE - Automatic renewals and emails are disabled. 
To disable staging mode, remove MEMBERSHIP_STAGING_MODE from wp-config.php
```

## Staging Mode Log Entries

I log-filen vil du se entries som:

```
[INFO] [STAGING MODE] Email blocked - To: user@example.com, Subject: Welcome!
[INFO] [STAGING MODE] Renewal blocked for subscription ID: 123 (User: 45)
[INFO] [STAGING MODE] Renewal process skipped - staging mode is active
```

## Best Practices

### Anbefalet Setup for Forskellige Miljøer

**Produktion (Live Site):**
```php
// Ingen MEMBERSHIP_STAGING_MODE definition
```

**Staging:**
```php
define( 'MEMBERSHIP_STAGING_MODE', true );
```

**Lokal Udvikling:**
```php
define( 'MEMBERSHIP_STAGING_MODE', true );
```

### Automatisk Detektering af Miljø

Du kan automatisk aktivere staging mode baseret på domænet:

```php
// I wp-config.php
if ( strpos( $_SERVER['HTTP_HOST'], 'staging.' ) !== false || 
     strpos( $_SERVER['HTTP_HOST'], '.local' ) !== false ||
     strpos( $_SERVER['HTTP_HOST'], '.test' ) !== false ) {
    define( 'MEMBERSHIP_STAGING_MODE', true );
}
```

Dette aktiverer automatisk staging mode hvis dit domæne indeholder:
- `staging.` (f.eks. staging.example.com)
- `.local` (f.eks. example.local)
- `.test` (f.eks. example.test)

## Test Staging Mode

For at teste om Staging Mode virker:

1. Aktiver det via wp-config.php
2. Tjek at du ser den gule advarsel i admin
3. Tjek log-filen for `[STAGING MODE]` entries
4. Forsøg at sende en test-email - den vil blive blokeret
5. Tjek at cron job ikke kører (eller logger at de er skipped)

## Troubleshooting

### "Jeg ser ikke admin notice"

- Tjek at konstanten er defineret **før** `ABSPATH` check
- Clear cache (både WordPress og browser)
- Log ind og ud igen

### "Emails sendes stadig"

- Tjek at `MEMBERSHIP_STAGING_MODE` er `true` (ikke string `'true'`)
- Tjek log-filen for confirmering
- Vær sikker på at cache er cleared

### "Fornyelser kører stadig"

- Deaktiver og genaktiver pluginet
- Tjek at cron job er registreret korrekt: `wp cron event list`
- Tjek log-filen

## Sikkerhedsnote

⚠️ **Vigtigt**: Husk at fjerne eller deaktivere MEMBERSHIP_STAGING_MODE på dit produktionsmiljø! 

Det anbefales kraftigt at bruge miljø-baseret detektering (se ovenfor) for at undgå at glemme at slå det fra.

## Support

Ved problemer eller spørgsmål, kontakt support eller tjek plugin-loggen for detaljer:

```
wp-content/plugins/membership-manager/logs/membership-YYYY-MM-DD.log
```
