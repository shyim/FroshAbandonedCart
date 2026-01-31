## Installation

1. Plugin via Composer installieren:
   ```bash
   composer require frosh/abandoned-cart
   ```

2. Aktualisieren und aktivieren:
   ```bash
   bin/console plugin:refresh
   bin/console plugin:install --activate FroshAbandonedCart
   bin/console cache:clear
   ```

## Konfiguration

### Geplante Aufgaben

Das Plugin verwendet geplante Aufgaben, die automatisch ausgeführt werden. Stellen Sie sicher, dass Ihr Cron konfiguriert ist:

```
* * * * * /path/to/shop/bin/console scheduled-task:run --no-wait
```

### Erste Automation erstellen

1. Navigieren Sie zu **Kunden > Abgebrochene Warenkörbe > Automationen**
2. Klicken Sie auf **Automation erstellen**
3. Fügen Sie Bedingungen hinzu (z.B. Warenkorbalt >= 24 Stunden)
4. Fügen Sie Aktionen hinzu (z.B. E-Mail senden)
5. Nutzen Sie **Bedingungen testen** zur Überprüfung
6. Aktivieren Sie die Automation

### E-Mail-Vorlagen

Erstellen Sie E-Mail-Vorlagen unter **Einstellungen > E-Mail-Vorlagen** für die "E-Mail senden"-Aktion. Verfügbare Variablen:

- `{{ customer }}` - Kundendaten
- `{{ abandonedCart }}` - Warenkorbinformationen
- `{{ lineItems }}` - Warenkorbartikel
- `{{ voucherCode }}` - Generierter Gutschein (falls vorhanden)
