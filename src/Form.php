<?php

namespace Aurora;

class Form
{
    /**
     * All of the label names that have been created.
     *
     * @var array
     */
    public static $labels = [];

    /**
     * The registered custom macros.
     *
     * @var array
     */
    public static $macros = [];

    /**
     * The reserved form open attributes.
     *
     * @var array
     */
    protected static $reserved = ['method', 'url', 'route', 'action', 'files'];

    /**
     * The form methods that should be spoofed, in uppercase.
     *
     * @var array
     */
    protected static $spoofedMethods = ['DELETE', 'PATCH', 'PUT'];

    /**
     * Dynamically handle calls to custom macros.
     *
     * @param string $method
     * @param array  $parameters
     */
    public static function __callStatic($method, $parameters)
    {
        if (isset(static::$macros[$method])) {
            return \call_user_func_array(static::$macros[$method], $parameters);
        }

        throw new \Exception("Method [$method] does not exist.");
    }

    /**
     * Registers a custom macro.
     *
     * @param string   $name
     * @param \Closure $macro
     */
    public static function macro($name, $macro): void
    {
        static::$macros[$name] = $macro;
    }

    /**
     * Open a HTML form with a HTTPS action URI.
     *
     * @param string $action
     * @param string $method
     * @param array  $attributes
     *
     * @return string
     */
    public static function open_secure($action = null, $method = 'POST', $attributes = [])
    {
        return static::open($action, $method, $attributes, true);
    }

    /**
     * Open a HTML form.
     *
     * <code>
     *        // Open a "POST" form to the current request URI
     *        echo Form::open();
     *
     *        // Open a "POST" form to a given URI
     *        echo Form::open('user/profile');
     *
     *        // Open a "PUT" form to a given URI
     *        echo Form::open('user/profile', 'put');
     *
     *        // Open a form that has HTML attributes
     *        echo Form::open('user/profile', 'post', array('class' => 'profile'));
     * </code>
     *
     * @param array $options
     *
     * @return string
     */
    public static function open($options = [])
    {
        $method = array_get($options, 'method', 'post');

        // We need to extract the proper method from the attributes. If the method is
        // something other than GET or POST we'll use POST since we will spoof the
        // actual method since forms don't support the reserved methods in HTML.
        $attributes['method'] = static::method($method);

        $attributes['action'] = static::action($options);

        // If a character encoding has not been specified in the attributes, we will
        // use the default encoding as specified in the application configuration
        // file for the "accept-charset" attribute.
        if (!\array_key_exists('accept-charset', $attributes)) {
            $attributes['accept-charset'] = Config::get('app.encoding');
        }

        // If the method is PUT, PATCH or DELETE we will need to add a spoofer hidden
        // field that will instruct the Symfony request to pretend the method is a
        // different method than it actually is, for convenience from the forms.
        $append = static::getAppendage($method);

        if (isset($options['files']) && $options['files']) {
            $options['enctype'] = 'multipart/form-data';
        }

        // Finally we're ready to create the final form HTML field. We will attribute
        // format the array of attributes. We will also add on the appendage which
        // is used to spoof requests for this PUT, PATCH, etc. methods on forms.
        $attributes = array_merge($attributes, array_except($options, static::$reserved));

        // Finally, we will concatenate all of the attributes into a single string so
        // we can build out the final form open statement. We'll also append on an
        // extra value for the hidden _method field if it's needed for the form.
        $attributes = HTML::attributes($attributes);

        return '<form' . $attributes . '>' . $append;
    }

    /**
     * Create a HTML hidden input element.
     *
     * @param string $name
     * @param string $value
     * @param array  $attributes
     *
     * @return string
     */
    public static function hidden($name, $value = null, $attributes = [])
    {
        return static::input('hidden', $name, $value, $attributes);
    }

    /**
     * Create a HTML input element.
     *
     * <code>
     *        // Create a "text" input element named "email"
     *        echo Form::input('text', 'email');
     *
     *        // Create an input element with a specified default value
     *        echo Form::input('text', 'email', 'example@gmail.com');
     * </code>
     *
     * @param string     $type
     * @param string     $name
     * @param array      $attributes
     * @param mixed|null $value
     *
     * @return string
     */
    public static function input($type, $name, $value = null, $attributes = [])
    {
        $name = $attributes['name'] ?? $name;

        $id = static::id($name, $attributes);

        $attributes = array_merge($attributes, compact('type', 'name', 'value', 'id'));

        return '<input' . HTML::attributes($attributes) . '>';
    }

    /**
     * Open a HTML form that accepts file uploads with a HTTPS action URI.
     *
     * @param string $action
     * @param string $method
     * @param array  $attributes
     *
     * @return string
     */
    public static function open_secure_for_files($action = null, $method = 'POST', $attributes = [])
    {
        return static::open_for_files($action, $method, $attributes, true);
    }

    /**
     * Open a HTML form that accepts file uploads.
     *
     * @param string $action
     * @param string $method
     * @param array  $attributes
     * @param bool   $https
     *
     * @return string
     */
    public static function open_for_files($action = null, $method = 'POST', $attributes = [], $https = null)
    {
        $attributes['enctype'] = 'multipart/form-data';

        return static::open($action, $method, $attributes, $https);
    }

    /**
     * Close a HTML form.
     *
     * @return string
     */
    public static function close()
    {
        return '</form>';
    }

    /**
     * Generate a hidden field containing the current CSRF token.
     *
     * @return string
     */
    public static function token()
    {
        return static::input('hidden', Session::CSRF_TOKEN, Session::token());
    }

    /**
     * Create a HTML label element.
     *
     * <code>
     *        // Create a label for the "email" input element
     *        echo Form::label('email', 'E-Mail Address');
     * </code>
     *
     * @param string $name
     * @param string $value
     * @param array  $attributes
     *
     * @return string
     */
    public static function label($name, $value, $attributes = [], $escape_html = true)
    {
        static::$labels[] = $name;

        $attributes = HTML::attributes($attributes);

        if ($escape_html) {
            $value = HTML::entities($value);
        }

        return '<label for="' . $name . '"' . $attributes . '>' . $value . '</label>';
    }

    /**
     * Create a HTML text input element.
     *
     * @param string $name
     * @param string $value
     * @param array  $attributes
     *
     * @return string
     */
    public static function text($name, $value = null, $attributes = [])
    {
        return static::input('text', $name, $value, $attributes);
    }

    /**
     * Create a HTML password input element.
     *
     * @param string $name
     * @param array  $attributes
     *
     * @return string
     */
    public static function password($name, $attributes = [])
    {
        return static::input('password', $name, null, $attributes);
    }

    /**
     * Create a HTML search input element.
     *
     * @param string $name
     * @param string $value
     * @param array  $attributes
     *
     * @return string
     */
    public static function search($name, $value = null, $attributes = [])
    {
        return static::input('search', $name, $value, $attributes);
    }

    /**
     * Create a HTML email input element.
     *
     * @param string $name
     * @param string $value
     * @param array  $attributes
     *
     * @return string
     */
    public static function email($name, $value = null, $attributes = [])
    {
        return static::input('email', $name, $value, $attributes);
    }

    /**
     * Create a HTML telephone input element.
     *
     * @param string $name
     * @param string $value
     * @param array  $attributes
     *
     * @return string
     */
    public static function telephone($name, $value = null, $attributes = [])
    {
        return static::input('tel', $name, $value, $attributes);
    }

    /**
     * Create a HTML URL input element.
     *
     * @param string $name
     * @param string $value
     * @param array  $attributes
     *
     * @return string
     */
    public static function url($name, $value = null, $attributes = [])
    {
        return static::input('url', $name, $value, $attributes);
    }

    /**
     * Create a HTML number input element.
     *
     * @param string $name
     * @param string $value
     * @param array  $attributes
     *
     * @return string
     */
    public static function number($name, $value = null, $attributes = [])
    {
        return static::input('number', $name, $value, $attributes);
    }

    /**
     * Create a HTML date input element.
     *
     * @param string $name
     * @param string $value
     * @param array  $attributes
     *
     * @return string
     */
    public static function date($name, $value = null, $attributes = [])
    {
        return static::input('date', $name, $value, $attributes);
    }

    /**
     * Create a HTML file input element.
     *
     * @param string $name
     * @param array  $attributes
     *
     * @return string
     */
    public static function file($name, $attributes = [])
    {
        return static::input('file', $name, null, $attributes);
    }

    /**
     * Create a HTML textarea element.
     *
     * @param string $name
     * @param string $value
     * @param array  $attributes
     *
     * @return string
     */
    public static function textarea($name, $value = '', $attributes = [])
    {
        $attributes['name'] = $name;

        $attributes['id'] = static::id($name, $attributes);

        if (!isset($attributes['rows'])) {
            $attributes['rows'] = 10;
        }

        if (!isset($attributes['cols'])) {
            $attributes['cols'] = 50;
        }

        return '<textarea' . HTML::attributes($attributes) . '>' . HTML::entities($value) . '</textarea>';
    }

    /**
     * Create a HTML select element.
     *
     * <code>
     *        // Create a HTML select element filled with options
     *        echo Form::select('sizes', array('S' => 'Small', 'L' => 'Large'));
     *
     *        // Create a select element with a default selected value
     *        echo Form::select('sizes', array('S' => 'Small', 'L' => 'Large'), 'L');
     * </code>
     *
     * @param string $name
     * @param array  $options
     * @param string $selected
     * @param array  $attributes
     *
     * @return string
     */
    public static function select($name, $options = [], $selected = null, $attributes = [])
    {
        $attributes['id'] = static::id($name, $attributes);

        $attributes['name'] = $name;

        $html = [];

        foreach ($options as $value => $display) {
            if (\is_array($display)) {
                $html[] = static::optgroup($display, $value, $selected);
            } else {
                $html[] = static::option($value, $display, $selected);
            }
        }

        return '<select' . HTML::attributes($attributes) . '>' . implode('', $html) . '</select>';
    }

    /**
     * Create a HTML checkbox input element.
     *
     * <code>
     *        // Create a checkbox element
     *        echo Form::checkbox('terms', 'yes');
     *
     *        // Create a checkbox that is selected by default
     *        echo Form::checkbox('terms', 'yes', true);
     * </code>
     *
     * @param string $name
     * @param string $value
     * @param bool   $checked
     * @param array  $attributes
     *
     * @return string
     */
    public static function checkbox($name, $value = 1, $checked = false, $attributes = [])
    {
        return static::checkable('checkbox', $name, $value, $checked, $attributes);
    }

    /**
     * Create a HTML radio button input element.
     *
     * <code>
     *        // Create a radio button element
     *        echo Form::radio('drinks', 'Milk');
     *
     *        // Create a radio button that is selected by default
     *        echo Form::radio('drinks', 'Milk', true);
     * </code>
     *
     * @param string $name
     * @param string $value
     * @param bool   $checked
     * @param array  $attributes
     *
     * @return string
     */
    public static function radio($name, $value = null, $checked = false, $attributes = [])
    {
        if (null === $value) {
            $value = $name;
        }

        return static::checkable('radio', $name, $value, $checked, $attributes);
    }

    /**
     * Create a HTML submit input element.
     *
     * @param string $value
     * @param array  $attributes
     *
     * @return string
     */
    public static function submit($value = null, $attributes = [])
    {
        return static::input('submit', null, $value, $attributes);
    }

    /**
     * Create a HTML reset input element.
     *
     * @param string $value
     * @param array  $attributes
     *
     * @return string
     */
    public static function reset($value = null, $attributes = [])
    {
        return static::input('reset', null, $value, $attributes);
    }

    /**
     * Create a HTML image input element.
     *
     * <code>
     *        // Create an image input element
     *        echo Form::image('img/submit.png');
     * </code>
     *
     * @param string $url
     * @param string $name
     * @param array  $attributes
     *
     * @return string
     */
    public static function image($url, $name = null, $attributes = [])
    {
        $attributes['src'] = Url::asset($url);

        return static::input('image', $name, null, $attributes);
    }

    /**
     * Create a HTML button element.
     *
     * @param string $value
     * @param array  $attributes
     *
     * @return string
     */
    public static function button($value = null, $attributes = [])
    {
        return '<button' . HTML::attributes($attributes) . '>' . HTML::entities($value) . '</button>';
    }

    /**
     * Get the form appendage for the given method.
     *
     * @param string $method
     *
     * @return string
     */
    protected static function getAppendage($method)
    {
        [$method, $appendage] = [mb_strtoupper($method), ''];

        // If the HTTP method is in this list of spoofed methods, we will attach the
        // method spoofer hidden input to the form. This allows us to use regular
        // form to initiate PUT and DELETE requests in addition to the typical.
        if (\in_array($method, static::$spoofedMethods, true)) {
            $appendage .= static::hidden(Request::spoofer, $method);
        }

        // If the method is something other than GET we will go ahead and attach the
        // CSRF token to the form, as this can't hurt and is convenient to simply
        // always have available on every form the developers creates for them.
        if ('GET' !== $method) {
            $appendage .= static::token();
        }

        return $appendage;
    }

    /**
     * Determine the appropriate request method to use for a form.
     *
     * @param string $method
     *
     * @return string
     */
    protected static function method($method)
    {
        return ('GET' !== $method) ? 'POST' : $method;
    }

    /**
     * Determine the appropriate action parameter to use for a form.
     *
     * If no action is specified, the current request URI will be used.
     *
     * @param array $options
     *
     * @return string
     */
    protected static function action($options = [])
    {
        // We will also check for a "route" or "action" parameter on the array so that
        // developers can easily specify a route or controller action when creating
        // a form providing a convenient interface for creating the form actions.
        if (isset($options['url'])) {
            return static::getUrlAction($options['url']);
        }

        if (isset($options['route'])) {
            return static::getRouteAction($options['route']);
        }

        // If an action is available, we are attempting to open a form to a controller
        // action route. So, we will use the URL generator to get the path to these
        // actions and return them from the method. Otherwise, we'll use current.
        if (isset($options['action'])) {
            return static::getControllerAction($options['action']);
        }

        return Uri::current();

        $uri = null === $action ? Uri::current() : $action;

        return HTML::entities(Url::to($uri, $https));
    }

    /**
     * Get the action for a "url" option.
     *
     * @param array|string $options
     *
     * @return string
     */
    protected static function getUrlAction($options)
    {
        if (\is_array($options)) {
            return Url::to($options[0], \array_slice($options, 1));
        }

        return Url::to($options);
    }

    /**
     * Get the action for a "route" option.
     *
     * @param array|string $options
     *
     * @return string
     */
    protected static function getRouteAction($options)
    {
        if (\is_array($options)) {
            return Url::route($options[0], \array_slice($options, 1));
        }

        return Url::route($options);
    }

    /**
     * Get the action for an "action" option.
     *
     * @param array|string $options
     *
     * @return string
     */
    protected static function getControllerAction($options)
    {
        if (\is_array($options)) {
            return Url::action($options[0], \array_slice($options, 1));
        }

        return Url::action($options);
    }

    /**
     * Determine the ID attribute for a form element.
     *
     * @param string $name
     * @param array  $attributes
     */
    protected static function id($name, $attributes)
    {
        // If an ID has been explicitly specified in the attributes, we will
        // use that ID. Otherwise, we will look for an ID in the array of
        // label names so labels and their elements have the same ID.
        if (\array_key_exists('id', $attributes)) {
            return $attributes['id'];
        }

        if (\in_array($name, static::$labels, true)) {
            return $name;
        }
    }

    /**
     * Create a HTML select element optgroup.
     *
     * @param array  $options
     * @param string $label
     * @param string $selected
     *
     * @return string
     */
    protected static function optgroup($options, $label, $selected)
    {
        $html = [];

        foreach ($options as $value => $display) {
            $html[] = static::option($value, $display, $selected);
        }

        return '<optgroup label="' . HTML::entities($label) . '">' . implode('', $html) . '</optgroup>';
    }

    /**
     * Create a HTML select element option.
     *
     * @param string $value
     * @param string $display
     * @param string $selected
     *
     * @return string
     */
    protected static function option($value, $display, $selected)
    {
        if (\is_array($selected)) {
            $selected = (\in_array($value, $selected, true)) ? 'selected' : null;
        } else {
            $selected = ((string)$value === (string)$selected) ? 'selected' : null;
        }

        $attributes = ['value' => HTML::entities($value), 'selected' => $selected];

        return '<option' . HTML::attributes($attributes) . '>' . HTML::entities($display) . '</option>';
    }

    /**
     * Create a checkable input element.
     *
     * @param string $type
     * @param string $name
     * @param string $value
     * @param bool   $checked
     * @param array  $attributes
     *
     * @return string
     */
    protected static function checkable($type, $name, $value, $checked, $attributes)
    {
        if ($checked) {
            $attributes['checked'] = 'checked';
        }

        $attributes['id'] = static::id($name, $attributes);

        return static::input($type, $name, $value, $attributes);
    }
}
