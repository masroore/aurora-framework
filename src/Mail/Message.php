<?php

namespace Aurora\Mail;

use Aurora\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class Message
{
    /**
     * The Symfony Email instance.
     */
    protected Email $message;

    /**
     * Create a new message instance.
     */
    public function __construct(Email $message)
    {
        $this->message = $message;
    }

    /**
     * Dynamically pass missing methods to the Swift instance.
     */
    public function __call(string $method, array $parameters)
    {
        $callable = [$this->message, $method];

        return \call_user_func_array($callable, $parameters);
    }

    /**
     * Add a "from" address to the message.
     *
     * @return $this
     */
    public function from(array|string $address, ?string $name = null): static
    {
        \is_array($address)
            ? $this->message->from(...$address)
            : $this->message->from(new Address($address, (string)$name));

        return $this;
    }

    /**
     * Set the "sender" of the message.
     *
     * @return $this
     */
    public function sender(array|string $address, ?string $name = null): static
    {
        \is_array($address)
            ? $this->message->sender(...$address)
            : $this->message->sender(new Address($address, (string)$name));

        return $this;
    }

    /**
     * Set the "return path" of the message.
     *
     * @return $this
     */
    public function returnPath(string $address): static
    {
        $this->message->returnPath($address);

        return $this;
    }

    /**
     * Add a recipient to the message.
     *
     * @param array|string $address
     * @param string|null  $name
     * @param bool         $override
     *
     * @return $this
     */
    public function to($address, $name = null, $override = false)
    {
        if ($override) {
            \is_array($address)
                ? $this->message->to(...$address)
                : $this->message->to(new Address($address, (string)$name));

            return $this;
        }

        return $this->addAddresses($address, $name, 'To');
    }

    /**
     * Add a recipient to the message.
     *
     * @return $this
     */
    protected function addAddresses(array|string $address, string $name, string $type): static
    {
        if (\is_array($address)) {
            $type = lcfirst($type);

            $addresses = collect($address)->map(static function ($address, $key) {
                if (\is_string($key) && \is_string($address)) {
                    return new Address($key, $address);
                }

                if (\is_array($address)) {
                    return new Address($address['email'] ?? $address['address'], $address['name'] ?? null);
                }

                if (null === $address) {
                    return new Address($key);
                }

                return $address;
            })->all();

            $this->message->{"{$type}"}(...$addresses);
        } else {
            $this->message->{"add{$type}"}(new Address($address, (string)$name));
        }

        return $this;
    }

    /**
     * Remove all "to" addresses from the message.
     *
     * @return $this
     */
    public function forgetTo(): static
    {
        if ($header = $this->message->getHeaders()->get('To')) {
            $this->addAddressDebugHeader('X-To', $this->message->getTo());

            $header->setAddresses([]);
        }

        return $this;
    }

    /**
     * Add an address debug header for a list of recipients.
     *
     * @param \Symfony\Component\Mime\Address[] $addresses
     *
     * @return $this
     */
    protected function addAddressDebugHeader(string $header, array $addresses): static
    {
        $this->message->getHeaders()->addTextHeader(
            $header,
            implode(', ', array_map(static fn ($a) => $a->toString(), $addresses)),
        );

        return $this;
    }

    /**
     * Add a carbon copy to the message.
     *
     * @param array|string $address
     * @param string|null  $name
     * @param bool         $override
     *
     * @return $this
     */
    public function cc($address, $name = null, $override = false)
    {
        if ($override) {
            \is_array($address)
                ? $this->message->cc(...$address)
                : $this->message->cc(new Address($address, (string)$name));

            return $this;
        }

        return $this->addAddresses($address, $name, 'Cc');
    }

    /**
     * Remove all carbon copy addresses from the message.
     *
     * @return $this
     */
    public function forgetCc(): static
    {
        if ($header = $this->message->getHeaders()->get('Cc')) {
            $this->addAddressDebugHeader('X-Cc', $this->message->getCC());

            $header->setAddresses([]);
        }

        return $this;
    }

    /**
     * Add a blind carbon copy to the message.
     *
     * @return $this
     */
    public function bcc(array|string $address, ?string $name = null, bool $override = false): static
    {
        if ($override) {
            \is_array($address)
                ? $this->message->bcc(...$address)
                : $this->message->bcc(new Address($address, (string)$name));

            return $this;
        }

        return $this->addAddresses($address, $name, 'Bcc');
    }

    /**
     * Remove all of the blind carbon copy addresses from the message.
     *
     * @return $this
     */
    public function forgetBcc(): static
    {
        if ($header = $this->message->getHeaders()->get('Bcc')) {
            $this->addAddressDebugHeader('X-Bcc', $this->message->getBcc());

            $header->setAddresses([]);
        }

        return $this;
    }

    /**
     * Add a "reply to" address to the message.
     *
     * @param array|string $address
     * @param string|null  $name
     *
     * @return $this
     */
    public function replyTo($address, $name = null)
    {
        return $this->addAddresses($address, $name, 'ReplyTo');
    }

    /**
     * Set the subject of the message.
     *
     * @return $this
     */
    public function subject(string $subject): static
    {
        $this->message->subject($subject);

        return $this;
    }

    /**
     * Set the message priority level.
     *
     * @return $this
     */
    public function priority(int $level): static
    {
        $this->message->priority($level);

        return $this;
    }

    /**
     * Attach in-memory data as an attachment.
     *
     * @return $this
     */
    public function attachData(string $data, string $name, array $options = []): static
    {
        $this->message->attach($data, $name, $options['mime'] ?? null);

        return $this;
    }

    /**
     * Attach a file to the message.
     *
     * @param string $file
     *
     * @return $this
     */
    public function attach($file, array $options = [])
    {
        $this->message->attachFromPath($file, $options['as'] ?? null, $options['mime'] ?? null);

        return $this;
    }

    /**
     * Embed in-memory data in the message and get the CID.
     */
    public function embedData(string $data, string $name, ?string $contentType = null): string
    {
        $this->message->embed($data, $name, $contentType);

        return "cid:$name";
    }

    /**
     * Embed a file in the message and get the CID.
     */
    public function embed(string $file): string
    {
        $cid = Str::random(10);
        $this->message->embedFromPath($file, $cid);

        return "cid:$cid";
    }

    /**
     * Get the underlying Symfony Email instance.
     */
    public function getSymfonyMessage(): Email
    {
        return $this->message;
    }
}
