#!/usr/bin/env python3
"""
Helper script til at oversætte PHP tekststrenge fra engelsk til dansk
"""

# Ordbog med oversættelser
translations = {
    # Generelle termer
    "Membership": "Medlemskab",
    "Memberships": "Medlemskaber",
    "Add New": "Tilføj ny",
    "Add New Membership": "Tilføj nyt medlemskab",
    "User ID": "Bruger-ID",
    "Start Date": "Startdato",
    "End Date": "Slutdato",
    "Status": "Status",
    "Active": "Aktiv",
    "Expired": "Udløbet",
    "Pending Cancel": "Afventer annullering",
    "Cancelled": "Annulleret",
    "On Hold": "På hold",
    "Total": "I alt",
    "Renewal Type": "Fornyelsestype",
    "Manual": "Manuel",
    "Automatic": "Automatisk",
    "Actions": "Handlinger",
    "View": "Vis",
    "Edit": "Rediger",
    "Delete": "Slet",
    "Filter": "Filtrer",
    "All Statuses": "Alle statusser",
    
    # Membership detaljer
    "Membership Details": "Medlemskabsdetaljer",
    "Membership Information": "Medlemskabsinformation",
    "User Information": "Brugerinformation",
    "Order History": "Ordrehistorik",
    "Billing Information": "Faktureringsinformation",
    "Billing Address": "Faktureringsadresse",
    
    # Knapper og handlinger
    "Update Membership": "Opdater medlemskab",
    "Add Membership": "Tilføj medlemskab",
    "Pause Membership": "Pause medlemskab",
    "Resume Membership": "Genoptag medlemskab",
    "Delete Membership": "Slet medlemskab",
    "Renew Membership": "Forny medlemskab",
    "Copy Renewal Link": "Kopier fornyelseslink",
    "Renew Now": "Forny nu",
    "Renew now": "Forny nu",
    
    # Indstillinger
    "Membership Settings": "Medlemskabsindstillinger",
    "Settings": "Indstillinger",
    "Automatic Renewal": "Automatisk fornyelse",
    "Manual Renewal": "Manuel fornyelse",
    "Products": "Produkter",
    "Add Product": "Tilføj produkt",
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
    "Send Test Email": "Send test e-mail",
    "Sending...": "Sender...",
    
    # Beskrivelser
    "Enter the WordPress User ID for the member.": "Indtast WordPress bruger-ID for medlemmet.",
    "Leave empty for no expiration.": "Lad stå tomt for ingen udløbsdato.",
    "Products that will automatically renew memberships on expiration.": "Produkter der automatisk fornyer medlemskaber ved udløb.",
    "Products that require manual renewal by members.": "Produkter der kræver manuel fornyelse af medlemmer.",
    "WordPress role to assign to members with active memberships.": "WordPress rolle der tildeles medlemmer med aktive medlemskaber.",
    "Automatically remove member role when membership expires": "Fjern automatisk medlemsrolle når medlemskabet udløber",
    "If enabled, the member role will be removed when the membership expires. Users will revert to the default WordPress role.": "Hvis aktiveret, vil medlemsrollen blive fjernet når medlemskabet udløber. Brugere vil vende tilbage til standard WordPress-rollen.",
    "Send automatic email reminders before membership expiration": "Send automatiske e-mailpåmindelser før medlemskabet udløber",
    "Emails will be sent 30, 14, 7, and 1 day before expiration.": "E-mails sendes 30, 14, 7 og 1 dag før udløb.",
    "The name that appears in the \\\"From\\\" field of emails.": "Navnet der vises i \\\"Fra\\\" feltet i e-mails.",
    "The email address that appears in the \\\"From\\\" field.": "E-mailadressen der vises i \\\"Fra\\\" feltet.",
    "Send a test reminder email to verify your settings.": "Send en test påmindelses-e-mail for at verificere dine indstillinger.",
    
    # Standard beskeder
    "Your membership will expire in 30 days": "Dit medlemskab udløber om 30 dage",
    "Your membership will expire in 14 days": "Dit medlemskab udløber om 14 dage",
    "Your membership will expire in 7 days": "Dit medlemskab udløber om 7 dage",
    "Your membership will expire tomorrow": "Dit medlemskab udløber i morgen",
    
    # Datorer og felter
    "Membership ID": "Medlemskabs-ID",
    "Start date:": "Startdato:",
    "Expiry Date": "Udløbsdato",
    "Expiration Date": "Udløbsdato",
    "Expires": "Udløber",
    "End date:": "Slutdato:",
    "Status Changed": "Status ændret",
    "Paused Date": "Pausedato",
    
    # Brugerinformation
    "Name": "Navn",
    "Email": "E-mail",
    "Username": "Brugernavn",
    "Registration Date": "Registreringsdato",
    "Full Name": "Fulde navn",
    
    # Ordre historie
    "Order #": "Ordre #",
    "Date": "Dato",
    "Payment Method": "Betalingsmetode",
    "Showing 10 of %d orders": "Viser 10 af %d ordrer",
    
    # Advarsler og beskeder
    "Are you sure?": "Er du sikker?",
    "Are you sure you want to pause this membership?": "Er du sikker på, at du vil pause dette medlemskab?",
    "Are you sure you want to resume this membership?": "Er du sikker på, at du vil genoptage dette medlemskab?",
    "Are you sure you want to delete this membership? This cannot be undone.": "Er du sikker på, at du vil slette dette medlemskab? Dette kan ikke fortrydes.",
    "Invalid membership ID.": "Ugyldigt medlemskabs-ID.",
    "Membership not found.": "Medlemskab ikke fundet.",
    "User not found.": "Bruger ikke fundet.",
    "Membership updated successfully!": "Medlemskab opdateret!",
    "Membership paused successfully!": "Medlemskab pauseret!",
    "Membership resumed successfully!": "Medlemskab genoptaget!",
    "Membership created successfully!": "Medlemskab oprettet!",
    "Renewal link copied to clipboard!": "Fornyelseslink kopieret til udklipsholder!",
    "No memberships found.": "Ingen medlemskaber fundet.",
    
    # Ekspirerende denne uge
    "Expiring This Week": "Udløber denne uge",
    "User": "Bruger",
    
    # Dashboard
    "Membership Status": "Medlemskabsstatus",
    "Membership Issues & Alerts": "Medlemskabsproblemer og advarsler",
    
    # Shortcodes
    "My Membership": "Mit medlemskab",
    "Status:": "Status:",
    "Start Date:": "Startdato:",
    "No active membership found.": "Intet aktivt medlemskab fundet.",
    "You must be logged in to view this content.": "Du skal være logget ind for at se dette indhold.",
    "This content is restricted to active members.": "Dette indhold er forbeholdt aktive medlemmer.",
    
    # Checkout
    "Your Membership": "Dit medlemskab",
    "Automatic - Will renew automatically": "Automatisk - Fornyes automatisk",
    "Manual - You will receive renewal reminders": "Manuel - Du vil modtage fornyelsespåmindelser",
    "Renewal Link": "Fornyelseslink",
    "Renewal Link:": "Fornyelseslink:",
    "Click here to renew": "Klik her for at forny",
    "YOUR MEMBERSHIP": "DIT MEDLEMSKAB",
    
    # Product types
    "Membership (Auto-Renewal)": "Medlemskab (Auto-fornyelse)",
    "Membership (Manual)": "Medlemskab (Manuel)",
    "Membership Duration": "Medlemskabsvarighed",
    "This membership will be valid for 1 year from purchase date.": "Dette medlemskab er gyldigt i 1 år fra købsdatoen.",
    "Attempt automatic payment on renewal": "Forsøg automatisk betaling ved fornyelse",
    "If enabled, the system will attempt to charge the customer's saved payment method on renewal.": "Hvis aktiveret, vil systemet forsøge at debitere kundens gemte betalingsmetode ved fornyelse.",
    "Membership Description": "Medlemskabsbeskrivelse",
    "Optional description shown on the product page about what this membership includes.": "Valgfri beskrivelse vist på produktsiden om hvad dette medlemskab inkluderer.",
    
    # Roles og log beskeder
    "Back to List": "Tilbage til liste",
}

# Udskriv ordbog til verificering
if __name__ == "__main__":
    print(f"Translation dictionary contains {len(translations)} entries")
    for en, da in sorted(translations.items()):
        print(f'"{en}" => "{da}"')
