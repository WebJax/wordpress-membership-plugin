# Test Tools Documentation

## Oversigt (Overview)

Test Tools-siden giver administratorer mulighed for at teste medlemskabsfornyelse og påmindelsesmails uden at vente på de daglige cron-jobs eller faktiske udløbsdatoer.

The Test Tools page allows administrators to test membership renewal and reminder emails without waiting for daily cron jobs or actual expiration dates.

## Adgang (Access)

Gå til WordPress Admin → **Medlemskaber** → **Test Tools**

Navigate to WordPress Admin → **Memberships** → **Test Tools**

## Funktioner (Features)

### 1. Test Påmindelsesmails (Test Reminder Emails)

Test at påmindelsesmails sendes korrekt for både automatiske og manuelle fornyelser.

Test that reminder emails are sent correctly for both automatic and manual renewals.

**Parametre (Parameters):**

- **Email Adresse (Email Address):** Den emailadresse hvor test-emails skal sendes (The email address where test emails should be sent)
- **Påmindelsestype (Reminder Type):** Vælg hvilke påmindelser der skal testes (Select which reminders to test):
  - Alle påmindelser (30, 14, 7, 1 dage) - All reminders (30, 14, 7, 1 days)
  - 30 dage før udløb - 30 days before expiration
  - 14 dage før udløb - 14 days before expiration
  - 7 dage før udløb - 7 days before expiration
  - 1 dag før udløb - 1 day before expiration
- **Fornyelsestype (Renewal Type):** Test emails for specifikke fornyelsestyper (Test emails for specific renewal types):
  - Begge (Manuel & Automatisk) - Both (Manual & Automatic)
  - Automatisk fornyelse - Automatic renewal
  - Manuel fornyelse - Manual renewal

**Sådan bruges det (How to use):**

1. Indtast en gyldig emailadresse (Enter a valid email address)
2. Vælg hvilke påmindelsestyper du vil teste (Select which reminder types to test)
3. Vælg fornyelsestype (Select renewal type)
4. Klik "Send Test Reminder Emails"
5. Tjek din indbakke og spam-mappe for test-emails (Check your inbox and spam folder for test emails)

**Hvad der sker (What happens):**

- Systemet opretter midlertidige test-medlemskaber med relevante udløbsdatoer (The system creates temporary test memberships with relevant expiration dates)
- Påmindelsesmails genereres og sendes til den angivne emailadresse (Reminder emails are generated and sent to the specified email address)
- Alle handlinger logges i systemloggen (All actions are logged in the system log)

### 2. Test Automatisk Fornyelsesproces (Test Automatic Renewal Process)

Test oprettelse af fornyelsesordrer for automatiske medlemskaber gennem WooCommerce.

Test creation of renewal orders for automatic memberships through WooCommerce.

**Parametre (Parameters):**

- **Vælg Medlemskab (Select Membership):** Dropdown med aktive medlemskaber (Dropdown with active memberships)
- **Tving Fornyelse (Force Renewal):** Opret fornyelsesordre selvom medlemskabet ikke er tæt på udløb (Create renewal order even if membership is not near expiration)

**Sådan bruges det (How to use):**

1. Vælg et medlemskab fra listen (Select a membership from the list)
2. Valgfrit: Markér "Tving Fornyelse" for at teste uden at vente på udløbsdato (Optional: Check "Force Renewal" to test without waiting for expiration date)
3. Klik "Test Automatic Renewal"
4. Se succesmeddelelsen med link til den oprettede ordre (See success message with link to created order)

**Hvad der sker (What happens):**

- Systemet opretter en WooCommerce-ordre for fornyelsesproduktet (The system creates a WooCommerce order for the renewal product)
- Ordren tilknyttes medlemskabet (The order is linked to the membership)
- Systemet forsøger at behandle betaling automatisk hvis der er en gemt betalingsmetode (The system attempts to process payment automatically if there's a saved payment method)
- Alle handlinger logges (All actions are logged)

**Verifikation (Verification):**

Efter test kan du:
- Klikke på linket for at se den oprettede ordre i WooCommerce (Click the link to view the created order in WooCommerce)
- Kontrollere at ordren har korrekt metadata (Check that the order has correct metadata)
- Verificere at betalingsmetoden er sat korrekt (Verify that the payment method is set correctly)
- Se om betalingen blev behandlet automatisk (See if payment was processed automatically)

### 3. Kør Fuld Fornyelsesproces (Run Full Renewal Process)

Kør hele den daglige fornyelsesproces manuelt for at teste alle aspekter.

Run the complete daily renewal process manually to test all aspects.

**Hvad der sker (What happens):**

1. Systemet kontrollerer alle aktive medlemskaber (The system checks all active memberships)
2. Sender påmindelsesmails for medlemskaber der udløber om 30, 14, 7 eller 1 dag (Sends reminder emails for memberships expiring in 30, 14, 7 or 1 day)
3. Opretter automatiske fornyelsesordrer for medlemskaber der udløber i dag (Creates automatic renewal orders for memberships expiring today)
4. Markerer udløbne medlemskaber som udløbet (Marks expired memberships as expired)

**Sådan bruges det (How to use):**

1. Klik "Kør Fornyelsesproces Nu" (Click "Run Renewal Process Now")
2. Bekræft handlingen (Confirm the action)
3. Se loggen nedenfor for detaljer om hvad der blev behandlet (View the log below for details about what was processed)

**Vigtigt (Important):**

Dette kører den samme proces som den daglige cron-job. Brug forsigtigt i produktionsmiljøer da det sender rigtige emails og opretter rigtige ordrer.

This runs the same process as the daily cron job. Use carefully in production environments as it sends real emails and creates real orders.

### 4. Nylig Aktivitetslog (Recent Activity Log)

Se de seneste 50 logindtastninger for at verificere testresultater og fejlfinde problemer.

View the most recent 50 log entries to verify test results and troubleshoot issues.

**Sådan bruges det (How to use):**

1. Klik "Vis Logger" (Click "View Logs")
2. Loggen vises nedenfor (The log appears below)
3. Genindlæs siden for at se opdaterede logger (Refresh the page to see updated logs)

**Log Format:**

```
[YYYY-MM-DD HH:MM:SS] [TYPE] - Message
```

**Log Typer (Log Types):**
- `INFO`: Normal information
- `WARNING`: Advarsler (Warnings)
- `ERROR`: Fejl (Errors)

## Test Scenarier (Test Scenarios)

### Scenarie 1: Test Email Levering (Test Email Delivery)

**Formål (Purpose):** Verificer at email-systemet fungerer korrekt (Verify that the email system works correctly)

**Trin (Steps):**
1. Gå til Test Tools (Go to Test Tools)
2. Vælg "Alle Påmindelser" (Select "All Reminders")
3. Vælg "Begge" fornyelsestyper (Select "Both" renewal types)
4. Indtast din emailadresse (Enter your email address)
5. Klik "Send Test Reminder Emails"
6. Tjek at du modtager 8 emails (4 intervaller × 2 typer) (Check that you receive 8 emails (4 intervals × 2 types))

**Forventet Resultat (Expected Result):**
- Du modtager alle 8 test-emails (You receive all 8 test emails)
- Emailsne har korrekte emnelinjer (Emails have correct subject lines)
- Emailsne indeholder relevante oplysninger (Emails contain relevant information)
- Loggen viser succesfuld afsendelse (Log shows successful delivery)

### Scenarie 2: Test Automatisk Fornyelse med WooCommerce (Test Automatic Renewal with WooCommerce)

**Formål (Purpose):** Verificer at automatiske fornyelsesordrer oprettes korrekt (Verify that automatic renewal orders are created correctly)

**Forudsætninger (Prerequisites):**
- Mindst ét automatisk fornyelsesprodukt konfigureret (At least one automatic renewal product configured)
- Mindst ét aktivt medlemskab med automatisk fornyelse (At least one active membership with automatic renewal)
- WooCommerce installeret og aktiveret (WooCommerce installed and activated)

**Trin (Steps):**
1. Gå til Test Tools (Go to Test Tools)
2. Vælg et medlemskab med automatisk fornyelse (Select a membership with automatic renewal)
3. Markér "Tving Fornyelse" (Check "Force Renewal")
4. Klik "Test Automatic Renewal"
5. Klik på linket til den oprettede ordre (Click the link to the created order)

**Forventet Resultat (Expected Result):**
- En WooCommerce-ordre oprettes (A WooCommerce order is created)
- Ordren indeholder fornyelsesproduktet (Order contains the renewal product)
- Ordren har korrekt metadata: `_membership_subscription_id` og `_is_membership_renewal` (Order has correct metadata)
- Hvis bruger har en gemt betalingsmetode, forsøges betaling automatisk (If user has saved payment method, payment is attempted automatically)

### Scenarie 3: Test Betalingsgateway Integration (Test Payment Gateway Integration)

**Formål (Purpose):** Verificer at betalingsgateway'en håndteres korrekt (Verify that payment gateway is handled correctly)

**Forudsætninger (Prerequisites):**
- Et medlemskab tilhørende en bruger med gemt betalingsmetode (A membership belonging to a user with saved payment method)
- WooCommerce betalingsgateway konfigureret (WooCommerce payment gateway configured)

**Trin (Steps):**
1. Identificer et medlemskab hvis bruger har en gemt betalingsmetode i WooCommerce (Identify a membership whose user has a saved payment method in WooCommerce)
2. Kør "Test Automatic Renewal" for dette medlemskab (Run "Test Automatic Renewal" for this membership)
3. Se den oprettede ordre i WooCommerce (View the created order in WooCommerce)
4. Kontroller ordrestatus (Check order status)
5. Tjek om betalingsmetoden er sat (Check if payment method is set)

**Forventet Resultat (Expected Result):**
- Ordren har den gemte betalingsmetode tilknyttet (Order has the saved payment method attached)
- For gateways der understøtter det, behandles betaling automatisk (For gateways that support it, payment is processed automatically)
- Hvis betaling fejler, sendes email til kunden (If payment fails, email is sent to customer)
- Log viser forsøg på automatisk betaling (Log shows attempt at automatic payment)

### Scenarie 4: Test Fuld Daglig Proces (Test Full Daily Process)

**Formål (Purpose):** Test hele fornyelsesprocessen som den ville køre dagligt (Test the entire renewal process as it would run daily)

**Trin (Steps):**
1. Opret test-medlemskaber med forskellige udløbsdatoer (Create test memberships with different expiration dates):
   - Et der udløber om 30 dage (One expiring in 30 days)
   - Et der udløber om 14 dage (One expiring in 14 days)
   - Et der udløber om 7 dage (One expiring in 7 days)
   - Et der udløber i morgen (One expiring tomorrow)
   - Et der er udløbet (One that has expired)
2. Klik "Kør Fornyelsesproces Nu" (Click "Run Renewal Process Now")
3. Se loggen for detaljer (View the log for details)

**Forventet Resultat (Expected Result):**
- Påmindelsesmails sendes til relevante medlemskaber (Reminder emails are sent to relevant memberships)
- Udløbne medlemskaber markeres som udløbet (Expired memberships are marked as expired)
- Log viser alle handlinger tydeligt (Log shows all actions clearly)

## Fejlfinding (Troubleshooting)

### Problem: Emails sendes ikke (Emails are not sent)

**Løsninger (Solutions):**
1. Kontroller at påmindelsesmails er aktiveret i Indstillinger (Check that reminder emails are enabled in Settings)
2. Verificer email-konfiguration på serveren (Verify email configuration on server)
3. Tjek spam-mappen (Check spam folder)
4. Se loggen for fejlmeddelelser (View log for error messages)
5. Test WordPress email-funktionalitet generelt (Test WordPress email functionality in general)

### Problem: Fornyelsesordrer oprettes ikke (Renewal orders are not created)

**Løsninger (Solutions):**
1. Kontroller at automatiske fornyelsesprodukter er konfigureret (Check that automatic renewal products are configured)
2. Verificer at WooCommerce er aktivt (Verify that WooCommerce is active)
3. Se loggen for specifikke fejlmeddelelser (View log for specific error messages)
4. Kontroller at produktet stadig findes (Check that the product still exists)

### Problem: Betaling behandles ikke automatisk (Payment is not processed automatically)

**Mulige Årsager (Possible Reasons):**
1. Bruger har ingen gemt betalingsmetode (User has no saved payment method)
2. Betalingsgateway understøtter ikke automatiske betalinger (Payment gateway doesn't support automatic payments)
3. Betalingsmetoden er udløbet eller ugyldig (Payment method is expired or invalid)

**Løsninger (Solutions):**
1. Se loggen for detaljer (View log for details)
2. Kontroller brugerens gemte betalingsmetoder i WooCommerce (Check user's saved payment methods in WooCommerce)
3. Verificer gateway-konfiguration (Verify gateway configuration)

## Bedste Praksis (Best Practices)

1. **Test først i staging-miljø (Test in staging environment first):** Kør tests i et staging-miljø før produktion (Run tests in staging environment before production)

2. **Brug test-emailadresser (Use test email addresses):** Brug dedikerede test-emailadresser for at undgå at spamme rigtige brugere (Use dedicated test email addresses to avoid spamming real users)

3. **Gennemgå logger regelmæssigt (Review logs regularly):** Tjek loggen efter hver test for at forstå hvad der skete (Check the log after each test to understand what happened)

4. **Test alle scenarier (Test all scenarios):** Test både succesfulde og fejlscenarier (Test both successful and failure scenarios)

5. **Dokumentér resultater (Document results):** Hold noter om testresultater for fremtidig reference (Keep notes of test results for future reference)

6. **Vær forsigtig med "Kør Fuld Proces" i produktion (Be careful with "Run Full Process" in production):** Dette sender rigtige emails og opretter rigtige ordrer (This sends real emails and creates real orders)

## Teknisk Information (Technical Information)

### Klasser (Classes)

- `Membership_Test_Tools`: Hovedklasse for test-funktionalitet (Main class for test functionality)
- `Membership_Renewals`: Håndterer fornyelseslogik (Handles renewal logic)
- `Membership_Emails`: Håndterer email-afsendelse (Handles email sending)

### Hooks og Filtre (Hooks and Filters)

Test-værktøjet bruger følgende hooks:
- `wp_mail`: Omdirigerer emails til test-adresse (Redirects emails to test address)
- `admin_menu`: Tilføjer test tools menu (Adds test tools menu)
- `admin_post_*`: Håndterer form submissions (Handles form submissions)

### Logning (Logging)

Alle handlinger logges til: `wp-content/plugins/wordpress-membership-plugin/logs/membership.log`

Log-format: `[YYYY-MM-DD HH:MM:SS] [TYPE] - Message`

## Support

For problemer eller spørgsmål, kontakt support eller se plugin-dokumentationen.

For issues or questions, contact support or see the plugin documentation.
