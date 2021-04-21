# EmailCommander

A plugin providing the ability to manage tickets via email.


## Status

This is definitely a work in progress.  The plugin currently loads into osTicket correctly and shows the very basic
configuration.  When new notes are created, the plugin reviews them to determine if they came from Email.  The tickets
that originate from email are then passed by the CommandToken engine for pattern matching and processing.

The CommandToken class makes it easy to define single-line commands.  The current Public/Private command token looks for
"#private", "#internal", or "#public" tokens.  Depending on the token found, it sets $flags['respond'] to either TRUE
or FALSE.  The default value for $flags['respond'] comes from the configuration.  After the command token finishes
processing the matches, it then decides what to do based on $flags['respond'].  If the flag is TRUE then it registers
a callback to be run when all the command tokens are done running.  The callback is where we change the entry type from
'N' to 'R', update the recipient list, and send an email to the recipients to notify them of the response.