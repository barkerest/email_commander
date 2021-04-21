<?php

class EmailCommanderConfig extends PluginConfig
{
    const AUTO_RESPONSE = 'auto_response';
    const ENABLE_CLOSE = 'enable_close';

    function getOptions()
    {
        return array(
            self::AUTO_RESPONSE => new BooleanField(array(
                'label' => 'Auto response',
                'default' => true,
                'configuration' => array(
                    'desc' => 'Convert inbound emails to responses instead of notes.'
                )
            )),
            self::ENABLE_CLOSE => new BooleanField(array(
                'label' => 'Enable close command',
                'default' => true,
                'configuration' => array(
                    'desc' => 'Enable the "#close" command.'
                )
            )),

        );
    }
}