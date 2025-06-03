# Order Fulfillment Prototype

This is a prototype module for handling order fulfillment logic in a Laravel eCommerce project.

## Features

- Chooses the best supplier to fulfill an order based on stock and cost
- Supports:
  - Full fulfillment by one supplier
  - Split fulfillment between multiple suppliers
  - Preferred supplier logic (within £1 of cheapest)
- Rejects unfulfillable orders with clear reasons (e.g., missing parts, long address lines, comments present)
- Simple admin view with a button to batch process orders
- PHPUnit test coverage for key fulfillment scenarios

## Admin View

Visit `/admin/fulfillment` to:
- See a list of pending orders
- Run fulfillment logic
- View structured output for each order

## Manual Review (Built-In)

Orders that require manual intervention are clearly marked with reasons:
- Customer comments
- Invalid address fields
- No matching supplier stock

These can be edited and reprocessed from this screen in the future — no need for a separate manual workflow unless you prefer it.

## Not Included (yet)

- Integration with QuickBooks (can be added using existing plugin or SDK)
- Actual order status updates or database writes — this is a logic preview only

## To Do (or Future Ideas)

- Allow inline editing of unfulfillable orders in the admin view
- Hook into actual order flow
- Add error handling and logging
- Convert logic to use QuickBooks SDK for sales receipt creation

## Contact

Created by Stuart as a proof of concept.