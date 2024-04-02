<?php

namespace Aurora;

use Aurora\HttpFoundation\AuroraResponse;
use Eloquent;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class Response
{
    /**
     * The content of the response.
     */
    public mixed $content;

    /**
     * The Symfony HttpFoundation Response instance.
     */
    public AuroraResponse $foundation;

    private bool $doNotCache;

    /**
     * Create a new response instance.
     */
    public function __construct($content, int $status = 200, array $headers = [])
    {
        $this->content = $content;

        $this->foundation = new AuroraResponse('', $status, $headers);
    }

    /**
     * Render the response when cast to string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * Create a new response instance.
     *
     * <code>
     *        // Create a response instance with string content
     *        return Response::make(json_encode($user));
     *
     *        // Create a response instance with a given status
     *        return Response::make('Not Found', 404);
     *
     *        // Create a response with some custom headers
     *        return Response::make(json_encode($user), 200, array('header' => 'value'));
     * </code>
     */
    public static function make($content, int $status = 200, array $headers = []): self
    {
        return new static($content, $status, $headers);
    }

    /**
     * Create a new response instance containing a view.
     *
     * <code>
     *        // Create a response instance with a view
     *        return Response::view('home.index');
     *
     *        // Create a response instance with a view and data
     *        return Response::view('home.index', array('name' => 'Taylor'));
     * </code>
     */
    public static function view(string $view, array $data = []): self
    {
        return new static(View::make($view, $data));
    }

    /**
     * Create a new JSON response.
     *
     * <code>
     *        // Create a response instance with JSON
     *        return Response::json($data, 200, array('header' => 'value'));
     * </code>
     */
    public static function json($data, int $status = 200, array $headers = [], int $json_options = 0): self
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';

        return new static(json_encode($data, $json_options), $status, $headers);
    }

    /**
     * Create a new XML response.
     *
     * <code>
     *        // Create a response instance with XML
     *        return Response::xml($view, $data, 200, array('header' => 'value'));
     * </code>
     *
     * @param mixed $data
     *
     * @return Response
     */
    public static function xml(string $view, array $data = [], int $status = 200, array $headers = [])
    {
        $headers['Content-Type'] = 'application/xml; charset=utf-8';

        return new static(View::make($view, $data), $status, $headers);
    }

    /**
     * Create a new JSONP response.
     *
     * <code>
     *        // Create a response instance with JSONP
     *        return Response::jsonp('myFunctionCall', $data, 200, array('header' => 'value'));
     * </code>
     *
     * @param int   $status
     * @param array $headers
     *
     * @return Response
     */
    public static function jsonp($callback, $data, $status = 200, $headers = [])
    {
        $headers['Content-Type'] = 'application/javascript; charset=utf-8';

        return new static($callback . '(' . json_encode($data) . ');', $status, $headers);
    }

    /**
     * Create a new response of JSON'd Eloquent models.
     *
     * <code>
     *        // Create a new response instance with Eloquent models
     *        return Response::eloquent($data, 200, array('header' => 'value'));
     * </code>
     *
     * @param array|\Eloquent $data
     * @param int             $status
     *
     * @return Response
     */
    public static function eloquent($data, $status = 200, array $headers = [])
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';

        return new static(eloquent_to_json($data), $status, $headers);
    }

    /**
     * Create a new error response instance.
     *
     * The response status code will be set using the specified code.
     *
     * The specified error should match a view in your views/error directory.
     *
     * <code>
     *        // Create a 404 response
     *        return Response::error('404');
     *
     *        // Create a 404 response with data
     *        return Response::error('404', array('message' => 'Not Found'));
     * </code>
     *
     * @param int   $code
     * @param array $data
     *
     * @return Response
     */
    public static function error($code, $data = [])
    {
        return new static(View::make('error.' . $code, $data), $code);
    }

    /**
     * Create a new download response instance.
     *
     * <code>
     *        // Create a download response to a given file
     *        return Response::download('path/to/file.jpg');
     *
     *        // Create a download response with a given file name
     *        return Response::download('path/to/file.jpg', 'your_file.jpg');
     * </code>
     *
     * @param string $path
     * @param string $name
     * @param array  $headers
     *
     * @return Response
     */
    public static function download($path, $name = null, $headers = [])
    {
        if (null === $name) {
            $name = basename($path);
        }

        // We'll set some sensible default headers, but merge the array given to
        // us so that the developer has the chance to override any of these
        // default headers with header values of their own liking.
        $headers = array_merge([
            'Content-Description' => 'File Transfer',
            'Content-Type' => File::mime(File::extension($path)),
            'Content-Transfer-Encoding' => 'binary',
            'Expires' => 0,
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Pragma' => 'public',
            'Content-Length' => File::size($path),
        ], $headers);

        // Once we create the response, we need to set the content disposition
        // header on the response based on the file's name. We'll pass this
        // off to the HttpFoundation and let it create the header text.
        $response = new static(File::get($path), 200, $headers);

        // If the Content-Disposition header has already been set by the
        // merge above, then do not override it with out generated one.
        if (!isset($headers['Content-Disposition'])) {
            $d = $response->disposition(Str::ascii($name));
            $response = $response->header('Content-Disposition', $d);
        }

        return $response;
    }

    /**
     * Create the proper Content-Disposition header.
     *
     * @param string $file
     *
     * @return string
     */
    public function disposition($file)
    {
        $type = ResponseHeaderBag::DISPOSITION_ATTACHMENT;

        return $this->foundation->headers->makeDisposition($type, $file);
    }

    /**
     * Add a header to the array of response headers.
     *
     * @param string $name
     * @param string $value
     *
     * @return Response
     */
    public function header($name, $value)
    {
        $this->foundation->headers->set($name, $value);

        return $this;
    }

    /**
     * Prepare a response from the given value.
     *
     * @return Response
     */
    public static function prepare($response)
    {
        // We will need to force the response to be a string before closing
        // the session since the developer may be utilizing the session
        // within the view, and we can't age it until rendering.
        if (!$response instanceof self) {
            $response = new static($response);
        }

        return $response;
    }

    /**
     * Send the headers and content of the response to the browser.
     */
    public function send(): void
    {
        $this->cookies();

        $this->foundation->prepare(Request::foundation());

        $this->foundation->send();
    }

    /**
     * Get the HttpFoundation Response headers.
     *
     * @return ResponseParameterBag
     */
    public function headers()
    {
        return $this->foundation->headers;
    }

    /**
     * Send all of the response headers to the browser.
     */
    public function send_headers(): void
    {
        $this->foundation->prepare(Request::foundation());

        $this->foundation->sendHeaders();
    }

    /**
     * Get / set the response status code.
     *
     * @param int $status
     */
    public function status($status = null)
    {
        if (null === $status) {
            return $this->foundation->getStatusCode();
        }

        $this->foundation->setStatusCode($status);

        return $this;
    }

    /**
     * Convert the content of the Response to a string and return it.
     *
     * @return string
     */
    public function render()
    {
        // If the content is a stringable object, we'll go ahead and call
        // the toString method so that we can get the string content of
        // the content object. Otherwise we'll just cast to string.
        if (str_object($this->content)) {
            $this->content = $this->content->__toString();
        } else {
            $this->content = (string)$this->content;
        }

        // Once we obtain the string content, we can set the content on
        // the HttpFoundation's Response instance in preparation for
        // sending it back to client browser when all is finished.
        $this->foundation->setContent($this->content);

        return $this->content;
    }

    /**
     * @return AuroraResponse
     */
    public function getFoundation()
    {
        return $this->foundation;
    }

    public function getDoNotCache(): bool
    {
        return $this->doNotCache;
    }

    public function setDoNotCache(bool $doNotCache): void
    {
        $this->doNotCache = $doNotCache;
    }

    /**
     * Set the cookies on the HttpFoundation Response.
     */
    protected function cookies(): void
    {
        $ref = new \ReflectionClass('Symfony\Component\HttpFoundation\Cookie');

        // All of the cookies for the response are actually stored on the
        // Cookie class until we're ready to send the response back to
        // the browser. This allows our cookies to be set easily.
        foreach (Cookie::$jar as $name => $cookie) {
            $config = array_values($cookie);

            $this->headers()->setCookie($ref->newInstanceArgs($config));
        }
    }
}
