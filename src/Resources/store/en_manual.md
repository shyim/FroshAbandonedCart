## Installation

1. Install the plugin via Composer:
   ```bash
   composer require frosh/abandoned-cart
   ```

2. Refresh and activate:
   ```bash
   bin/console plugin:refresh
   bin/console plugin:install --activate FroshAbandonedCart
   bin/console cache:clear
   ```

## Configuration

### Scheduled Tasks

The plugin uses scheduled tasks that run automatically. Ensure your cron is configured:

```
* * * * * /path/to/shop/bin/console scheduled-task:run --no-wait
```

### Creating Your First Automation

1. Navigate to **Customers > Abandoned Carts > Automations**
2. Click **Create Automation**
3. Add conditions (e.g., Cart Age >= 24 hours)
4. Add actions (e.g., Send Email)
5. Use **Test Conditions** to verify
6. Activate the automation

### Mail Templates

Create mail templates in **Settings > Email templates** for use with the "Send Email" action. Available variables:

- `{{ customer }}` - Customer data
- `{{ abandonedCart }}` - Cart information
- `{{ lineItems }}` - Cart items
- `{{ voucherCode }}` - Generated voucher (if applicable)
