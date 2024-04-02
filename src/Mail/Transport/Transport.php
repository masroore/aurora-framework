<?php

namespace Aurora\Mail\Transport;

abstract class Transport implements \Swift_Transport
{
    /**
     * The plug-ins registered with the transport.
     *
     * @var array
     */
    public $plugins = [];

    public function isStarted()
    {
        return true;
    }

    public function start()
    {
        return true;
    }

    public function stop()
    {
        return true;
    }

    public function ping()
    {
        return true;
    }

    /**
     * Register a plug-in with the transport.
     */
    public function registerPlugin(\Swift_Events_EventListener $plugin): void
    {
        $this->plugins[] = $plugin;
    }

    /**
     * Iterate through registered plugins and execute plugins' methods.
     */
    protected function beforeSendPerformed(\Swift_Mime_SimpleMessage $message): void
    {
        $event = new \Swift_Events_SendEvent($this, $message);

        foreach ($this->plugins as $plugin) {
            if (method_exists($plugin, 'beforeSendPerformed')) {
                $plugin->beforeSendPerformed($event);
            }
        }
    }

    /**
     * Iterate through registered plugins and execute plugins' methods.
     */
    protected function sendPerformed(\Swift_Mime_SimpleMessage $message): void
    {
        $event = new \Swift_Events_SendEvent($this, $message);

        foreach ($this->plugins as $plugin) {
            if (method_exists($plugin, 'sendPerformed')) {
                $plugin->sendPerformed($event);
            }
        }
    }

    /**
     * Get the number of recipients.
     *
     * @return int
     */
    protected function numberOfRecipients(\Swift_Mime_SimpleMessage $message)
    {
        return \count(array_merge(
            (array)$message->getTo(),
            (array)$message->getCc(),
            (array)$message->getBcc()
        ));
    }
}
