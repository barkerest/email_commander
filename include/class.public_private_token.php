<?php

class PublicPrivateToken extends CommandToken
{
    public function __construct()
    {
        $this->regex = $this->buildRegex('(public|private|internal)');
    }

    public function runIndex(): int
    {
        return 10001;
    }

    public function getName(): string
    {
        return "Public/Private";
    }

    protected function beforeProcess(array &$flags, EmailCommanderConfig $config, Ticket $ticket, ThreadEntry $note, osTicket $ost)
    {
        $flags['respond'] = $config->get(EmailCommanderConfig::AUTO_RESPONSE);
    }

    protected function processMatch(array $matches, array &$flags, EmailCommanderConfig $config, Ticket $ticket, ThreadEntry $note, osTicket $ost):string
    {
        $match = $matches['token_match'];
        if ($match == 'public')
        {
            $flags['respond'] = true;
        }
        else
        {
            $flags['respond'] = false;
        }
        return '';
    }

    function callback(array $flags, EmailCommanderConfig $config, Ticket $ticket, ThreadEntry $note, osTicket $ost)
    {

    }

    protected function performActions(array &$flags, EmailCommanderConfig $config, Ticket $ticket, ThreadEntry $note, osTicket $ost): bool
    {
        if ($flags['respond'])
        {
            // TODO: Change note to response and gather email recipients.

            // Register the callback to send the email after processing.
            $flags['callback'][] = array($this, 'callback');
        }

        return true;
    }
}

CommandToken::_registerToken(new PublicPrivateToken());
