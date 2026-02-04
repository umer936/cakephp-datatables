<?php

/**
 * A wrapper for JavaScript function calls.
 * Used to pass callback functions in the DataTables configuration.
 */

namespace DataTables\Lib;

class CallbackFunction implements \JsonSerializable
{
    /**
     * Placeholder for storing prepared JS statements to be injected into JSON.
     *
     * @var array<string, string>
     */
    protected static array $_placeholders = [];

    /**
     * A unique hash for this specific object's JavaScript code.
     *
     * @var string
     */
    protected string $hash;

    /**
     * Constructor.
     *
     * @param string $name Name of the JavaScript function to call.
     * @param array $args Optional arguments to pass when calling the function.
     * @throws \JsonException
     */
    public function __construct(string $name, array $args = [])
    {
        $code = $this->generateCode($name, $args);
        $this->setHash($code);
    }

    /**
     * Resolves all placeholders in a JSON string with their respective JavaScript code.
     *
     * @param string $json JSON-encoded data.
     * @return string JSON-encoded data with placeholders replaced by JavaScript code.
     */
    public static function resolve(string $json): string
    {
        // Allow one recursion (a callback with a callback as an argument).
        $replacements = array_map(
            static fn(string $content) => strtr($content, self::$_placeholders),
            self::$_placeholders
        );

        return strtr($json, $replacements);
    }

    /**
     * Generates JavaScript code based on the function name and arguments.
     *
     * @param string $name Name of the JavaScript function.
     * @param array $args Arguments to pass to the JavaScript function.
     * @return string Generated JavaScript code.
     * @throws \JsonException
     */
    protected function generateCode(string $name, array $args): string
    {
        if (empty($args)) {
            return $name;
        }

        $code = 'function () { ';
        foreach ($args as $arg) {
            $encodedArg = json_encode($arg, JSON_THROW_ON_ERROR);
            $code .= "Array.prototype.push.call(arguments, {$encodedArg}); ";
        }
        $code .= "return {$name}.apply(this, arguments); }";

        return $code;
    }

    /**
     * Sets the hash for this wrapper and registers it in the placeholder list.
     *
     * @param string $code JavaScript code payload.
     */
    protected function setHash(string $code): void
    {
        // Use xxh128 for faster and more secure hashing.
        $this->hash = hash('xxh128', $code);
        // Use double quotes around the hash as it will appear in JSON.
        self::$_placeholders['"' . $this->hash . '"'] = $code;
    }

    /**
     * Retrieves the JavaScript code associated with this callback.
     *
     * @return string The generated JavaScript code.
     */
    public function code(): string
    {
        return self::$_placeholders['"' . $this->hash . '"'];
    }

    /**
     * Serializes the object to a placeholder string for JSON encoding.
     *
     * @return string A unique hash to be replaced by `resolve()` after `json_encode()`.
     */
    public function jsonSerialize(): string
    {
        return $this->hash;
    }
}
