<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\PageSection;
use App\Models\VisionStep;
use App\Support\Settings;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        Settings::put([
            'store_name' => 'REFRA Atelier Optics',
            'store_tagline' => 'See beyond the ordinary',
            'store_identifier' => 'refra-atelier',
            'primary_domain' => 'refra.com',
            'concierge_email' => 'concierge@refra.com',
            'support_phone' => '+91 8470007367',
            'registered_address' => 'Rue du Rhône 42, 1204 Genève, Switzerland',
            'footer_blurb' => 'Precision physical optics & architectural eyewear ateliers engineered for human clarity.',
            'ateliers' => 'Paris · Tokyo · Zürich · New York',
            'currency' => 'USD',
            'timezone' => 'Europe/Zurich',
            'date_format' => 'd M Y',
            'order_prefix' => 'RF-',
            'member_bogo_enabled' => true,
            'free_shipping_threshold' => 300,
            'exchange_days' => 14,
            'warranty_days' => 365,
            'rx_submission_days' => 15,
            'prices_tax_inclusive' => true,
            'rx_vat_exemption' => true,
            'vat_ch' => 8.1,
            'vat_eu' => 20,
            'vat_uk' => 20,
            'rx_verification_gate' => true,
            'address_validation' => true,
            'policy_terms' => 'Terms of Haute Service — last revised Oct 2026.',
            'policy_privacy' => 'Privacy & Biometric Vault Policy — last revised Oct 2026. VisionMatch portraits are processed in the browser and never uploaded.',
            'policy_remake' => '30-Day Remake Guarantee — if your vision is not crisp and comfortable within 30 days, we re-glaze your lenses free of charge.',
            'compliance_swissmedic' => true,
            'compliance_eu_mdr' => true,
            'meta_title' => 'REFRA Atelier Optics — Luxury Precision Eyewear',
            'meta_description' => 'Precision titanium eyewear and prism sunglasses calibrated to nanometer-grade optical refraction.',
        ]);

        $sections = [
            ['key' => 'hero', 'label' => 'Hero — Bending Light', 'content' => [
                'badge' => 'Atelier Refraction 2025 Edition',
                'headline' => 'Bending Light. Elevating Vision.',
                'subheadline' => 'Precision titanium eyewear calibrated to nanometer-grade optical refraction. Crafted for discerning collectors who experience the world with total visual clarity.',
                'primary_cta' => 'Explore New Prism Collection',
                'secondary_cta' => 'Launch Virtual AR Try-On (360°)',
                'image' => 'images/hero.jpg',
                'caption_model' => 'Model: Apex 01 Titanium',
                'caption_metric' => 'REFRACTIVE INDEX: 1.74',
                'stats' => [['value' => '0.02 mm', 'label' => 'Tolerance Calibrated'], ['value' => '100% UV400', 'label' => 'Prism Polarization'], ['value' => '45,000+', 'label' => 'Precision Face Fits']],
            ]],
            ['key' => 'visionmatch', 'label' => 'VisionMatch™ teaser', 'content' => [
                'badge' => 'Proprietary biometric fitting',
                'title' => 'REFRA VISIONMATCH™',
                'lead' => 'Choose your frame that matches you the best.',
                'body' => 'Our AI scans your facial landmarks in real-time to curate your mathematically perfect optical fit.',
            ]],
            ['key' => 'flagships', 'label' => 'Optical & Prism Flagships', 'content' => [
                'eyebrow' => 'Curated atelier formulations',
                'title' => 'Optical & Prism Flagships',
                'archive_title' => 'View All Eyeglasses & Sunglasses',
                'archive_body' => 'Discover our full portfolio of precision titanium, bio-acetate, and polarized prism designs calibrated to your exact optical prescription.',
            ]],
            ['key' => 'gallery', 'label' => 'Gallery & Runway Edit', 'content' => [
                'badge' => 'Haute couture • Optical science • Milan & Paris atelier editions',
                'title' => 'The Gallery & Runway Edit: Sculptural Visions in Light',
                'body' => 'Discover limited-edition frame collaborations conceived with visionary architectural sculptors and avant-garde couture designers.',
                'cards' => [
                    ['image' => 'images/runway.jpg', 'tag' => 'REFRA × Aura Studio — Milan Fashion Week 2025', 'sub' => 'Limited to 150 pieces', 'title' => 'Aura Prismatic Sculpture 01', 'meta' => 'Edition #042/150', 'body' => 'Architectural sculpted geometric glass and precision Japanese titanium sunglasses, featuring warm prismatic reflection refraction engineered for Milan Runway showcases.', 'foot' => 'Hand-numbered Atelier Archive', 'cta' => 'View Editorial Piece', 'url' => '/sunglasses'],
                    ['image' => 'images/gallery.jpg', 'tag' => 'Paris Contemporary', 'sub' => 'Gallery art residency', 'title' => 'Architectural Prism Salon', 'meta' => '', 'body' => 'Geometric minimalist optical frames exhibited alongside light-bending kinetic sculptures, calibrated nanometer lenses responding to architectural exhibition lighting.', 'foot' => 'Palais de Tokyo • Paris', 'cta' => 'RSVP Private VIP', 'url' => '/science'],
                ],
                'facts' => [['title' => '3 Global Residencies', 'sub' => 'Milan • Paris • Tokyo'], ['title' => '6 Avant-Garde Sculptors', 'sub' => 'Curated collaborations'], ['title' => 'Bespoke Craftsmanship', 'sub' => 'Hand-polished bio-acetate & Ti'], ['title' => 'Private Exhibition', 'sub' => 'By salon invitation only']],
            ]],
            ['key' => 'science', 'label' => 'Science of Light', 'content' => [
                'eyebrow' => 'Atelier optical physics',
                'title' => 'Harnessing The Science Of Light For Better Vision',
                'body' => 'Standard eyewear filters light indiscriminately. REFRA engineers curved molecular crystal substrates that redirect harmful high-energy blue scatter while sharpening focus contrast across edge-to-edge focal zones.',
                'items' => [
                    ['title' => 'Diffraction-Free Multi-Coating', 'body' => '9 layers of anti-reflective nano-deposition eliminate ghost reflections during night driving and high-lumen digital screen immersion.', 'angle' => 0, 'metric' => 'λ = 580.3 nm'],
                    ['title' => 'Bespoke Photochromic Transition', 'body' => 'Activates smoothly in under 18 seconds from ultra-pure optical clarity indoors to a tailored Champagne or Sage tint under direct UV exposure.', 'angle' => 18, 'metric' => 'UV ACT < 18 s'],
                    ['title' => 'Prism Alignment for Eye Strain Reduction', 'body' => 'Corrects micro-misalignments between binocular sightlines, reducing visual fatigue and tension headaches by up to 84%.', 'angle' => 32, 'metric' => 'Δ 0.25 PD'],
                ],
            ]],
            ['key' => 'protocol', 'label' => 'Atelier Protocol', 'content' => [
                'eyebrow' => 'Bespoke craftsmanship',
                'title' => 'The REFRA Atelier Protocol',
                'body' => 'Luxury eyewear calibrated without compromise. Experience trial at home, physical atelier appointments, or digital prescription transcription.',
                'cards' => [
                    ['icon' => 'gift', 'title' => 'Atelier Home Trial Kit', 'body' => 'Select 4 titanium or acetate frames delivered to your residence in a custom velvet-lined presentation box. Experience them for 7 days with prepaid return courier.', 'note' => '100% Complimentary', 'cta' => 'Select 4 Frames →', 'url' => '/sunglasses#home-trial'],
                    ['icon' => 'stethoscope', 'title' => 'Consult a Refraction Master', 'body' => 'Connect via 1-on-1 encrypted video session with our board-certified clinical opticians to diagnose progressive prescriptions, astigmatism, and high-index optimization.', 'note' => 'Global Timezones', 'cta' => 'Book 30-Min Session →', 'url' => '/science#consultation'],
                    ['icon' => 'scan-line', 'title' => 'Instant Rx Scanner AI', 'body' => 'Upload a photograph of your existing optical prescription paper. Our secure OCR system auto-populates sphere, cylinder, axis, and pupillary values in seconds.', 'note' => 'HIPAA Encrypted', 'cta' => 'Upload Paper Rx →', 'url' => '/product/heritage-gold'],
                ],
            ]],
            ['key' => 'press', 'label' => 'Press reviews', 'content' => [
                'eyebrow' => 'Critical acclaim & scientific recognition',
                'quotes' => [
                    ['quote' => 'REFRA has dismantled the barrier between surgical laboratory precision and Milanese runway luxury.', 'source' => 'Vogue Eyewear Editorial'],
                    ['quote' => 'The 0.02mm tolerance and refractive crystal substrate makes standard luxury frames feel archaic.', 'source' => 'Wallpaper* Architecture & Design'],
                    ['quote' => 'Demonstrates an 84% reduction in visual strain across digital workers through targeted prism alignment.', 'source' => 'Optical Science Journal 2024'],
                ],
            ]],
            ['key' => 'newsletter', 'label' => 'Newsletter dispatch', 'content' => [
                'eyebrow' => 'Private circulation',
                'title' => 'Join The Refraction Dispatch',
                'body' => 'Receive priority invitations to limited titanium small-batch drops, bespoke lens formulation releases, and private atelier trunk shows.',
            ]],
        ];
        foreach ($sections as $i => $section) {
            PageSection::query()->updateOrCreate(['page' => 'home', 'key' => $section['key']], $section + ['sort_order' => $i, 'is_enabled' => true]);
        }

        $steps = [
            ['key' => 'scan', 'title' => 'Face photo telemetry', 'options' => 'Camera · Upload', 'is_required' => true],
            ['key' => 'fit', 'title' => 'Fit profile', 'options' => 'Feminine · Masculine · Unisex · Narrow Fit', 'is_required' => false],
            ['key' => 'material', 'title' => 'Material craft', 'options' => 'Titanium · Bio-Acetate · Alloy', 'is_required' => false],
            ['key' => 'shape', 'title' => 'Frame shapes — AI scan match', 'options' => 'Architectural Square · Classic Rounded Panto · Geometric Hexagon · Aviator Prism · Beveled Rimless Contour', 'is_required' => false],
            ['key' => 'finish', 'title' => 'Finish palette', 'options' => 'Champagne Bronze · Obsidian · Sage · Raw Titanium', 'is_required' => false],
        ];
        foreach ($steps as $i => $step) {
            VisionStep::query()->updateOrCreate(['key' => $step['key']], $step + ['sort_order' => $i]);
        }

        $templates = [
            ['slug' => 'order-placed', 'name' => 'Order Confirmation', 'hook' => 'order.placed', 'category' => 'Transactional', 'status' => 'Live'],
            ['slug' => 'rx-approved', 'name' => 'Optometrist Approved', 'hook' => 'optometrist.approved', 'category' => 'Clinical', 'status' => 'Live'],
            ['slug' => 'lab-complete', 'name' => 'Lens Surfacing Complete', 'hook' => 'lab.surfacing.complete', 'category' => 'Operational', 'status' => 'Live'],
            ['slug' => 'shipment', 'name' => 'Shipment Dispatched', 'hook' => 'shipment.created', 'category' => 'Logistics', 'status' => 'Live'],
            ['slug' => 'rx-reminder', 'name' => 'Submit Prescription Reminder', 'hook' => 'rx.pending.day7', 'category' => 'Clinical', 'status' => 'Draft'],
        ];
        foreach ($templates as $template) {
            EmailTemplate::query()->updateOrCreate(['slug' => $template['slug']], $template + [
                'locale' => 'en-GB',
                'subject' => $template['name'].' — {{order.id}}',
                'preheader' => 'Your bespoke optics are progressing.',
                'blocks' => [
                    ['id' => '1', 'type' => 'Logo', 'text' => ''],
                    ['id' => '2', 'type' => 'Heading', 'text' => $template['name']],
                    ['id' => '3', 'type' => 'Paragraph', 'text' => 'Dear {{customer.first_name}}, your REFRA order {{order.id}} has an update: '.$template['hook'].'.'],
                    ['id' => '4', 'type' => 'Order Summary', 'text' => ''],
                    ['id' => '5', 'type' => 'CTA Button', 'text' => 'View order'],
                    ['id' => '6', 'type' => 'Footer', 'text' => 'REFRA Atelier Optics · Genève'],
                ],
            ]);
        }
    }
}
