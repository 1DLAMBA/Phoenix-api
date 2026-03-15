<?php

namespace App\Mail\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

class BrevoApiTransport extends AbstractTransport
{
    public function __construct(
        private readonly Client $client,
        private readonly string $apiKey,
        private readonly ?string $defaultFromAddress = null,
        private readonly ?string $defaultFromName = null
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $from = $email->getFrom();
        $sender = $from[0] ?? null;

        if (!$sender && $this->defaultFromAddress) {
            $sender = new Address($this->defaultFromAddress, $this->defaultFromName ?? '');
        }

        if (!$sender) {
            throw new TransportException('Brevo sender email is missing. Set a from address in the mailable or config(mail.from.address).');
        }

        $to = $this->mapAddresses($email->getTo());
        $cc = $this->mapAddresses($email->getCc());
        $bcc = $this->mapAddresses($email->getBcc());

        if (empty($to) && empty($cc) && empty($bcc)) {
            throw new TransportException('Brevo recipient list is empty.');
        }

        $payload = [
            'sender' => $this->formatAddress($sender),
            'to' => $to,
            'subject' => (string) $email->getSubject(),
        ];

        if (!empty($cc)) {
            $payload['cc'] = $cc;
        }

        if (!empty($bcc)) {
            $payload['bcc'] = $bcc;
        }

        $htmlBody = $email->getHtmlBody();
        $textBody = $email->getTextBody();

        // Brevo accepts one content body per API call. Prefer HTML when available.
        if ($htmlBody !== null) {
            $payload['htmlContent'] = $htmlBody;
        } elseif ($textBody !== null) {
            $payload['textContent'] = $textBody;
        } else {
            $payload['textContent'] = '';
        }

        $attachments = $this->formatAttachments($email->getAttachments());
        if (!empty($attachments)) {
            $payload['attachment'] = $attachments;
        }

        Log::info('Brevo API: sending email', [
            'subject' => $payload['subject'],
            'to' => $payload['to'],
            'sender' => $payload['sender'],
            'has_html' => isset($payload['htmlContent']),
            'has_text' => isset($payload['textContent']),
            'attachments_count' => isset($payload['attachment']) ? count($payload['attachment']) : 0,
        ]);

        try {
            $response = $this->client->post('https://api.brevo.com/v3/smtp/email', [
                'headers' => [
                    'accept' => 'application/json',
                    'api-key' => $this->apiKey,
                    'content-type' => 'application/json',
                ],
                'json' => $payload,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $exception) {
            Log::error('Brevo API: request failed', [
                'message' => $exception->getMessage(),
                'exception' => get_class($exception),
            ]);
            throw new TransportException(
                'Unable to send message via Brevo API: '.$exception->getMessage(),
                0,
                $exception
            );
        }

        $statusCode = $response->getStatusCode();
        $responseBody = (string) $response->getBody();

        if ($statusCode >= 200 && $statusCode < 300) {
            $decoded = json_decode($responseBody, true);
            Log::info('Brevo API: email sent successfully', [
                'status' => $statusCode,
                'message_id' => $decoded['messageId'] ?? null,
            ]);
        } else {
            Log::error('Brevo API: non-2xx response', [
                'status' => $statusCode,
                'body' => $responseBody,
            ]);
            throw new TransportException(
                sprintf(
                    'Brevo API request failed with status %d: %s',
                    $statusCode,
                    $responseBody
                )
            );
        }
    }

    public function __toString(): string
    {
        return 'brevo-api';
    }

    /**
     * @param  array<Address>  $addresses
     * @return array<int, array{name:string,email:string}>
     */
    private function mapAddresses(array $addresses): array
    {
        return array_map(fn (Address $address) => $this->formatAddress($address), $addresses);
    }

    /**
     * @return array{name:string,email:string}
     */
    private function formatAddress(Address $address): array
    {
        $email = $address->getAddress();
        $name = $address->getName();
        if ($name === null || $name === '') {
            $name = $email;
        }

        return [
            'name' => $name,
            'email' => $email,
        ];
    }

    /**
     * @param  iterable<DataPart>  $attachments
     * @return array<int, array{name:string,content:string,contentType:string}>
     */
    private function formatAttachments(iterable $attachments): array
    {
        $result = [];

        foreach ($attachments as $attachment) {
            if (!$attachment instanceof DataPart) {
                continue;
            }

            $body = $attachment->getBody();
            $content = null;

            if ($body instanceof File) {
                $path = $body->getPath();
                if ($path && is_file($path)) {
                    $content = file_get_contents($path) ?: '';
                }
            } elseif (is_resource($body)) {
                $content = stream_get_contents($body) ?: '';
            } else {
                $content = (string) $body;
            }

            if ($content === null) {
                continue;
            }

            $filename = $attachment->getFilename() ?? 'attachment';
            $mediaType = $attachment->getMediaType().'/'.$attachment->getMediaSubtype();
            if (strpos($filename, '.') === false) {
                $filename .= '.' . $this->getExtensionFromMimeType($mediaType);
            }

            $result[] = [
                'name' => $filename,
                'content' => base64_encode($content),
                'contentType' => $mediaType,
            ];
        }

        return $result;
    }

    private function getExtensionFromMimeType(string $mimeType): string
    {
        return match (strtolower($mimeType)) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
