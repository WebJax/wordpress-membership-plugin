#!/usr/bin/env python3
"""
Automatisk oversættelsesskript for WordPress Membership Plugin
Dette script finder og erstatter engelske tekststrenge med danske oversættelser
"""

import re
import os
import sys

# Ordbog med oversættelser (engelsk => dansk)
TRANSLATIONS = {
    # Core terms
    "Membership": "Medlemskab",
    "Memberships": "Medlemskaber",
    "Membership Manager": "Medlemskabsstyring",
    
    # Actions/Buttons
    "Add New": "Tilføj ny",
    "Add New Membership": "Tilføj nyt medlemskab",
    "Add Membership": "Tilføj medlemskab",
    "Add Product": "Tilføj produkt",
    "Update Membership": "Opdater medlemskab",
    "Pause Membership": "Pause medlemskab",
    "Resume Membership": "Genoptag medlemskab",
    "Delete Membership": "Slet medlemskab",
    "Renew Membership": "Forny medlemskab",
    "Renew Now": "Forny nu",
    "Renew now": "Forny nu",
    "Copy Renewal Link": "Kopier fornyelseslink",
    "Send Test Email": "Send test e-mail",
    "Sending...": "Sender...",
    "Filter": "Filtrer",
    "View": "Vis",
    "Edit": "Rediger",
    "Delete": "Slet",
    "Remove": "Fjern",
    "Back to List": "Tilbage til liste",
    "Click here to renew": "Klik her for at forny",
    
    # Status
    "Status": "Status",
    "Active": "Aktiv",
    "Expired": "Udløbet",
    "Pending Cancel": "Afventer annullering",
    "Cancelled": "Annulleret",
    "On Hold": "På hold",
    "All Statuses": "Alle statusser",
    
    # Fields
    "User": "Bruger",
    "User ID": "Bruger-ID",
    "Start Date": "Startdato",
    "End Date": "Slutdato",
    "Expiry Date": "Udløbsdato",
    "Expiration Date": "Udløbsdato",
    "Expires": "Udløber",
    "End date:": "Slutdato:",
    "Start Date:": "Startdato:",
    "Status:": "Status:",
    "Renewal Type": "Fornyelsestype",
    "Renewal Type:": "Fornyelsestype:",
    "Renewal Link": "Fornyelseslink",
    "Renewal Link:": "Fornyelseslink:",
    "Manual": "Manuel",
    "Automatic": "Automatisk",
    "Membership ID": "Medlemskabs-ID",
    "Status Changed": "Status ændret",
    "Paused Date": "Pausedato",
    "Actions": "Handlinger",
    "Name": "Navn",
    "Email": "E-mail",
    "Username": "Brugernavn",
    "Registration Date": "Registreringsdato",
    "Full Name": "Fulde navn",
    
    # Pages/Sections  
    "Membership Details": "Medlemskabsdetaljer",
    "Membership Information": "Medlemskabsinformation",
    "User Information": "Brugerinformation",
    "Order History": "Ordrehistorik",
    "Order #": "Ordre #",
    "Date": "Dato",
    "Total": "I alt",
    "Payment Method": "Betalingsmetode",
    "Billing Information": "Faktureringsinformation",
    "Billing Address": "Faktureringsadresse",
    
    # Settings
    "Membership Settings": "Medlemskabsindstillinger",
    "Settings": "Indstillinger",
    "Indstillinger": "Indstillinger",  # Keep Danish
    "Automatic Renewal": "Automatisk fornyelse",
    "Manual Renewal": "Manuel fornyelse",
    "Products": "Produkter",
    "User Roles & Capabilities": "Brugerroller og rettigheder",
    "Member Role": "Medlemsrolle",
    "Remove Role on Expiration": "Fjern rolle ved udløb",
    "Email Settings": "E-mailindstillinger",
    "Enable Email Reminders": "Aktiver e-mailpåmindelser",
    "From Name": "Afsendernavn",
    "From Email Address": "Afsender e-mailadresse",
    "Email Subject Lines": "E-mail emnelinjer",
    "30-Day Reminder Subject": "30-dages påmindelse emne",
    "14-Day Reminder Subject": "14-dages påmindelse emne",
    "7-Day Reminder Subject": "7-dages påmindelse emne",
    "1-Day Reminder Subject": "1-dages påmindelse emne",
    "Test Email": "Test e-mail",
    
    # Messages
    "Your Membership": "Dit medlemskab",
    "My Membership": "Mit medlemskab",
    "YOUR MEMBERSHIP": "DIT MEDLEMSKAB",
    "No memberships found.": "Ingen medlemskaber fundet.",
    "No active membership found.": "Intet aktivt medlemskab fundet.",
    "Invalid membership ID.": "Ugyldigt medlemskabs-ID.",
    "Membership not found.": "Medlemskab ikke fundet.",
    "User not found.": "Bruger ikke fundet.",
    "Membership updated successfully!": "Medlemskab opdateret!",
    "Membership paused successfully!": "Medlemskab pauseret!",
    "Membership resumed successfully!": "Medlemskab genoptaget!",
    "Membership created successfully!": "Medlemskab oprettet!",
    "Renewal link copied to clipboard!": "Fornyelseslink kopieret til udklipsholder!",
    
    # Descriptions
    "Enter the WordPress User ID for the member.": "Indtast WordPress bruger-ID for medlemmet.",
    "Leave empty for no expiration.": "Lad stå tomt for ingen udløbsdato.",
    "Products that will automatically renew memberships on expiration.": "Produkter der automatisk fornyer medlemskaber ved udløb.",
    "Products that require manual renewal by members.": "Produkter der kræver manuel fornyelse af medlemmer.",
    "WordPress role to assign to members with active memberships.": "WordPress rolle der tildeles medlemmer med aktive medlemskaber.",
    "Automatically remove member role when membership expires": "Fjern automatisk medlemsrolle når medlemskabet udløber",
    "Send automatic email reminders before membership expiration": "Send automatiske e-mailpåmindelser før medlemskabet udløber",
    "Emails will be sent 30, 14, 7, and 1 day before expiration.": "E-mails sendes 30, 14, 7 og 1 dag før udløb.",
    "Send a test reminder email to verify your settings.": "Send en test påmindelses-e-mail for at verificere dine indstillinger.",
    "Automatic - Will renew automatically": "Automatisk - Fornyes automatisk",
    "Manual - You will receive renewal reminders": "Manuel - Du vil modtage fornyelsespåmindelser",
    
    # Confirmations
    "Are you sure?": "Er du sikker?",
    "Are you sure you want to pause this membership?": "Er du sikker på, at du vil pause dette medlemskab?",
    "Are you sure you want to resume this membership?": "Er du sikker på, at du vil genoptage dette medlemskab?",
    "Are you sure you want to delete this membership? This cannot be undone.": "Er du sikker på, at du vil slette dette medlemskab? Dette kan ikke fortrydes.",
    
    # Dashboard
    "Membership Status": "Medlemskabsstatus",
    "Membership Issues & Alerts": "Medlemskabsproblemer og advarsler",
    "Expiring This Week": "Udløber denne uge",
    
    # Shortcodes/Content
    "You must be logged in to view this content.": "Du skal være logget ind for at se dette indhold.",
    "This content is restricted to active members.": "Dette indhold er forbeholdt aktive medlemmer.",
    
    # Product Types
    "Membership (Auto-Renewal)": "Medlemskab (Auto-fornyelse)",
    "Membership (Manual)": "Medlemskab (Manuel)",
    "Membership Duration": "Medlemskabsvarighed",
    "This membership will be valid for 1 year from purchase date.": "Dette medlemskab er gyldigt i 1 år fra købsdatoen.",
    "Attempt automatic payment on renewal": "Forsøg automatisk betaling ved fornyelse",
    "Membership Description": "Medlemskabsbeskrivelse",
    
    # Standard subjects
    "Your membership will expire in 30 days": "Dit medlemskab udløber om 30 dage",
    "Your membership will expire in 14 days": "Dit medlemskab udløber om 14 dage",
    "Your membership will expire in 7 days": "Dit medlemskab udløber om 7 dage",
    "Your membership will expire tomorrow": "Dit medlemskab udløber i morgen",
    
    # Email messages
    "Welcome to Your Membership!": "Velkommen til dit medlemskab!",
    "Hi %s,\\n\\nWelcome! Your membership is now active.\\n\\nStart Date: %s\\nExpiry Date: %s\\nRenewal Type: %s\\n\\n": "Hej %s,\\n\\nVelkommen! Dit medlemskab er nu aktivt.\\n\\nStartdato: %s\\nUdløbsdato: %s\\nFornyelsestype: %s\\n\\n",
    "You can renew your membership at any time using this link:\\n%s\\n\\n": "Du kan forny dit medlemskab når som helst ved at bruge dette link:\\n%s\\n\\n",
    "Thank you for being a member!\\n": "Tak for at være medlem!\\n",
    
    # Log messages
    "Email template not found: %s": "E-mail skabelon ikke fundet: %s",
    "Sent automatic renewal reminder (%s) to: %s": "Sendte automatisk fornyelsespåmindelse (%s) til: %s",
    "Failed to send automatic renewal reminder (%s) to: %s. Missing to, subject, or message.": "Kunne ikke sende automatisk fornyelsespåmindelse (%s) til: %s. Mangler modtager, emne eller besked.",
    "Sent manual renewal reminder (%s) to: %s": "Sendte manuel fornyelsespåmindelse (%s) til: %s",
    "Failed to send manual renewal reminder (%s) to: %s. Missing to, subject, or message.": "Kunne ikke sende manuel fornyelsespåmindelse (%s) til: %s. Mangler modtager, emne eller besked.",
    "[STAGING MODE] Email blocked - To: %s, Subject: %s": "[STAGING MODE] E-mail blokeret - Til: %s, Emne: %s",
    "Invalid email address: %s": "Ugyldig e-mailadresse: %s",
    "Empty subject or message in email": "Tomt emne eller besked i e-mail",
    "Failed to send email to: %s with subject: %s": "Kunne ikke sende e-mail til: %s med emne: %s",
    
    # Product buttons
    "Add membership": "Tilføj medlemskab",
    "Purchase membership": "Køb medlemskab",
    
    # Additional confirmations/messages
    "Showing 10 of %d orders": "Viser 10 af %d ordrer",
    "View customer membership page": "Vis kundens medlemskabsside",
    "Viewing membership for %s (User ID: %d)": "Viser medlemskab for %s (Bruger-ID: %d)",
    
    # System & Database messages
    "Plugin activated. Database result: %s": "Plugin aktiveret. Database resultat: %s",
    "Warning: Failed to create .htaccess file in logs directory (%s). Please check directory permissions.": "Advarsel: Kunne ikke oprette .htaccess fil i logs mappen (%s). Tjek venligst mappe rettigheder.",
    "[STAGING MODE] Renewal process skipped - staging mode is active": "[STAGING MODE] Fornyelsesproces sprunget over - staging mode er aktiv",
    "Starting renewal process.": "Starter fornyelsesproces.",
    "Finished renewal process.": "Fornyelsesproces afsluttet.",
    "You do not have sufficient permissions to access this page.": "Du har ikke tilstrækkelige rettigheder til at tilgå denne side.",
    "Security check failed. Please try again.": "Sikkerhedstjek mislykkedes. Prøv venligst igen.",
    "Nonce verification failed for migration.": "Nonce verificering mislykkedes for migration.",
    "Nonce verification failed for validation.": "Nonce verificering mislykkedes for validering.",
    "Please select at least one product to migrate.": "Vælg venligst mindst ét produkt at migrere.",
    "No products selected for migration.": "Ingen produkter valgt til migration.",
    "Database error occurred. Please check the logs.": "Database fejl opstod. Tjek venligst logs.",
    "Database error creating membership: %s": "Database fejl ved oprettelse af medlemskab: %s",
    "Database error updating membership ID %d: %s": "Database fejl ved opdatering af medlemskabs-ID %d: %s",
    "Invalid date format. Please use a valid date.": "Ugyldigt datoformat. Brug venligst en gyldig dato.",
    "Invalid date format provided. Please use a valid date format.": "Ugyldigt datoformat angivet. Brug venligst et gyldigt datoformat.",
    "Invalid status value.": "Ugyldig statusværdi.",
    "Invalid renewal type value.": "Ugyldig fornyelsestypeværdi.",
    "Invalid renewal link. Please contact support.": "Ugyldigt fornyelseslink. Kontakt venligst support.",
    "No renewal product configured. Please contact support.": "Intet fornyelsesprodukt konfigureret. Kontakt venligst support.",
    "Invalid date": "Ugyldig dato",
    "No date set": "Ingen dato angivet",
    "Uden udløb": "Uden udløb",
    
    # Migration messages
    "Starting WooCommerce subscription migration with products: %s": "Starter WooCommerce abonnements migration med produkter: %s",
    "WooCommerce Subscriptions not active for migration.": "WooCommerce Subscriptions er ikke aktiv for migration.",
    "Product migration completed: %d products converted": "Produkt migration fuldført: %d produkter konverteret",
    "Product migration summary: %d converted, %d already migrated, %d skipped": "Produkt migrations oversigt: %d konverteret, %d allerede migreret, %d sprunget over",
    "Product ID %d not found. Skipping.": "Produkt-ID %d ikke fundet. Springer over.",
    "Product ID %d is already a membership product type. Skipping.": "Produkt-ID %d er allerede en medlemskabsprodukttype. Springer over.",
    "Product ID %d is a WooCommerce subscription - converting to membership_auto": "Produkt-ID %d er et WooCommerce abonnement - konverterer til membership_auto",
    "Product ID %d is not a subscription - converting to membership_manual": "Produkt-ID %d er ikke et abonnement - konverterer til membership_manual",
    "Set default renewal period (1 year) for product ID %d": "Indstil standard fornyelsesperiode (1 år) for produkt-ID %d",
    "Added product ID %d to automatic renewal products list": "Tilføjede produkt-ID %d til automatisk fornyelsesproduktliste",
    "Added product ID %d to manual renewal products list": "Tilføjede produkt-ID %d til manuel fornyelsesproduktliste",
    "Successfully converted product ID %d from %s to %s": "Konverterede succesfuldt produkt-ID %d fra %s til %s",
    "Migrated subscription for user ID: %d with renewal type: %s": "Migrerede abonnement for bruger-ID: %d med fornyelsestype: %s",
    "Failed to migrate subscription for user ID: %d": "Kunne ikke migrere abonnement for bruger-ID: %d",
    "Subscription already exists for user ID: %d. Skipping.": "Abonnement findes allerede for bruger-ID: %d. Springer over.",
    "Finished WooCommerce subscription migration. Migrated %d subscriptions, skipped %d.": "Afsluttede WooCommerce abonnements migration. Migrerede %d abonnementer, sprang %d over.",
    "Product ID %d is a subscription product - setting as automatic renewal.": "Produkt-ID %d er et abonnementsprodukt - indstiller som automatisk fornyelse.",
    "Skipped subscription for user ID %d - does not contain selected products.": "Sprang abonnement over for bruger-ID: %d - indeholder ikke valgte produkter.",
    "Generated end_date for subscription user ID %d: %s": "Genererede slutdato for abonnement bruger-ID %d: %s",
    
    # Membership operations
    "Creating or extending membership for order ID: %d": "Opretter eller forlænger medlemskab for ordre-ID: %d",
    "No user ID found for order ID: %d. Aborting.": "Intet bruger-ID fundet for ordre-ID: %d. Afbryder.",
    "Detected subscription product (ID: %d) in order %d - setting as automatic renewal.": "Fandt abonnementsprodukt (ID: %d) i ordre %d - indstiller som automatisk fornyelse.",
    "Order ID: %d does not contain any membership products. Skipping.": "Ordre-ID: %d indeholder ikke nogen medlemskabsprodukter. Springer over.",
    "Extended membership for user ID: %d": "Forlængede medlemskab for bruger-ID: %d",
    "Created new membership for user ID: %d": "Oprettede nyt medlemskab for bruger-ID: %d",
    "Created new membership ID: %d for user ID: %d by admin.": "Oprettede nyt medlemskabs-ID: %d for bruger-ID: %d af admin.",
    "User ID %d already has a membership (ID: %d). Please edit the existing membership instead.": "Bruger-ID %d har allerede et medlemskab (ID: %d). Rediger venligst det eksisterende medlemskab i stedet.",
    "Updated membership ID: %d by user ID: %d": "Opdaterede medlemskabs-ID: %d af bruger-ID: %d",
    "Deleted membership ID: %d by user ID: %d": "Slettede medlemskabs-ID: %d af bruger-ID: %d",
    "Paused membership ID: %d by user ID: %d": "Pausede medlemskabs-ID: %d af bruger-ID: %d",
    "Resumed membership ID: %d by user ID: %d": "Genoptog medlemskabs-ID: %d af bruger-ID: %d",
    "Pauseret: ": "Pauseret: ",
    
    # Validation messages
    "Starting membership data validation.": "Starter medlemskabsdata validering.",
    "No membership products configured. Please configure membership products in settings first.": "Ingen medlemskabsprodukter konfigureret. Konfigurer venligst medlemskabsprodukter i indstillinger først.",
    "Validation failed: No membership products configured.": "Validering mislykkedes: Ingen medlemskabsprodukter konfigureret.",
    "Validation completed: %d orders checked, %d memberships checked, %d issues found.": "Validering fuldført: %d ordrer tjekket, %d medlemskaber tjekket, %d problemer fundet.",
    "Validation errors:<br>%s": "Valideringsfejl:<br>%s",
    "Validation failed with error: %s": "Validering mislykkedes med fejl: %s",
    "Checking memberships against order map...": "Tjekker medlemskaber mod ordre kort...",
    "Order #%d has membership product but no user ID (guest order).": "Ordre #%d har medlemskabsprodukt men intet bruger-ID (gæsteordre).",
    "Order #%d references membership #%d which no longer exists in database.": "Ordre #%d refererer til medlemskab #%d som ikke længere findes i databasen.",
    "Order #%d (user %d) has membership #%d but membership belongs to user %d.": "Ordre #%d (bruger %d) har medlemskab #%d men medlemskabet tilhører bruger %d.",
    "Order #%d (user %d) should have membership but none exists for this user.": "Ordre #%d (bruger %d) burde have medlemskab men der eksisterer ikke noget for denne bruger.",
    "Order #%d (user %d) should have membership but meta is not set. User has membership #%d.": "Ordre #%d (bruger %d) burde have medlemskab men meta er ikke sat. Bruger har medlemskab #%d.",
    "Membership #%d (user %d) has no associated completed order with membership products. May be manually created or migrated.": "Medlemskab #%d (bruger %d) har ingen tilknyttet fuldført ordre med medlemskabsprodukter. Kan være manuelt oprettet eller migreret.",
    
    # Renewal messages
    "Generated renewal tokens for %d memberships.": "Genererede fornyelsestokens for %d medlemskaber.",
    "Regenerated renewal token for subscription ID: %d": "Regenererede fornyelsestoken for abonnements-ID: %d",
    "User accessed renewal link for subscription ID: %d, redirecting to checkout": "Bruger tilgik fornyelseslink for abonnements-ID: %d, omdirigerer til checkout",
    "Migration": "Migration",
    
    # Role management messages
    "Handling activation for user ID: %d, subscription ID: %d": "Håndterer aktivering for bruger-ID: %d, abonnements-ID: %d",
    "User ID %d not found": "Bruger-ID %d ikke fundet",
    'Added role "%s" to user ID: %d': 'Tilføjede rolle "%s" til bruger-ID: %d',
    "Handling expiration for user ID: %d, subscription ID: %d": "Håndterer udløb for bruger-ID: %d, abonnements-ID: %d",
    'Removed role "%s" from user ID: %d': 'Fjernede rolle "%s" fra bruger-ID: %d',
    'Role removal disabled. Role "%s" retained for user ID: %d': 'Rolle fjernelse deaktiveret. Rolle "%s" beholdt for bruger-ID: %d',
    'Status changed for subscription ID: %d (User: %d) from "%s" to "%s"': 'Status ændret for abonnements-ID: %d (Bruger: %d) fra "%s" til "%s"',
    
    # Renewal order messages
    "Attempting to create renewal order for subscription ID: %d (User: %d)": "Forsøger at oprette fornyelsesordre for abonnements-ID: %d (Bruger: %d)",
    "Created renewal order #%d for subscription ID: %d": "Oprettede fornyelsesordre #%d for abonnements-ID: %d",
    "Automatic Renewal - %s": "Automatisk fornyelse - %s",
    "Automatic renewal order for membership subscription ID: %d": "Automatisk fornyelsesordre for medlemskabsabonnement ID: %d",
    "Awaiting automatic payment processing.": "Afventer automatisk betalingsbehandling.",
    "Action Required: Membership Renewal Failed": "Handling påkrævet: Medlemskabsfornyelse mislykkedes",
    
    # Validation additional
    "Data Mismatches": "Data uoverensstemmelser",
    "Cleanup failed. Please check the logs for more details.": "Oprydning mislykkedes. Tjek venligst logs for flere detaljer.",
    
    # Settings page - long descriptions
    "If enabled, the member role will be removed when the membership expires. Users will revert to the default WordPress role.": "Hvis aktiveret, vil medlemsrollen blive fjernet når medlemskabet udløber. Brugere vil vende tilbage til standard WordPress-rollen.",
    "If enabled, the system will attempt to charge the customer's saved payment method on renewal.": "Hvis aktiveret, vil systemet forsøge at debitere kundens gemte betalingsmetode ved fornyelse.",
    "Optional description shown on the product page about what this membership includes.": "Valgfri beskrivelse vist på produktsiden om hvad dette medlemskab inkluderer.",
    'The name that appears in the "From" field of emails.': 'Navnet der vises i "Fra" feltet i e-mails.',
    'The email address that appears in the "From" field.': 'E-mailadressen der vises i "Fra" feltet.',
    
    # Additional renewal/failed notifications
    "Failed Membership Renewal - Admin Notification": "Mislykket medlemskabsfornyelse - Admin notifikation",
    "Action Required: Membership Renewal Failed": "Handling påkrævet: Medlemskabsfornyelse mislykkedes",
    "A membership renewal has failed.<br><br>Subscription ID: %d<br>User: %s (ID: %d)<br>Email: %s<br>Order ID: %s<br>Reason: %s<br><br>Please take appropriate action.": "En medlemskabsfornyelse er mislykkedes.<br><br>Abonnements-ID: %d<br>Bruger: %s (ID: %d)<br>E-mail: %s<br>Ordre-ID: %s<br>Årsag: %s<br><br>Tag venligst passende handling.",
    "Failed to create automatic renewal order for subscription ID: %d": "Kunne ikke oprette automatisk fornyelsesordre for abonnements-ID: %d",
    "Failed to create order: %s": "Kunne ikke oprette ordre: %s",
    "Failed to create renewal order for subscription ID %d. Check the logs below for details.": "Kunne ikke oprette fornyelsesordre for abonnements-ID %d. Tjek logs nedenfor for detaljer.",
    "Exception creating renewal order: %s": "Undtagelse ved oprettelse af fornyelsesordre: %s",
    
    # Test tools
    "Generate Missing Tokens": "Generer manglende tokens",
    "Fix Invalid Dates": "Ret ugyldige datoer",
    "Count": "Antal",
    "%d days until expiry": "%d dage til udløb",
    "1 Day Before Expiration": "1 dag før udløb",
    "7 Days Before Expiration": "7 dage før udløb",
    "14 Days Before Expiration": "14 dage før udløb",
    "30 Days Before Expiration": "30 dage før udløb",
    "Expiry Date:": "Udløbsdato:",
    
    # Last 3 missing strings
    "Membership Renewal Reminder": "Medlemskabsfornyelsespåmindelse",
    "No automatic renewal products configured. Cannot create renewal order for subscription ID: %d": "Ingen automatiske fornyelsesprodukter konfigureret. Kan ikke oprette fornyelsesordre for abonnements-ID: %d",
    "Product ID %d not found. Cannot create renewal order for subscription ID: %d": "Produkt-ID %d ikke fundet. Kan ikke oprette fornyelsesordre for abonnements-ID: %d",
    
    # Final 4 missing English strings
    "Failed automatic renewal for subscription ID: %d. Reason: %s. Status set to pending-cancel.": "Automatisk fornyelse mislykkedes for abonnements-ID: %d. Årsag: %s. Status sat til afventer-annullering.",
    "Hi %s,<br><br>Your membership renewal order has been created but requires payment.<br><br>Please complete the payment here: %s<br><br>Order Details:<br>Order #%d<br>Amount: %s<br><br>Thank you!": "Hej %s,<br><br>Din medlemskabsfornyelsesordre er oprettet, men kræver betaling.<br><br>Venligst gennemfør betalingen her: %s<br><br>Ordredetaljer:<br>Ordre #%d<br>Beløb: %s<br><br>Tak!",
    "Hi %s,<br><br>We were unable to automatically renew your membership.<br><br>Please update your payment method and complete the renewal here: %s<br><br>If you have any questions, please contact us.<br><br>Thank you!": "Hej %s,<br><br>Vi kunne ikke automatisk forny dit medlemskab.<br><br>Venligst opdater din betalingsmetode og gennemfør fornyelsen her: %s<br><br>Hvis du har spørgsmål, kontakt os venligst.<br><br>Tak!",
    "Renewal order already exists for subscription ID: %d today (Order #%d)": "Fornyelsesordre eksisterer allerede for abonnements-ID: %d i dag (Ordre #%d)",
    "This membership is not set for automatic renewal. Check \"Force Renewal\" to test anyway.": "Dette medlemskab er ikke sat til automatisk fornyelse. Marker \"Gennemtving fornyelse\" for at teste alligevel.",
    
    # Final 5 test tools strings
    "Successfully sent %d test reminder email(s) to %s. Check your inbox and spam folder.": "Sendte succesfuldt %d test påmindelses-e-mail(s) til %s. Tjek din indbakke og spam-mappe.",
    "Successfully ran the full renewal process. Check the logs below for details.": "Kørte succesfuldt den fulde fornyelsesproces. Tjek logs nedenfor for detaljer.",
    "This will process all active memberships and send reminder emails where applicable. Continue?": "Dette vil behandle alle aktive medlemskaber og sende påmindelses-e-mails hvor relevant. Fortsæt?",
    "Migration failed. Please check the logs for more details.": "Migration mislykkedes. Tjek venligst logs for flere detaljer.",
    "No issues found! All membership data is consistent with WooCommerce orders.": "Ingen problemer fundet! Alle medlemskabsdata er konsistente med WooCommerce-ordrer.",
    
    # Final 10 remaining English strings
    "Sent failed renewal email to: %s": "Sendte mislykket fornyelsese-mail til: %s",
    "Successfully created automatic renewal order #%d for subscription ID: %d": "Oprettede succesfuldt automatisk fornyelsesordre #%d for abonnements-ID: %d",
    "Test automatic renewal successful. Created order #%d": "Test automatisk fornyelse vellykket. Oprettede ordre #%d",
    "Test automatic renewal failed for subscription ID: %d": "Test automatisk fornyelse mislykkedes for abonnements-ID: %d",
    "Migration failed with error: %s": "Migration mislykkedes med fejl: %s",
    "Successfully created test renewal order #%d for subscription ID %d. <a href=\"%s\" target=\"_blank\">View Order</a>": "Oprettede succesfuldt test fornyelsesordre #%d for abonnements-ID %d. <a href=\"%s\" target=\"_blank\">Se ordre</a>",
    "Migration completed successfully! %d subscriptions migrated.": "Migration fuldført succesfuldt! %d abonnementer migreret.",
    "Invalid dates cleanup completed successfully!": "Oprydning af ugyldige datoer fuldført succesfuldt!",
    "Validation completed successfully!": "Validering fuldført succesfuldt!",
    "Run Validation Check": "Kør valideringstjek",
    
    # Final 8 technical log messages
    "[STAGING MODE] Renewal blocked for subscription ID: %d (User: %d)": "[STAGING MODE] Fornyelse blokeret for abonnements-ID: %d (Bruger: %d)",
    "No payment token found for user %d. Manual payment required for order #%d": "Intet betalingstoken fundet for bruger %d. Manuel betaling påkrævet for ordre #%d",
    "No saved payment methods for user %d. Manual payment required for order #%d": "Ingen gemte betalingsmetoder for bruger %d. Manuel betaling påkrævet for ordre #%d",
    "Sent payment required email to: %s for order #%d": "Sendte betaling påkrævet e-mail til: %s for ordre #%d",
    "Invalid email address.": "Ugyldig e-mailadresse.",
    "Starting test reminder email process. Target: %s, Type: %s, Renewal: %s": "Starter test påmindelses-e-mail proces. Mål: %s, Type: %s, Fornyelse: %s",
    "Sent test email: %s to %s": "Sendte test e-mail: %s til %s",
    "Test reminder email process completed. Sent %d emails.": "Test påmindelses-e-mail proces fuldført. Sendte %d e-mails.",
    
    # New fix data issues strings
    "Problemer fundet (%d)": "Problemer fundet (%d)",
    "%d Fejl": "%d Fejl",
    "%d Advarsler": "%d Advarsler",
    "%d Info": "%d Info",
    "Dette vil automatisk rette simple dataproblemer (manglende ordre-links). Vil du fortsætte?": "Dette vil automatisk rette simple dataproblemer (manglende ordre-links). Vil du fortsætte?",
    "Ret dataproblemer": "Ret dataproblemer",
    "Denne validering er skrivebeskyttet og vil ikke ændre nogen data. Den rapporterer kun uoverensstemmelser til manuel gennemgang.": "Denne validering er skrivebeskyttet og vil ikke ændre nogen data. Den rapporterer kun uoverensstemmelser til manuel gennemgang.",
    "Nonce verificering mislykkedes for reparation.": "Nonce verificering mislykkedes for reparation.",
    "Starter automatisk reparation af medlemskabsdata.": "Starter automatisk reparation af medlemskabsdata.",
    "Ingen medlemskabsprodukter konfigureret.": "Ingen medlemskabsprodukter konfigureret.",
    "Linkede ordre #%d til medlemskab #%d": "Linkede ordre #%d til medlemskab #%d",
    "Rettede manglende ordre-link: Ordre #%d → Medlemskab #%d": "Rettede manglende ordre-link: Ordre #%d → Medlemskab #%d",
    "Reparation fuldført: %d problemer rettet (%d ordrer linket)": "Reparation fuldført: %d problemer rettet (%d ordrer linket)",
    "Reparation mislykkedes med fejl: %s": "Reparation mislykkedes med fejl: %s",
    "Reparation fuldført! %d problemer rettet.": "Reparation fuldført! %d problemer rettet.",
    "Ingen problemer fundet der kunne rettes automatisk.": "Ingen problemer fundet der kunne rettes automatisk.",
    "Reparation mislykkedes. Tjek logs for detaljer.": "Reparation mislykkedes. Tjek logs for detaljer.",
    "Reparationsdetaljer": "Reparationsdetaljer",
    "Total rettelser: %d": "Total rettelser: %d",
    "Ordrer linket: %d": "Ordrer linket: %d",
    
    # Extended fix functionality strings
    "Linkede ordre #%d til eksisterende medlemskab #%d": "Linkede ordre #%d til eksisterende medlemskab #%d",
    "Oprettede nyt medlemskab #%d for ordre #%d (bruger #%d)": "Oprettede nyt medlemskab #%d for ordre #%d (bruger #%d)",
    "Oprettede manglende medlemskab #%d fra ordre #%d": "Oprettede manglende medlemskab #%d fra ordre #%d",
    "Kunne ikke oprette medlemskab for ordre #%d": "Kunne ikke oprette medlemskab for ordre #%d",
    "Reparation fuldført: %d problemer rettet (%d ordrer linket, %d medlemskaber oprettet)": "Reparation fuldført: %d problemer rettet (%d ordrer linket, %d medlemskaber oprettet)",
    "Medlemskaber oprettet: %d": "Medlemskaber oprettet: %d",
}

def find_translatable_strings(content):
    """Find all __( and _e( function calls in PHP content"""
    # Pattern to match __( 'text', 'domain' ) and _e( 'text', 'domain' )
    pattern = r"(__\(|_e\()\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]membership-manager['\"]\s*\)"
    matches = re.finditer(pattern, content)
    return [(m.group(0), m.group(2)) for m in matches]

def translate_content(content):
    """Replace English strings with Danish translations"""
    changes = 0
    for english, danish in TRANSLATIONS.items():
        # Escape special regex characters
        english_escaped = re.escape(english)
        danish_escaped = danish.replace('\\', '\\\\').replace("'", "\\'")
        
        # Replace in __( function
        pattern1 = rf"__\(\s*['\"]{ english_escaped}['\"]\s*,\s*['\"]membership-manager['\"]\s*\)"
        replacement1 = f"__( '{danish_escaped}', 'membership-manager' )"
        content, n1 = re.subn(pattern1, replacement1, content)
        
        # Replace in _e( function
        pattern2 = rf"_e\(\s*['\"]{ english_escaped}['\"]\s*,\s*['\"]membership-manager['\"]\s*\)"
        replacement2 = f"_e( '{danish_escaped}', 'membership-manager' )"
        content, n2 = re.subn(pattern2, replacement2, content)
        
        changes += n1 + n2
        
    return content, changes

def process_file(filepath):
    """Process a single PHP file"""
    with open(filepath, 'r', encoding='utf-8') as f:
        original = f.read()
    
    translated, changes = translate_content(original)
    
    if changes > 0:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(translated)
        print(f"✓ {filepath}: {changes} oversættelser")
        return changes
    else:
        print(f"- {filepath}: ingen ændringer")
        return 0

def main():
    base_dir = "/Users/jacobthygesen/Sites/dianalund/wp-content/plugins/wordpress-membership-plugin"
    
    # Directories to process
    dirs_to_process = [
        os.path.join(base_dir, "includes"),
        os.path.join(base_dir, "admin/views"),
    ]
    
    total_changes = 0
    total_files = 0
    
    for directory in dirs_to_process:
        if not os.path.exists(directory):
            print(f"⚠ Directory not found: {directory}")
            continue
            
        print(f"\n📁 Processing {directory}...")
        for root, dirs, files in os.walk(directory):
            for file in files:
                if file.endswith('.php'):
                    filepath = os.path.join(root, file)
                    changes = process_file(filepath)
                    total_changes += changes
                    total_files += 1
    
    print(f"\n{'='*60}")
    print(f"✅ Færdig! {total_changes} oversættelser i {total_files} filer")
    print(f"{'='*60}")

if __name__ == "__main__":
    main()
