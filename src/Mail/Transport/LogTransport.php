<?php

namespace Aurora\Mail\Transport;

use Psr\Log\LoggerInterface;

class LogTransport extends Transport
{
    /**
     * The Logger instance.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Create a new log transport instance.
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function send(\Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $this->logger->debug($this->getMimeEntityString($message));

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }

    /**
     * Get a loggable string out of a Swiftmailer entity.
     *
     * @return string
     */
    protected function getMimeEntityString(\Swift_Mime_SimpleMimeEntity $entity)
    {
        $string = $entity->getHeaders() . \PHP_EOL . $entity->getBody();

        foreach ($entity->getChildren() as $children) {
            $string .= \PHP_EOL . \PHP_EOL . $this->getMimeEntityString($children);
        }

        return $string;
    }
}
