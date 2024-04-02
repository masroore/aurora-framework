<?php

namespace Aurora\Mail;

use Aurora\Event;
use Aurora\View;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

class Mailer
{
    /**
     * The global from address and name.
     *
     * @var array
     */
    protected $from;

    /**
     * The QueueManager instance.
     *
     * @var \Illuminate\Queue\QueueManager
     */
    protected $queue;

    /**
     * Indicates if the actual sending is disabled.
     */
    protected bool $pretending = false;

    /**
     * Array of failed recipients.
     */
    protected array $failedRecipients = [];

    /**
     * Array of parsed views containing html and text view name.
     *
     * @var array
     */
    protected $parsedViews = [];

    private TransportInterface $transport;

    /**
     * Create a new Mailer instance.
     */
    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    /**
     * Set the global from address and name.
     */
    public function alwaysFrom(string $address, ?string $name = null): void
    {
        $this->from = compact('address', 'name');
    }

    /**
     * Send a new message when only a plain part.
     */
    public function plain(string $view, array $data, string|\Closure $callback): void
    {
        $this->send(['text' => $view], $data, $callback);
    }

    /**
     * Send a new message using a view.
     */
    public function send(array|string $view, array $data, string|\Closure $callback): void
    {
        // First we need to parse the view, which could either be a string or an array
        // containing both an HTML and plain text versions of the view which should
        // be used when sending an e-mail. We will extract both of them out here.
        [$view, $plain] = $this->parseView($view);

        $data['message'] = $message = $this->createMessage();

        $this->callMessageBuilder($callback, $message);

        // Once we have retrieved the view content for the e-mail we will set the body
        // of this message using the HTML type, which will provide a simple wrapper
        // to creating view based emails that are able to receive arrays of data.
        $this->addContent($message, $view, $plain, $data);

        $message = $message->getSwiftMessage();

        $this->sendSwiftMessage($message);
    }

    /**
     * Parse the given view name or array.
     *
     * @param array|string $view
     *
     * @return array
     */
    protected function parseView($view)
    {
        if (\is_string($view)) {
            return [$view, null];
        }

        // If the given view is an array with numeric keys, we will just assume that
        // both a "pretty" and "plain" view were provided, so we will return this
        // array as is, since must should contain both views with numeric keys.
        if (\is_array($view) && isset($view[0])) {
            return $view;
        }

        // If the view is an array, but doesn't contain numeric keys, we will assume
        // the the views are being explicitly specified and will extract them via
        // named keys instead, allowing the developers to use one or the other.
        if (\is_array($view)) {
            return [
                array_get($view, 'html'), array_get($view, 'text'),
            ];
        }

        throw new \InvalidArgumentException('Invalid view.');
    }

    /**
     * Create a new message instance.
     *
     * @return Message
     */
    protected function createMessage()
    {
        $message = new Message(new Email());

        // If a global from address has been specified we will set it on every message
        // instances so the developer does not have to repeat themselves every time
        // they create a new message. We will just go ahead and push the address.
        if (isset($this->from['address'])) {
            $message->from($this->from['address'], $this->from['name']);
        }

        return $message;
    }

    /**
     * Call the provided message builder.
     *
     * @param \Closure|string $callback
     * @param Message         $message
     */
    protected function callMessageBuilder($callback, $message)
    {
        if ($callback instanceof \Closure) {
            return \call_user_func($callback, $message);
        }
        if (\is_string($callback)) {
            return $this->container[$callback]->mail($message);
        }

        throw new \InvalidArgumentException('Callback is not valid.');
    }

    /**
     * Add the content to a given message.
     *
     * @param Message $message
     * @param string  $view
     * @param string  $plain
     * @param array   $data
     */
    protected function addContent($message, $view, $plain, $data): void
    {
        if (isset($view)) {
            $message->setBody($this->getView($view, $data), 'text/html');
        }

        if (isset($plain)) {
            $message->addPart($this->getView($plain, $data), 'text/plain');
        }
    }

    /**
     * Render the given view.
     *
     * @param string $view
     * @param array  $data
     *
     * @return string
     */
    protected function getView($view, $data)
    {
        return View::make($view, $data)->render();
    }

    /**
     * Send a Swift Message instance.
     */
    protected function sendSwiftMessage(Email $message): void
    {
        Event::fire('mailer.sending', [$message]);

        if (!$this->pretending) {
            try {
                $this->transport->send($message, Envelope::create($message));
            } finally {
            }
        } elseif (isset($this->logger)) {
            $this->logMessage($message);
        }
    }

    /**
     * Log that a message was sent.
     */
    protected function logMessage(Email $message): void
    {
        $emails = implode(', ', array_keys($message->getTo()));

        $this->logger->info("Pretending to mail message to: {$emails}");
    }

    /**
     * Queue a new e-mail message for sending on the given queue.
     */
    public function queueOn(string $queue, array|string $view, array $data, string|\Closure $callback)
    {
        return $this->queue($view, $data, $callback, $queue);
    }

    /**
     * Queue a new e-mail message for sending.
     */
    public function queue(array|string $view, array $data, string|\Closure $callback, ?string $queue = null)
    {
        $callback = $this->buildQueueCallable($callback);

        return $this->queue->push('mailer@handleQueuedMessage', compact('view', 'data', 'callback'), $queue);
    }

    /**
     * Build the callable for a queued e-mail job.
     */
    protected function buildQueueCallable($callback)
    {
        if (!$callback instanceof \Closure) {
            return $callback;
        }

        return serialize(new SerializableClosure($callback));
    }

    /**
     * Queue a new e-mail message for sending after (n) seconds on the given queue.
     *
     * @param string          $queue
     * @param int             $delay
     * @param array|string    $view
     * @param \Closure|string $callback
     */
    public function laterOn($queue, $delay, $view, array $data, $callback)
    {
        return $this->later($delay, $view, $data, $callback, $queue);
    }

    /**
     * Queue a new e-mail message for sending after (n) seconds.
     *
     * @param int             $delay
     * @param array|string    $view
     * @param \Closure|string $callback
     * @param string          $queue
     */
    public function later($delay, $view, array $data, $callback, $queue = null)
    {
        $callback = $this->buildQueueCallable($callback);

        return $this->queue->later($delay, 'mailer@handleQueuedMessage', compact('view', 'data', 'callback'), $queue);
    }

    /**
     * Handle a queued e-mail message job.
     *
     * @param \Aurora\Queue\Jobs\Job $job
     * @param array                  $data
     */
    public function handleQueuedMessage($job, $data): void
    {
        $this->send($data['view'], $data['data'], $this->getQueuedCallable($data));

        $job->delete();
    }

    /**
     * Get the true callable for a queued e-mail message.
     */
    protected function getQueuedCallable(array $data)
    {
        if (str_contains($data['callback'], 'SerializableClosure')) {
            return with(unserialize($data['callback']))->getClosure();
        }

        return $data['callback'];
    }

    /**
     * Tell the mailer to not really send messages.
     *
     * @param bool $value
     */
    public function pretend($value = true): void
    {
        $this->pretending = $value;
    }

    /**
     * Check if the mailer is pretending to send messages.
     *
     * @return bool
     */
    public function isPretending()
    {
        return $this->pretending;
    }

    /**
     * Get the array of failed recipients.
     *
     * @return array
     */
    public function failures()
    {
        return $this->failedRecipients;
    }

    /**
     * Set the queue manager instance.
     *
     * @param \Aurora\Queue\QueueManager $queue
     *
     * @return $this
     */
    public function setQueue(QueueManager $queue)
    {
        $this->queue = $queue;

        return $this;
    }

    /**
     * Set the Symfony Transport instance.
     */
    public function setSymfonyTransport(TransportInterface $transport): void
    {
        $this->transport = $transport;
    }

    /**
     * Get the Symfony Transport instance.
     */
    public function getSymfonyTransport(): TransportInterface
    {
        return $this->transport;
    }
}
