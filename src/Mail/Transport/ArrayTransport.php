<?php

namespace Aurora\Mail\Transport;

use Illuminate\Support\Collection;

class ArrayTransport extends Transport
{
    /**
     * The collection of Swift Messages.
     *
     * @var Collection
     */
    protected $messages;

    /**
     * Create a new array transport instance.
     */
    public function __construct()
    {
        $this->messages = new Collection();
    }

    public function send(\Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $this->messages[] = $message;

        return $this->numberOfRecipients($message);
    }

    /**
     * Retrieve the collection of messages.
     *
     * @return Collection
     */
    public function messages()
    {
        return $this->messages;
    }

    /**
     * Clear all of the messages from the local collection.
     *
     * @return Collection
     */
    public function flush()
    {
        return $this->messages = new Collection();
    }
}
