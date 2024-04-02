<?php

namespace Aurora\Mail;

use Aurora\Arr;
use Aurora\Config;
use Aurora\IoC;
use Aurora\Mail\Transport\ArrayTransport;
use Aurora\Mail\Transport\LogTransport;
use Aurora\Mail\Transport\MailgunTransport;
use Aurora\Mail\Transport\MandrillTransport;
use Aurora\Mail\Transport\SesTransport;
use Aws\Ses\SesClient;
use GuzzleHttp\Client as HttpClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class TransportManager
{
    /**
     * Create the SMTP Swift Transport instance.
     */
    public static function createSmtpTransport(): EsmtpTransport
    {
        $config = Config::get('mail');

        $factory = new EsmtpTransportFactory();

        $scheme = $config['scheme'] ?? null;

        if (!$scheme) {
            $scheme = !empty($config['encryption']) && 'tls' === $config['encryption']
                ? ((465 === $config['port']) ? 'smtps' : 'smtp')
                : '';
        }

        $transport = $factory->create(new Dsn(
            $scheme,
            $config['host'],
            $config['username'] ?? null,
            $config['password'] ?? null,
            $config['port'] ?? null,
            $config
        ));

        return self::configureSmtpTransport($transport, $config);

        /*

        // The Swift SMTP transport instance will allow us to use any SMTP backend
        // for delivering mail such as Sendgrid, Amazon SES, or a custom server
        // a developer has available. We will just pass this configured host.
        $transport = new Swift_SmtpTransport($config['host'], $config['port']);

        if (isset($config['encryption'])) {
            $transport->setEncryption($config['encryption']);
        }

        // Once we have the transport we will check for the presence of a username
        // and password. If we have it we will set the credentials on the Swift
        // transporter instance so that we'll properly authenticate delivery.
        if (isset($config['username'])) {
            $transport->setUsername($config['username']);

            $transport->setPassword($config['password']);
        }

        // Next we will set any stream context options specified for the transport
        // and then return it. The option is not required any may not be inside
        // the configuration array at all so we'll verify that before adding.
        if (isset($config['stream'])) {
            $transport->setStreamOptions($config['stream']);
        }

        return $transport;
        */
    }

    /**
     * Configure the additional SMTP driver options.
     */
    protected static function configureSmtpTransport(EsmtpTransport $transport, array $config): EsmtpTransport
    {
        $stream = $transport->getStream();

        if ($stream instanceof SocketStream) {
            if (isset($config['source_ip'])) {
                $stream->setSourceIp($config['source_ip']);
            }

            if (isset($config['timeout'])) {
                $stream->setTimeout($config['timeout']);
            }
        }

        return $transport;
    }

    /**
     * Create the Sendmail Swift Transport instance.
     */
    public static function createSendmailTransport(): SendmailTransport
    {
        return new SendmailTransport(Config::get('mail.sendmail'));
    }

    /**
     * Create the Amazon SES Swift Transport instance.
     *
     * @return SesTransport
     */
    public static function createSesTransport()
    {
        $config = Config::get('services.ses', []);

        return new SesTransport(
            new SesClient(self::addSesCredentials($config)),
            array_get($config, 'options', [])
        );
    }

    /**
     * Add the SES credentials to the configuration array.
     *
     * @return array
     */
    public static function addSesCredentials(array $config)
    {
        if ($config['key'] && $config['secret']) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return $config;
    }

    /**
     * Create the Mailgun Swift Transport instance.
     *
     * @return MailgunTransport
     */
    public static function createMailgunTransport()
    {
        $config = Config::get('services.mailgun', []);

        return new MailgunTransport(
            self::guzzle($config),
            $config['secret'],
            $config['domain'],
            array_get($config, 'endpoint')
        );
    }

    /**
     * Get a fresh Guzzle HTTP client instance.
     *
     * @return HttpClient
     */
    protected static function guzzle(array $config)
    {
        return new HttpClient(Arr::add(
            array_get($config, 'guzzle', []),
            'connect_timeout',
            60
        ));
    }

    /**
     * Create the Mandrill Swift Transport instance.
     *
     * @return MandrillTransport
     */
    public static function createMandrillTransport()
    {
        $config = Config::get('services.mandrill', []);

        return new MandrillTransport(
            self::guzzle($config),
            $config['secret']
        );
    }

    /**
     * Create an instance of the Log Swift Transport driver.
     *
     * @return LogTransport
     */
    public static function createLogTransport()
    {
        return new LogTransport(IoC::resolve(LoggerInterface::class));
    }

    /**
     * Create an instance of the Array Swift Transport driver.
     *
     * @return ArrayTransport
     */
    public static function createArrayTransport()
    {
        return new ArrayTransport();
    }
}
