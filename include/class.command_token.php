<?php

abstract class CommandToken
{
    private const TOKEN_PRE = '/(?<ct__before>^|\n|<p(?:\s+[^>]*)?>)\s*#\s*(?<token_match>';
    private const TOKEN_POST = ')[ \t]*(?<ct__after>$|\r?\n|<br\s*\/>|<\/p>)/';

    /**
     * @var string The regular expression to match with.
     */
    protected $regex = null;

    /**
     * The preferred run index.  Tokens are processed from low to high.
     *
     * @return int
     */
    public abstract function runIndex();

    /**
     * The name of this token.
     *
     * @return string
     */
    public abstract function getName();

    /**
     * Builds a complete regular expression to match the subpattern on any line in the message body.
     *
     * @param string $subpattern
     * @return string
     */
    protected function buildRegex(string $subpattern): string
    {
        return self::TOKEN_PRE . $subpattern . self::TOKEN_POST;
    }

    /**
     * Handles each instance of a match for this token.
     *
     * @param array $matches The array of matches found in the body.  $matches['token_match'] will have the entire subpattern match if you use the buildRegex() function to build your regex pattern.
     * @param array $flags An array of flags to be handled by the token after processing the message body.
     * @param EmailCommanderConfig $config The configuration for the plugin.
     * @param Ticket $ticket The ticket currently being processed.
     * @param ThreadEntry $note The note currently being processed.
     * @param osTicket $ost The osTicket instance to use for logging.
     * @return string The value to replace the token with (eg - '').
     */
    protected abstract function processMatch(array $matches, array &$flags, EmailCommanderConfig $config, Ticket $ticket, ThreadEntry $note, osTicket $ost): string;

    /**
     * @param array $flags The flags to be modified during body processing.
     * @param EmailCommanderConfig $config The configuration for the plugin.
     * @param Ticket $ticket The ticket currently being processed.
     * @param ThreadEntry $note The note currently being processed.
     * @param osTicket $ost The osTicket instance to use for logging.
     * @return void
     */
    protected function beforeProcess(array &$flags, EmailCommanderConfig $config, Ticket $ticket, ThreadEntry $note, osTicket $ost)
    {
        // override as necessary.
    }

    /**
     * Performs any necessary actions for this token.
     *
     * @param array $flags The flags generated during body processing.
     * @param EmailCommanderConfig $config The configuration for the plugin.
     * @param Ticket $ticket The ticket currently being processed.
     * @param ThreadEntry $note The note currently being processed.
     * @param osTicket $ost The osTicket instance to use for logging.
     * @return bool
     */
    protected abstract function performActions(array &$flags, EmailCommanderConfig $config, Ticket $ticket, ThreadEntry $note, osTicket $ost): bool;

    /**
     * An empty callback method.
     *
     * @param array $flags The flags generated during body processing.
     * @param EmailCommanderConfig $config The configuration for the plugin.
     * @param Ticket $ticket The ticket currently being processed.
     * @param ThreadEntry $note The note currently being processed.
     * @param osTicket $ost The osTicket instance to use for logging.
     */
    protected function callback(array $flags, EmailCommanderConfig $config, Ticket $ticket, ThreadEntry $note, osTicket $ost)
    {
        // empty callback.
    }

    /**
     * Processes this token.
     *
     * @param array $flags The flags generated during body processing.
     * @param EmailCommanderConfig $config The configuration for the plugin.
     * @param Ticket $ticket The ticket currently being processed.
     * @param ThreadEntry $note The note currently being processed.
     * @param osTicket $ost The osTicket instance to use for logging.
     * @return bool
     */
    function process(array &$flags, EmailCommanderConfig $config, Ticket $ticket, ThreadEntry $note, osTicket $ost): bool
    {
        $this->beforeProcess($flags, $config, $ticket, $note, $ost);

        $cb = function ($matches) use (&$flags, $config, $ticket, $note, $ost) {
            $m = array();

            $before = $matches['ct__before'][0];
            $after = $matches['ct__after'][0];

            foreach ($matches as $key => $match) {
                if (is_numeric($key) || $key == 'ct__before' || $key == 'ct__after')
                    continue;
                $m[$key] = $match[0];
            }

            $rep = $this->processMatch($m, $flags, $config, $ticket, $note, $ost);
            if (!is_string($rep)) $rep = '';

            return $before . $rep . $after;
        };

        $noteBody = $note->getBody();

        $cnt = 0;
        $newBody = preg_replace_callback($this->regex, $cb, $noteBody->body, -1, $cnt, PREG_OFFSET_CAPTURE);
        if ($newBody === null) {
            return false;
        }

        if ($cnt > 0) {
            $noteBody->body = $newBody;
            $note->setBody($noteBody);
        }

        return $this->performActions($flags, $config, $ticket, $note, $ost);
    }

    /**
     * @var CommandToken[]
     */
    private static $tokens = array();

    /**
     * @return CommandToken[]
     */
    public static function getTokens(): array
    {
        return self::$tokens;
    }

    public static function _registerToken(CommandToken $token)
    {
        self::$tokens[] = $token;
    }

    /**
     * @param $a CommandToken
     * @param $b CommandToken
     * @return int
     */
    public static function compare(CommandToken $a, CommandToken $b): int
    {
        if ($a === null && $b === null) return 0;
        if ($a === null) return -1;
        if ($b === null) return 1;
        $ia = $a->runIndex();
        $ib = $b->runIndex();
        if ($ia < $ib) return -1;
        if ($ia > $ib) return 1;
        $na = $a->getName();
        $nb = $b->getName();
        $ret = strcmp($na, $nb);
        if ($ret) return $ret;
        $ca = get_class($a);
        $cb = get_class($b);
        return strcmp($ca, $cb);
    }

    /**
     * Processes all registered tokens in the preferred run order.
     *
     * If an error occurs while processing the return value indicates the token that caused an error.
     * For instance, if -6 is returned, then the 6th token being processed caused the error.
     *
     * If any changes are made to the ticket or note during processing, they will be saved to the database.
     *
     * @param EmailCommanderConfig $config The configuration for the plugin.
     * @param Ticket $ticket The ticket currently being processed.
     * @param ThreadEntry $note The note currently being processed.
     * @param osTicket $ost The osTicket instance to use for logging.
     * @return int The number of tokens processed.  Negative on error.
     * @throws OrmException Error raised when saving ticket or note changes.
     */
    public static function processAll(EmailCommanderConfig $config, Ticket $ticket, ThreadEntry $note, osTicket $ost)
    {
        $flags = array(
            'callback' => array()   // list of callables with the 'callback' signature to run after all tokens are processed.
        );

        usort(self::$tokens, array('CommandToken', 'compare'));
        $cnt = 0;
        foreach (self::$tokens as $token) {
            $cnt++;
            if (!$token->process($flags, $config, $ticket, $note, $ost)) {
                return -$cnt;
            }
        }

        // save before callback processing.
        if ($note->dirty)
            $note->save();
        if ($ticket->dirty)
            $ticket->save();

        if ($flags['callback']) {
            foreach ($flags['callback'] as $cb) {
                call_user_func($cb, $flags, $config, $ticket, $note, $ost);
            }
        }

        // save again if necessary, but callbacks should clean up after themselves.
        if ($note->dirty || $ticket->dirty)
            $ost->logWarning(
                'EmailCommander::CommandToken::processAll() cleanup',
                'One or more callbacks failed to clean up after themselves.');

        if ($note->dirty)
            $note->save();
        if ($ticket->dirty)
            $ticket->save();

        return $cnt;
    }

}

require_once('class.public_private_token.php');
