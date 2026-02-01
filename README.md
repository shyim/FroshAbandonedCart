# Frosh Abandoned Cart

A Shopware 6 plugin that captures abandoned shopping carts and provides powerful automation capabilities to recover lost sales.

## Features

### Cart Recovery
- **Automatic Cart Archiving**: Captures shopping carts that have been inactive for a configurable period
- **Cart Restoration**: Automatically restores abandoned carts when customers log back in
- **Line Item Preservation**: Stores complete cart contents including quantities and prices

### Automation System
Create sophisticated automation rules to engage customers with abandoned carts:

#### Conditions
- **Cart Age**: Trigger based on how long a cart has been abandoned (hours/days)
- **Cart Value**: Filter by minimum or maximum cart value
- **Automation Count**: Limit how many times a customer receives automated outreach
- **Time Since Last Automation**: Ensure appropriate spacing between communications
- **Customer Tag**: Target or exclude customers based on tags
- **Line Item Count**: Filter by number of items in cart

#### Actions
- **Send Email**: Send personalized emails using Shopware mail templates
- **Generate Voucher**: Create individual promotion codes to incentivize purchase completion
- **Add/Remove Customer Tag**: Segment customers based on automation triggers
- **Set Custom Field**: Store custom data on customer records

### Administration
- **Statistics Dashboard**: View abandoned cart metrics and trends over time
- **Automation Management**: Create, edit, and manage automation rules
- **Automation Testing**: Preview which customers would be affected before activating

## Requirements

- Shopware 6.7.0 or higher
- PHP 8.2 or higher

## Installation

### Via Composer (Recommended)

```bash
composer require frosh/abandoned-cart
bin/console plugin:refresh
bin/console plugin:install --activate FroshAbandonedCart
```

### Manual Installation

1. Download the plugin
2. Extract to `custom/plugins/FroshAbandonedCart`
3. Run the following commands:

```bash
bin/console plugin:refresh
bin/console plugin:install --activate FroshAbandonedCart
bin/console cache:clear
```

## Configuration

### Scheduled Tasks

The plugin uses two scheduled tasks:

1. **Cart Archiver** (`frosh_abandoned_cart.cart_archiver`): Runs hourly to archive abandoned carts
2. **Automation Processor** (`frosh_abandoned_cart.automation_processor`): Runs hourly to process automation rules

Ensure your Shopware scheduled task runner is configured:

```bash
bin/console scheduled-task:run
```

Or via cron:

```
* * * * * /path/to/shop/bin/console scheduled-task:run --no-wait
```

## Usage

### Creating an Automation

1. Navigate to **Customers > Abandoned Carts > Automations** in the Administration
2. Click **Create Automation**
3. Configure:
   - **Name**: A descriptive name for the automation
   - **Priority**: Higher priority automations are evaluated first (only one automation executes per cart)
   - **Active**: Enable/disable the automation
   - **Sales Channel**: Optionally limit to a specific sales channel
4. Add **Conditions** to define which carts should trigger this automation
5. Add **Actions** to define what happens when conditions are met
6. Use **Test Conditions** to preview affected carts before activating

### Example: First Reminder Email

```
Conditions:
- Cart Age >= 24 hours
- Automation Count = 0

Actions:
- Send Email (using "Abandoned Cart Reminder" template)
```

### Example: Follow-up with Discount

```
Conditions:
- Cart Age >= 72 hours
- Automation Count = 1
- Cart Value >= 50

Actions:
- Generate Voucher (10% discount promotion)
- Send Email (using "Abandoned Cart with Discount" template)
```

### Mail Template Variables

When using the "Send Email" action, the following variables are available in your mail templates:

| Variable | Description |
|----------|-------------|
| `customer` | The customer entity |
| `abandonedCart` | The abandoned cart entity |
| `lineItems` | Collection of line items in the cart |
| `voucherCode` | Generated voucher code (if "Generate Voucher" action was used) |
| `salesChannel` | The sales channel entity |

Example template snippet:

```twig
Hello {{ customer.firstName }},

You left some items in your cart:

{% for item in lineItems %}
- {{ item.label }} (Qty: {{ item.quantity }})
{% endfor %}

{% if voucherCode %}
Use code {{ voucherCode }} for a special discount!
{% endif %}
```

## Development

### Running Tests

```bash
# Unit tests only (fast)
./vendor/bin/phpunit -c phpunit.unit.xml

# All tests including integration
./vendor/bin/phpunit -c phpunit.xml
```

### Code Formatting

Format PHP and JavaScript files according to Shopware coding standards:

```bash
shopware-cli extension format custom/plugins/FroshAbandonedCart
```

### Validation

Validate the extension for store compatibility:

```bash
# Basic validation
shopware-cli extension validate custom/plugins/FroshAbandonedCart

# Full validation including all checks
shopware-cli extension validate --full custom/plugins/FroshAbandonedCart
```

### Project Structure

```
src/
├── Automation/
│   ├── Action/           # Action implementations
│   ├── Condition/        # Condition implementations
│   └── AutomationProcessor.php
├── Controller/
│   └── Api/              # API controllers
├── Entity/               # DAL entity definitions
├── Migration/            # Database migrations
├── Resources/
│   ├── app/
│   │   └── administration/  # Admin UI components
│   └── config/
│       └── services.xml
├── ScheduledTask/        # Scheduled task handlers
└── Subscriber/           # Event subscribers
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

## Support

- [GitHub Issues](https://github.com/FriendsOfShopware/FroshAbandonedCart/issues)
- [FriendsOfShopware](https://friendsofshopware.com)
