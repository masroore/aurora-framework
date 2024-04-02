<?php

namespace Aurora;

use Aurora\Mail\Mailer as AuroraMailer;
use Aurora\Mail\TransportManager;
use Symfony\Component\Mailer\Transport\TransportInterface;

class Mail
{
    private static AuroraMailer $mailer;

    private static TransportInterface $mail_transport;

    public static function boot(): void
    {
        self::registerMailer();

        self::$mailer = new AuroraMailer(self::$mail_transport);
    }

    private static function registerMailer(): void
    {
        $config = Config::get('mail');
        self::registerMailTransport($config);

        // Once we have the transporter registered, we will register the actual Swift
        // mailer instance, passing in the transport instances, which allows us to
        // override this transporter instances during app start-up if necessary.
        // self::$swift_mailer = new Swift_Mailer(self::$mail_transport);
    }

    /**
     * Register the Swift Transport instance.
     */
    private static function registerMailTransport(array $config): void
    {
        self::$mail_transport = match ($config['driver']) {
            'mail', 'smtp' => TransportManager::createSmtpTransport(),
            'sendmail' => TransportManager::createSendmailTransport(),
            'mailgun' => TransportManager::createMailgunTransport(),
            'ses' => TransportManager::createSesTransport(),
            'mandrill' => TransportManager::createMandrillTransport(),
            'log' => TransportManager::createLogTransport(),
            default => throw new \InvalidArgumentException('Invalid mail driver.'),
        };
    }

    /**
     * Get the default mail driver name.
     */
    public static function getDefaultDriver(): string
    {
        return Config::get('mail.driver');
    }

    /**
     * Set the default mail driver name.
     */
    public function setDefaultDriver(string $name): void
    {
        Config::set('mail.driver', $name);
    }
}
