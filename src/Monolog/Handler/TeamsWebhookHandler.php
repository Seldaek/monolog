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
use Monolog\Utils;
use Monolog\Handler\Teams\TeamsPayload;
use Monolog\LogRecord;

/**
 * Sends notifications through MS Teams Webhooks
 *
 * @author Sébastien Alfaiate <s.alfaiate@webarea.fr>
 * @see    https://learn.microsoft.com/en-us/microsoftteams/platform/webhooks-and-connectors/how-to/add-incoming-webhook
 * @see    https://support.microsoft.com/office/create-incoming-webhooks-with-workflows-for-microsoft-teams-8ae491c7-0394-4861-ba59-055e33f75498
 */
class TeamsWebhookHandler extends AbstractProcessingHandler
{
    /**
     * MS Teams Webhook URL.
     *
     * @var non-empty-string
     */
    private string $webhookUrl;

    /**
     * Instance of the TeamsPayload util class preparing data for MS Teams API.
     */
    private TeamsPayload $teamsPayload;

    /**
     * @param non-empty-string $webhookUrl             MS Teams Webhook URL
     * @param bool             $includeContextAndExtra Whether the card should include context and extra data
     * @param string[]         $excludeFields          Dot separated list of fields to exclude from MS Teams message. E.g. ['context.field1', 'extra.field2']
     * @param string[]         $toggleFields           Dot separated list of fields to display with a toggle button in MS Teams message. E.g. ['context.field1', 'extra.field2']
     *
     * @throws MissingExtensionException If the curl extension is missing
     */
    public function __construct(
        string $webhookUrl,
        bool $includeContextAndExtra = false,
        bool $formatMessage = false,
        $level = Level::Critical,
        bool $bubble = true,
        array $excludeFields = [],
        array $toggleFields = []
    ) {
        if (!\extension_loaded('curl')) {
            throw new MissingExtensionException('The curl extension is needed to use the TeamsWebhookHandler');
        }

        parent::__construct($level, $bubble);

        $this->webhookUrl = $webhookUrl;

        $this->teamsPayload = new TeamsPayload($includeContextAndExtra, $formatMessage, $excludeFields, $toggleFields);
    }

    public function getTeamsPayload(): TeamsPayload
    {
        return $this->teamsPayload;
    }

    /**
     * @inheritDoc
     */
    protected function write(LogRecord $record): void
    {
        $postData = $this->teamsPayload->getAdaptiveCardPayload($record, $this->getFormatter());
        $postString = Utils::jsonEncode($postData);

        $ch = curl_init();
        $options = [
            CURLOPT_URL => $this->webhookUrl,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-type: application/json'],
            CURLOPT_POSTFIELDS => $postString,
        ];

        curl_setopt_array($ch, $options);

        Curl\Util::execute($ch);
    }
}
