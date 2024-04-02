<?php

namespace Aurora\Socialite;

class AuthorizeFailedException extends \RuntimeException
{
    /**
     * Response body.
     *
     * @var array
     */
    public $body;

    /**
     * Constructor.
     */
    public function __construct(string $message, array $body)
    {
        parent::__construct($message, -1);

        $this->body = $body;
    }
}
