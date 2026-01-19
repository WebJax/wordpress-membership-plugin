# Membership Manager WordPress Plugin

Et simpelt WordPress-plugin til at håndtere medlemskaber, fornyelser og udsende påmindelsesmails.

## Funktioner

*   **Medlemsadministration:** Enkel grænseflade til at se og administrere medlemmer.
*   **Automatiske E-mail Påmindelser:** Sender automatisk påmindelser ud til medlemmer, hvis medlemskab er ved at udløbe.
*   **Skabelonbaserede E-mails:** E-mail skabeloner for 1, 7, 14 og 30 dage før udløb.
*   **Manuelle Fornyelser:** Understøtter processer for manuelle fornyelser.
*   **Automatiske Fornyelser:** Integration med WooCommerce til automatisk fornyelse af medlemskaber.
*   **Test Tools:** Omfattende test-funktionalitet til at verificere automatiske fornyelser og påmindelsesmails.
*   **Indstillingsside:** Konfigurer plugin-indstillinger.
*   **Klar til oversættelse:** Inkluderer en `.pot`-fil til nem oversættelse.

## Installation

1.  Download plugin'et som en `.zip`-fil.
2.  Gå til dit WordPress kontrolpanel > **Plugins** > **Tilføj nyt**.
3.  Klik på **Upload Plugin** og vælg den downloadede `.zip`-fil.
4.  Aktiver plugin'et efter installation.

Alternativt kan du pakke mappen ud og uploade den direkte til `/wp-content/plugins/`-mappen via FTP.

## Brug

Efter aktivering vil du finde et nyt menupunkt i WordPress-administratoren, hvor du kan administrere medlemmer og konfigurere indstillingerne for plugin'et.

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

For at tilpasse e-mail-skabelonerne skal du kopiere dem fra:
`wp-content/plugins/wordpress-membership-plugin/templates/emails/`

til din tema-mappe her:
`wp-content/themes/DIT-TEMA/membership-manager/emails/`

Ved at placere dem i din tema-mappe sikrer du, at dine ændringer ikke bliver overskrevet, når plugin'et opdateres.

## Licens

Dette plugin er udgivet under GPLv2 eller en senere version.
