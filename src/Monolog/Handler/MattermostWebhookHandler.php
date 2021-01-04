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

use Monolog\Handler\Mattermost\MattermostWebhookMessage;
use RuntimeException;
use Monolog\Logger;
use Monolog\Handler\Curl\Util as CurlExecutor;

/**
 * Mattermost webhook handler.
 *
 * Usage example:
 *
 * $logger = new Logger('application');
 *
 * $mattermostHandler = new MattermostWebhookHandler(
 *     'https://your.mattermost.server/hooks/YOURHOOKID', // WebhookUrl
 *     'Monolog', // Username
 *     'town-square', // Channel
 *     'https://mattermost.org/wp-content/uploads/2016/04/icon.png', // IconUrl
 * );
 *
 * $logger->pushHandler($mattermostHandler);
 * $logger->debug('Log message **test** :tada:');
 *
 * @author Christin Gruber <c.gruber@touchdesign.de>
 */
class MattermostWebhookHandler extends AbstractProcessingHandler
{
    /**
     * @var string Mattermost webhook url.
     */
    protected $webhookUrl = '';

    /**
     * @var MattermostWebhookMessage
     */
    protected $message;

    public function __construct(
        string $webhookUrl,
        ?string $username = null,
        ?string $channel = null,
        ?string $iconUrl = null,
        $level = Logger::DEBUG,
        bool $bubble = true
    ) {
        $this->webhookUrl = $webhookUrl;
        $this->message = (new MattermostWebhookMessage())
            ->setUsername($username)
            ->setChannel($channel)
            ->setIconUrl($iconUrl);

        parent::__construct($level, $bubble);
    }

    public function setMessage(?MattermostWebhookMessage $message = null): self
    {
        $this->message = $message;

        return $this;
    }

    public function getMessage(): MattermostWebhookMessage
    {
        return $this->message;
    }

    protected function write(array $record): void
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);

        $this->message->setText($record['message'] ?? '');

        curl_setopt($ch, CURLOPT_POSTFIELDS, (string) $this->message);

        $result = CurlExecutor::execute($ch);

        if ($result !== 'ok') {
            throw new RuntimeException(
                sprintf(
                    'Mattermost API error: %s',
                    json_decode($result, true)['message'] ?? 'Unknown error.'
                )
            );
        }
    }
}
