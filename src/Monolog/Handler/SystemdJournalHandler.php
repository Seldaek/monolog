<?php

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
 * Logs to systemd-journald service.
 *
 * This handler requires the sd_journal_send function from php-systemd
 * extension (https://github.com/systemd/php-systemd).
 *
 * @author Damián Nohales <damiannohales@gmail.com>
 */
class SystemdJournalHandler extends AbstractPosixBasedHandler
{
    protected $extraFields;

    /**
     * @param array   $extraFields Extra fields to send to the journal, Monolog will prepend the
     *                             MONOLOG prefix and will normalize each field name according to
     *                             the restrictions pointed in sd_journal_print(3) manpage.
     *                             Example: array(FIELD1 => 'field 1 content', 'FIELD2' => 'field 2 content')
     * @param integer $level       The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble      Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($extraFields = array(), $level = Logger::DEBUG, $bubble = true)
    {
        $this->checkSystemdExtension();

        parent::__construct($level, $bubble);

        $this->extraFields = $extraFields;
    }

    /**
     * Check if the php-systemd extension is properly enabled.
     */
    public function checkSystemdExtension()
    {
        if (!function_exists('sd_journal_send')) {
            throw new MissingExtensionException('php-systemd extension is required to use Monolog\'s SystemdJournalHandler (see https://github.com/systemd/php-systemd)');
        }
    }

    /**
     * Convert a "key => value" array to an array of fields to be used with
     * sd_journal_send.
     *
     * @param array  $arr    The array to be converted, the key of each item will be
     *                       used to generate the field name and the value to set the
     *                       field value.
     * @param string $prefix A prefix to prepend to each field name.
     */
    private function fieldsFromArray(array $arr, $prefix)
    {
        $fields = array();

        foreach ($arr as $name => $value) {
            $fieldName = preg_replace('/[^A-Z0-9_]/', '', strtoupper($name));
            $fieldName = ltrim($fieldName, '_');
            $fields[] = sprintf('%s%s=%s', $prefix, $fieldName, $value);
        }

        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        $fields = array();

        $fields[] = sprintf('MESSAGE=%s', (string) $record['message']);
        $fields[] = sprintf('PRIORITY=%d', $this->logLevels[$record['level']]);
        $fields[] = sprintf('MONOLOG_CHANNEL=%s', $record['channel']);

        $fields = array_merge($fields, $this->fieldsFromArray($record['context'], 'MONOLOG_CONTEXT_'));
        $fields = array_merge($fields, $this->fieldsFromArray($record['extra'], 'MONOLOG_EXTRA_'));
        $fields = array_merge($fields, $this->fieldsFromArray($this->extraFields, 'MONOLOG_HANDLEREXTRA_'));

        $this->sendToJournal($fields);
    }

    /**
     * Send log entry to the journal.
     *
     * @param array $fields Log entry fields
     */
    public function sendToJournal(array $fields)
    {
        call_user_func_array('sd_journal_send', $fields);
    }
}
