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

use Monolog\Logger;

/**
 * Class DingtalkBotHandler
 *
 * Send log informations to dingtalk bot
 * (Dingtalk is work collaboration tool for small business, see more: https://www.dingtalk.com/en)
 *
 * @author Yarco Wang <yarco.wang@gmail.com>
 * @since 7/23/17 10:11 AM
 * @see https://open-doc.dingtalk.com/docs/doc.htm?spm=a219a.7629140.0.0.CXUyDQ&treeId=257&articleId=105735&docType=1
 *
 * @package Monolog\Handler
 */
class DingtalkBotHandler extends AbstractProcessingHandler
{
    const WEBHOOK = 'https://oapi.dingtalk.com/robot/send?access_token=%s';

    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $url;

    /**
     * DingtalkBotHandler constructor.
     * @param int $token
     * @param string $title
     * @param bool|int $level
     * @param bool $bubble
     */
    public function __construct($token, $title, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->token = $token;
        $this->title = $title;
        $this->url = sprintf(self::WEBHOOK, $this->token);
    }

    /**
     * {@inheritdoc}
     *
     * @param array $record
     */
    protected function write(array $record)
    {
        $payload = [
            'msgtype' => 'markdown',
            'markdown' => [
                'title' => $this->title,
                'text' => sprintf("#### %s\n> _[%s %s]_\n\n> **%s**\n", $this->title, $record['channel'], $record['datetime']->format('c'), $record['formatted']),
            ]
        ];
        $payload = json_encode($payload);
        $length = strlen($payload);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json', "Content-Length: $length"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        Curl\Util::execute($ch);
    }
}