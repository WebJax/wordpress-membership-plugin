# Membership Manager WordPress Plugin

Et professionelt WordPress-plugin til at håndtere medlemskaber, fornyelser og udsende påmindelsesmails.

## 🌟 Funktioner

### Medlemsadministration
* **Komplet medlemsadministration** - Enkel grænseflade til at se og administrere medlemmer
* **Dashboard widgets** - Få hurtig oversigt over medlemsstatistik direkte på WordPress dashboard
* **Detaljeret medlemsvisning** - Se alle detaljer om individuelle medlemskaber
* **Bulk handlinger** - Administrer flere medlemskaber samtidigt
* **Søgning og filtrering** - Find hurtigt specifikke medlemmer

### Automatiske Fornyelser
* **Automatiske E-mail Påmindelser** - Sender automatisk påmindelser til medlemmer før udløb
* **Skabelonbaserede E-mails** - Tilpasselige e-mail skabeloner for 1, 7, 14 og 30 dage før udløb
* **Manuelle og automatiske fornyelser** - Understøtter både manuelle fornyelser og automatiske betalinger
* **Fornyelseslinks** - Sikre, unikke links til manuelle fornyelser

### WooCommerce Integration
* **Custom produkttyper** - Medlemsprodukter (automatisk og manuel fornyelse)
* **Ordreintegration** - Automatisk oprettelse af medlemskaber ved gennemført køb
* **Betalingsgateway support** - Understøttelse af gemte betalingsmetoder
* **Migration fra WooCommerce Subscriptions** - Nem import af eksisterende abonnementer

### Sikkerhed & Ydeevne
* **Sikker datahåndtering** - Prepared statements og input validering
* **Log rotation** - Automatisk logfil-rotation for at spare plads
* **Caching** - Indbygget caching for bedre ydeevne
* **Nonce beskyttelse** - CSRF-beskyttelse på alle admin handlinger

## 📋 Systemkrav

* **WordPress:** 5.2 eller nyere
* **PHP:** 7.2 eller nyere
* **WooCommerce:** 4.0 eller nyere (anbefalet)
* **MySQL:** 5.6 eller nyere

## 🚀 Installation

### Metode 1: Via WordPress Admin
1. Download plugin'et som en `.zip`-fil
2. Gå til dit WordPress kontrolpanel > **Plugins** > **Tilføj nyt**
3. Klik på **Upload Plugin** og vælg den downloadede `.zip`-fil
4. Klik på **Installer Nu** og derefter **Aktiver Plugin**

### Metode 2: Via FTP
1. Pak `.zip`-filen ud
2. Upload mappen `membership-manager` til `/wp-content/plugins/` via FTP
3. Gå til **Plugins** i WordPress admin og aktiver "JW Membership Manager"

### Første Gang Opsætning
1. Gå til **Medlemskaber** > **Indstillinger** i WordPress admin
2. Konfigurer email indstillinger
3. Vælg medlemsprodukter (automatiske og/eller manuelle)
4. Vælg medlemsrolle (standard: subscriber)
5. Gem indstillingerne

## 💡 Brug

### Oprettelse af Medlemsprodukter

1. Gå til **Produkter** > **Tilføj nyt** i WooCommerce
2. Vælg produkttype: **Medlemskab (Auto-Fornyelse)** eller **Medlemskab (Manual)**
3. Udfyld produktoplysninger (navn, pris, beskrivelse)
4. Under **Medlemskab** fanen:
   - Tilføj beskrivelse af hvad medlemskabet inkluderer
   - For auto-fornyelse: Aktiver automatisk betaling om ønsket
5. Gem produktet

### Manuel Oprettelse af Medlemskaber

1. Gå til **Medlemskaber** > **Tilføj nyt**
2. Vælg bruger (indtast bruger ID eller søg)
3. Vælg startdato og slutdato
4. Vælg status og fornyelsestype
5. Klik **Opret Medlemskab**

### Migration fra WooCommerce Subscriptions

1. Gå til **Medlemskaber** > **Migration**
2. Vælg de produkter du vil migrere
3. Klik **Migrer Abonnementer**
4. Systemet vil:
   - Konvertere produkter til medlemsprodukter
   - Importere eksisterende abonnementer
   - Bevare abonnementdata
   - Generere fornyelsestokens

### Test af Automatisk Fornyelse og Påmindelsesmails

For at teste at automatisk fornyelse og påmindelsesmails fungerer korrekt:

1. Gå til **Medlemskaber** → **Test Tools** i WordPress admin
2. Brug test-værktøjerne til at:
   - Sende test påmindelsesmails for alle intervaller (30, 14, 7, 1 dage)
   - Teste automatisk oprettelse af fornyelsesordrer gennem WooCommerce
   - Køre den fulde fornyelsesproces manuelt
   - Se logs for at verificere resultater

Se den detaljerede [Test Tools Guide](docs/TEST-TOOLS-GUIDE.md) for mere information.

### Tilpasning af E-mail Skabeloner

For at tilpasse e-mail-skabelonerne:

1. Kopiér skabeloner fra:
   ```
   wp-content/plugins/membership-manager/templates/emails/
   ```

2. Til din tema-mappe:
   ```
   wp-content/themes/DIT-TEMA/membership-manager/emails/
   ```

3. Rediger kopierne - de vil ikke blive overskrevet ved plugin-opdateringer

### Shortcodes

#### `[member_only]` - Beskyt Indhold
Vis indhold kun for aktive medlemmer:
```php
[member_only]
Dette indhold er kun synligt for medlemmer.
[/member_only]
```

Med custom besked:
```php
[member_only message="Du skal være medlem for at se dette"]
Medlemsindhold her
[/member_only]
```

#### `[membership_details]` - Vis Medlemsdetaljer
Vis brugerens medlemsoplysninger:
```php
[membership_details]
```

### Hooks & Filters

#### Action Hooks
```php
// Når et medlemskab aktiveres
add_action( 'membership_manager_subscription_activated', function( $user_id, $subscription_id ) {
    // Din kode her
}, 10, 2 );

// Når et medlemskab udløber
add_action( 'membership_manager_subscription_expired', function( $user_id, $subscription_id ) {
    // Din kode her
}, 10, 2 );

// Når medlemsstatus ændres
add_action( 'membership_manager_status_changed', function( $subscription_id, $old_status, $new_status ) {
    // Din kode her
}, 10, 3 );

// Når en fornyelse fejler
add_action( 'membership_manager_failed_renewal', function( $subscription, $order, $reason ) {
    // Din kode her
}, 10, 3 );
```

## 🔧 Avancerede Funktioner

### Database Schema

Plugin'et opretter følgende tabel:
```sql
wp_membership_subscriptions (
    id mediumint(9) AUTO_INCREMENT,
    user_id bigint(20),
    start_date datetime,
    end_date datetime,
    status varchar(20),
    renewal_type varchar(20),
    renewal_token varchar(64),
    paused_date datetime,
    status_changed_date datetime,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY renewal_token (renewal_token),
    KEY status (status),
    KEY end_date (end_date)
)
```

### Medlemsstatus

* **active** - Aktivt medlemskab
* **expired** - Udløbet medlemskab
* **pending-cancel** - Afventer annullering (fejlet fornyelse)
* **cancelled** - Annulleret
* **on-hold** - På pause

### Fornyelsestyper

* **automatic** - Automatisk fornyelse med gemt betalingsmetode
* **manual** - Manuel fornyelse via fornyelseslink

### Cron Jobs

Plugin'et registrerer en daglig cron job (`membership_renewal_cron`) der:
* Tjekker for udløbne medlemskaber
* Sender påmindelsesmails
* Opretter fornyelsesordrer for automatiske fornyelser
* Opdaterer medlemsstatus

### Logging

Alle handlinger logges i:
```
wp-content/plugins/membership-manager/logs/membership.log
```

Logfiler roteres automatisk når de når 5MB og der bevares de seneste 5 backup-filer.

## 🛡️ Sikkerhed

* Alle admin handlinger er beskyttet med nonce verificering
* Input saniteres og valideres
* SQL injection beskyttelse via prepared statements
* XSS beskyttelse via output escaping
* Log mappe er beskyttet med .htaccess
* Sikre tokens genereres med random_bytes()

## 🔄 Opdateringer

Plugin'et tracker database version og kan håndtere fremtidige opdateringer:
* Database version gemmes i option: `membership_manager_db_version`
* Fremtidige migrationer kan tilføjes via upgrade rutiner

## 🗑️ Afinstallation

Ved sletning af plugin gennem WordPress admin:
* Databasetabel fjernes
* Alle plugin options slettes
* Bruger meta data ryddes op
* Cron jobs fjernes
* Rewrite rules flushes

**OBS:** Denne handling kan ikke fortrydes!

## 📊 Status Counts & Statistik

Plugin'et tilbyder built-in statistik via:
* Dashboard widgets med realtids counts
* Status oversigt med farvekodning
* Advarsler om fejlede fornyelser
* Liste over kommende udløb

## 🐛 Fejlfinding

### E-mails sendes ikke
1. Tjek **Indstillinger** > Email konfiguration
2. Send test-email fra indstillingssiden
3. Tjek at WordPress kan sende emails (test med standard WordPress email)
4. Overvej at bruge et SMTP plugin

### Cron jobs kører ikke
1. Tjek at WordPress cron er aktiveret
2. Test manuel cron kørsel: `wp cron event run membership_renewal_cron`
3. Overvej at bruge en server cron job i stedet for WP-Cron

### Medlemskaber oprettes ikke ved køb
1. Tjek at produktet er konfigureret som medlemsprodukt
2. Verificer at ordren er sat til "Completed" status
3. Tjek logs for fejlmeddelelser

### Log filer for store
Logfiler roteres automatisk, men du kan:
1. Slette gamle backup filer manuelt fra `/logs/` mappen
2. Reducere logging ved kun at logge warnings og errors

## 📝 Licens

Dette plugin er udgivet under **GPL v2 eller senere**.

## 🤝 Support

For support og fejlrapportering:
* Opret et issue på GitHub
* Kontakt plugin udvikler via email

## 👨‍💻 Udvikling

### Struktur
```
membership-manager/
├── admin/
│   ├── css/
│   ├── js/
│   └── views/
├── includes/
│   ├── class-membership-*.php
│   └── products/
├── languages/
├── logs/
├── templates/
│   └── emails/
├── membership-manager.php
└── uninstall.php
```

### Bidrag

Bidrag er velkomne! For at bidrage:
1. Fork projektet
2. Opret en feature branch
3. Commit dine ændringer
4. Push til branchen
5. Opret en Pull Request

## 📈 Changelog

### Version 1.0.0
* Initial release
* Medlemsadministration
* E-mail påmindelser
* WooCommerce integration
* Dashboard widgets
* Migration tool
* Sikkerhedsforbedringer
* Logging system
* Utility klasser

---

**Udviklet af:** Jaxweb + AI  
**Website:** https://jaxweb.dk/
