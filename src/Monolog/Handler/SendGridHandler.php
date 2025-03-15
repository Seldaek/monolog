<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Level;

/**
 * SendGridrHandler uses the SendGrid API v2 function to send Log emails, more information in https://sendgrid.com/docs/API_Reference/Web_API/mail.html
 *
 * @author Ricardo Fontanelli <ricardo.fontanelli@hotmail.com>
 */
class SendGridHandler extends MailHandler
{
    private const string CONTENT_TYPE = 'Content-Type: application/json';
    /**
     * @throws MissingExtensionException If the curl extension is missing
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $from,
        /** @var list<string> */
        private readonly array $to,
        private readonly string $subject,
        int|string|Level $level = Level::Error,
        protected bool $bubble = true,
        /** @var non-empty-string */
        private readonly string $sendGridApiUrl = 'https://api.sendgrid.com/v3/mail/send',
        )
    {
        if (!\extension_loaded('curl')) {
            throw new MissingExtensionException('The curl extension is needed to use the SendGridHandler');
        }

        parent::__construct($level, $bubble);
    }

    protected function send(string $content, array $records): void
    {
        $body = [];
        $body['personalizations'] = [];
        $body['from']['email'] = $this->from;
        foreach ($this->to as $recipient) {
            $body['personalizations'][]['to'][]['email'] = $recipient;
        }
        $body['subject'] = $this->subject;

        if ($this->isHtmlBody($content)) {
            $body['content'][] = [
                'type' => 'text/html',
                'value' => $content,
            ];
        } else {
            $body['content'][] = [
                'type' => 'text/plain',
                'value' => $content,
            ];
        }
        $authorization = "Authorization: Bearer {$this->apiKey}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, [self::CONTENT_TYPE , $authorization]);
        curl_setopt($ch, CURLOPT_URL, $this->sendGridApiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));

        Curl\Util::execute($ch, 2);
    }
}
