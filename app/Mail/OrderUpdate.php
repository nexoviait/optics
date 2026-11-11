<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Customer email for an order's current status. Uses the admin's Live email
 * template for the status hook when one exists, otherwise built-in copy.
 */
class OrderUpdate extends Mailable
{
    use Queueable, SerializesModels;

    private ?EmailTemplate $template;

    /** @var array<string, string> */
    private array $tokens;

    public function __construct(public Order $order)
    {
        $hook = config("store.order_status_hooks.{$order->status}");
        $this->template = $hook
            ? EmailTemplate::query()->where('hook', $hook)->where('status', 'Live')->latest()->first()
            : null;

        $trackUrl = $order->publicTrackingUrl();
        $this->tokens = [
            '{{customer.first_name}}' => strtok($order->customerName(), ' ') ?: 'there',
            '{{order.id}}' => (string) $order->order_number,
            '{{tracking.url}}' => $order->trackingUrl() ?? $trackUrl,
            '{{lens.index}}' => '',
        ];
    }

    public function envelope(): Envelope
    {
        $subject = $this->template
            ? EmailTemplate::replaceTokens($this->template->subject, $this->tokens)
            : "Order {$this->order->order_number}: {$this->order->statusLabel()}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order-update', with: [
            'order' => $this->order,
            'preheader' => EmailTemplate::replaceTokens($this->template?->preheader ?? $this->order->statusDescription(), $this->tokens),
            'blocks' => collect($this->template?->blocks ?: $this->defaultBlocks())
                ->map(fn (array $b) => ['type' => $b['type'], 'text' => EmailTemplate::replaceTokens($b['text'] ?? '', $this->tokens)])
                ->all(),
            'trackUrl' => $this->order->publicTrackingUrl(),
        ]);
    }

    /** @return list<array{type: string, text: string}> */
    private function defaultBlocks(): array
    {
        return [
            ['type' => 'Logo', 'text' => ''],
            ['type' => 'Heading', 'text' => $this->order->statusLabel()],
            ['type' => 'Paragraph', 'text' => 'Hello {{customer.first_name}}, '.lcfirst($this->order->statusDescription())],
            ['type' => 'Order Summary', 'text' => ''],
            ['type' => 'CTA Button', 'text' => 'Track your order'],
        ];
    }
}
