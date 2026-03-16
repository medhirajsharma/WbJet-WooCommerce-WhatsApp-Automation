=== WBWWA for WooCommerce ===  
Contributors: Dhiraj Sharma
Tags: WhatsApp, WooCommerce, Order Alerts, Chat Support, Messaging Widget  
Requires at least: 4.7  
Tested up to: 6.7.1  
Stable tag: 1.0  
Requires PHP: 8.2  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Deliver WhatsApp order alerts and offer real-time chat assistance on your WooCommerce store using WBWWA.

== Description ==

WBWWA for WooCommerce bridges the gap between store owners and customers by sending instant WhatsApp notifications for order updates and embedding a live chat widget directly on your site—making customer support quick, easy, and accessible.

== Core Features ==

- **Automated Order Status Notifications**  
  Send instant alerts when an order is Placed, Shipped, Processing, or Completed. Fully compatible with all native WooCommerce statuses.

- **Abandoned Cart Recovery (Sequence)**  
  Automatically follow up with customers who left items in their cart. Set multiple reminders (e.g., 1 hour, 12 hours, 24 hours) to boost conversion.

- **Post-Purchase Follow-ups (Sequence)**  
  Request reviews or offer discount coupons automatically after an order is marked "Completed".

- **Admin/Business Notifications**  
  Keep your team updated. Receive real-time WhatsApp alerts on your business phone whenever a new order is placed or status changes.

- **Interactive WhatsApp Buttons**  
  Use WhatsApp's native interactive buttons (Confirm/Cancel). Perfect for Cash on Delivery (COD) verification.

- **Real-Time WhatsApp Chat Widget**  
  Embedded chat bubble with customizable position, pre-filled messages, and "online" indicators to improve customer trust.

- **Dynamic Personalization (Variable Mapping)**  
  Map WooCommerce data fields (Customer Name, Order Total, Tracking URL, Items List) to your WhatsApp template variables.

== Configuration & Settings ==

Access these options under **WhatsApp Automation > Settings**:

- **API Business Setup**: Securely connect your store using your wbjet.com API Key.
- **Global Country Code**: Set a default country code for numbers without a '+' prefix.
- **Business Notification Settings**: Enable/Disable admin alerts and set the receiver phone number.
- **Abandoned Cart Settings**: Enable the feature and set the inactivity timeout (in minutes).
- **Chat Widget Customization**:
    - **Position**: Place the widget on the bottom-right or bottom-left.
    - **Custom Message**: Set a default starting message for customers.
    - **Specific Phone**: Use a different number for support queries than your main business number.
- **Opt-in Management**: Add a GDPR-compliant opt-in checkbox to the checkout page.

== Automation Triggers & Sequences ==

Under **WhatsApp Automation > Triggers**, you can configure:

1. **Standard Triggers**: Single messages sent exactly when a status changes.
2. **Abandoned Cart Reminders**: Multi-message sequences triggered by incomplete sessions.
3. **Post-Purchase Sequences**: Multi-message sequences triggered after successful completion.

== Webhook Setup (Interactive Replies) ==

To handle button clicks (Confirm/Cancel) from customers, configure your webhook in **wbjet.com**:

- **URL**: `https://your-domain.com/wp-json/wbwwa/v1/webhook`
- **Required Event**: `message.received`

== Available Personalization Tags ==

You can map these tags to your WhatsApp templates:
- `{{order_id}}`, `{{order_total}}`, `{{customer_name}}`
- `{{billing_first_name}}`, `{{billing_last_name}}`, `{{shipping_address}}`
- `{{payment_method}}`, `{{order_status}}`, `{{order_items}}`
- `{{order_date}}`, `{{tracking_number}}`, `{{tracking_url}}`

== Installation ==

1. Upload the plugin to your `/wp-content/plugins/` folder.
2. Activate and navigate to **WhatsApp Automation**.
3. Enter your API Key from [wbjet.com](https://app.wbjet.com/).
4. Create your first Trigger and you're good to go!


== Changelog ==

= 1.0.1 =
- Added Abandoned Cart Sequence.
- Added Post-Purchase Review Sequence.
- Added Webhook Receiver for interactive button replies.
- Added Safety checks for automatic order confirmation.

= 1.0 =
- Initial release: WhatsApp notifications + real-time site chat widget.
