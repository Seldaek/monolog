<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 */

namespace Monolog\Formatter;

/**
 * Serializes a log message to ExceptionLess Event Format
 *
 *
 * @author Israel Garcia <igarcia@nearsolutions.net>
 */
class ExceptionLessFormatter extends JsonFormatter
{
    const V4 = 4;

    /**
     * @var string an application name for the ExceptionLess log message.
     */
    protected $applicationName;

    /**
     * @var int ExceptionLess version to use
     */
    protected $version;

    /**
     * @param string $applicationName the application that sends the data.
     * @param int    $version         the ExceptionLess format version to use, defaults to 4
     */
    public function __construct($applicationName, $version = self::V4)
    {
        parent::__construct('Y-m-d\TH:i:s.uP');

        $this->systemName = gethostname();
        $this->applicationName = $applicationName;
        $this->version = $version;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $record = parent::format($record);

        if ($this->version === self::V4) {
            $message = $this->formatV4(json_decode($record, true));
        } 

        return $this->toJson($message) . "\n";
    }

    protected function MapLevel($monologLevel){
        if ($monologLevel == 100) return 'debug';
        if ($monologLevel == 200) return 'info';
        if ($monologLevel == 250) return 'info';
        if ($monologLevel == 300) return 'warn';
        if ($monologLevel == 400) return 'error';
        if ($monologLevel >= 500) return 'error';
    }

    protected function formatV4(array $record)
    {
        if (empty($record['datetime'])) {
            $record['datetime'] = gmdate('c');
        }

        if (!empty($record['extra'])) {
            foreach ($record['extra'] as $key => $val) {
                $message['data']['@request']['ext_' . $key] = $val;
            }
        }

        $context = array();

        if (!empty($record['context'])) {
            foreach ($record['context'] as $key => $val) {
                $context['cxt_'.preg_replace('/[^A-Za-z0-9 ]/','', $key)] = $val;
            }
        }

        $message = array(
            'type' => $this->MapLevel($record['level']),
            'date' => $record['datetime']["date"],
            'source' => (isset($_SERVER["REQUEST_URI"])) ? $_SERVER["REQUEST_URI"] : "",
            'message' => $record['message'],
            'geo' => null,
            'value' => null,
            'count' => null,
            'data' => array(
                '@version' => '1.0.0.0',
                '@environment' => array())            
        );

        if ($message['type'] == 'error'){
            $message['data']['@error'] = array(
                'message' => $record['message'],
                'type' =>  (isset($context['ctx_severity']) ? $context['ctx_severity'] : "")
            );
        }

        if (!empty($context['cxt_Exceptiontrace'])){
            foreach ($context['cxt_Exceptiontrace'] as $element) {                
                $message['data']['@error']['stack_trace'][] = array(
                    'file_name' => $element['file'],
                    'line_number' => $element['line'],
                    'name' => $element['function'],
                    'declaring_type' => $element['class']
                );
            }           
        }

        if ($message['type'] != 'session'){
            $message['data']['@request'] = array();
            $message['data']['@request']['user_agent'] = (isset($_SERVER["HTTP_USER_AGENT"])) ? $_SERVER["HTTP_USER_AGENT"] : "";
            $message['data']['@request']['http_method'] = (isset($_SERVER["REQUEST_METHOD"])) ? $_SERVER["REQUEST_METHOD"] : "";
            $message['data']['@request']['is_secure'] = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] != "off") ? true : false;
            if (isset($_SERVER["HTTP_HOST"])){
                $http_host = $_SERVER["HTTP_HOST"];
                $http_p = explode(":",$_SERVER["HTTP_HOST"]);
                $message['data']['@request']['host'] = $http_p[0];
                if (count($http_p) > 1 && ctype_digit($http_p[1])) $message['data']['@request']['port'] = (int)$http_p[1];
            }
            $message['data']['@request']['path'] = (isset($_SERVER["REQUEST_URI"])) ? $_SERVER["REQUEST_URI"] : "";
            $message['data']['@request']['referrer'] = (isset($_SERVER["HTTP_REFERER"])) ? $_SERVER["HTTP_REFERER"] : "";
            $message['data']['@request']['client_ip_address'] = (isset($_SERVER["REMOTE_ADDR"])) ? $_SERVER["REMOTE_ADDR"] : "";
            if (!empty($_POST)) $message['data']['@request']['post'] = $_POST;
        }
        
        $message['data']['@environment']['process_id'] = function_exists("getmypid") ? getmypid() : null;
        $message['data']['@environment']['process_name'] = PHP_BINARY;
        $message['data']['@environment']['process_owner'] = function_exists("get_current_user") ? get_current_user() : null;
        $message['data']['@environment']['command_line'] = (isset($_SERVER["SCRIPT_FILENAME"])) ? $_SERVER["SCRIPT_FILENAME"] : "";
        $message['data']['@environment']['machine_name'] = (isset($_SERVER["SERVER_NAME"])) ? $_SERVER["SERVER_NAME"] : "";
        $message['data']['@environment']['runtime_version'] = PHP_VERSION;
        $message['data']['@environment']['o_s_name'] = function_exists("php_uname") ? php_uname('s')." ".php_uname('r')." ".php_uname('v') : "";
        $message['data']['@environment']['o_s_version'] = function_exists("php_uname") ? php_uname('r') : "";
        
        if(2147483647 == PHP_INT_MAX) {
            $message['data']['@environment']['architecture'] = 'x86';
          }else{
            $message['data']['@environment']['architecture'] = 'x64';
        }

        $message['data']['@environment']['processor_count'] = num_cpus();
        $message['data']['@environment']['process_memory_size'] = function_exists("memory_get_usage") ? memory_get_usage() : null;

        $memory = getServerMemoryUsage(false);

        $message['data']['@environment']['total_physical_memory'] = $memory["total"];
        $message['data']['@environment']['available_physical_memory'] = $memory["free"];


        

        return $message;
    }

    function getServerMemoryUsage($getPercentage=true)
    {
        $memoryTotal = null;
        $memoryFree = null;

        if (stristr(PHP_OS, "win")) {
            // Get total physical memory (this is in bytes)
            $cmd = "wmic ComputerSystem get TotalPhysicalMemory";
            @exec($cmd, $outputTotalPhysicalMemory);

            // Get free physical memory (this is in kibibytes!)
            $cmd = "wmic OS get FreePhysicalMemory";
            @exec($cmd, $outputFreePhysicalMemory);

            if ($outputTotalPhysicalMemory && $outputFreePhysicalMemory) {
                // Find total value
                foreach ($outputTotalPhysicalMemory as $line) {
                    if ($line && preg_match("/^[0-9]+\$/", $line)) {
                        $memoryTotal = $line;
                        break;
                    }
                }

                // Find free value
                foreach ($outputFreePhysicalMemory as $line) {
                    if ($line && preg_match("/^[0-9]+\$/", $line)) {
                        $memoryFree = $line;
                        $memoryFree *= 1024;  // convert from kibibytes to bytes
                        break;
                    }
                }
            }
        }
        else
        {
            if (is_readable("/proc/meminfo"))
            {
                $stats = @file_get_contents("/proc/meminfo");

                if ($stats !== false) {
                    // Separate lines
                    $stats = str_replace(array("\r\n", "\n\r", "\r"), "\n", $stats);
                    $stats = explode("\n", $stats);

                    // Separate values and find correct lines for total and free mem
                    foreach ($stats as $statLine) {
                        $statLineData = explode(":", trim($statLine));

                        //
                        // Extract size (TODO: It seems that (at least) the two values for total and free memory have the unit "kB" always. Is this correct?
                        //

                        // Total memory
                        if (count($statLineData) == 2 && trim($statLineData[0]) == "MemTotal") {
                            $memoryTotal = trim($statLineData[1]);
                            $memoryTotal = explode(" ", $memoryTotal);
                            $memoryTotal = $memoryTotal[0];
                            $memoryTotal *= 1024;  // convert from kibibytes to bytes
                        }

                        // Free memory
                        if (count($statLineData) == 2 && trim($statLineData[0]) == "MemFree") {
                            $memoryFree = trim($statLineData[1]);
                            $memoryFree = explode(" ", $memoryFree);
                            $memoryFree = $memoryFree[0];
                            $memoryFree *= 1024;  // convert from kibibytes to bytes
                        }
                    }
                }
            }
        }

        if (is_null($memoryTotal) || is_null($memoryFree)) {
            return null;
        } else {
            if ($getPercentage) {
                return (100 - ($memoryFree * 100 / $memoryTotal));
            } else {
                return array(
                    "total" => $memoryTotal,
                    "free" => $memoryFree,
                );
            }
        }
    }

    function num_cpus()
    {
        $numCpus = 1;
        if (is_file('/proc/cpuinfo'))
        {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $numCpus = count($matches[0]);
        }
        else if ('WIN' == strtoupper(substr(PHP_OS, 0, 3)))
        {
            $process = @popen('wmic cpu get NumberOfCores', 'rb');
            if (false !== $process)
            {
            fgets($process);
            $numCpus = intval(fgets($process));
            pclose($process);
            }
        }
        else
        {
            $process = @popen('sysctl -a', 'rb');
            if (false !== $process)
            {
            $output = stream_get_contents($process);
            preg_match('/hw.ncpu: (\d+)/', $output, $matches);
            if ($matches)
            {
                $numCpus = intval($matches[1][0]);
            }
            pclose($process);
            }
        }        
        return $numCpus;
    }
}
