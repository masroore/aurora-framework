<?php

namespace Aurora\Mail\Transport;

use Aws\Ses\SesClient;

class SesTransport extends Transport
{
    /**
     * The Amazon SES instance.
     *
     * @var SesClient
     */
    protected $ses;

    /**
     * The Amazon SES transmission options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * Create a new SES transport instance.
     *
     * @param array $options
     */
    public function __construct(SesClient $ses, $options = [])
    {
        $this->ses = $ses;
        $this->options = $options;
    }

    public function send(\Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $result = $this->ses->sendRawEmail(
            array_merge(
                $this->options,
                [
                    'Source' => key($message->getSender() ?: $message->getFrom()),
                    'RawMessage' => [
                        'Data' => $message->toString(),
                    ],
                ]
            )
        );

        $message->getHeaders()->addTextHeader('X-SES-Message-ID', $result->get('MessageId'));

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }

    /**
     * Get the transmission options being used by the transport.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Set the transmission options being used by the transport.
     *
     * @return array
     */
    public function setOptions(array $options)
    {
        return $this->options = $options;
    }
}
