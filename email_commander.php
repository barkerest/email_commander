<?php

set_include_path(get_include_path().PATH_SEPARATOR.dirname(__file__).'/include');

require_once('config.php');
require_once('class.command_token.php');

class EmailCommanderPlugin extends Plugin
{
    var $config_class = 'EmailCommanderConfig';

    /**
     * @var EmailCommanderConfig
     */
    private static $config;

    function bootstrap()
    {
        self::$config = $this->getConfig();

        Signal::connect(
            'object.created',
            array(
                'EmailCommanderPlugin',
                'objectCreated'
            )
        );

    }

    public static function objectCreated($object, $data)
    {
        // osTicket instance for logging purposes.
        $ost = new osTicket();

        $ost->logDebug(
            'EmailCommander::objectCreated() signalled',
            '<pre>Signal object.created has been received.
Object: '.get_class($object).'
Data: '.print_r($data,TRUE).'
</pre>');

        // Object must be a ticket.
        if (!($object instanceof Ticket))
            return;

        // Data must specify that a note was just created.
        if (!is_array($data) || !isset($data['type']) || $data['type'] != 'note')
            return;

        $ticketNum = $object->getNumber();

        $thread = $object->getThread();
        if (!$thread || !($thread instanceof TicketThread))
        {
            $ost->logError(
                'EmailCommander::objectCreated() error',
                "Failed to load the message thread from ticket #{$ticketNum}."
            );
            return;
        }

        // get the last entry ID from the thread.
        $noteId = (clone $thread->getEntries())->order_by('-id')->first()->getId();

        // get the last note from the thread.
        $note = $thread->getEntry(array('id' => $noteId));
        if (!$note || !($note instanceof ThreadEntry))
        {
            $ost->logError(
                'EmailCommander::objectCreated() error',
                "Failed to load the last thread entry from ticket #{$ticketNum}."
            );
            return;
        }
        if ($note->type != NoteThreadEntry::ENTRY_TYPE)
        {
            $ost->logError(
                'EmailCommander::objectCreated() error',
                "Last thread entry from ticket #{$ticketNum} is not a note."
            );
            return;
        }

        // if the note wasn't from email, ignore it.
        if ($note->getSource() != 'Email')
        {
            $ost->logDebug(
                'EmailCommander::objectCreated() skip',
                "Last note entered for ticket #{$ticketNum} did not come from an email."
            );
            return;
        }

        // process the tokens.
        $processed = CommandToken::processAll(self::$config, $object, $note, $ost);

        if ($processed < 0)
        {
            $processed = -$processed - 1;
            $bad = CommandToken::getTokens()[$processed]->getName();
            $ost->logError(
                'EmailCommander::objectCreated() error',
                "An error in the $bad token after processing $processed tokens for ticket #${ticketNum}.");
        }
        else
        {
            $ost->logDebug(
                'EmailCommander::objectCreated() processed',
                "Successfully processed $processed tokens for ticket #${ticketNum}.");
        }
    }

}