r<?php declare(strict_types=1);

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
use Monolog\LogRecord;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\Curl;

/**
 * AzureHandler uses cURL to send log to Azure Monitor
 * https://learn.microsoft.com/en-us/azure/azure-monitor/
 * 
 * @author Michele D'Amico <michele.damico@linfaservice.it>
 * Linfa Service - https://www.linfaservice.it
 * Damikael - https://www.damikael.dev
 */
class AzureHandler extends AbstractProcessingHandler
{
    private string $tenantId;
    private string $appId;
    private string $appSecret;
    private string $dceURI;
    private string $dcrImmutableId;
    private string $table;

    /**
     * @param string $tenantId
     * @param string $appId
     * @param string $appSecret
     * @param string $dceURI
     * @param string $dcrImmutableId;
     * @param string $table
     *
     * @throws MissingExtensionException If the curl extension is missing
     */
    public function __construct(string $tenantId, string $appId, string $appSecret, string $dceURI, string $dcrImmutableId, string $table, int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        if (!\extension_loaded('curl')) {
            throw new MissingExtensionException('The curl extension is needed to use the AzureHandler');
        }

        $this->tenantId = $tenantId;
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->dceURI = $dceURI;
        $this->dcrImmutableId = $dcrImmutableId;
        $this->table = $table;

        parent::__construct($level, $bubble);
    }

    /**
     * @inheritDoc
     */
    public function write(LogRecord $record): void
    {

        // retrieve access_token
        $url = "https://login.microsoftonline.com/" . $this->tenantId . "/oauth2/v2.0/token";
        $postString = "
            grant_type=client_credentials
            &scope=https://monitor.azure.com//.default
            &client_id=" . $this->appId . "
            &client_secret=" . $this->appSecret . "
        ";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/x-www-form-urlencoded",
        ]);

        $response = Curl\Util::execute($ch);
        $access_token = json_decode($response)->access_token;

        // send log
        $url = $this->dceURI . "/dataCollectionRules/" . $this->dcrImmutableId . "/streams/Custom-" . $this->table . "?api-version=2023-01-01";
        $sourceUrl = $_SERVER['HTTP_HOST'];
        $clientIp = $_SERVER['REMOTE_ADDR'];

        $postString = "[
            {
                \"TimeGenerated\": \"" . (new \DateTime())->format('c') . "\",
                \"Direction\": \"REQUEST\",
                \"Method\": \"GET\",
                \"Url\": \"" . $sourceUrl ."\",
                \"IP\": \"" . $clientIp ."\",
                \"Level\": \"INFO\",
                \"response_type\": \"code\",
                \"message\": \"" . $record->message . "\"
            }
        ]";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $access_token,
            "Content-Type: application/json",
        ]);

        $response = Curl\Util::execute($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    }
}