<?php

namespace Aurora;

class Input
{
    /**
     * The key used to store old input in the session.
     *
     * @var string
     */
    public const OLD_INPUT = '_old_input';

    /**
     * The JSON payload for applications using Backbone.js or similar.
     *
     * @var object
     */
    public static $json;

    /**
     * Get all of the input data for the request, including files.
     *
     * @return array
     */
    public static function all()
    {
        $input = array_merge(static::get(), static::query(), static::file());

        unset($input[Request::spoofer]);

        return $input;
    }

    /**
     * Get an item from the input data.
     *
     * This method is used for all request verbs (GET, POST, PUT, and DELETE).
     *
     * <code>
     *        // Get the "email" item from the input array
     *        $email = Input::get('email');
     *
     *        // Return a default value if the specified item doesn't exist
     *        $email = Input::get('name', 'Taylor');
     * </code>
     *
     * @param string     $key
     * @param mixed|null $default
     */
    public static function get($key = null, $default = null)
    {
        $input = Request::foundation()->request->all();

        if (null === $key) {
            return array_merge($input, static::query());
        }

        $value = array_get($input, $key);

        if (null === $value) {
            return array_get(static::query(), $key, $default);
        }

        return $value;
    }

    /**
     * Get an item from the query string.
     *
     * <code>
     *        // Get the "email" item from the query string
     *        $email = Input::query('email');
     *
     *        // Return a default value if the specified item doesn't exist
     *        $email = Input::query('name', 'Taylor');
     * </code>
     *
     * @param string     $key
     * @param mixed|null $default
     */
    public static function query($key = null, $default = null)
    {
        return array_get(Request::foundation()->query->all(), $key, $default);
    }

    /**
     * Get an item from the uploaded file data.
     *
     * <code>
     *        // Get the array of information for the "picture" upload
     *        $picture = Input::file('picture');
     * </code>
     *
     * @param string     $key
     * @param mixed|null $default
     *
     * @return UploadedFile
     */
    public static function file($key = null, $default = null)
    {
        return array_get($_FILES, $key, $default);
    }

    /**
     * Determine if the input data contains an item.
     *
     * If the input item is an empty string, false will be returned.
     *
     * @param string $key
     *
     * @return bool
     */
    public static function has($key)
    {
        if (\is_array(static::get($key))) {
            return true;
        }

        return '' !== trim((string)static::get($key));
    }

    /**
     * Get the JSON payload for the request.
     *
     * @param bool $as_array
     *
     * @return object
     */
    public static function json($as_array = false)
    {
        if (null !== static::$json) {
            return static::$json;
        }

        return static::$json = json_decode(Request::foundation()->getContent(), $as_array);
    }

    /**
     * Get a subset of the items from the input data.
     *
     * <code>
     *        // Get only the email from the input data
     *        $value = Input::only('email');
     *
     *        // Get only the username and email from the input data
     *        $input = Input::only(array('username', 'email'));
     * </code>
     *
     * @param array|string $keys
     *
     * @return array
     */
    public static function only($keys)
    {
        return array_only(static::get(), $keys);
    }

    /**
     * Get all of the input data except for a specified array of items.
     *
     * <code>
     *        // Get all of the input data except for username
     *        $input = Input::except('username');
     *
     *        // Get all of the input data except for username and email
     *        $input = Input::except(array('username', 'email'));
     * </code>
     *
     * @param array $keys
     *
     * @return array
     */
    public static function except($keys)
    {
        return array_except(static::get(), $keys);
    }

    /**
     * Determine if the old input data contains an item.
     *
     * @param string $key
     *
     * @return bool
     */
    public static function had($key)
    {
        if (\is_array(static::old($key))) {
            return true;
        }

        return '' !== trim((string)static::old($key));
    }

    /**
     * Get input data from the previous request.
     *
     * <code>
     *        // Get the "email" item from the old input
     *        $email = Input::old('email');
     *
     *        // Return a default value if the specified item doesn't exist
     *        $email = Input::old('name', 'Taylor');
     * </code>
     *
     * @param string     $key
     * @param mixed|null $default
     *
     * @return string
     */
    public static function old($key = null, $default = null)
    {
        return array_get(Session::get(self::OLD_INPUT, []), $key, $default);
    }

    /**
     * Determine if the uploaded data contains a file.
     *
     * @param string $key
     *
     * @return bool
     */
    public static function has_file($key)
    {
        return mb_strlen(static::file("{$key}.tmp_name", '')) > 0;
    }

    /**
     * Move an uploaded file to permanent storage.
     *
     * This method is simply a convenient wrapper around move_uploaded_file.
     *
     * <code>
     *        // Move the "picture" file to a new permanent location on disk
     *        Input::upload('picture', 'path/to/photos', 'picture.jpg');
     * </code>
     *
     * @param string $key
     * @param string $directory
     * @param string $name
     *
     * @return bool|Symfony\Component\HttpFoundation\File\File
     */
    public static function upload($key, $directory, $name = null)
    {
        if (null === static::file($key)) {
            return false;
        }

        return Request::foundation()->files->get($key)->move($directory, $name);
    }

    /**
     * Flash the input for the current request to the session.
     *
     * <code>
     *        // Flash all of the input to the session
     *        Input::flash();
     *
     *        // Flash only a few input items to the session
     *        Input::flash('only', array('name', 'email'));
     *
     *        // Flash all but a few input items to the session
     *        Input::flash('except', array('password', 'social_number'));
     * </code>
     *
     * @param string $filter
     * @param array  $keys
     */
    public static function flash($filter = null, $keys = []): void
    {
        $flash = (null !== $filter) ? static::$filter($keys) : static::get();

        Session::flash(self::OLD_INPUT, $flash);
    }

    /**
     * Flush all of the old input from the session.
     */
    public static function flush(): void
    {
        Session::flash(self::OLD_INPUT, []);
    }

    /**
     * Merge new input into the current request's input array.
     */
    public static function merge(array $input): void
    {
        Request::foundation()->request->add($input);
    }

    /**
     * Replace the input for the current request.
     */
    public static function replace(array $input): void
    {
        Request::foundation()->request->replace($input);
    }

    /**
     * Clear the input for the current request.
     */
    public static function clear(): void
    {
        Request::foundation()->request->replace([]);
    }
}
