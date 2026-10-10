<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through the Cloudflare Email Service REST API
 * (POST /accounts/{account_id}/email/sending/send).
 */
class CloudflareEmailTransport extends AbstractTransport
{
    protected const STANDARD_HEADERS = [
        'from', 'to', 'cc', 'bcc', 'reply-to', 'subject', 'date', 'message-id', 'mime-version',
        'content-type', 'content-transfer-encoding', 'sender', 'return-path',
    ];

    public function __construct(
        protected string $accountId,
        protected string $token,
        protected string $baseUrl = 'https://api.cloudflare.com/client/v4',
        protected int $timeout = 20,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();

        $payload = $this->payload($email, $envelope);

        $response = Http::withToken($this->token)
            ->timeout($this->timeout)
            ->acceptJson()
            ->post(rtrim($this->baseUrl, '/')."/accounts/{$this->accountId}/email/sending/send", $payload);

        $json = $response->json();
        if (! $response->successful() || ! ($json['success'] ?? false)) {
            $errors = collect($json['errors'] ?? [])->map(fn ($e) => ($e['code'] ?? '').' '.($e['message'] ?? ''))->filter()->implode('; ');
            throw new TransportException(sprintf(
                'Cloudflare Email API error (HTTP %d): %s',
                $response->status(),
                $errors !== '' ? $errors : mb_substr($response->body(), 0, 500)
            ));
        }

        $result = $json['result'] ?? [];
        if (! empty($result['message_id'])) {
            $message->setMessageId((string) $result['message_id']);
        }
        if (! empty($result['permanent_bounces']) && empty($result['delivered']) && empty($result['queued'])) {
            throw new TransportException('Cloudflare Email: all recipients permanently bounced ('.implode(', ', $result['permanent_bounces']).')');
        }
    }

    protected function payload(Email $email, Envelope $envelope): array
    {
        $from = $email->getFrom()[0] ?? $envelope->getSender();

        $payload = [
            'from' => $this->address($from),
            'to' => array_map(fn (Address $a) => $this->address($a), $email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];
        if ($email->getCc()) {
            $payload['cc'] = array_map(fn (Address $a) => $this->address($a), $email->getCc());
        }
        if ($email->getBcc()) {
            $payload['bcc'] = array_map(fn (Address $a) => $this->address($a), $email->getBcc());
        }
        if ($email->getReplyTo()) {
            $payload['reply_to'] = $email->getReplyTo()[0]->getAddress();
        }
        if ($email->getHtmlBody() !== null) {
            $payload['html'] = (string) $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null) {
            $payload['text'] = (string) $email->getTextBody();
        }
        if (! isset($payload['html']) && ! isset($payload['text'])) {
            $payload['text'] = '';
        }

        $attachments = [];
        foreach ($email->getAttachments() as $part) {
            $attachments[] = [
                'content' => base64_encode($part->getBody()),
                'filename' => $part->getFilename() ?? 'attachment',
                'type' => $part->getMediaType().'/'.$part->getMediaSubtype(),
                'disposition' => $part->getDisposition() ?: 'attachment',
            ];
        }
        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }

        $headers = [];
        foreach ($email->getHeaders()->all() as $header) {
            $name = strtolower($header->getName());
            if (in_array($name, self::STANDARD_HEADERS, true)) {
                continue;
            }
            $headers[$header->getName()] = $header->getBodyAsString();
        }
        if ($headers !== []) {
            $payload['headers'] = $headers;
        }

        return $payload;
    }

    protected function address(Address $address): array|string
    {
        if ($address->getName() !== '') {
            return ['address' => $address->getAddress(), 'name' => $address->getName()];
        }

        return $address->getAddress();
    }

    public function __toString(): string
    {
        return 'cloudflare://'.$this->accountId;
    }
}
