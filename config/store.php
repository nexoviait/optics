<?php

/*
|--------------------------------------------------------------------------
| Store domain constants
|--------------------------------------------------------------------------
|
| Values that are part of the business rules (validation patterns, workflow
| states). Editable store data (prices, zones, copy) lives in the database
| and is managed from the admin console.
|
*/

return [
    // Set when behind Cloudflare or a load balancer: '*' or comma-separated proxy IPs. Empty = no proxy.
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Optional CDN base for product media (e.g. https://cdn.example.com). Empty = serve from this app.
    'media_url' => env('MEDIA_URL'),

    // Responsive widths generated for every gallery photo (WebP + JPEG), plus a square thumbnail.
    'image_widths' => [1080, 720, 480],
    'image_thumb' => 200,

    // Product page gallery on desktop: 'grid' (first photo full width, rest in 2 columns) or 'thumbnails' (main photo + strip).
    'gallery_layout' => env('GALLERY_LAYOUT', 'grid'),

    'categories' => [
        'eyeglasses' => 'Eyeglasses',
        'sunglasses' => 'Sunglasses',
    ],

    'genders' => [
        'men' => 'Men',
        'women' => 'Women',
        'unisex' => 'Unisex',
        'kids' => 'Kids',
    ],

    'frame_types' => [
        'full_rim' => 'Full Rim',
        'half_rim' => 'Half Rim',
        'rimless' => 'Rimless',
    ],

    'catalog_sorts' => [
        'featured' => 'Featured',
        'popular' => 'Most popular',
        'newest' => 'New arrivals',
        'price_asc' => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'rating' => 'Top rated',
    ],

    'footer_groups' => [
        'help' => 'Help',
        'company' => 'Company',
        'legal' => 'Legal',
    ],

    'social_networks' => ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube', 'x' => 'X'],

    'order_statuses' => ['New', 'Processing', 'Ready to Ship', 'Shipped', 'Delivered', 'Cancelled'],

    // The linear fulfilment pipeline an order is advanced through.
    'order_pipeline' => ['New', 'Processing', 'Ready to Ship', 'Shipped', 'Delivered'],

    // Customer-facing wording for each order status (confirmation, tracking, emails).
    'order_status_copy' => [
        'New' => ['Order placed', 'We have received your order and payment details.'],
        'Processing' => ['In the lab', 'An optician has verified your prescription and your lenses are being surfaced.'],
        'Ready to Ship' => ['Quality checked', 'Your eyewear passed final inspection and is packed for dispatch.'],
        'Shipped' => ['Shipped', 'Your parcel is with the courier.'],
        'Delivered' => ['Delivered', 'Your eyewear has been delivered. Enjoy the view.'],
        'Cancelled' => ['Cancelled', 'This order was cancelled. Any payment taken is refunded to the original method.'],
    ],

    // Email template hook sent when an order reaches each status.
    'order_status_hooks' => [
        'New' => 'order.placed',
        'Processing' => 'optometrist.approved',
        'Ready to Ship' => 'lab.surfacing.complete',
        'Shipped' => 'shipment.created',
        'Delivered' => 'order.delivered',
        'Cancelled' => 'order.cancelled',
    ],

    // Couriers offered when shipping an order; {number} is replaced with the tracking number.
    'carriers' => [
        'DHL Express' => 'https://www.dhl.com/global-en/home/tracking/tracking-express.html?tracking-id={number}',
        'UPS' => 'https://www.ups.com/track?tracknum={number}',
        'FedEx' => 'https://www.fedex.com/fedextrack/?trknbr={number}',
        'Swiss Post' => 'https://service.post.ch/ekp-web/ui/entry/search/{number}',
        'Royal Mail' => 'https://www.royalmail.com/track-your-item#/tracking-results/{number}',
        'USPS' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels={number}',
        'Other' => null,
    ],

    // How long the signed tracking links in order emails stay valid.
    'tracking_link_days' => 60,

    'payment_statuses' => ['Succeeded', 'Pending', 'Refunded', 'Failed'],

    // Concierge (staff-placed) payment methods and their initial payment status.
    // Storefront checkout methods are managed in the admin (payment_methods table).
    'payment_methods' => [
        'phone' => ['label' => 'Card over phone', 'status' => 'Succeeded'],
        'link' => ['label' => 'Payment link via email', 'status' => 'Pending'],
        'cod' => ['label' => 'Cash on Delivery', 'status' => 'Pending'],
        'boutique' => ['label' => 'Boutique terminal', 'status' => 'Succeeded'],
    ],

    // Checkout behaviour per payment method type.
    'payment_method_types' => [
        'card' => 'Card (collects card details)',
        'wallet' => 'Digital wallet',
        'installment' => 'Pay in 4 installments',
        'mobile' => 'Mobile banking (wallet number + transaction ID)',
        'offline' => 'Offline / pay on delivery',
    ],

    'tiers' => ['Sage Member', 'Champagne Circle', 'Obsidian Circle'],

    'rx_modes' => ['later', 'manual', 'upload'],

    // Card processing fee used for payout reconciliation.
    'processing_fee' => ['percent' => 2.9, 'fixed' => 0.30],

    'dispatch_business_days' => 2,

    // Postal-code validation per destination country.
    'delivery_regions' => [
        'CH' => ['name' => 'Switzerland', 'pattern' => '/^\d{4}$/'],
        'IN' => ['name' => 'India', 'pattern' => '/^[1-9]\d{5}$/'],
        'US' => ['name' => 'United States', 'pattern' => '/^\d{5}(-\d{4})?$/'],
        'GB' => ['name' => 'United Kingdom', 'pattern' => '/^[A-Z]{1,2}\d[A-Z\d]? ?\d[A-Z]{2}$/'],
        'DE' => ['name' => 'Germany', 'pattern' => '/^\d{5}$/'],
        'FR' => ['name' => 'France', 'pattern' => '/^\d{5}$/'],
        'BD' => ['name' => 'Bangladesh', 'pattern' => '/^\d{4}$/'],
        'OTHER' => ['name' => 'Other country — estimate unavailable', 'pattern' => '/^.{1,12}$/'],
    ],

    'email_block_types' => ['Logo', 'Heading', 'Paragraph', 'CTA Button', 'Order Summary', 'Lens Specs', 'Divider', 'Image', 'Footer'],

    'email_tokens' => ['{{customer.first_name}}', '{{order.id}}', '{{lens.index}}', '{{tracking.url}}'],

    'email_categories' => ['Transactional', 'Clinical', 'Operational', 'Logistics'],

    'lens_coatings' => ['Hard coat', 'Anti-reflective', 'Blue 420nm', 'Hydrophobic', 'Oleophobic'],

    'lens_labs' => ['Zürich', 'Paris', 'Milan', 'Tokyo'],

    'lens_substrates' => ['CR-39', 'MR-8', 'MR-174', 'Crown Glass', 'Polycarbonate'],

    /*
    | Bootstrap administrator created by the database seeder.
    */
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@refra.com'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'fit_advisor' => [
        'model' => env('FIT_ADVISOR_MODEL', 'claude-opus-5-5'),
        'max_tokens' => 4000,
        // Seconds per attempt (one retry) before the rule-based matcher answers instead.
        'timeout' => (float) env('FIT_ADVISOR_TIMEOUT', 20),
    ],
    /*
    | Minutes of inactivity after which an admin session is signed out
    | (0 disables). Kept shorter than the storefront session lifetime.
    */
    'admin_idle_minutes' => (int) env('ADMIN_IDLE_MINUTES', 30),

];
