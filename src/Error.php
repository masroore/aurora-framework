<?php

namespace Aurora;

use ErrorException;

class Error
{
    /**
     * Handle a native PHP error as an ErrorException.
     *
     * @param int    $code
     * @param string $error
     * @param string $file
     * @param int    $line
     */
    public static function native($code, $error, $file, $line)
    {
        if (0 === error_reporting()) {
            return;
        }
        // For a PHP error, we'll create an ErrorException and then feed that
        // exception to the exception method, which will create a simple view
        // of the exception details for the developer.
        $exception = new \ErrorException($error, $code, 0, $file, $line);
        if (\in_array($code, Config::get('error.ignore'), true)) {
            return static::log($exception);
        }
        static::exception($exception);
    }

    /**
     * Log an exception.
     *
     * @param Exception $exception
     */
    public static function log($exception): void
    {
        if (Config::get('error.log')) {
            \call_user_func(Config::get('error.logger'), $exception);
        }
    }

    /**
     * Handle the PHP shutdown event.
     */
    public static function shutdown(): void
    {
        // If a fatal error occurred that we have not handled yet, we will
        // create an ErrorException and feed it to the exception handler,
        // as it will not yet have been handled.
        $error = error_get_last();

        if (null !== $error) {
            extract($error, \EXTR_SKIP);

            static::exception(new \ErrorException($message, $type, 0, $file, $line), false);
        }
    }

    /**
     * Handle an exception and display the exception report.
     *
     * @param Exception $exception
     * @param bool      $trace
     */
    public static function exception($exception, $trace = true): void
    {
        static::log($exception);

        ob_get_level() && ob_end_clean();

        $message = $exception->getMessage();

        // For Aurora view errors we want to show a prettier error:
        $file = $exception->getFile();

        if (str_contains($exception->getFile(), 'eval()') && str_contains($exception->getFile(), 'aurora' . DS . 'view.php')) {
            $message = 'Error rendering view: [' . View::$last['name'] . ']' . \PHP_EOL . \PHP_EOL . $message;

            $file = View::$last['path'];
        }

        // If detailed errors are enabled, we'll just format the exception into
        // a simple error message and display it on the screen. We don't use a
        // View in case the problem is in the View class.

        if (Config::get('error.detail')) {
            $response_body = <<<'EOT'
                <html>
                    <h2>Unhandled Exception</h2>
                    <h3>Message:</h3>
                    <pre>$message</pre>
                    <h3>Location:</h3>
                	<pre>$file on line $exception->getLine()</pre>
                EOT;

            if ($trace) {
                $response_body .= '<h3>Stack Trace:</h3>
				  <pre>' . $exception->getTraceAsString() . '</pre>';
            }

            $response_body .= '</html>';
            $response = Response::make($response_body, 500);
        }

        // If we're not using detailed error messages, we'll use the event
        // system to get the response that should be sent to the browser.
        // Using events gives the developer more freedom.
        else {
            $response = Event::first('500', [$exception]);

            $response = Response::prepare($response);
        }

        $response->render();
        $response->send();
        $response->foundation->finish();

        exit(1);
    }
}
